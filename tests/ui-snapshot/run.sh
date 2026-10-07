#!/usr/bin/env bash
# Normalized HTML of every admin screen, to prove a change leaves the UI untouched:
#   bash tests/ui-snapshot/run.sh build/ui-before && ... && bash tests/ui-snapshot/run.sh build/ui-after && diff -r build/ui-before build/ui-after
source "$(dirname "$0")/../e2e/lib.sh"
OUT=${1:-$ROOT/build/ui-snapshot}
rm -rf "$OUT"; mkdir -p "$OUT"
TOOLS="/wp-admin/admin.php?page=culprit-finder"

norm() {
  python3 -c '
import re, sys
html = sys.stdin.read()
parts = []
for pat in [r"<div class=\"wrap culprit-finder\">.*?<div class=\"clear\"></div>", r"<div class=\"culprit-finder-widget\">.*?</div>\s*</div>\s*</div>", r"<li id=\"wp-admin-bar-culprit-finder\".*?</ul></div></li>", r"<li id=\"toplevel_page_culprit-finder\".*?</li>"]:
    parts += re.findall(pat, html, re.S)
s = "\n".join(parts)
s = re.sub(r"[0-9a-f]{64}", "KEY", s)
s = re.sub(r"_wpnonce=[0-9a-f]+", "_wpnonce=N", s)
s = re.sub(r"name=\"_wpnonce\" value=\"[0-9a-f]+\"", "name=\"_wpnonce\" value=\"N\"", s)
s = re.sub(r"result=[0-9a-f]{12}", "result=ID", s)
s = re.sub(r"\d+ min left", "M min left", s)
s = re.sub(r"(January|February|March|April|May|June|July|August|September|October|November|December) \d{1,2}, \d{4}( \d{1,2}:\d{2} [ap]m)?", "DATE", s)
s = re.sub(r"ver=[^\"&]+", "ver=V", s)
s = re.sub(r"Culprit Finder \d+\.\d+\.\d+", "Culprit Finder VERSION", s)
s = re.sub(r">\s*<", ">\n<", s)
print(s)
'
}
snap() { fetch "$JAR" "$2" | norm > "$OUT/$1.html"; [ -s "$OUT/$1.html" ] || fail "empty snapshot $1"; }

reset_site $(noise 1 4) cff-fixtures/cff-solo >/dev/null
wpcli option delete culprit_finder_results >/dev/null 2>&1 || true
wpcli option delete culprit_finder_last_result >/dev/null 2>&1 || true
JAR=$TMP/admin.jar; login "$JAR"

snap results-empty "$TOOLS&tab=results"
snap help "$TOOLS&tab=help"
snap dashboard-idle "/wp-admin/index.php"
# The setup page changes its key every load; normalized above.
snap setup "$TOOLS"

PAGE=$(fetch "$JAR" "$TOOLS")
NONCE=$(grep -m1 -oE 'name="_wpnonce" value="[^"]+"' <<<"$PAGE" | sed -E 's/.*value="([^"]+)"/\1/')
KEY=$(grep -m1 -oE 'name="culprit_finder_recovery" value="[^"]+"' <<<"$PAGE" | sed -E 's/.*value="([^"]+)"/\1/')
curl -s -o /dev/null -b "$JAR" -c "$JAR" --data-urlencode "_wpnonce=$NONCE" --data "action=culprit_finder_start&culprit_finder_saved=1&culprit_finder_recovery=$KEY" \
  --data-urlencode "culprit_finder_problem_url=$BASE/sample-page/" --data-urlencode "culprit_finder_pin[]=cff-fixtures/cff-noise-01.php" "$BASE/wp-admin/admin-post.php?culprit_safe=1"
snap running "$TOOLS&culprit_safe=1"
snap adminbar-running "/"
snap dashboard-running "/wp-admin/index.php"
answer_loop "$JAR" symptom_marker
wpcli eval '$o = get_option( "culprit_finder_results" ); foreach ( $o as &$r ) { $r["finished_at"] = 1790000000; } update_option( "culprit_finder_results", $o, false ); $l = get_option( "culprit_finder_last_result" ); $l["finished_at"] = 1790000000; update_option( "culprit_finder_last_result", $l, false );' >/dev/null
RID=$(wpjson option get culprit_finder_last_result --format=json | jq -r .id)
snap done "$TOOLS&culprit_safe=1"
snap result-current "$TOOLS&tab=results&result=$RID&culprit_safe=1"
snap adminbar-done "/"
wpcli culprit-finder exit >/dev/null
snap results-list "$TOOLS&tab=results"
snap result-detail "$TOOLS&tab=results&result=$RID"
snap dashboard-after "/wp-admin/index.php"
ls "$OUT" | wc -l | tr -d ' ' | xargs echo "snapshots:"
