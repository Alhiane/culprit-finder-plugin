#!/usr/bin/env bash
# Deactivating Culprit Finder mid-session ends it and removes the loader (AC-9, ADR-0007).
source "$(dirname "$0")/../lib.sh"
BEFORE=$(reset_site $(noise 1 8) cff-fixtures/cff-solo)
JAR=$TMP/admin.jar; login "$JAR"
start_session
add_token "$JAR" "$TOKEN"
assert_eq "$(noise_count "$(fetch "$JAR" /)")" 0 "filtered during the session"
wpcli plugin deactivate culprit-finder >/dev/null
wpcli eval 'echo file_exists( WPMU_PLUGIN_DIR . "/culprit-finder-loader.php" ) ? "present" : "absent";' | grep -qx absent || fail "loader file still installed"
wpcli option get culprit_finder_session >/dev/null 2>&1 && fail "session still exists after deactivation"
PAGE=$(fetch "$JAR" /)
assert_eq "$(noise_count "$PAGE")" 8 "normal site after deactivation"
assert_contains "$PAGE" "CFF-SYMPTOM" "normal site after deactivation"
EXPECTED=$(jq -c 'map(select(. != "culprit-finder/culprit-finder.php"))' <<<"$BEFORE")
assert_eq "$(wpcli option get active_plugins --format=json | jq -c .)" "$EXPECTED" "only Culprit Finder itself was deactivated"
wpcli plugin activate culprit-finder >/dev/null
