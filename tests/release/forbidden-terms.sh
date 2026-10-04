#!/usr/bin/env bash
# Published content must never mention paid plans or the .pro domain:
# the plugin zip, readme.txt, and the website sources and build. (website/README.md is internal and exempt.)
set -euo pipefail
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

FILES=("$ROOT/readme.txt")
if [ -f "$ROOT/build/culprit-finder.zip" ]; then
  unzip -q "$ROOT/build/culprit-finder.zip" -d "$TMP/zip"
  while IFS= read -r f; do FILES+=("$f"); done < <(find "$TMP/zip" -type f \( -name '*.php' -o -name '*.txt' -o -name '*.js' -o -name '*.css' -o -name '*.pot' -o -name '*.svg' \))
fi
for dir in "$ROOT/website/src" "$ROOT/website/dist" "$ROOT/website/public"; do
  [ -d "$dir" ] || continue
  while IFS= read -r f; do FILES+=("$f"); done < <(find "$dir" -type f \( -name '*.md' -o -name '*.mdx' -o -name '*.astro' -o -name '*.ts' -o -name '*.html' -o -name '*.txt' -o -name '*.xml' -o -name '*.json' -o -name '*.css' -o -name '*.js' \))
done

PATTERN='getculpritfinder\.pro|\bPro\b|\bpricing\b|\bpremium\b|\bupgrade (to|now)\b|\bupsell|coming soon|\bpaid (plan|version|feature)'
HITS=$(grep -nHiE "$PATTERN" "${FILES[@]}" 2>/dev/null | grep -viE 'Upgrade Notice' | grep -vE '\bpro(cess|duct|blem|tect|vide|per|ject|gress|mpt|of)' || true)
# "Pro" is matched case-sensitively as a standalone word; re-check those to avoid "pro" inside other words.
HITS=$(printf '%s\n' "$HITS" | grep -E 'getculpritfinder\.pro|\bPro\b|[Pp]ricing|[Pp]remium|[Uu]pgrade (to|now)|[Uu]psell|[Cc]oming soon|[Pp]aid (plan|version|feature)' || true)
if [ -n "$HITS" ]; then
  echo "forbidden-terms: found terms that must not be published:" >&2
  printf '%s\n' "$HITS" | sed "s#$TMP/zip/##; s#$ROOT/##" >&2
  exit 1
fi
echo "forbidden-terms: OK (${#FILES[@]} files)"
