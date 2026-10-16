#!/usr/bin/env bash
# Every kind of request from the session owner's browser runs the step's reduced plugin set: admin-ajax,
# a /wp-json/ route (with and without a REST nonce), a front-end form POST, a followed redirect (both
# hops) and a same-site page loaded as an iframe. A visitor and a second admin get the full set for the
# same requests. Filtered AJAX/REST responses carry no-cache headers. The probe (cff-request-probe)
# reports the marker plugins PHP actually included, not the option value.
source "$(dirname "$0")/../lib.sh"
PROBE=cff-fixtures/cff-request-probe.php
BEFORE=$(reset_site $(noise 1 8) cff-fixtures/cff-solo "$PROBE")
wpcli user get admin2 >/dev/null 2>&1 || wpcli user create admin2 admin2@example.test --role=administrator --user_pass=password >/dev/null
JAR=$TMP/admin.jar; login "$JAR"
JAR2=$TMP/admin2.jar; login "$JAR2" admin2 password
TOOLS="/wp-admin/admin.php?page=culprit-finder"

# Start from the setup form like a real browser, keeping the probe on, so the cookie is the one WordPress sets.
PAGE=$(fetch "$JAR" "$TOOLS")
NONCE=$(grep -m1 -oE 'name="_wpnonce" value="[^"]+"' <<<"$PAGE" | sed -E 's/.*value="([^"]+)"/\1/')
KEY=$(grep -m1 -oE 'name="culprit_finder_recovery" value="[^"]+"' <<<"$PAGE" | sed -E 's/.*value="([^"]+)"/\1/')
curl -s -o /dev/null -b "$JAR" -c "$JAR" --data-urlencode "_wpnonce=$NONCE" --data "action=culprit_finder_start&culprit_finder_saved=1&culprit_finder_recovery=$KEY" \
  --data-urlencode "culprit_finder_pin[]=$PROBE" "$BASE/wp-admin/admin-post.php"
COOKIE_LINE=$(grep -F wp-culprit-finder "$JAR" || true)
[ -n "$COOKIE_LINE" ] || fail "start did not set the session cookie"
assert_eq "$(awk '{print $3}' <<<"$COOKIE_LINE")" / "session cookie path covers wp-admin, wp-json and the front end"
admin_cli answer no >/dev/null

STATUS=$(wpjson culprit-finder status --format=json)
assert_eq "$(jq_r .phase "$STATUS")" find_first "mid-search step"
markers() { jq -r '.[] | select(test("^cff-fixtures/cff-(noise-[0-9]+|solo)\\.php$"))' | sed -E 's#^cff-fixtures/(.*)\.php$#\1#' | sort | paste -sd, -; }
REDUCED=$(jq '.enabled' <<<"$STATUS" | markers)
FULL=$(jq '.snapshot' <<<"$(wpjson option get culprit_finder_session --format=json)" | markers)
[ -n "$REDUCED" ] && [ "$REDUCED" != "$FULL" ] || fail "step should have some but not all markers on ($REDUCED)"
echo "reduced=$REDUCED"
echo "full=$FULL"

probe_of() { grep -oE 'CFF-LOADED\[via=[a-z-]+;[^]]*\]' | head -n 1; }
expect_probe() { assert_eq "$1" "CFF-LOADED[via=$2;$3]" "$4"; }
lower() { tr 'A-Z' 'a-z'; }

