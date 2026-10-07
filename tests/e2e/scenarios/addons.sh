#!/usr/bin/env bash
# Add-ons (ADR-0024): an add-on that declares Requires Plugins: culprit-finder and asks through
# culprit_finder_always_on stays on in every step and in safe mode without being ticked; a plugin that
# asks without declaring the dependency is ignored. UI hooks and the answer API work for the owner only.
source "$(dirname "$0")/../lib.sh"
ADDON=cff-fixtures/cff-addon.php
INTRUDER=cff-fixtures/cff-intruder.php
SOLO=cff-fixtures/cff-solo.php
trap 'wpcli option delete cff_addon_mode >/dev/null 2>&1 || true; wpcli option delete cff_addon_log >/dev/null 2>&1 || true; wpcli user delete cffadmin2 --yes >/dev/null 2>&1 || true' EXIT
BEFORE=$(reset_site $(noise 1 4) cff-fixtures/cff-solo cff-fixtures/cff-addon cff-fixtures/cff-intruder)
mode() { wpcli option update cff_addon_mode "$1" >/dev/null; }
session() { wpjson option get culprit_finder_session --format=json; }
mode ''
JAR=$TMP/admin.jar; login "$JAR"
TOOLS="/wp-admin/admin.php?page=culprit-finder"

# browser_start [curl args...]: submit the setup form as the browser would; headers in $TMP/start.h.
browser_start() {
  local page nonce key
  page=$(fetch "$JAR" "$TOOLS")
  nonce=$(grep -m1 -oE 'name="_wpnonce" value="[^"]+"' <<<"$page" | sed -E 's/.*value="([^"]+)"/\1/')
  key=$(grep -m1 -oE 'name="culprit_finder_recovery" value="[^"]+"' <<<"$page" | sed -E 's/.*value="([^"]+)"/\1/')
  curl -s -o /dev/null -D "$TMP/start.h" -b "$JAR" -c "$JAR" --data-urlencode "_wpnonce=$nonce" \
    --data "action=culprit_finder_start&culprit_finder_saved=1&culprit_finder_recovery=$key" "$@" "$BASE/wp-admin/admin-post.php?culprit_safe=1"
  START_KEY=$key
}
token_of() { awk '$6 == "wp-culprit-finder" { print $7 }' "$1" | tail -n 1; }

# Setup screen: the add-on is listed as always on (checked, disabled, not submitted); the intruder is a normal choice.
SETUP=$(fetch "$JAR" "$TOOLS")
assert_contains "$SETUP" '<label class="cf-addon"><input type="checkbox" checked disabled> CFF Add-on 1.0.0 <span class="cf-muted">Always on · Culprit Finder add-on</span></label>' "setup: add-on shown as always on"
assert_not_contains "$SETUP" "value=\"$ADDON\"" "setup: add-on is not a pin choice"
assert_contains "$SETUP" "name=\"culprit_finder_pin[]\" value=\"$INTRUDER\"" "setup: intruder is an ordinary plugin"
assert_contains "$SETUP" "6 plugins to test" "setup: the add-on is not counted as tested"
assert_contains "$SETUP" "CFF-ADDON-SETUP" "setup_fields: add-on fields render inside the form"
assert_contains "$SETUP" ">Optional<" "problem_url_required: optional by default"

# Start without ticking anything.
browser_start
S=$(session)
assert_eq "$(jq -c .always_on <<<"$S")" "[\"$ADDON\"]" "always_on: only the declared add-on is accepted"
assert_eq "$(jq -c .pinned <<<"$S")" "[]" "always_on: nothing was ticked"
assert_eq "$(jq -r --arg a "$ADDON" '.fixed | index($a) != null' <<<"$S")" true "always_on: add-on in the fixed set"
assert_eq "$(jq -r --arg a "$INTRUDER" '.fixed | index($a) == null' <<<"$S")" true "always_on: intruder is a suspect"
assert_eq "$(jq -r '.fixed | index("cff-fixtures/cff-noise-01.php") == null' <<<"$S")" true "always_on: other plugins named by the filter are ignored"
assert_eq "$(jq -r '.ends_at - .created_at' <<<"$S")" 10800 "session: three-hour maximum by default"
assert_eq "$(jq -r '.expires_at - .created_at' <<<"$S")" 3600 "session: one idle hour"

PANEL=$(fetch "$JAR" "$TOOLS&culprit_safe=1")
assert_contains "$PANEL" "CFF Add-on 1.0.0 (always on · Culprit Finder add-on)" "step list: add-on labeled"
assert_contains "$PANEL" "Is the problem still there?" "step_panel: default question without a panel"
assert_contains "$PANEL" "CFF-ADDON-ADMIN" "add-on loads on the control panel (safe mode)"

