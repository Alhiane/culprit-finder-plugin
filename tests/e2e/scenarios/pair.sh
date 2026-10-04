#!/usr/bin/env bash
# "Cannot redeclare" fatal between two plugins among 14 suspects (AC-4, AC-2).
source "$(dirname "$0")/../lib.sh"
# Log in on a clean site first: once both pair plugins are active, wp-login.php itself fatals.
reset_site >/dev/null
JAR=$TMP/admin.jar; login "$JAR"
BEFORE=$(reset_site $(noise 1 12) cff-fixtures/cff-pair-a cff-fixtures/cff-pair-b)
symptom_500 "$JAR" || fail "precondition: HTTP 500 with everything on"
start_session
add_token "$JAR" "$TOKEN"
answer_loop "$JAR" symptom_500
assert_result PAIR cff-fixtures/cff-pair-a.php cff-fixtures/cff-pair-b.php
assert_le "$ANSWERS" "$(any_bound 14)" "AC-4 answer bound"
assert_eq "$(wpcli option get active_plugins --format=json)" "$BEFORE" "AC-2 active_plugins unchanged"
wpcli culprit-finder exit >/dev/null
echo "pair: PAIR in $ANSWERS answers"
