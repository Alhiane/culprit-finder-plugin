#!/usr/bin/env bash
# Session limits (ADR-0024): a hard maximum length that a filter can lower but never raise, enforced by
# the loader and the session manager, and the cookie refresh after automatic answers (FP-8).
source "$(dirname "$0")/../lib.sh"
SPY=cff-fixtures/cff-hooks-spy.php
trap 'wpcli option delete cff_addon_mode >/dev/null 2>&1 || true; wpcli option delete cff_hooks_spy_mode >/dev/null 2>&1 || true; wpcli config delete CULPRIT_FINDER_SESSION_TTL >/dev/null 2>&1 || true' EXIT
BEFORE=$(reset_site $(noise 1 4) cff-fixtures/cff-solo cff-fixtures/cff-addon cff-fixtures/cff-hooks-spy)
session() { wpjson option get culprit_finder_session --format=json; }
length() { jq -r '.ends_at - .created_at' <<<"$(session)"; }
mode() { wpcli option update cff_addon_mode "$1" >/dev/null; }
loader_state() { wpcli eval 'echo file_exists( WPMU_PLUGIN_DIR . "/culprit-finder-loader.php" ) ? "present" : "absent";'; }

# The filter can lower the maximum (to at least a minute) but never raise it.
for pair in ":10800" "max-high:10800" "max-low:120" "max-tiny:60"; do
  mode "${pair%%:*}"; start_session
  assert_eq "$(length)" "${pair##*:}" "max length with mode '${pair%%:*}'"
done
mode ''

# The idle expiry never runs past the maximum.
start_session
wpcli eval '$s = get_option( "culprit_finder_session" ); $s["ends_at"] = time() + 30; update_option( "culprit_finder_session", $s, false );' >/dev/null
admin_cli answer no >/dev/null
S=$(session)
assert_eq "$(jq -r '.expires_at == .ends_at' <<<"$S")" true "an answer never moves expiry past ends_at"

# Past the maximum, the loader stops filtering and the manager ends the session (with a notice).
JAR=$TMP/admin.jar; login "$JAR"
start_session
add_token "$JAR" "$TOKEN"
assert_eq "$(noise_count "$(fetch "$JAR" /)")" 0 "filtering while within the limits"
wpcli eval '$s = get_option( "culprit_finder_session" ); $s["ends_at"] = time() - 1; update_option( "culprit_finder_session", $s, false );' >/dev/null
PAGE=$(fetch "$JAR" /)
assert_eq "$(noise_count "$PAGE")" 4 "loader: no filtering past ends_at, although the idle timeout is still ahead"
assert_contains "$PAGE" "CFF-SYMPTOM" "loader: normal site past ends_at"
wpcli option get culprit_finder_session >/dev/null 2>&1 && fail "manager: session still stored past ends_at"
assert_eq "$(loader_state)" absent "manager: loader removed at the maximum"
assert_contains "$(fetch "$JAR" /wp-admin/admin.php?page=culprit-finder)" "reached its maximum length" "notice: maximum length reached"

# FP-8: automatic answers refresh the browser's cookie expiry.
wpcli config set CULPRIT_FINDER_SESSION_TTL 60 --raw --type=constant >/dev/null
JAR=$TMP/fp8.jar; login "$JAR"
TOOLS="/wp-admin/admin.php?page=culprit-finder"
PAGE=$(fetch "$JAR" "$TOOLS")
NONCE=$(grep -m1 -oE 'name="_wpnonce" value="[^"]+"' <<<"$PAGE" | sed -E 's/.*value="([^"]+)"/\1/')
KEY=$(grep -m1 -oE 'name="culprit_finder_recovery" value="[^"]+"' <<<"$PAGE" | sed -E 's/.*value="([^"]+)"/\1/')
curl -s -o /dev/null -b "$JAR" -c "$JAR" --data-urlencode "_wpnonce=$NONCE" --data "action=culprit_finder_start&culprit_finder_saved=1&culprit_finder_recovery=$KEY" \
  --data-urlencode "culprit_finder_pin[]=$SPY" "$BASE/wp-admin/admin-post.php?culprit_safe=1"
wpcli eval '$s = get_option( "culprit_finder_session" ); $s["expires_at"] = time() + 20; update_option( "culprit_finder_session", $s, false );' >/dev/null
wpcli option update cff_hooks_spy_mode auto-yes >/dev/null
curl -s -o /dev/null -D "$TMP/auto.h" -b "$JAR" "$BASE$TOOLS&culprit_safe=1"
wpcli option update cff_hooks_spy_mode '' >/dev/null
S=$(session)
assert_eq "$(jq -r '.answers | length' <<<"$S")" 1 "auto_answer recorded the answer"
COOKIE_EXP=$(grep -i '^set-cookie: wp-culprit-finder=' "$TMP/auto.h" | grep -m1 -oiE 'expires=[^;]+' | cut -d= -f2-)
[ -n "$COOKIE_EXP" ] || fail "FP-8: no Set-Cookie after automatic answers"
assert_eq "$(python3 -c 'import sys,email.utils; print(int(email.utils.parsedate_to_datetime(sys.argv[1]).timestamp()))' "$COOKIE_EXP")" "$(jq -r .expires_at <<<"$S")" "FP-8: cookie expiry = new session expiry"
wpcli culprit-finder exit >/dev/null
assert_eq "$(wpcli option get active_plugins --format=json)" "$BEFORE" "AC-2 active_plugins unchanged"
