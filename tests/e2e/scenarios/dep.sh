#!/usr/bin/env bash
# Child requires parent; the engine must never load the child without it (AC-7).
source "$(dirname "$0")/../lib.sh"
BEFORE=$(reset_site $(noise 1 8) cff-dep-parent cff-dep-child)
JAR=$TMP/admin.jar; login "$JAR"
symptom_marker "$JAR" || fail "precondition: symptom visible"
start_session
add_token "$JAR" "$TOKEN"
no_fatal() { local c; c=$(code "$1" /); [ "$c" != 500 ] || fail "HTTP 500 during a step (dependency loaded without parent)"; }
answer_loop "$JAR" symptom_marker no_fatal
assert_result SINGLE cff-dep-child/cff-dep-child.php
assert_le "$ANSWERS" "$(single_bound 10)" "answer bound"
assert_eq "$(wpcli option get active_plugins --format=json)" "$BEFORE" "AC-2 active_plugins unchanged"
assert_contains "$(wpcli culprit-finder result --format=report)" "together with its required plugins CFF Dep Parent" "report mentions dependency"
wpcli culprit-finder exit >/dev/null
