#!/usr/bin/env bash
# Wrong first answer produces a result; Undo reverts it and the search continues (AC-10).
source "$(dirname "$0")/../lib.sh"
BEFORE=$(reset_site $(noise 1 24) cff-fixtures/cff-solo)
JAR=$TMP/admin.jar; login "$JAR"
start_session
add_token "$JAR" "$TOKEN"
symptom_marker "$JAR" && fail "baseline should not show the symptom"
WRONG=$(admin_cli answer yes)
assert_eq "$(jq_r .result.type "$WRONG")" NOT_PLUGIN "wrong answer gives a result"
UNDONE=$(admin_cli undo)
assert_eq "$(jq_r .status "$UNDONE")" asking "undo reopens the question"
assert_eq "$(jq_r .question "$UNDONE")" 1 "back to question 1"
wpcli culprit-finder result >/dev/null 2>&1 && fail "undone result must not remain as last result"
answer_loop "$JAR" symptom_marker
assert_result SINGLE cff-fixtures/cff-solo.php
assert_eq "$(wpcli option get active_plugins --format=json)" "$BEFORE" "AC-2 active_plugins unchanged"
wpcli culprit-finder exit >/dev/null
