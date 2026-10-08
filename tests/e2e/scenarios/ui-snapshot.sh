#!/usr/bin/env bash
# Without add-ons every screen renders exactly as in 0.1.0/0.2.0 (ADR-0020, ADR-0024): normalized HTML of
# each screen must match tests/ui-snapshot/baseline. Pro discovery (ADR-0025) only adds tagged placements:
#   hidden  "Hide Pro tips" for the user          → byte-for-byte equal to the baseline
#   pro     CULPRIT_FINDER_PRO_VERSION defined    → byte-for-byte equal to the baseline
#   tips    default                               → equal to the baseline once the placements are removed,
#                                                   and each placement is on exactly the expected screens
# The setup screen was re-baselined on purpose in 0.3.0 for its two-column layout (ADR-0026); every
# other screen's baseline is unchanged since 0.2.0.
source "$(dirname "$0")/../lib.sh"
TIP_ATTR=data-culprit-finder-pro-tip
cleanup() {
  wpcli user meta delete admin culprit_finder_hide_pro_tips >/dev/null 2>&1 || true
  wpcli config delete CULPRIT_FINDER_PRO_VERSION >/dev/null 2>&1 || true
}
trap cleanup EXIT
cleanup

wpcli user meta update admin culprit_finder_hide_pro_tips 1 >/dev/null
bash "$ROOT/tests/ui-snapshot/run.sh" "$TMP/hidden" >/dev/null
diff -ru "$ROOT/tests/ui-snapshot/baseline" "$TMP/hidden" || fail "tips hidden: UI differs from the baseline"
cleanup

wpcli config set CULPRIT_FINDER_PRO_VERSION 1.0.0 >/dev/null
bash "$ROOT/tests/ui-snapshot/run.sh" "$TMP/pro" >/dev/null
diff -ru "$ROOT/tests/ui-snapshot/baseline" "$TMP/pro" || fail "Pro present: UI differs from the baseline"
cleanup

bash "$ROOT/tests/ui-snapshot/run.sh" "$TMP/tips" >/dev/null
mkdir -p "$TMP/stripped"
for f in "$TMP/tips"/*.html; do
  python3 -c '
import re, sys
s = open(sys.argv[1]).read()
s = re.sub(r"\n<(aside|section|p|a) [^>]*data-culprit-finder-pro-tip=\"[a-z_]+\"[^>]*>.*?</\1>", "", s, flags=re.S)
sys.stdout.write(s)' "$f" > "$TMP/stripped/$(basename "$f")"
done
diff -ru "$ROOT/tests/ui-snapshot/baseline" "$TMP/stripped" || fail "tips shown: UI differs from the baseline outside the Pro placements"

expect() {
  local name=$1 want=$2 got
  got=$(grep -oE "$TIP_ATTR=\"[a-z_]+\"" "$TMP/tips/$name.html" | sed -E 's/.*="([a-z_]+)"/\1/' | sort | tr '\n' ' ' || true)
  assert_eq "$got" "$want" "placements on $name"
}
expect setup "pro_tab setup "
expect help "help pro_tab "
expect results-empty "pro_tab "
expect results-list "pro_tab "
expect running "pro_tab "
expect done "pro_tab "
expect result-current "pro_tab result "
expect result-detail "pro_tab result "
for s in dashboard-idle dashboard-running dashboard-after adminbar-running adminbar-done; do expect "$s" ""; done
