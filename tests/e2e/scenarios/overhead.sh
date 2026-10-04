#!/usr/bin/env bash
# AC-14: a visitor request (no session cookie) runs no extra queries because of Culprit Finder.
source "$(dirname "$0")/../lib.sh"
trap 'wpcli config delete SAVEQUERIES >/dev/null 2>&1 || true' EXIT
reset_site $(noise 1 4) cff-fixtures/cff-query-probe >/dev/null
wpcli config set SAVEQUERIES true --raw --type=constant >/dev/null
probe() { grep -oE 'CFF-QUERIES total=[0-9]+ ours=[0-9]+' <<<"$1"; }
fetch - / >/dev/null # warm caches
WITH=$(probe "$(fetch - /)")
[ -n "$WITH" ] || fail "query probe did not print"
assert_contains "$WITH" "ours=0" "no culprit_finder queries on a visitor request"

# A logged-in admin without our cookie: same.
JAR=$TMP/admin.jar; login "$JAR"
assert_contains "$(probe "$(fetch "$JAR" /)")" "ours=0" "no culprit_finder queries for a logged-in admin without the cookie"

wpcli plugin deactivate culprit-finder >/dev/null
fetch - / >/dev/null
WITHOUT=$(probe "$(fetch - /)")
assert_eq "${WITH%% ours*}" "${WITHOUT%% ours*}" "same query count with and without Culprit Finder"
wpcli plugin activate culprit-finder >/dev/null
echo "overhead: $WITH vs $WITHOUT"
