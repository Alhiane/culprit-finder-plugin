#!/usr/bin/env bash
# When a step fatals, the safe-mode control panel still loads (AC-8).
source "$(dirname "$0")/../lib.sh"
# Log in on a clean site first: once both pair plugins are active, wp-login.php itself fatals.
reset_site >/dev/null
JAR=$TMP/admin.jar; login "$JAR"
BEFORE=$(reset_site $(noise 1 12) cff-fixtures/cff-pair-a cff-fixtures/cff-pair-b)
start_session
add_token "$JAR" "$TOKEN"
FATAL=0
for i in $(seq 1 20); do
  if [ "$(code "$JAR" /)" = 500 ]; then FATAL=1; break; fi
  STEP=$(admin_cli answer no)
  [ "$(jq_r .status "$STEP")" = done ] && break
done
assert_eq "$FATAL" 1 "reached a step that fatals"
assert_eq "$(code "$JAR" /wp-admin/)" 500 "normal admin request fatals in this step"
assert_eq "$(code "$JAR" '/wp-admin/tools.php?page=culprit-finder&culprit_safe=1')" 200 "AC-8 control panel loads in safe mode"
assert_eq "$(wpcli option get active_plugins --format=json)" "$BEFORE" "AC-2 active_plugins unchanged"
wpcli culprit-finder exit >/dev/null
