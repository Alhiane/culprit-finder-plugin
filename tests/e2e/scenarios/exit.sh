#!/usr/bin/env bash
# Exit ends the session; the old cookie no longer filters anything (AC-9).
source "$(dirname "$0")/../lib.sh"
BEFORE=$(reset_site $(noise 1 24) cff-fixtures/cff-solo)
JAR=$TMP/admin.jar; login "$JAR"
start_session
add_token "$JAR" "$TOKEN"
assert_eq "$(noise_count "$(fetch "$JAR" /)")" 0 "filtered during the session"
admin_cli answer no >/dev/null
wpcli culprit-finder exit >/dev/null
wpcli option get culprit_finder_session >/dev/null 2>&1 && fail "session option still exists"
PAGE=$(fetch "$JAR" /)
assert_eq "$(noise_count "$PAGE")" 24 "old jar sees all noise plugins after exit"
assert_contains "$PAGE" "CFF-SYMPTOM" "old jar sees the solo plugin after exit"
assert_eq "$(wpcli option get active_plugins --format=json)" "$BEFORE" "AC-2 active_plugins unchanged"
