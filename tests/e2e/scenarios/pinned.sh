#!/usr/bin/env bash
# The culprit is pinned, so it is never switched off: NOT_PLUGIN after one answer (AC-5, AC-6).
source "$(dirname "$0")/../lib.sh"
BEFORE=$(reset_site $(noise 1 8) cff-fixtures/cff-solo)
JAR=$TMP/admin.jar; login "$JAR"
start_session cff-fixtures/cff-solo.php
add_token "$JAR" "$TOKEN"
assert_contains "$(fetch "$JAR" /)" "CFF-SYMPTOM" "AC-6 pinned plugin loads in the step"
answer_loop "$JAR" symptom_marker
assert_result NOT_PLUGIN
assert_eq "$ANSWERS" 1 "AC-5 exactly one answer"
assert_eq "$(jq -c .result.kept_on <<<"$STEP")" '["cff-fixtures/cff-solo.php"]' "kept_on"
assert_eq "$(wpcli option get active_plugins --format=json)" "$BEFORE" "AC-2 active_plugins unchanged"
wpcli culprit-finder exit >/dev/null
