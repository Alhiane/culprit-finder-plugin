#!/usr/bin/env bash
# Browser flow through the real UI: Tools page, admin-post handlers, admin bar, Plugins lock (M5).
source "$(dirname "$0")/../lib.sh"
BEFORE=$(reset_site $(noise 1 8) cff-fixtures/cff-solo)
JAR=$TMP/admin.jar; login "$JAR"
TOOLS="/wp-admin/tools.php?page=culprit-finder"
# Follow-redirect requests that also store cookies set by the server (our session cookie).
browse() { curl -s -L -b "$JAR" -c "$JAR" "$BASE$1"; }
attr() { grep -oE "$1" <<<"$2" | head -1 | sed -E 's/.*value="([^"]+)".*/\1/'; }
href() { grep -oE "id=['\"]wp-admin-bar-$1['\"]><a [^>]*href=['\"][^'\"]+" <<<"$2" | sed -E "s/.*href=['\"]//" | sed 's/&#038;/\&/g; s/&amp;/\&/g' | head -1; }

# Idle page.
IDLE=$(browse "$TOOLS")
assert_contains "$IDLE" "9 active plugins will be tested" "idle: plugin count"
assert_contains "$IDLE" "culprit_finder_pin[]" "idle: pin checkboxes"
assert_contains "$IDLE" "culprit_safe=1" "idle: control panel URL"
assert_contains "$IDLE" "culprit-finder-exit=" "idle: emergency exit URL"
NONCE=$(attr 'name="_wpnonce" value="[^"]+"' "$IDLE")
KEY=$(attr 'name="culprit_finder_recovery" value="[^"]+"' "$IDLE")
assert_contains "$IDLE" "culprit-finder-exit=$KEY" "idle: exit URL uses the posted key"

# Editors can't see it.
wpcli user get cfeditor >/dev/null 2>&1 || wpcli user create cfeditor cfeditor@example.test --role=editor --user_pass=password >/dev/null
EJAR=$TMP/editor.jar; login "$EJAR" cfeditor password
assert_eq "$(code "$EJAR" "$TOOLS")" 403 "editor cannot open the Tools page"

# Start without a nonce is refused.
assert_eq "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" --data 'action=culprit_finder_start' "$BASE/wp-admin/admin-post.php?culprit_safe=1")" 403 "start without nonce refused"

# Start with a pin.
RUNNING=$(curl -s -L -b "$JAR" -c "$JAR" --data-urlencode "_wpnonce=$NONCE" --data "action=culprit_finder_start&culprit_finder_recovery=$KEY" \
  --data-urlencode "culprit_finder_pin[]=cff-fixtures/cff-noise-01.php" --data-urlencode "culprit_finder_pin[]=not-a/plugin.php" "$BASE/wp-admin/admin-post.php?culprit_safe=1")
grep -q 'wp-culprit-finder' "$JAR" || fail "start did not set the session cookie"
assert_contains "$RUNNING" "Step 1 of about" "running: step text"
assert_contains "$RUNNING" "for you only" "running: for-you-only copy"
SESSION=$(wpjson option get culprit_finder_session --format=json)
assert_eq "$(jq -c .pinned <<<"$SESSION")" '["cff-fixtures/cff-noise-01.php"]' "pins validated against the snapshot"

# Front end: admin bar node, filtered page.
FRONT=$(browse /)
assert_contains "$FRONT" "Culprit Finder: step 1 of" "admin bar node on the front end"
assert_contains "$FRONT" "CFF-NOISE-01" "pinned plugin is on"
assert_not_contains "$FRONT" "CFF-NOISE-02" "suspects are off in the baseline step"
NO=$(href culprit-finder-no "$FRONT")
[ -n "$NO" ] || fail "admin bar 'No' link missing"
assert_contains "$NO" "culprit_safe=1" "control links run in safe mode"

# Answer "No" from the front end: redirected back to the same page with the next set.
FINAL=$(curl -s -L -o /dev/null -w '%{url_effective}' -b "$JAR" -c "$JAR" "$NO")
assert_eq "$FINAL" "$BASE/" "answer redirects back to the page the user came from"
assert_contains "$(browse /)" "step 2 of" "admin bar advanced to step 2"

# Bad redirect target falls back to the control panel.
YES=$(href culprit-finder-yes "$(browse /)")
EVIL=$(sed -E 's#redirect_to=[^&]+#redirect_to=https%3A%2F%2Fevil.example%2F#' <<<"$YES")
FINAL=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR" "$EVIL")
assert_contains "$FINAL" "page=culprit-finder" "off-site redirect_to is rejected"
admin_cli undo >/dev/null

# Plugins screen lock.
PLUGINS=$(browse /wp-admin/plugins.php)
assert_contains "$PLUGINS" "Plugin management is paused while Culprit Finder is running" "plugins screen notice"
assert_eq "$(code "$JAR" '/wp-admin/plugins.php?action=deactivate&plugin=cff-fixtures%2Fcff-solo.php')" 409 "plugin actions refused"
assert_eq "$(code "$JAR" '/wp-admin/plugins.php?action=-1&action2=activate-selected')" 409 "bulk actions refused"

# Finish via the Tools page links (control panel in safe mode).
for i in $(seq 1 12); do
  PANEL=$(browse "$TOOLS&culprit_safe=1")
  grep -q 'culprit-finder-result' <<<"$PANEL" && break
  WHICH=no; symptom_marker "$JAR" && WHICH=yes
  LINK=$(grep -oE "href=\"[^\"]*answer=$WHICH[^\"]*\"" <<<"$PANEL" | head -1 | sed -E 's/href="([^"]+)"/\1/; s/&amp;/\&/g; s/&#038;/\&/g')
  [ -n "$LINK" ] || fail "no answer link on the control panel"
  curl -s -L -o /dev/null -b "$JAR" -c "$JAR" "$LINK"
done
RESULT=$(browse "$TOOLS")
assert_contains "$RESULT" "Found it: one plugin causes the problem." "result verdict"
assert_contains "$RESULT" "CFF Solo 1.0.0" "culprit card"
assert_contains "$RESULT" 'id="culprit-finder-report"' "report textarea"
assert_contains "$RESULT" "culprit-finder-copy" "copy button"
assert_contains "$RESULT" "Run again" "run again"
assert_contains "$(browse /)" "Culprit Finder: result ready" "admin bar shows result"
assert_eq "$(noise_count "$(browse /)")" 8 "site back to normal for the admin on the result"

# Exit from the result screen.
EXIT=$(grep -oE 'href="[^"]*action=culprit_finder_exit[^"]*"' <<<"$RESULT" | head -1 | sed -E 's/href="([^"]+)"/\1/; s/&amp;/\&/g; s/&#038;/\&/g')
AFTER=$(curl -s -L -b "$JAR" -c "$JAR" "$EXIT")
assert_contains "$AFTER" "Troubleshooting ended" "exit notice"
assert_contains "$AFTER" "Last result" "last result survives exit"
wpcli option get culprit_finder_session >/dev/null 2>&1 && fail "session still exists after exit"
assert_not_contains "$(browse /)" "Culprit Finder: step" "admin bar node gone after exit"
assert_eq "$(wpcli option get active_plugins --format=json)" "$BEFORE" "AC-2 active_plugins unchanged"
