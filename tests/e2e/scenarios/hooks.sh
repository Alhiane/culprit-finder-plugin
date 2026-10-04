#!/usr/bin/env bash
# Every extension hook fires with its documented arguments, and no hook can break an invariant (ADR-0021).
source "$(dirname "$0")/../lib.sh"
SPY=cff-fixtures/cff-hooks-spy.php
trap 'wpcli option delete cff_hooks_spy_mode >/dev/null 2>&1 || true' EXIT
BEFORE=$(reset_site $(noise 1 4) cff-fixtures/cff-solo cff-fixtures/cff-hooks-spy)
mode() { wpcli option update cff_hooks_spy_mode "$1" >/dev/null; }
clear_log() { wpcli option delete cff_hooks_spy_log >/dev/null 2>&1 || true; }
log() { wpjson option get cff_hooks_spy_log --format=json 2>/dev/null || echo '[]'; }
last() { jq -c "[.[] | select(.hook == \"$1\")] | last | .data" <<<"$(log)"; }
count() { jq "[.[] | select(.hook == \"$1\")] | length" <<<"$(log)"; }
mode ''; clear_log

# session_started( view, step ): redacted view, step array.
admin_cli start --pin="$SPY" >/dev/null
D=$(last session_started)
assert_eq "$(jq -r .status <<<"$D")" asking "session_started: step status"
assert_eq "$(jq -r .question <<<"$D")" 1 "session_started: question"
assert_eq "$(jq -r '.keys | index("snapshot") != null' <<<"$D")" true "session_started: view has snapshot"
assert_eq "$(jq -r '.keys | (index("token_hash") == null and index("recovery_hash") == null)' <<<"$D")" true "session_started: no secrets in the view"

# step_changed( step, view, cause ).
admin_cli answer no >/dev/null
assert_eq "$(last step_changed | jq -r .cause)" answer "step_changed: cause answer"
assert_eq "$(last step_changed | jq -r .question)" 2 "step_changed: next question"
admin_cli undo >/dev/null
assert_eq "$(last step_changed | jq -r .cause)" undo "step_changed: cause undo"

# result_found( record, view ): record without user id.
admin_cli answer yes >/dev/null
D=$(last result_found)
assert_eq "$(jq -r .type <<<"$D")" NOT_PLUGIN "result_found: type"
assert_eq "$(jq -r .has_id <<<"$D")" true "result_found: record has id"
assert_eq "$(jq -r .has_user_id <<<"$D")" false "result_found: no user id"

# report_sections( sections, record ).
mode section
REPORT=$(wpcli culprit-finder result --format=report)
assert_contains "$REPORT" "CFF-SPY-SECTION" "report_sections: added section is in the report"
assert_eq "$(last report_sections | jq -c .sections)" '["result","environment","footer"]' "report_sections: documented sections"
assert_eq "$(last report_sections | jq -r .has_user_id)" false "report_sections: no user id"
mode ''

# session_ended( reason, view ) for every reason.
wpcli culprit-finder exit >/dev/null
assert_eq "$(last session_ended | jq -r .reason)" exit "session_ended: exit"
admin_cli start --pin="$SPY" >/dev/null; admin_cli start --pin="$SPY" >/dev/null
assert_eq "$(last session_ended | jq -r .reason)" replaced "session_ended: replaced"
wpcli eval '$s = get_option( "culprit_finder_session" ); $s["expires_at"] = time() - 1; update_option( "culprit_finder_session", $s, false );' >/dev/null
wpcli culprit-finder status >/dev/null 2>&1 || true
assert_eq "$(last session_ended | jq -r .reason)" expired "session_ended: expired"
start_session "$SPY"
fetch - "/?culprit-finder-exit=$KEY" >/dev/null
assert_eq "$(last session_ended | jq -r .reason)" recovery "session_ended: recovery"
assert_eq "$(last session_ended | jq -r '.keys | index("token_hash") == null')" true "session_ended: no secrets"
admin_cli start --pin="$SPY" >/dev/null
wpcli plugin deactivate culprit-finder >/dev/null
assert_eq "$(last session_ended | jq -r .reason)" deactivated "session_ended: deactivated"
wpcli plugin activate culprit-finder >/dev/null

