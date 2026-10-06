#!/usr/bin/env bash
# The loader exists only while troubleshooting, and a foreign file at its path is never touched (ADR-0023).
source "$(dirname "$0")/../lib.sh"
LOADER=wp-content/mu-plugins/culprit-finder-loader.php
loader_state() { wpcli eval '$f = WPMU_PLUGIN_DIR . "/culprit-finder-loader.php"; if ( ! file_exists( $f ) ) { echo "absent"; } elseif ( false !== strpos( file_get_contents( $f ), "culprit-finder-loader-marker" ) ) { echo "ours"; } else { echo "foreign:" . md5_file( $f ); }'; }
trap 'wpcli eval "@unlink( WPMU_PLUGIN_DIR . \"/culprit-finder-loader.php\" );" >/dev/null 2>&1; wpcli plugin activate culprit-finder >/dev/null 2>&1 || true' EXIT

BEFORE=$(reset_site $(noise 1 4) cff-fixtures/cff-solo)
assert_eq "$(loader_state)" absent "activation writes nothing to mu-plugins"
wpcli plugin deactivate culprit-finder >/dev/null; wpcli plugin activate culprit-finder >/dev/null
assert_eq "$(loader_state)" absent "re-activation writes nothing"
JAR=$TMP/admin.jar; login "$JAR"
fetch "$JAR" /wp-admin/ >/dev/null
assert_eq "$(loader_state)" absent "admin page loads write nothing"

start_session
assert_eq "$(loader_state)" ours "Start installs the loader"
add_token "$JAR" "$TOKEN"
assert_eq "$(noise_count "$(fetch "$JAR" /)")" 0 "the installed loader filters the owner's requests"
wpcli culprit-finder exit >/dev/null
assert_eq "$(loader_state)" absent "Exit removes the loader"

start_session
fetch - "/?culprit-finder-exit=$KEY" >/dev/null
assert_eq "$(loader_state)" absent "the emergency exit link removes the loader"

start_session
wpcli eval '$s = get_option( "culprit_finder_session" ); $s["expires_at"] = time() - 1; update_option( "culprit_finder_session", $s, false );' >/dev/null
wpcli culprit-finder status >/dev/null 2>&1 || true
assert_eq "$(loader_state)" absent "an expired session removes the loader"

start_session
wpcli plugin deactivate culprit-finder >/dev/null
assert_eq "$(loader_state)" absent "deactivation removes the loader"
wpcli plugin activate culprit-finder >/dev/null

# A different file with the same name is never overwritten or deleted.
wpcli eval 'file_put_contents( WPMU_PLUGIN_DIR . "/culprit-finder-loader.php", "<?php\n// Someone else'"'"'s must-use plugin.\n" );' >/dev/null
FOREIGN=$(loader_state)
case "$FOREIGN" in foreign:*) ;; *) fail "could not plant a foreign file ($FOREIGN)";; esac
OUT=$(wpcli --user=admin culprit-finder start 2>&1 || true)
assert_contains "$OUT" "never overwrites files it didn" "Start is refused with an explanation"
wpcli option get culprit_finder_session >/dev/null 2>&1 && fail "a session started despite the foreign file"
assert_contains "$(fetch "$JAR" /wp-admin/admin.php?page=culprit-finder)" "A different file already exists at" "the Culprit Finder page explains the conflict"
wpcli plugin deactivate culprit-finder >/dev/null; wpcli plugin activate culprit-finder >/dev/null
assert_eq "$(loader_state)" "$FOREIGN" "activation and deactivation leave the foreign file untouched"
wpcli eval '@unlink( WPMU_PLUGIN_DIR . "/culprit-finder-loader.php" );' >/dev/null
assert_eq "$(wpcli option get active_plugins --format=json)" "$BEFORE" "AC-2 active_plugins unchanged"
