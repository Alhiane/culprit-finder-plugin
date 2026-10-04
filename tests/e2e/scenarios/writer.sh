#!/usr/bin/env bash
# A plugin tries to wipe active_plugins during the session; the write guard blocks it (AC-2).
source "$(dirname "$0")/../lib.sh"
BEFORE=$(reset_site $(noise 1 4) cff-fixtures/cff-writer cff-fixtures/cff-solo)
JAR=$TMP/admin.jar; login "$JAR"
start_session cff-fixtures/cff-writer.php
add_token "$JAR" "$TOKEN"
WROTE=0
attempt_write() {
  if [ "$WROTE" = 0 ]; then
    assert_contains "$(fetch "$1" '/?cff_write=i-know')" "CFF-WROTE" "writer attempted the write"
    WROTE=1
    assert_eq "$(wpcli option get active_plugins --format=json)" "$BEFORE" "AC-2 write was blocked"
  fi
}
answer_loop "$JAR" symptom_marker attempt_write
assert_eq "$WROTE" 1 "write attempted"
assert_result SINGLE cff-fixtures/cff-solo.php
assert_eq "$(wpcli option get active_plugins --format=json)" "$BEFORE" "AC-2 active_plugins unchanged"
wpcli culprit-finder exit >/dev/null
