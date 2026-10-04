#!/usr/bin/env bash
# One culprit among 25 suspects (AC-3, AC-2).
source "$(dirname "$0")/../lib.sh"
BEFORE=$(reset_site $(noise 1 24) cff-fixtures/cff-solo)
JAR=$TMP/admin.jar; login "$JAR"
symptom_marker "$JAR" || fail "precondition: symptom visible with everything on"
start_session
add_token "$JAR" "$TOKEN"
answer_loop "$JAR" symptom_marker
assert_result SINGLE cff-fixtures/cff-solo.php
assert_le "$ANSWERS" "$(single_bound 25)" "AC-3 answer bound"
assert_eq "$(wpcli option get active_plugins --format=json)" "$BEFORE" "AC-2 active_plugins unchanged"
REPORT=$(wpcli culprit-finder result --format=report)
assert_contains "$REPORT" "Caused by one plugin" "report verdict"
assert_contains "$REPORT" "CFF Solo 1.0.0" "report culprit"
assert_not_contains "$REPORT" "localhost" "AC-11 no site URL in report"
wpcli culprit-finder exit >/dev/null
echo "solo: SINGLE in $ANSWERS answers"