step_check() {
  local front safe
  front=$(fetch "$1" /); safe=$(fetch "$1" "/?culprit_safe=1")
  assert_contains "$front" "CFF-ADDON" "add-on on in step $(jq_r .question "$STEP")"
  assert_contains "$safe" "CFF-ADDON" "add-on on in safe mode"
  assert_eq "$(noise_count "$safe")" 0 "safe mode: only the fixed set"
  assert_not_contains "$safe" "CFF-INTRUDER" "safe mode: intruder off"
}
assert_not_contains "$(fetch "$JAR" /)" "CFF-INTRUDER" "baseline step: intruder off"
answer_loop "$JAR" symptom_marker step_check
assert_result SINGLE "$SOLO"
REC=$(wpjson option get culprit_finder_last_result --format=json)
assert_eq "$(jq -c .always_on <<<"$REC")" "[\"$ADDON\"]" "result record lists the add-on"
assert_eq "$(jq -r .tested <<<"$REC")" 6 "result: add-on not tested"
REPORT=$(wpcli culprit-finder result --format=report)
assert_contains "$REPORT" "(Culprit Finder add-ons kept on: CFF Add-on 1.0.0)" "report: add-on kept on"
assert_not_contains "$REPORT" "CFF Intruder" "report: intruder not kept on"
RID=$(jq -r .id <<<"$REC")
DETAIL=$(fetch "$JAR" "$TOOLS&tab=results&result=$RID&culprit_safe=1")
assert_contains "$DETAIL" "<dt>Add-ons kept on</dt><dd>CFF Add-on 1.0.0</dd>" "result page: add-on row"
assert_contains "$DETAIL" "CFF-ADDON-ACTION with-session clean" "result_actions: session of the result, record without user id"
wpcli culprit-finder exit >/dev/null
assert_contains "$(fetch "$JAR" "$TOOLS&tab=results&result=$RID")" "CFF-ADDON-ACTION no-session clean" "result_actions: no session after exit"
assert_not_contains "$(fetch "$JAR" "$TOOLS&tab=results")" "CFF-ADDON-ACTION" "result_actions: not on the list"

# A throwing always_on callback leaves Start working, without add-ons.
mode throw-always
start_session
assert_eq "$(jq -c .always_on <<<"$(session)")" "[]" "always_on: a throwing callback keeps nothing on"
wpcli culprit-finder exit >/dev/null

# Step panel replaces the question (and Undo), core keeps progress and exit; admin bar label.
mode panel,label
browser_start
admin_cli answer no >/dev/null
PANEL=$(fetch "$JAR" "$TOOLS&culprit_safe=1")
assert_contains "$PANEL" "CFF-ADDON-PANEL q=2 always=$ADDON" "step_panel: add-on panel with step and session"
assert_not_contains "$PANEL" "Is the problem still there?" "step_panel: replaces the question"
CARD=$(python3 -c 'import re,sys; print("".join(re.findall(r"<section class=\"cf-card cf-question culprit-finder-running\">.*?</section>", sys.stdin.read(), re.S)))' <<<"$PANEL")
assert_contains "$CARD" "CFF-ADDON-PANEL" "step_panel: inside the question card"
assert_not_contains "$CARD" "Undo last answer" "step_panel: replaces Undo"
assert_contains "$PANEL" "Step 2 of about" "step_panel: progress stays"
assert_contains "$PANEL" "Stop and exit" "step_panel: exit stays"
assert_contains "$(fetch "$JAR" /)" "CFF-ADDON-LABEL step 2" "admin_bar_label: toolbar text"
YES=$(grep -m1 -oE 'href="[^"]+">CFF-YES' <<<"$PANEL" | sed -E 's/href="([^"]+)".*/\1/; s/&#038;/\&/g; s/&amp;/\&/g')
curl -s -o /dev/null -b "$JAR" -c "$JAR" "$YES"
assert_eq "$(wpjson culprit-finder status --format=json | jq -r .answers_used)" 2 "culprit_finder_action_url: answers like the built-in button"
wpcli culprit-finder exit >/dev/null
mode ''

# problem_url_required and before_start refuse a start, explain why, and keep the input and the saved exit link.
mode require-url
browser_start --data-urlencode "culprit_finder_addon[cff][note]=keep me" --data-urlencode "culprit_finder_pin[]=$INTRUDER"
wpcli option get culprit_finder_session >/dev/null 2>&1 && fail "required problem URL: a session started"
AGAIN=$(fetch "$JAR" "$TOOLS")
assert_contains "$AGAIN" "Please paste the address of the page where you see the problem." "problem_url_required: message"
assert_contains "$AGAIN" "name=\"culprit_finder_recovery\" value=\"$START_KEY\"" "refused start keeps the saved exit link"
assert_contains "$AGAIN" "CFF-ADDON-KEPT:keep me" "refused start keeps add-on input"
assert_contains "$AGAIN" "value=\"$INTRUDER\" checked" "refused start keeps ticked plugins"
assert_contains "$AGAIN" ">Required<" "problem_url_required: marked required"
assert_not_contains "$(fetch "$JAR" "$TOOLS")" "Please paste the address" "the message shows once"
mode block
browser_start --data-urlencode "culprit_finder_problem_url=$BASE/sample-page/"
wpcli option get culprit_finder_session >/dev/null 2>&1 && fail "before_start: a session started"
AGAIN=$(fetch "$JAR" "$TOOLS")
assert_contains "$AGAIN" "CFF-ADDON-BLOCKED: the add-on said no" "before_start: plain-text message"
assert_contains "$AGAIN" "value=\"$BASE/sample-page/\"" "refused start keeps the problem URL"
LOG=$(wpjson option get cff_addon_log --format=json)
assert_eq "$(jq -r '[.[] | select(.hook == "before_start")] | last | .data.input.problem_url' <<<"$LOG")" "$BASE/sample-page/" "before_start: gets the validated problem URL"
mode ''

