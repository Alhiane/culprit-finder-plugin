#!/usr/bin/env bash
# Local readme.txt checks (the official validator is a web form; paste the file there before submitting).
set -euo pipefail
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
R="$ROOT/readme.txt"
fail() { echo "readme-lint: $*" >&2; exit 1; }
field() { grep -m1 -E "^$1:" "$R" | sed -E "s/^$1:[[:space:]]*//"; }

[ "$(head -1 "$R")" = "=== Culprit Finder ===" ] || fail "first line must be === Culprit Finder ==="
for f in Contributors Tags "Requires at least" "Tested up to" "Requires PHP" "Stable tag" License "License URI"; do
  [ -n "$(field "$f")" ] || fail "missing header field: $f"
done
TAGS=$(field Tags | tr ',' '\n' | sed '/^[[:space:]]*$/d' | wc -l | tr -d ' ')
[ "$TAGS" -le 5 ] || fail "$TAGS tags (max 5)"
[ "$(field 'Requires at least')" = "6.5" ] || fail "Requires at least must be 6.5"
[ "$(field 'Requires PHP')" = "7.4" ] || fail "Requires PHP must be 7.4"
[ "$(field License)" = "GPLv2 or later" ] || fail "License must be GPLv2 or later"

STABLE=$(field "Stable tag")
HEADER=$(grep -m1 -E '^ \* Version:' "$ROOT/culprit-finder.php" | sed -E 's/.*Version:[[:space:]]*//')
CONST=$(grep -m1 "CULPRIT_FINDER_VERSION'" "$ROOT/culprit-finder.php" | sed -E "s/.*'CULPRIT_FINDER_VERSION', '([^']+)'.*/\1/")
[ "$STABLE" = "$HEADER" ] && [ "$STABLE" = "$CONST" ] || fail "Stable tag ($STABLE), header Version ($HEADER) and CULPRIT_FINDER_VERSION ($CONST) differ"
grep -q "^= $STABLE =" "$R" || fail "no changelog entry for $STABLE"

PURI=$(grep -m1 -E '^ \* Plugin URI:' "$ROOT/culprit-finder.php" | sed -E 's/.*Plugin URI:[[:space:]]*//')
AURI=$(grep -m1 -E '^ \* Author URI:' "$ROOT/culprit-finder.php" | sed -E 's/.*Author URI:[[:space:]]*//')
[ -z "$PURI" ] || [ -z "$AURI" ] || [ "${PURI%/}" != "${AURI%/}" ] || fail "Plugin URI and Author URI must differ (WordPress.org rejects identical ones)"

SHORT=$(awk 'NR>1 && /^$/ {getline; print; exit}' "$R")
[ -n "$SHORT" ] || fail "missing short description"
[ ${#SHORT} -le 150 ] || fail "short description is ${#SHORT} characters (max 150)"

for s in Description Installation "Frequently Asked Questions" Screenshots Changelog "Upgrade Notice"; do
  grep -q "^== $s ==$" "$R" || fail "missing section: $s"
done
SHOTS=$(sed -n '/^== Screenshots ==/,/^== /p' "$R" | grep -cE '^[0-9]+\. ')
for i in $(seq 1 "$SHOTS"); do
  [ -f "$ROOT/.wordpress-org/screenshot-$i.png" ] || fail "screenshot-$i.png listed in readme but missing in .wordpress-org/"
done
echo "readme-lint: OK ($TAGS tags, short description ${#SHORT} chars, $SHOTS screenshots, stable $STABLE)"