# check <jar|-> <expected set> <who>
check() {
  local jar=$1 want=$2 who=$3 c=() hdrs body nonce
  [ "$jar" = - ] || c=(-b "$jar")

  hdrs=$(curl -s -D - -o "$TMP/ajax" ${c[@]+"${c[@]}"} "$BASE/wp-admin/admin-ajax.php?action=cff_probe")
  expect_probe "$(probe_of < "$TMP/ajax")" ajax "$want" "$who: admin-ajax GET"
  [ "$who" = owner ] && assert_contains "$(lower <<<"$hdrs")" "cache-control: no-cache" "$who: filtered admin-ajax is not cacheable"
  expect_probe "$(curl -s ${c[@]+"${c[@]}"} --data "action=cff_probe" "$BASE/wp-admin/admin-ajax.php" | probe_of)" ajax "$want" "$who: admin-ajax POST"

  hdrs=$(curl -s -D - -o "$TMP/rest" ${c[@]+"${c[@]}"} "$BASE/wp-json/cff/v1/probe")
  expect_probe "$(jq -r .probe "$TMP/rest")" rest "$want" "$who: REST without nonce"
  [ "$who" = owner ] && assert_contains "$(lower <<<"$hdrs")" "cache-control: no-cache" "$who: filtered REST (no nonce) is not cacheable"
  if [ "$jar" != - ]; then
    nonce=$(curl -s ${c[@]+"${c[@]}"} "$BASE/wp-admin/admin-ajax.php?action=rest-nonce")
    [ -n "$nonce" ] && [ "$nonce" != 0 ] || fail "$who: no REST nonce"
    hdrs=$(curl -s -D - -o "$TMP/rest" ${c[@]+"${c[@]}"} -H "X-WP-Nonce: $nonce" -X POST "$BASE/wp-json/cff/v1/probe")
    expect_probe "$(jq -r .probe "$TMP/rest")" rest "$want" "$who: REST POST with nonce"
    [ "$who" = owner ] && assert_contains "$(lower <<<"$hdrs")" "cache-control: no-cache" "$who: filtered REST (nonce) is not cacheable"
  fi

  expect_probe "$(curl -s ${c[@]+"${c[@]}"} --data "cff_probe_form=1&field=value" "$BASE/" | probe_of)" form-post "$want" "$who: front-end form POST"

  hdrs=$(curl -s -L -D - -o "$TMP/redir" ${c[@]+"${c[@]}"} "$BASE/?cff_probe_redirect=1")
  assert_contains "$hdrs" "302" "$who: redirect response"
  expect_probe "$(grep -i '^x-cff-probe:' <<<"$hdrs" | sed -E 's/^[^:]+: //' | tr -d '\r')" redirect-hop "$want" "$who: redirecting request"
  expect_probe "$(probe_of < "$TMP/redir")" redirect "$want" "$who: redirect target"

  body=$(curl -s ${c[@]+"${c[@]}"} "$BASE/?cff_probe_frame=1")
  local src
  src=$(grep -oE 'src="[^"]+"' <<<"$body" | head -n 1 | sed -E 's/src="([^"]+)"/\1/; s/&#038;/\&/g; s/&amp;/\&/g')
  [ -n "$src" ] || fail "$who: frame host page has no iframe"
  expect_probe "$(curl -s ${c[@]+"${c[@]}"} -H 'Sec-Fetch-Dest: iframe' -H 'Sec-Fetch-Mode: navigate' -H 'Sec-Fetch-Site: same-origin' -e "$BASE/?cff_probe_frame=1" "$src" | probe_of)" iframe "$want" "$who: iframe target"
}

check "$JAR" "$REDUCED" owner
check - "$FULL" visitor
check "$JAR2" "$FULL" "second admin"
echo "curl: ajax GET/POST, REST (no nonce, nonce), form POST, redirect (both hops), iframe target: owner reduced, visitor + second admin full"

# A real browser: the iframe of a same-site page sends the session cookie (SameSite=Lax) and gets the step's set.
if command -v node >/dev/null 2>&1 && [ -d "$ROOT/tests/screenshots/node_modules/playwright-core" ]; then
  FRAME=$(node "$ROOT/tests/screenshots/iframe-probe.mjs" "$BASE" "$JAR" 2>"$TMP/node.err") || { cat "$TMP/node.err" >&2; fail "browser iframe check failed to run"; }
  expect_probe "$FRAME" iframe "$REDUCED" "owner: iframe in a real browser"
  FRAME_V=$(node "$ROOT/tests/screenshots/iframe-probe.mjs" "$BASE" - 2>"$TMP/node.err") || { cat "$TMP/node.err" >&2; fail "browser iframe check failed to run"; }
  expect_probe "$FRAME_V" iframe "$FULL" "visitor: iframe in a real browser"
  echo "browser iframe: owner $FRAME, visitor $FRAME_V"
else
  echo "SKIP: real-browser iframe check (node or playwright-core missing)"
fi

assert_eq "$(wpcli option get active_plugins --format=json)" "$BEFORE" "AC-2 active_plugins unchanged"
wpcli culprit-finder exit >/dev/null
