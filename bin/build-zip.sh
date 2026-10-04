#!/usr/bin/env bash
# Build build/culprit-finder.zip (top folder culprit-finder/) from an explicit allow-list,
# so planning docs, tests and dev tooling can never ship.
set -euo pipefail
ROOT=$(cd "$(dirname "$0")/.." && pwd)
STAGE="$ROOT/build/stage/culprit-finder"
rm -rf "$ROOT/build/stage" "$ROOT/build/culprit-finder.zip"
mkdir -p "$STAGE"
for item in culprit-finder.php uninstall.php readme.txt mu-loader src assets languages; do
  [ -e "$ROOT/$item" ] && cp -R "$ROOT/$item" "$STAGE/"
done
find "$STAGE" -name '.DS_Store' -delete
(cd "$ROOT/build/stage" && zip -qr -X ../culprit-finder.zip culprit-finder)
rm -rf "$ROOT/build/stage"
echo "Built build/culprit-finder.zip:"
unzip -l "$ROOT/build/culprit-finder.zip" | awk 'NR>3 {print "  " $4}' | sed '/^  $/d' | sed '$d'
