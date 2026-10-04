#!/usr/bin/env bash
# Visitors and other admins see every plugin during a session (AC-1).
source "$(dirname "$0")/../lib.sh"
BEFORE=$(reset_site $(noise 1 24) cff-fixtures/cff-solo)
wpcli user get admin2 >/dev/null 2>&1 || wpcli user create admin2 admin2@example.test --role=administrator --user_pass=password >/dev/null
JAR=$TMP/admin.jar; login "$JAR"
JAR2=$TMP/admin2.jar; login "$JAR2" admin2 password
start_session
add_token "$JAR" "$TOKEN"

OWNER=$(fetch "$JAR" /)
assert_eq "$(noise_count "$OWNER")" 0 "session owner's baseline step has every suspect off"
assert_not_contains "$OWNER" "CFF-SYMPTOM" "session owner's step"

VISITOR=$(fetch - /)
assert_eq "$(noise_count "$VISITOR")" 24 "AC-1 visitor sees all noise plugins"
assert_contains "$VISITOR" "CFF-SYMPTOM" "AC-1 visitor sees the solo plugin"

OTHER=$(fetch "$JAR2" /)
assert_eq "$(noise_count "$OTHER")" 24 "AC-1 second admin sees all noise plugins"
assert_contains "$OTHER" "CFF-SYMPTOM" "AC-1 second admin sees the solo plugin"

# A token without a login cookie does nothing (ADR-0002).
TOKEN_ONLY=$TMP/token-only.jar; : > "$TOKEN_ONLY"; add_token "$TOKEN_ONLY" "$TOKEN"
assert_eq "$(noise_count "$(fetch "$TOKEN_ONLY" /)")" 24 "token without login is ignored"

# Another admin holding the owner's token is not filtered past init and loses the cookie.
STOLEN=$TMP/stolen.jar; cp "$JAR2" "$STOLEN"; add_token "$STOLEN" "$TOKEN"
HDRS=$(curl -s -D - -o /dev/null -b "$STOLEN" "$BASE/")
assert_contains "$HDRS" "wp-culprit-finder=deleted" "non-owner's cookie is expired"

# Filtered responses are not cacheable (ADR-0007).
OWNER_HDRS=$(curl -s -D - -o /dev/null -b "$JAR" "$BASE/")
assert_contains "$(tr 'A-Z' 'a-z' <<<"$OWNER_HDRS")" "cache-control: no-cache" "no-cache headers on filtered response"

assert_eq "$(wpcli option get active_plugins --format=json)" "$BEFORE" "AC-2 active_plugins unchanged"
wpcli culprit-finder exit >/dev/null
