#!/usr/bin/env bash
# `make zip` and the WordPress.org deploy (which copies the repo minus .distignore) must ship the same files.
# Both are derived from the committed tree (git archive HEAD), so local-only folders can't skew the result.
set -euo pipefail
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
mkdir -p "$TMP/tree"; git -C "$ROOT" archive HEAD | tar -x -C "$TMP/tree"

# What the deploy action would ship.
rsync -a --exclude-from="$TMP/tree/.distignore" "$TMP/tree/" "$TMP/deploy/"
( cd "$TMP/deploy" && find . -type f | sed 's#^\./##' | sort ) > "$TMP/deploy.txt"

# What make zip ships, built from the same tree.
( cd "$TMP/tree" && bash bin/build-zip.sh >/dev/null )
unzip -Z1 "$TMP/tree/build/culprit-finder.zip" | grep -v '/$' | sed 's#^culprit-finder/##' | sort > "$TMP/zip.txt"

if ! diff -u "$TMP/zip.txt" "$TMP/deploy.txt"; then
  echo "distignore-match: make zip (-) and .distignore deploy (+) ship different files" >&2
  exit 1
fi
for forbidden in docs/ tests/ .github/ .wordpress-org/ .claude/; do
  ! grep -q "^$forbidden" "$TMP/zip.txt" || { echo "distignore-match: $forbidden must not ship" >&2; exit 1; }
done
echo "distignore-match: OK ($(wc -l < "$TMP/zip.txt" | tr -d ' ') files, identical)"
