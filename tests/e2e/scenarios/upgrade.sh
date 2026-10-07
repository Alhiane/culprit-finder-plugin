#!/usr/bin/env bash
# Updating from 0.1.0 in the middle of a session (ADR-0024): a session stored by 0.1.0 (no ends_at,
# no always_on) with the 0.1.0 loader still installed keeps working, ends cleanly at its maximum
# length, and the next Start installs the current loader.
source "$(dirname "$0")/../lib.sh"
trap 'wpcli culprit-finder exit >/dev/null 2>&1 || true' EXIT
OLD=$(git -C "$ROOT" show v0.1.0:mu-loader/culprit-finder-loader.php)
grep -q "Version: 0.1.1" <<<"$OLD" || fail "could not read the 0.1.0 loader from git"
BEFORE=$(reset_site $(noise 1 4) cff-fixtures/cff-solo)
loader_md5() { wpcli eval 'echo file_exists( WPMU_PLUGIN_DIR . "/culprit-finder-loader.php" ) ? md5_file( WPMU_PLUGIN_DIR . "/culprit-finder-loader.php" ) : "absent";'; }

start_session
wpcli eval '$s = get_option( "culprit_finder_session" ); unset( $s["ends_at"], $s["always_on"] ); update_option( "culprit_finder_session", $s, false );' >/dev/null
printf '%s\n' "$OLD" | wpcli eval 'file_put_contents( WPMU_PLUGIN_DIR . "/culprit-finder-loader.php", stream_get_contents( STDIN ) );' >/dev/null
OLD_MD5=$(loader_md5)
[ "$OLD_MD5" != absent ] || fail "could not plant the 0.1.0 loader"

JAR=$TMP/admin.jar; login "$JAR"
add_token "$JAR" "$TOKEN"
assert_eq "$(noise_count "$(fetch "$JAR" /)")" 0 "0.1.0 session + 0.1.0 loader: still filtering"
assert_contains "$(fetch "$JAR" "/wp-admin/admin.php?page=culprit-finder&culprit_safe=1")" "Is the problem still there?" "0.1.0 session: running screen"
STEP=$(admin_cli answer no)
assert_eq "$(jq_r .question "$STEP")" 2 "0.1.0 session: answers still work"
S=$(wpjson option get culprit_finder_session --format=json)
assert_eq "$(jq -r 'has("ends_at")' <<<"$S")" false "0.1.0 session is not rewritten with new keys"
assert_eq "$(jq -r '.expires_at <= .created_at + 10800' <<<"$S")" true "0.1.0 session: expiry capped at three hours from its start"
assert_eq "$(loader_md5)" "$OLD_MD5" "the installed loader is not rewritten mid-session"

wpcli eval '$s = get_option( "culprit_finder_session" ); $s["created_at"] = time() - 10801; update_option( "culprit_finder_session", $s, false );' >/dev/null
fetch "$JAR" / >/dev/null
wpcli option get culprit_finder_session >/dev/null 2>&1 && fail "0.1.0 session older than three hours was not ended"
assert_eq "$(loader_md5)" absent "ending the 0.1.0 session removes its loader"
PAGE=$(fetch "$JAR" /)
assert_eq "$(noise_count "$PAGE")" 4 "after the end: normal site"
assert_contains "$PAGE" "CFF-SYMPTOM" "after the end: normal site"

start_session
assert_eq "$(loader_md5)" "$(md5 -q "$ROOT/mu-loader/culprit-finder-loader.php" 2>/dev/null || md5sum "$ROOT/mu-loader/culprit-finder-loader.php" | cut -d' ' -f1)" "the next Start installs the current loader"
wpcli culprit-finder exit >/dev/null

# An outdated loader left from 0.1.0 is replaced at Start (is_current/md5 path).
printf '%s\n' "$OLD" | wpcli eval 'file_put_contents( WPMU_PLUGIN_DIR . "/culprit-finder-loader.php", stream_get_contents( STDIN ) );' >/dev/null
start_session
assert_contains "$(wpcli eval 'echo file_get_contents( WPMU_PLUGIN_DIR . "/culprit-finder-loader.php" );')" "const VERSION    = '0.2.0';" "Start replaces the outdated loader"
wpcli culprit-finder exit >/dev/null
assert_eq "$(wpcli option get active_plugins --format=json)" "$BEFORE" "AC-2 active_plugins unchanged"
