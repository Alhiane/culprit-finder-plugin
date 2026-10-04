#!/usr/bin/env bash
# Results tab: history capped at 10, rendered result, .md/.txt downloads, delete, clear all, access control.
source "$(dirname "$0")/../lib.sh"
reset_site $(noise 1 2) cff-fixtures/cff-solo >/dev/null
wpcli option delete culprit_finder_results >/dev/null 2>&1 || true
wpcli option delete culprit_finder_last_result >/dev/null 2>&1 || true
JAR=$TMP/admin.jar; login "$JAR"
TOOLS="/wp-admin/admin.php?page=culprit-finder"
EMPTY=$(fetch "$JAR" "$TOOLS&tab=results")
assert_contains "$EMPTY" "No results yet" "empty state"

for i in $(seq 1 11); do
  admin_cli start >/dev/null
  admin_cli answer yes >/dev/null
done
wpcli culprit-finder exit >/dev/null
COUNT=$(wpjson option get culprit_finder_results --format=json | jq length)
assert_eq "$COUNT" 10 "history keeps the last 10"

LIST=$(fetch "$JAR" "$TOOLS&tab=results")
assert_contains "$LIST" "Not a plugin" "badge in the table"
assert_contains "$LIST" "cf-tab__count\">10<" "tab shows the count"
ID=$(wpjson option get culprit_finder_results --format=json | jq -r '.[0].id')
DETAIL=$(fetch "$JAR" "$TOOLS&tab=results&result=$ID")
assert_contains "$DETAIL" "It’s not one of the plugins tested" "rendered verdict"
assert_contains "$DETAIL" "Plugins tested" "environment table"
assert_not_contains "$DETAIL" "This is the result of your current session." "no session bar without a session"

link() { grep -oE "href=\"[^\"]*action=culprit_finder_$1[^\"]*$2[^\"]*\"" <<<"$3" | head -1 | sed -E 's/href="([^"]+)"/\1/; s/&amp;/\&/g; s/&#038;/\&/g'; }
MD=$(link download "format=md" "$DETAIL"); TXT=$(link download "format=txt" "$DETAIL")
[ -n "$MD" ] && [ -n "$TXT" ] || fail "download links missing"
HDR=$(curl -s -D - -o "$TMP/r.md" -b "$JAR" "$MD")
assert_contains "$HDR" "Content-Disposition: attachment; filename=\"culprit-finder-report-" "md is a download"
assert_contains "$HDR" "text/markdown" "md content type"
assert_contains "$(cat "$TMP/r.md")" "### Plugin conflict report" "md body"
curl -s -o "$TMP/r.txt" -b "$JAR" "$TXT"
assert_contains "$(head -1 "$TMP/r.txt")" "Plugin conflict report (Culprit Finder" "txt body"
assert_not_contains "$(cat "$TMP/r.txt")" "###" "txt has no Markdown heading"
assert_not_contains "$(cat "$TMP/r.md")" "localhost" "AC-11 no site URL in the download"

# Without a nonce, or as an editor: refused.
assert_eq "$(code "$JAR" "/wp-admin/admin-post.php?action=culprit_finder_download&result=$ID&format=md")" 403 "download needs a nonce"
wpcli user get cfeditor >/dev/null 2>&1 || wpcli user create cfeditor cfeditor@example.test --role=editor --user_pass=password >/dev/null
EJAR=$TMP/editor.jar; login "$EJAR" cfeditor password
assert_eq "$(code "$EJAR" "$(sed "s#^$BASE##" <<<"$MD")")" 403 "editor cannot download"

# Delete one.
DEL=$(link delete_result "result=$ID" "$DETAIL")
curl -s -o /dev/null -b "$JAR" "$DEL"
assert_eq "$(wpjson option get culprit_finder_results --format=json | jq length)" 9 "delete removes one result"
assert_contains "$(fetch "$JAR" "$TOOLS&tab=results&result=$ID")" "That result no longer exists." "deleted result is gone"

# Clear all.
CLEAR=$(link clear_results "" "$(fetch "$JAR" "$TOOLS&tab=results")")
curl -s -o /dev/null -b "$JAR" "$CLEAR"
wpcli option get culprit_finder_results >/dev/null 2>&1 && fail "results still stored after Clear all"
assert_contains "$(fetch "$JAR" "$TOOLS&tab=results")" "No results yet" "empty after Clear all"

# Help tab renders.
assert_contains "$(fetch "$JAR" "$TOOLS&tab=help")" "How to get out" "help tab"
