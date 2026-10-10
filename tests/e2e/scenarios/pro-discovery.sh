#!/usr/bin/env bash
# Pro discovery (ADR-0025): placements only on Culprit Finder's screens and the Plugins row, utm per
# placement, never in other admin screens, the front end, the support report or WP-CLI; "Hide Pro tips"
# is nonce- and capability-checked and per user; everything disappears when Culprit Finder Pro is
# present (by its active state, tested with the stub in tests/fixtures/pro-stub, or by its constant),
# and Pro's own `pro` tab is then the only one.
source "$(dirname "$0")/../lib.sh"
TOOLS="/wp-admin/admin.php?page=culprit-finder"
STUB_SRC=wp-content/plugins/culprit-finder/tests/fixtures/pro-stub/culprit-finder-pro
STUB_DST=wp-content/plugins/culprit-finder-pro
PRO=culprit-finder-pro/culprit-finder-pro.php
ATTR=data-culprit-finder-pro-tip

stub_remove() {
  wpcli plugin deactivate "$PRO" >/dev/null 2>&1 || true
  docker exec -w /var/www/html "$CLI_CONTAINER" sh -c "grep -qs 'Culprit Finder Pro (test stub)' $STUB_DST/culprit-finder-pro.php && rm -rf $STUB_DST" >/dev/null 2>&1 || true
}
cleanup() {
  wpcli culprit-finder exit >/dev/null 2>&1 || true
  stub_remove
  wpcli config delete CULPRIT_FINDER_PRO_VERSION >/dev/null 2>&1 || true
  local u
  for u in admin cfadmin3; do wpcli user meta delete "$u" culprit_finder_hide_pro_tips >/dev/null 2>&1 || true; done
}
trap cleanup EXIT
[ -n "$CLI_CONTAINER" ] || fail "needs the wp-env cli container"
cleanup

reset_site $(noise 1 4) cff-fixtures/cff-solo >/dev/null
wpcli option delete culprit_finder_results >/dev/null 2>&1 || true
wpcli option delete culprit_finder_last_result >/dev/null 2>&1 || true
JAR=$TMP/admin.jar; login "$JAR"

start_session
add_token "$JAR" "$TOKEN"
answer_loop "$JAR" symptom_marker
assert_result SINGLE cff-fixtures/cff-solo.php
CLIOUT="$(admin_cli status --format=json)$(admin_cli result --format=json)$(wpcli culprit-finder --help 2>&1)"
assert_not_contains "$CLIOUT" "Culprit Finder Pro" "WP-CLI output has no tips"
assert_not_contains "$CLIOUT" "getculpritfinder.com/pro" "WP-CLI output has no Pro link"
wpcli culprit-finder exit >/dev/null
RID=$(wpjson option get culprit_finder_last_result --format=json | jq -r .id)

no_tips() { # <label> <html>
  assert_not_contains "$2" "$ATTR" "$1: no placement"
  assert_not_contains "$2" "getculpritfinder.com/pro" "$1: no Pro link"
  assert_not_contains "$2" "Culprit Finder Pro checks" "$1: no setup copy"
  assert_not_contains "$2" "Culprit Finder Pro turns" "$1: no result copy"
  assert_not_contains "$2" "Is there a Pro version" "$1: no help copy"
  assert_not_contains "$2" "From \$29/year" "$1: no price"
  assert_not_contains "$2" "Hide Pro tips" "$1: no hide link"
}
href() { python3 -c 'import re,sys,html; m=re.search(sys.argv[1], sys.stdin.read()); print(html.unescape(m.group(1)) if m else "")' "$1"; }
utm_link() { # <html> <placement> → the Pro link of that placement
  href "href=\"(https://getculpritfinder\.com/pro/\?utm_source=plugin&(?:#038;|amp;)?utm_medium=$2)\"" <<<"$1"
}

