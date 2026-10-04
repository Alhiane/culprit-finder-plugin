#!/usr/bin/env bash
# Idle expiry (AC-9): with a 2-second TTL the session stops filtering by itself.
source "$(dirname "$0")/../lib.sh"
BEFORE=$(reset_site $(noise 1 24) cff-fixtures/cff-solo)
trap 'wpcli config delete CULPRIT_FINDER_SESSION_TTL >/dev/null 2>&1 || true' EXIT
wpcli config set CULPRIT_FINDER_SESSION_TTL 2 --raw --type=constant >/dev/null
JAR=$TMP/admin.jar; login "$JAR"
start_session
add_token "$JAR" "$TOKEN"
sleep 3
PAGE=$(fetch "$JAR" /)
assert_eq "$(noise_count "$PAGE")" 24 "expired session no longer filters"
assert_contains "$PAGE" "CFF-SYMPTOM" "expired session no longer filters"
wpcli culprit-finder status >/dev/null 2>&1 && fail "expired session still reported as running"
assert_eq "$(wpcli option get active_plugins --format=json)" "$BEFORE" "AC-2 active_plugins unchanged"
