#!/usr/bin/env bash
# Build build/culprit-finder.zip (top folder culprit-finder/) from an explicit allow-list,
# so planning docs, tests and dev tooling can never ship.
#
# Never ships (not on the allow-list below): .wordpress-org/ (WordPress.org SVN assets: plugin
# directory icons), docs/ (including docs/brand/), tests/, .claude/, CLAUDE.md, KICKOFF.md,
# README.md, vendor/, node_modules/, build/, bin/, .wp-env*, composer.*, phpunit.xml*,
# phpcs.xml*, Makefile, .git*. The guard after the build fails if brand or docs files slip in.
set -euo pipefail
ROOT=$(cd "$(dirname "$0")/.." && pwd)
STAGE="$ROOT/build/stage/culprit-finder"
rm -rf "$ROOT/build/stage" "$ROOT/build/culprit-finder.zip"
mkdir -p "$STAGE"
for item in culprit-finder.php uninstall.php readme.txt LICENSE mu-loader src assets languages; do
  [ -e "$ROOT/$item" ] && cp -R "$ROOT/$item" "$STAGE/"
done
find "$STAGE" -name '.DS_Store' -delete
(cd "$ROOT/build/stage" && zip -qr -X ../culprit-finder.zip culprit-finder)
rm -rf "$ROOT/build/stage"
if unzip -Z1 "$ROOT/build/culprit-finder.zip" | grep -E '(^|/)(\.wordpress-org|docs)/'; then
  echo "ERROR: the zip contains .wordpress-org/ or docs/ files (listed above)." >&2
  rm -f "$ROOT/build/culprit-finder.zip"
  exit 1
fi
echo "Built build/culprit-finder.zip:"
unzip -l "$ROOT/build/culprit-finder.zip" | awk 'NR>3 {print "  " $4}' | sed '/^  $/d' | sed '$d'