# Placements and their links.
SETUP=$(fetch "$JAR" "$TOOLS")
HELP=$(fetch "$JAR" "$TOOLS&tab=help")
TAB=$(fetch "$JAR" "$TOOLS&tab=pro")
RESULT=$(fetch "$JAR" "$TOOLS&tab=results&result=$RID")
PLUGINS=$(fetch "$JAR" "/wp-admin/plugins.php")
assert_contains "$SETUP" "Prefer hands-free? Culprit Finder Pro checks the page at every step for you." "setup line"
assert_contains "$HELP" "Is there a Pro version?" "help entry"
assert_contains "$RESULT" "Fixing this for a client? Culprit Finder Pro turns this result into a branded report you can send them, and next time it finds the culprit without you answering." "result card"
for s in "Check it for me" "Theme check" "Client reports" "From \$29/year per site" "14-day refund" "Your settings and history stay. Pro adds to this plugin." "Single Site" "5 Sites" "Unlimited Sites" "\$29/year" "\$99/year" "\$199/year" ">Get Pro<"; do
  assert_contains "$TAB" "$s" "Pro tab: $s"
done
assert_eq "$(grep -o "$ATTR=\"pro_tab\"" <<<"$TAB" | wc -l | tr -d ' ')" 2 "Pro tab: nav link + panel"
[ -n "$(utm_link "$SETUP" setup)" ] || fail "no utm link for setup"
[ -n "$(utm_link "$HELP" help)" ] || fail "no utm link for help"
[ -n "$(utm_link "$TAB" pro_tab)" ] || fail "no utm link for pro_tab"
[ -n "$(utm_link "$RESULT" result)" ] || fail "no utm link for result"
[ -n "$(utm_link "$PLUGINS" plugins_row)" ] || fail "no utm link for plugins_row"
assert_eq "$(grep -o "utm_medium=plugins_row" <<<"$PLUGINS" | wc -l | tr -d ' ')" 1 "one Plugins row link"
assert_eq "$(grep -o ">Get Pro</a>" <<<"$PLUGINS" | wc -l | tr -d ' ')" 1 "Plugins row: Get Pro once"
assert_eq "$(grep -oE 'https?://getculpritfinder\.com/pro[^"'"'"' <]*' <<<"$SETUP$HELP$TAB$RESULT$PLUGINS" | grep -vcE '^https://getculpritfinder\.com/pro/\?utm_source=plugin&(#038;|amp;)?utm_medium=(setup|help|pro_tab|result|plugins_row)$' || true)" 0 "every Pro link carries utm"

# The support report never contains tips: the copy text and both downloads.
REPORT=$(python3 -c 'import re,sys; m=re.search(r"<pre id=\"culprit-finder-report\"[^>]*>(.*?)</pre>", sys.stdin.read(), re.S); print(m.group(1) if m else "MISSING")' <<<"$RESULT")
assert_not_contains "$REPORT" "MISSING" "report text present"
no_tips "report copy text" "$REPORT"
for f in md txt; do
  URL=$(href "href=\"([^\"]*action=culprit_finder_download[^\"]*format=$f[^\"]*)\"" <<<"$RESULT")
  [ -n "$URL" ] || fail "no .$f download link"
  no_tips "report .$f" "$(curl -s -b "$JAR" "$URL")"
done

# Never on other admin screens, the dashboard (widget), or the front end and its toolbar.
for p in /wp-admin/index.php /wp-admin/options-general.php /wp-admin/edit.php /wp-admin/themes.php /wp-admin/profile.php /wp-admin/tools.php; do
  no_tips "$p" "$(fetch "$JAR" "$p")"
done
no_tips "front end (admin)" "$(fetch "$JAR" /)"
no_tips "front end (visitor)" "$(fetch - /)"

# Not for users who can't manage plugins.
wpcli user get cfeditor >/dev/null 2>&1 || wpcli user create cfeditor cfeditor@example.test --role=editor --user_pass=password >/dev/null
EJAR=$TMP/editor.jar; login "$EJAR" cfeditor
no_tips "editor dashboard" "$(fetch "$EJAR" /wp-admin/index.php)"

