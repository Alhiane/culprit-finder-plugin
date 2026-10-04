#!/usr/bin/env bash
# Compatibility matrix: run the solo and pair E2E scenarios on WordPress 6.5 and latest x PHP 7.4 and newest.
# Uses official php:*-cli images, PHP's built-in web server and SQLite (WordPress's SQLite Database Integration),
# so it needs no MySQL image and no image pulls beyond the PHP CLI images. Results go to build/compat/results.md.
#   bash tests/compat/run.sh                      # full matrix
#   CELLS="6.5:7.4" bash tests/compat/run.sh       # one cell (wp:php)
set -uo pipefail
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
WORK="$ROOT/build/compat"
PORT=${COMPAT_PORT:-8892}
NAME=cff-compat
LATEST=$(curl -fsS https://api.wordpress.org/core/version-check/1.7/ | jq -r '.offers[0].current')
CELLS=${CELLS:-"6.5:7.4 6.5:8.5 $LATEST:7.4 $LATEST:8.5"}
image() { case "$1" in 7.4) echo php:7.4-cli ;; 8.2) echo php:8.2-cli ;; 8.5) echo php:8.5-cli-bookworm ;; *) echo "php:$1-cli" ;; esac; }
mkdir -p "$WORK/cache"

fetch() { [ -f "$2" ] || curl -fsSL "$1" -o "$2"; }
fetch https://github.com/wp-cli/wp-cli/releases/download/v2.12.0/wp-cli-2.12.0.phar "$WORK/cache/wp-cli.phar"
# 2.2.x: the 3.x driver needs a newer SQLite library than the php:7.4-cli image ships (3.34).
fetch https://downloads.wordpress.org/plugin/sqlite-database-integration.2.2.23.zip "$WORK/cache/sqlite-2.2.23.zip"

RESULTS="$WORK/results.md"
printf '| WordPress | PHP | solo | pair |\n|---|---|---|---|\n' > "$RESULTS"

for cell in $CELLS; do
  WPV=${cell%%:*}; PHPV=${cell##*:}
  echo "== WordPress $WPV / PHP $PHPV"
  fetch "https://wordpress.org/wordpress-$WPV.zip" "$WORK/cache/wordpress-$WPV.zip"
  SITE="$WORK/site"
  rm -rf "$SITE"; mkdir -p "$SITE"
  unzip -q "$WORK/cache/wordpress-$WPV.zip" -d "$SITE"
  unzip -q "$WORK/cache/sqlite-2.2.23.zip" -d "$SITE/wordpress/wp-content/plugins"
  cp "$SITE/wordpress/wp-content/plugins/sqlite-database-integration/db.copy" "$SITE/wordpress/wp-content/db.php"
  sed -i.bak -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#/var/www/html/wp-content/plugins/sqlite-database-integration#" -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" "$SITE/wordpress/wp-content/db.php"
  mkdir -p "$SITE/wordpress/wp-content/mu-plugins"

  docker rm -f "$NAME" >/dev/null 2>&1
  docker run -d --name "$NAME" -p "$PORT:80" \
    -v "$SITE/wordpress:/var/www/html" \
    -v "$ROOT:/var/www/html/wp-content/plugins/culprit-finder:ro" \
    -v "$ROOT/tests/fixtures/plugins/cff-fixtures:/var/www/html/wp-content/plugins/cff-fixtures:ro" \
    -v "$ROOT/tests/fixtures/plugins/cff-dep-parent:/var/www/html/wp-content/plugins/cff-dep-parent:ro" \
    -v "$ROOT/tests/fixtures/plugins/cff-dep-child:/var/www/html/wp-content/plugins/cff-dep-child:ro" \
    -v "$WORK/cache/wp-cli.phar:/usr/local/bin/wp:ro" \
    -w /var/www/html "$(image "$PHPV")" php -d memory_limit=512M -S 0.0.0.0:80 -t /var/www/html >/dev/null
  W() { docker exec -i -w /var/www/html "$NAME" php -d memory_limit=512M /usr/local/bin/wp --allow-root "$@"; }
  sleep 1
  W config create --dbname=wp --dbuser=wp --dbpass=wp --dbhost=localhost --skip-check --quiet --extra-php <<'PHP'
define( 'DB_DIR', __DIR__ . '/wp-content/database/' );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', true );
PHP
  W core install --url="http://localhost:$PORT" --title="Compat" --admin_user=admin --admin_password=password --admin_email=admin@example.test --skip-email >/dev/null
  RUNTIME=$(W eval 'echo get_bloginfo( "version" ), " / PHP ", PHP_VERSION;' 2>/dev/null)

  # The harness calls `wp`; give it a wrapper that adds --allow-root.
  docker exec "$NAME" sh -c 'printf "#!/bin/sh\nexec php -d memory_limit=512M /usr/local/bin/wp --allow-root \"\$@\"\n" > /usr/local/bin/wpr && chmod +x /usr/local/bin/wpr' 2>/dev/null
  LOG="$WORK/wp$WPV-php$PHPV.log"
  BASE="http://localhost:$PORT" WPENV_CLI_CONTAINER="$NAME" CF_WP_BIN=wpr bash "$ROOT/tests/e2e/run.sh" solo pair > "$LOG" 2>&1
  SOLO=$(grep -E '^(PASS|FAIL)  solo' "$LOG" | cut -c1-4); PAIR=$(grep -E '^(PASS|FAIL)  pair' "$LOG" | cut -c1-4)
  echo "   $RUNTIME: solo=${SOLO:-ERR} pair=${PAIR:-ERR}"
  printf '| %s | %s | %s | %s |\n' "$WPV" "${RUNTIME##*PHP }" "${SOLO:-ERROR}" "${PAIR:-ERROR}" >> "$RESULTS"
  docker rm -f "$NAME" >/dev/null 2>&1
done
echo; cat "$RESULTS"
! grep -qE 'FAIL|ERROR' "$RESULTS"
