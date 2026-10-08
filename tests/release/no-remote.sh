#!/usr/bin/env bash
# No external requests and no remote content (ADR-0008, ADR-0025): the plugin source never calls out over
# HTTP and never loads scripts, styles, images or fonts from another host. Runs on the source tree.
set -euo pipefail
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
FILES=("$ROOT/culprit-finder.php" "$ROOT/uninstall.php")
while IFS= read -r f; do FILES+=("$f"); done < <(find "$ROOT/src" "$ROOT/assets" "$ROOT/mu-loader" -type f \( -name '*.php' -o -name '*.js' -o -name '*.css' -o -name '*.svg' \))
PATTERN='wp_remote_|wp_safe_remote_|WP_Http|download_url\(|curl_|fsockopen|stream_socket_client|file_get_contents\([^)]*https?:|fopen\([^)]*https?:|\bfetch\(|XMLHttpRequest|sendBeacon|@import|url\([[:space:]]*["'"'"']?(https?:)?//|(src|srcset|poster|data)=["'"'"'](https?:)?//|<link[^>]+href=["'"'"'](https?:)?//|wp_(enqueue|register)_(script|style)\([^)]*https?:'
HITS=$(grep -nHE "$PATTERN" "${FILES[@]}" || true)
if [ -n "$HITS" ]; then
  echo "no-remote: external requests or remote content found:" >&2
  printf '%s\n' "$HITS" | sed "s#$ROOT/##" >&2
  exit 1
fi
echo "no-remote: OK (${#FILES[@]} files)"
