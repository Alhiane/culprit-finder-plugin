#!/usr/bin/env bash
# Whole site crashing (wp-admin too) before a session exists: get in through WordPress recovery
# mode, start Culprit Finder, exit recovery mode, and finish the search (readme FAQ "whole site down").
source "$(dirname "$0")/../lib.sh"
reset_site >/dev/null
JAR=$TMP/admin.jar; login "$JAR"   # log in while the site still works
BEFORE=$(reset_site $(noise 1 6) cff-fixtures/cff-pair-a cff-fixtures/cff-pair-b)
assert_eq "$(code "$JAR" /wp-admin/)" 500 "precondition: wp-admin crashes"
assert_eq "$(code "$JAR" '/wp-admin/tools.php?page=culprit-finder')" 500 "precondition: Culprit Finder page unreachable"

# The link WordPress would email (generated here because wp-env can't send mail).
LINK=$(wpcli eval '
$token = wp_generate_password( 22, false );
$keys  = new WP_Recovery_Mode_Key_Service();
$key   = $keys->generate_and_store_recovery_mode_key( $token );
echo add_query_arg( array( "action" => "enter_recovery_mode", "rm_token" => $token, "rm_key" => $key ), wp_login_url() );' | tail -n 1)
assert_contains "$LINK" "enter_recovery_mode" "recovery link generated"
curl -s -o /dev/null -L -b "$JAR" -c "$JAR" "$LINK"
grep -q wordpress_rec_ "$JAR" || fail "recovery mode cookie not set"
# In recovery mode WordPress pauses the plugin that crashed (on the first crash it sees) for this browser.
for i in 1 2 3; do [ "$(code "$JAR" /wp-admin/)" = 200 ] && break; done
assert_eq "$(code "$JAR" /wp-admin/)" 200 "wp-admin loads in recovery mode"

# Start Culprit Finder from inside recovery mode.
PAGE=$(curl -s -b "$JAR" "$BASE/wp-admin/tools.php?page=culprit-finder")
assert_contains "$PAGE" "You are in WordPress recovery mode." "idle page explains recovery mode"
assert_contains "$PAGE" "action=exit_recovery_mode" "idle page links to exit recovery mode"
NONCE=$(grep -oE 'name="_wpnonce" value="[^"]+"' <<<"$PAGE" | head -1 | sed -E 's/.*value="([^"]+)"/\1/')
KEY=$(grep -oE 'name="culprit_finder_recovery" value="[^"]+"' <<<"$PAGE" | head -1 | sed -E 's/.*value="([^"]+)"/\1/')
[ -n "$NONCE" ] && [ -n "$KEY" ] || fail "start form not found in recovery mode"
curl -s -o /dev/null -b "$JAR" -c "$JAR" --data-urlencode "_wpnonce=$NONCE" --data "action=culprit_finder_start&culprit_finder_recovery=$KEY" "$BASE/wp-admin/admin-post.php?culprit_safe=1"
grep -q wp-culprit-finder "$JAR" || fail "session cookie not set"
assert_contains "$(curl -s -b "$JAR" "$BASE/wp-admin/tools.php?page=culprit-finder&culprit_safe=1")" "You are still in WordPress recovery mode." "running page warns while still in recovery mode"
SNAP=$(wpjson option get culprit_finder_session --format=json)
assert_eq "$(jq '.snapshot | length' <<<"$SNAP")" 9 "snapshot includes the paused plugin"

# Exit recovery mode (drop its cookie, as WordPress's Exit button does). Our session keeps the site usable.
sed -i.bak '/wordpress_rec_/d' "$JAR"
PANEL=$(curl -s -b "$JAR" "$BASE/wp-admin/tools.php?page=culprit-finder&culprit_safe=1")
assert_contains "$PANEL" "Step 1 of about" "control panel loads after leaving recovery mode"
assert_not_contains "$PANEL" "recovery mode." "no recovery-mode warning after leaving it"
assert_eq "$(code "$JAR" /)" 200 "baseline step loads"

answer_loop "$JAR" symptom_500
assert_result PAIR cff-fixtures/cff-pair-a.php cff-fixtures/cff-pair-b.php
assert_eq "$(wpcli option get active_plugins --format=json)" "$BEFORE" "AC-2 active_plugins unchanged"
wpcli culprit-finder exit >/dev/null
reset_site >/dev/null
echo "recovery-mode: PAIR in $ANSWERS answers"