# A hook that wipes active_plugins or throws changes nothing.
mode wipe,throw,section
clear_log
admin_cli start --pin="$SPY" >/dev/null
admin_cli answer yes >/dev/null
assert_eq "$(wpcli option get active_plugins --format=json)" "$BEFORE" "hooks cannot write active_plugins"
REPORT=$(wpcli culprit-finder result --format=report)
assert_contains "$REPORT" "### Plugin conflict report" "a throwing filter falls back to the normal report"
assert_not_contains "$REPORT" "CFF-SPY-SECTION" "a throwing filter's changes are dropped"
wpcli culprit-finder exit >/dev/null
assert_eq "$(wpcli option get active_plugins --format=json)" "$BEFORE" "still unchanged after exit"
mode ''

# admin_tabs filter + render action: core tabs stay first and unchanged.
JAR=$TMP/admin.jar; login "$JAR"
TOOLS="/wp-admin/admin.php?page=culprit-finder"
mode tab
PAGE=$(fetch "$JAR" "$TOOLS")
assert_contains "$PAGE" ">Spy tab<" "admin_tabs: add-on tab shown"
assert_not_contains "$PAGE" "Hijacked" "admin_tabs: core labels cannot be replaced"
ORDER=$(grep -oE 'class="cf-tab[^"]*" href="[^"]*"' <<<"$PAGE" | sed -E 's/&#038;/\&/g; s/&amp;/\&/g' | sed -E 's/.*page=culprit-finder(&tab=)?([a-z]*).*/\2/' | tr '\n' ',')
assert_eq "$ORDER" ",results,help,spy," "admin_tabs: core tabs first"
assert_contains "$(fetch "$JAR" "$TOOLS&tab=spy")" "CFF-SPY-TAB" "render_tab_{id}: add-on tab renders"
mode ''

# auto_answer: honored only for exactly yes/no, inside the owner's session.
browser_start() {
  local page nonce key
  page=$(fetch "$JAR" "$TOOLS")
  nonce=$(grep -m1 -oE 'name="_wpnonce" value="[^"]+"' <<<"$page" | sed -E 's/.*value="([^"]+)"/\1/')
  key=$(grep -m1 -oE 'name="culprit_finder_recovery" value="[^"]+"' <<<"$page" | sed -E 's/.*value="([^"]+)"/\1/')
  curl -s -o /dev/null -b "$JAR" -c "$JAR" --data-urlencode "_wpnonce=$nonce" --data "action=culprit_finder_start&culprit_finder_saved=1&culprit_finder_recovery=$key" \
    --data-urlencode "culprit_finder_pin[]=$SPY" "$BASE/wp-admin/admin-post.php?culprit_safe=1"
}
mode auto-bad
browser_start
PANEL=$(curl -s -b "$JAR" "$BASE$TOOLS&culprit_safe=1")
assert_contains "$PANEL" "Step 1 of about" "auto_answer: invalid values are ignored"
assert_eq "$(last auto_answer | jq -r '.keys | index("token_hash") == null')" true "auto_answer: no secrets"
admin_cli answer no >/dev/null
N=$(count auto_answer)
admin_cli answer no >/dev/null
assert_eq "$(count auto_answer)" "$N" "auto_answer: never asked in WP-CLI"
fetch - / >/dev/null
assert_eq "$(count auto_answer)" "$N" "auto_answer: never asked for visitors"
wpcli culprit-finder exit >/dev/null
mode auto-yes
browser_start
PANEL=$(curl -s -b "$JAR" "$BASE$TOOLS&culprit_safe=1")
assert_contains "$PANEL" "Result ready" "auto_answer: yes is recorded as an answer"
assert_eq "$(last auto_answer | jq -r .in)" null "auto_answer: default is null"
wpcli culprit-finder exit >/dev/null
assert_eq "$(wpcli option get active_plugins --format=json)" "$BEFORE" "AC-2 active_plugins unchanged"