# Hide Pro tips: capability and nonce checked, then per user and permanent. Click it from the Help tab:
# its redirect must land back on the Help tab, not merely on the Culprit Finder page (Links::tools()
# is both the fallback and the target, so "contains page=culprit-finder" would pass either way).
HIDE_RAW=$(href "href=\"([^\"]*action=culprit_finder_hide_pro_tips[^\"]*)\"" <<<"$HELP")
[ -n "$HIDE_RAW" ] || fail "no Hide Pro tips link on the Help tab"
HIDE=$(python3 -c 'import html,sys; print(html.unescape(sys.argv[1]))' "$HIDE_RAW")
for page in "$SETUP" "$HELP" "$TAB" "$RESULT"; do assert_contains "$page" "Hide Pro tips" "hide link on each placement"; done
NONONCE=$(sed -E 's/[?&]_wpnonce=[^&]*//' <<<"$HIDE_RAW")
assert_eq "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" "$NONONCE")" 403 "hide without nonce refused"
assert_eq "$(curl -s -o /dev/null -w '%{http_code}' -b "$EJAR" "$HIDE")" 403 "hide by an editor refused"
assert_eq "$(wpcli user meta get admin culprit_finder_hide_pro_tips 2>/dev/null || true)" "" "still visible after refused requests"
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR" "$HIDE")
assert_contains "$LOC" "page=culprit-finder" "hide redirects back"
assert_eq "$LOC" "$BASE$TOOLS&tab=help" "hide from the Help tab redirects to the exact Help URL"
assert_eq "$(wpcli user meta get admin culprit_finder_hide_pro_tips)" 1 "hidden in user meta"
for p in "$TOOLS" "$TOOLS&tab=help" "$TOOLS&tab=pro" "$TOOLS&tab=results&result=$RID" /wp-admin/plugins.php; do
  no_tips "hidden: $p" "$(fetch "$JAR" "$p")"
done
assert_not_contains "$(fetch "$JAR" "$TOOLS&tab=pro")" "class=\"cf-pro\"" "hidden: no Pro tab"
JAR2=$TMP/admin3.jar
wpcli user get cfadmin3 >/dev/null 2>&1 || wpcli user create cfadmin3 cfadmin3@example.test --role=administrator --user_pass=password >/dev/null
login "$JAR2" cfadmin3
assert_contains "$(fetch "$JAR2" "$TOOLS")" "$ATTR=\"setup\"" "another admin still sees tips"
login "$JAR"
no_tips "hidden after a new login" "$(fetch "$JAR" "$TOOLS")"
wpcli user meta delete admin culprit_finder_hide_pro_tips >/dev/null

# Culprit Finder Pro active (stub at Pro's basename, no constant): nothing from discovery, one `pro` tab: Pro's.
docker exec -w /var/www/html "$CLI_CONTAINER" cp -r "$STUB_SRC" "$STUB_DST"
wpcli plugin activate "$PRO" >/dev/null
for p in "$TOOLS" "$TOOLS&tab=help" "$TOOLS&tab=results&result=$RID" /wp-admin/plugins.php; do
  no_tips "Pro active: $p" "$(fetch "$JAR" "$p")"
done
PROTAB=$(fetch "$JAR" "$TOOLS&tab=pro")
assert_contains "$PROTAB" "CFF-PRO-STUB-TAB" "Pro's own tab renders"
assert_not_contains "$PROTAB" "class=\"cf-pro\"" "free Pro tab not rendered"
assert_eq "$(grep -oE 'class="cf-tab[^"]*" href="[^"]*tab=pro' <<<"$PROTAB" | wc -l | tr -d ' ')" 1 "exactly one Pro tab"
no_tips "Pro active: Pro tab" "$PROTAB"
# During a session the step may switch Pro off for this browser; the real plugin list still counts.
start_session
add_token "$JAR" "$TOKEN"
no_tips "Pro active, session, safe mode" "$(fetch "$JAR" "$TOOLS&culprit_safe=1")"
wpcli culprit-finder exit >/dev/null
stub_remove

# Culprit Finder Pro's constant alone also hides everything.
wpcli config set CULPRIT_FINDER_PRO_VERSION 1.0.0 >/dev/null
# PHP's opcache may serve the previous wp-config.php for up to revalidate_freq seconds.
for i in $(seq 1 10); do grep -qF "$ATTR=" <<<"$(fetch "$JAR" "$TOOLS")" || break; sleep 1; done
for p in "$TOOLS" "$TOOLS&tab=help" "$TOOLS&tab=pro" "$TOOLS&tab=results&result=$RID" /wp-admin/plugins.php; do
  no_tips "Pro constant: $p" "$(fetch "$JAR" "$p")"
done
wpcli config delete CULPRIT_FINDER_PRO_VERSION >/dev/null
for i in $(seq 1 10); do grep -qF "$ATTR=" <<<"$(fetch "$JAR" "$TOOLS")" && break; sleep 1; done
assert_contains "$(fetch "$JAR" "$TOOLS")" "$ATTR=\"setup\"" "tips back without Pro"