# Answer API (culprit_finder_current_step / culprit_finder_submit_answer) from the add-on's REST route.
browser_start
TOKEN=$(token_of "$JAR")
[ ${#TOKEN} -eq 64 ] || fail "no session cookie after start"
PAGE=$(fetch "$JAR" "$TOOLS&culprit_safe=1")
RNONCE=$(grep -m1 -oE 'CFF-REST-NONCE:[a-f0-9]+' <<<"$PAGE" | cut -d: -f2)
rest() { local jar=$1; shift; curl -s -b "$jar" "$@"; }
assert_eq "$(rest "$JAR" -H "X-WP-Nonce: $RNONCE" "$BASE/wp-json/cff-addon/v1/step" | jq -r .step.question)" 1 "current_step: owner sees the step"
assert_eq "$(curl -s "$BASE/wp-json/cff-addon/v1/step" | jq -r .step)" null "current_step: null for visitors"
post() { local jar=$1 answer=$2 q=$3; shift 3; curl -s -b "$jar" "$@" --data "answer=$answer&question=$q" "$BASE/wp-json/cff-addon/v1/answer"; }
assert_eq "$(post "$JAR" no 2 -H "X-WP-Nonce: $RNONCE" | jq -r .code)" culprit_finder_stale_question "submit_answer: wrong question refused"
assert_eq "$(post "$JAR" maybe 1 -H "X-WP-Nonce: $RNONCE" | jq -r .code)" culprit_finder_invalid_answer "submit_answer: maybe refused"
assert_eq "$(post "$JAR" no 1 | jq -r .code)" culprit_finder_not_owner "submit_answer: no REST nonce, no user"
assert_eq "$(curl -s --data "answer=no&question=1" "$BASE/wp-json/cff-addon/v1/answer" | jq -r .code)" culprit_finder_not_owner "submit_answer: logged out refused"
grep -v wp-culprit-finder "$JAR" > "$TMP/nosession.jar"
assert_eq "$(post "$TMP/nosession.jar" no 1 -H "X-WP-Nonce: $RNONCE" | jq -r .code)" culprit_finder_not_owner "submit_answer: missing session cookie refused"
wpcli user create cffadmin2 cffadmin2@example.com --role=administrator --user_pass=password >/dev/null 2>&1 || true
JAR2=$TMP/admin2.jar; login "$JAR2" cffadmin2 password
PAGE=$(fetch "$JAR2" /wp-admin/)
N2=$(grep -m1 -oE 'CFF-REST-NONCE:[a-f0-9]+' <<<"$PAGE" | cut -d: -f2)
add_token "$JAR2" "$TOKEN"
assert_eq "$(post "$JAR2" no 1 -H "X-WP-Nonce: $N2" | jq -r .code)" culprit_finder_not_owner "submit_answer: another admin with the token refused"
assert_eq "$(wpjson culprit-finder status --format=json | jq -r .answers_used)" 0 "refused answers changed nothing"
OUT=$(wpcli --user=admin eval '$r = culprit_finder_submit_answer( "no", 1 ); echo is_wp_error( $r ) ? $r->get_error_code() : "ANSWERED";')
assert_eq "$OUT" culprit_finder_not_owner "submit_answer: not available in WP-CLI"
BODY=$(post "$JAR" no 1 -H "X-WP-Nonce: $RNONCE" -D "$TMP/answer.h")
assert_eq "$(jq -r .step.question <<<"$BODY")" 2 "submit_answer: step advances"
EXP=$(jq -r .expires_at <<<"$(session)")
COOKIE_EXP=$(grep -i '^set-cookie: wp-culprit-finder=' "$TMP/answer.h" | grep -m1 -oiE 'expires=[^;]+' | cut -d= -f2-)
[ -n "$COOKIE_EXP" ] || fail "submit_answer: no cookie refresh"
assert_eq "$(python3 -c 'import sys,email.utils; print(int(email.utils.parsedate_to_datetime(sys.argv[1]).timestamp()))' "$COOKIE_EXP")" "$EXP" "submit_answer: cookie expiry = session expiry"
admin_cli undo >/dev/null
assert_eq "$(wpjson culprit-finder status --format=json | jq -r .question)" 1 "submit_answer: Undo works on API answers"
wpcli culprit-finder exit >/dev/null
assert_eq "$(wpcli option get active_plugins --format=json)" "$BEFORE" "AC-2 active_plugins unchanged"
