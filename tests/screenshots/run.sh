#!/usr/bin/env bash
# make screenshots: seed the wp-env site with fictional plugins and results, then capture .wordpress-org/screenshot-{1..6}.png.
source "$(dirname "$0")/../e2e/lib.sh"
SHOW=(acme-invoices acme-gallery acme-shop northwind-forms contoso-seo fabrikam-cache tailspin-slider litware-backup wingtip-security proseware-redirects adventure-maps)
reset_site $(printf 'cff-showcase/%s ' "${SHOW[@]}") >/dev/null
wpcli option delete culprit_finder_results >/dev/null 2>&1 || true
wpcli option delete culprit_finder_last_result >/dev/null 2>&1 || true
wpcli option update show_avatars 0 >/dev/null
wpcli user meta update admin admin_color light >/dev/null
wpcli user meta update admin show_welcome_panel 0 >/dev/null 2>&1 || true
wpcli option update blogname "Acme Coffee Roasters" >/dev/null

# oracle_session <pins> <culprit...>: answer like a user who sees the problem only when all culprits are on.
oracle_session() {
  local pins=$1; shift
  if [ -n "$pins" ]; then STEP=$(admin_cli start --pin="$pins" | jq -c .step); else STEP=$(admin_cli start | jq -c .step); fi
  local i
  for i in $(seq 1 30); do
    [ "$(jq -r .status <<<"$STEP")" = done ] && break
    local yes=yes c
    for c in "$@"; do jq -e --arg c "cff-showcase/$c.php" '.enabled | index($c)' <<<"$STEP" >/dev/null || yes=no; done
    [ $# -eq 0 ] && yes=yes
    STEP=$(admin_cli answer "$yes")
  done
  wpcli culprit-finder exit >/dev/null
}
oracle_session "" northwind-forms
oracle_session "cff-showcase/acme-shop.php"
oracle_session "" fabrikam-cache tailspin-slider
oracle_session "cff-showcase/acme-shop.php" acme-invoices
# Fixed dates, newest first: 4, 3, 1 Oct and 26 Sep 2026, 9:00-17:00 UTC.
wpcli eval '
$times = array( 1791123600, 1791043200, 1790845200, 1790413200 );
$r = get_option( "culprit_finder_results" );
foreach ( $r as $i => &$rec ) { $rec["finished_at"] = $times[ $i ]; }
update_option( "culprit_finder_results", $r, false );
update_option( "culprit_finder_last_result", $r[0], false );' >/dev/null

cd "$(dirname "$0")"
[ -d node_modules ] || npm install --silent --no-audit --no-fund
BASE="$BASE" WPENV_CLI_CONTAINER="$CLI_CONTAINER" node capture.mjs "$ROOT/.wordpress-org"
wpcli culprit-finder exit >/dev/null 2>&1 || true
wpcli user meta delete admin admin_color >/dev/null 2>&1 || true
