#!/usr/bin/env bash
# Published content (readme.txt, the plugin source and, when built, the zip) follows ADR-0025:
# only the approved Pro discovery copy may mention Pro or prices, and only links to
# https://getculpritfinder.com/pro/. Never the .pro domain, "premium", "upgrade now", "coming soon",
# urgency words, exclamation marks in discovery copy, or URLs to other hosts in PHP/JS/CSS.
# (The website repository runs its own check, unchanged.)
set -euo pipefail
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

FILES=("$ROOT/readme.txt" "$ROOT/culprit-finder.php" "$ROOT/uninstall.php")
while IFS= read -r f; do FILES+=("$f"); done < <(find "$ROOT/src" "$ROOT/assets" "$ROOT/mu-loader" "$ROOT/languages" -type f \( -name '*.php' -o -name '*.js' -o -name '*.css' -o -name '*.pot' -o -name '*.svg' \))
if [ -f "$ROOT/build/culprit-finder.zip" ]; then
  unzip -q "$ROOT/build/culprit-finder.zip" -d "$TMP/zip"
  while IFS= read -r f; do FILES+=("$f"); done < <(find "$TMP/zip" -type f \( -name '*.php' -o -name '*.txt' -o -name '*.js' -o -name '*.css' -o -name '*.pot' -o -name '*.svg' \))
fi
show() { sed "s#$TMP/zip/#zip:#; s#$ROOT/##"; }
FAILED=0
report() { echo "forbidden-terms: $1" >&2; printf '%s\n' "$2" | show >&2; FAILED=1; }

# 1. Always banned, everywhere.
BANNED='getculpritfinder\.pro|\bpremium\b|\bupgrade (to|now)\b|\bupsell|coming soon|\bpaid (plan|version|feature)|\bpricing\b|\b(hurry|act now|today only|last chance|limited[- ]time|countdown|don.t miss)\b'
HITS=$(grep -nHiE "$BANNED" "${FILES[@]}" 2>/dev/null | grep -viE 'Upgrade Notice' || true)
[ -z "$HITS" ] || report "banned terms:" "$HITS"

# 2. "Pro" and prices only inside the approved discovery copy (ADR-0025). Each approved phrase is removed
#    from the line first; any "Pro" or "$<n>" left over is a finding.
APPROVED=(
  'Culprit Finder Pro' 'Get Pro' 'See Pro' 'Hide Pro tips' 'Pro tips' 'Pro tab' 'Is there a Pro version\?'
  'Pro adds to this plugin\.' 'From \$29/year per site' '\$%d/year'
  "__\\( 'Pro', 'culprit-finder' \\)" 'msgid "Pro"' 'Pro discovery' 'ProDiscovery' 'PRO_[A-Z_]+' 'culprit-finder-pro'
  '\$29/year' '\$99/year' '\$199/year'
)
HITS=$(grep -nHE '\bPro\b|\$[0-9]+' "${FILES[@]}" 2>/dev/null | python3 -c '
import re, sys
approved = [re.compile(p) for p in sys.argv[1:]]
for line in sys.stdin:
    m = re.match(r"^(.*?:[0-9]+:)(.*)$", line.rstrip("\n"))
    if not m:
        continue
    text = m.group(2)
    for a in approved:
        text = a.sub("", text)
    if re.search(r"\bPro\b|\$[0-9]+(/| ?(a|per) )", text):
        print(m.group(1) + m.group(2))
' "${APPROVED[@]}" || true)
[ -z "$HITS" ] || report "Pro or prices outside the approved discovery copy:" "$HITS"

# 3. Exclamation marks are not allowed in discovery copy (strings of ProDiscovery.php, the readme section).
DISC=$(grep -nH -oE "__\( '[^']*'" "$ROOT/src/Admin/ProDiscovery.php" | grep '!' || true)
README_PRO=$(sed -n '/^== Culprit Finder Pro ==/,/^== /p' "$ROOT/readme.txt" | grep -n '!' || true)
[ -z "$DISC$README_PRO" ] || report "exclamation marks in discovery copy:" "$DISC$README_PRO"

# 4. Links to getculpritfinder.com from the plugin go to the Pro page or the site root (Plugin URI) only.
HITS=$(grep -nHoE 'https?://([a-z0-9-]+\.)?getculpritfinder\.com[^"'"'"' )<]*' "${FILES[@]}" 2>/dev/null | sed -E 's/[.,;:]$//' \
  | grep -vE ':https://getculpritfinder\.com(/pro/|/docs[^ ]*|/?)$' || true)
[ -z "$HITS" ] || report "unexpected getculpritfinder.com links:" "$HITS"

# 5. PHP/JS/CSS may only contain URLs of getculpritfinder.com and wordpress.org (plus the SVG XML
#    namespace and the GPL license URI in the plugin header, which are identifiers, not requests).
CODE=()
for f in "${FILES[@]}"; do case "$f" in *.php|*.js|*.css) CODE+=("$f") ;; esac; done
HITS=$(grep -nHoE 'https?://[^"'"'"' )<>]+' "${CODE[@]}" 2>/dev/null \
  | grep -vE ':https://([a-z0-9-]+\.)?(getculpritfinder\.com|wordpress\.org)(/|$)' \
  | grep -vE ':http://www\.w3\.org/2000/svg$|:https://www\.gnu\.org/licenses/gpl-2\.0\.html$' || true)
[ -z "$HITS" ] || report "URLs to other hosts in PHP/JS/CSS:" "$HITS"

[ "$FAILED" = 0 ] || exit 1
echo "forbidden-terms: OK (${#FILES[@]} files)"
