#!/usr/bin/env bash
# Install build/culprit-finder.zip on a fresh, separate wp-env site (ports 8890/8891) and run
# the solo E2E scenario against it. The dev wp-env maps this repo as the plugin folder, so the
# zip must never be installed there (ADR-0011).
set -euo pipefail
ROOT=$(cd "$(dirname "$0")/.." && pwd)
ENV_DIR="$ROOT/build/zip-env"
PORT=${ZIP_ENV_PORT:-8890}
WPENV=${WPENV:-npx --yes @wordpress/env}

[ -f "$ROOT/build/culprit-finder.zip" ] || bash "$ROOT/bin/build-zip.sh"
mkdir -p "$ENV_DIR/zip"
cp "$ROOT/build/culprit-finder.zip" "$ENV_DIR/zip/"
cat > "$ENV_DIR/.wp-env.json" <<JSON
{
  "core": null,
  "phpVersion": "8.2",
  "port": $PORT,
  "testsPort": $((PORT + 1)),
  "mappings": {
    "wp-content/plugins/cff-fixtures": "../../tests/fixtures/plugins/cff-fixtures",
    "wp-content/plugins/cff-dep-parent": "../../tests/fixtures/plugins/cff-dep-parent",
    "wp-content/plugins/cff-dep-child": "../../tests/fixtures/plugins/cff-dep-child",
    "wp-content/cf-zip": "./zip"
  },
  "config": { "WP_DEBUG": true, "WP_DEBUG_LOG": true, "WP_DEBUG_DISPLAY": false }
}
JSON

cd "$ENV_DIR"
if [ "${ZIP_ENV_FRESH:-1}" = 1 ]; then $WPENV destroy --force >/dev/null 2>&1 || true; fi
$WPENV start

CLI=$(docker ps --filter label=com.docker.compose.service=cli --format '{{.Names}}' | while read -r c; do
  docker inspect -f '{{range .Mounts}}{{.Source}}{{"\n"}}{{end}}' "$c" | sed 's#^/host_mnt##' | grep -qxF "$ENV_DIR/zip" && echo "$c" || true
done | head -1)
[ -n "$CLI" ] || { echo "zip-env cli container not found" >&2; exit 1; }

docker exec -w /var/www/html "$CLI" wp plugin install /var/www/html/wp-content/cf-zip/culprit-finder.zip --force --activate
docker exec -w /var/www/html "$CLI" wp plugin get culprit-finder --fields=name,status,version
docker exec -w /var/www/html "$CLI" sh -c 'ls wp-content/plugins/culprit-finder; test ! -e wp-content/plugins/culprit-finder/tests && test ! -e wp-content/plugins/culprit-finder/docs && echo "no tests/docs shipped"'

cd "$ROOT"
BASE="http://localhost:$PORT" WPENV_CLI_CONTAINER="$CLI" bash tests/e2e/run.sh solo
