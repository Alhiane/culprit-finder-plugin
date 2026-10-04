#!/usr/bin/env bash
# Logged-out recovery URL ends the session (AC-9).
source "$(dirname "$0")/../lib.sh"
BEFORE=$(reset_site $(noise 1 24) cff-fixtures/cff-solo)
JAR=$TMP/admin.jar; login "$JAR"
start_session
add_token "$JAR" "$TOKEN"
assert_eq "$(noise_count "$(fetch "$JAR" /)")" 0 "filtered during the session"
# A wrong key does nothing.
fetch - "/?culprit-finder-exit=$(printf '0%.0s' {1..64})" >/dev/null
wpcli option get culprit_finder_session >/dev/null 2>&1 || fail "wrong key ended the session"
assert_eq "$(code - "/?culprit-finder-exit=$KEY")" 200 "recovery URL loads normally"
wpcli option get culprit_finder_session >/dev/null 2>&1 && fail "session option still exists after recovery URL"
assert_eq "$(noise_count "$(fetch "$JAR" /)")" 24 "owner sees the normal site after recovery"
assert_eq "$(wpcli option get active_plugins --format=json)" "$BEFORE" "AC-2 active_plugins unchanged"
