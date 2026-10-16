#!/usr/bin/env bash
# Without add-ons every screen renders exactly as in 0.1.0/0.2.0 (ADR-0020, ADR-0024): normalized HTML of
# each screen must match tests/ui-snapshot/baseline. Pro discovery (ADR-0025) only adds tagged placements:
#   hidden  "Hide Pro tips" for the user          → equal to the baseline once the placements are removed,
#                                                   and "Show them again" is the only placement, on Help
#   pro     CULPRIT_FINDER_PRO_VERSION defined    → byte-for-byte equal to the baseline, with no placements
#   tips    default                               → equal to the baseline once the placements are removed,
#                                                   and each placement is on exactly the expected screens
# A user who hid the tips gets one "Pro tips are hidden. Show them again" line on the Help tab, so the
# hidden mode strips tagged placements before its diff, exactly as the default mode does.
# The setup screen was re-baselined on purpose in 0.3.0 for its two-column layout (ADR-0026). In 0.3.1
# result-current and result-detail were re-baselined on purpose for the Steps section (ADR-0028): the
# only change is the added collapsed "Steps tested" list and the Steps block in the plain-text report.
# Every other screen's baseline is unchanged since 0.2.0.
source "$(dirname "$0")/../lib.sh"
TIP_ATTR=data-culprit-finder-pro-tip
cleanup() {
  wpcli user meta delete admin culprit_finder_hide_pro_tips >/dev/null 2>&1 || true
  wpcli config delete CULPRIT_FINDER_PRO_VERSION >/dev/null 2>&1 || true
}
trap cleanup EXIT
cleanup

# count_placements() <dir> <placement>: how many times a placement is tagged across a capture set.
count_placements() {
  { grep -ohF "$TIP_ATTR=\"$2\"" "$1"/*.html 2>/dev/null || true; } | wc -l | tr -d ' '
}

# strip_placements() <dir> <outdir>: the captures without the tagged Pro placements, keeping every other
# byte, so the rest can be compared with the frozen baseline.
strip_placements() {
  mkdir -p "$2"
  for f in "$1"/*.html; do
    python3 -c '
import re, sys
s = open(sys.argv[1]).read()
s = re.sub(r"\n<(aside|section|p|a) [^>]*data-culprit-finder-pro-tip=\"[a-z_]+\"[^>]*>.*?</\1>", "", s, flags=re.S)
sys.stdout.write(s)' "$f" > "$2/$(basename "$f")"
  done
}

# expect() <name> <placements> <mode>: the placements tagged on one captured screen, in order.
expect() {
  local name=$1 want=$2 mode=$3 got
  got=$(grep -oE "$TIP_ATTR=\"[a-z_]+\"" "$TMP/$mode/$name.html" | sed -E 's/.*="([a-z_]+)"/\1/' | sort | tr '\n' ' ' || true)
  assert_eq "$got" "$want" "placements on $mode/$name"
}

# Hiding the tips leaves the Help tab with the way back and every other screen untouched.
wpcli user meta update admin culprit_finder_hide_pro_tips 1 >/dev/null
bash "$ROOT/tests/ui-snapshot/run.sh" "$TMP/hidden" >/dev/null
strip_placements "$TMP/hidden" "$TMP/hidden-stripped"
diff -ru "$ROOT/tests/ui-snapshot/baseline" "$TMP/hidden-stripped" || fail "tips hidden: UI differs from the baseline outside the Pro placements"
expect help "show_again " hidden
for s in setup results-empty results-list running done result-current result-detail dashboard-idle dashboard-running dashboard-after adminbar-running adminbar-done; do expect "$s" "" hidden; done
assert_eq "$(count_placements "$TMP/hidden" show_again)" 1 "tips hidden: the show-again line appears exactly once"
cleanup

wpcli config set CULPRIT_FINDER_PRO_VERSION 1.0.0 >/dev/null
bash "$ROOT/tests/ui-snapshot/run.sh" "$TMP/pro" >/dev/null
diff -ru "$ROOT/tests/ui-snapshot/baseline" "$TMP/pro" || fail "Pro present: UI differs from the baseline"
assert_eq "$(count_placements "$TMP/pro" show_again)" 0 "Pro present: no show-again line"
assert_eq "$(count_placements "$TMP/pro" help)" 0 "Pro present: no placement at all"
cleanup

bash "$ROOT/tests/ui-snapshot/run.sh" "$TMP/tips" >/dev/null
strip_placements "$TMP/tips" "$TMP/stripped"
diff -ru "$ROOT/tests/ui-snapshot/baseline" "$TMP/stripped" || fail "tips shown: UI differs from the baseline outside the Pro placements"
assert_eq "$(count_placements "$TMP/tips" show_again)" 0 "tips shown: no show-again line"

expect setup "pro_tab setup " tips
expect help "help pro_tab " tips
expect results-empty "pro_tab " tips
expect results-list "pro_tab " tips
expect running "pro_tab " tips
expect done "pro_tab " tips
expect result-current "pro_tab result " tips
expect result-detail "pro_tab result " tips
for s in dashboard-idle dashboard-running dashboard-after adminbar-running adminbar-done; do expect "$s" "" tips; done
