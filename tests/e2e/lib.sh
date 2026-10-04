#!/usr/bin/env bash
# Shared helpers for E2E scenarios. Target: the disposable wp-env site ONLY (ADR-0011).
set -euo pipefail

E2E_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
ROOT=$(cd "$E2E_DIR/../.." && pwd)
BASE=${BASE:-http://localhost:8888}
HOST=$(echo "$BASE" | sed -E 's#^https?://([^:/]+).*#\1#')
SELF=culprit-finder/culprit-finder.php
TMP=${TMP_E2E:-$(mktemp -d)}

# Refuse to run against anything that isn't wp-env's port range by mistake.
case "$BASE" in
  http://localhost:88[89]?|http://localhost:889?) ;;
  *) [ "${CF_E2E_ALLOW_ANY_BASE:-}" = 1 ] || { echo "Refusing BASE=$BASE (expected a wp-env site). Set CF_E2E_ALLOW_ANY_BASE=1 to override." >&2; exit 2; } ;;
esac

# ---- WP-CLI ---------------------------------------------------------------
# docker exec into the running wp-env cli container that mounts this repo; else npx.
find_cli_container() {
  [ -n "${WPENV_CLI_CONTAINER:-}" ] && { echo "$WPENV_CLI_CONTAINER"; return; }
  local c
  for c in $(docker ps --filter label=com.docker.compose.service=cli --format '{{.Names}}' 2>/dev/null); do
    docker inspect -f '{{range .Mounts}}{{.Source}}{{"\n"}}{{end}}' "$c" | sed 's#^/host_mnt##' | grep -qxF "$ROOT" || continue
    # The dev site (8888) uses "cli"; the zip env passes WPENV_CLI_CONTAINER explicitly.
    echo "$c"; return
  done
}
CLI_CONTAINER=${CLI_CONTAINER:-$(find_cli_container || true)}

wpcli() {
  if [ -n "$CLI_CONTAINER" ]; then
    docker exec -i -w /var/www/html "$CLI_CONTAINER" wp "$@"
  else
    (cd "$ROOT" && ${WPENV:-npx --yes @wordpress/env} run cli wp "$@" 2>/dev/null)
  fi
}
# JSON output: keep only the last line (wp-env may print status lines).
wpjson() { wpcli "$@" | tail -n 1; }
admin_cli() { wpjson --user=admin culprit-finder "$@"; }

# ---- HTTP -----------------------------------------------------------------
# login <jar> [user] [password]
login() {
  local jar=$1 user=${2:-admin} pass=${3:-password}
  : > "$jar"
  curl -s -o /dev/null -c "$jar" -b "wordpress_test_cookie=WP%20Cookie%20check" \
    --data-urlencode "log=$user" --data-urlencode "pwd=$pass" \
    --data "wp-submit=Log+In&testcookie=1" "$BASE/wp-login.php"
  grep -q wordpress_logged_in_ "$jar" || fail "login failed for $user"
}
add_token() { printf '%s\tFALSE\t/\tFALSE\t0\twp-culprit-finder\t%s\n' "$HOST" "$2" >> "$1"; }
# fetch <jar|-> <path> → body to stdout
fetch() { if [ "$1" = - ]; then curl -s "$BASE$2"; else curl -s -b "$1" "$BASE$2"; fi; }
code() { if [ "$1" = - ]; then curl -s -o /dev/null -w '%{http_code}' "$BASE$2"; else curl -s -o /dev/null -w '%{http_code}' -b "$1" "$BASE$2"; fi; }

# ---- Assertions -----------------------------------------------------------
fail() { echo "FAIL: $*" >&2; exit 1; }
assert_eq() { [ "$1" = "$2" ] || fail "$3 (expected '$2', got '$1')"; }
assert_contains() { grep -qF -- "$2" <<<"$1" || fail "$3 (missing '$2')"; }
assert_not_contains() { ! grep -qF -- "$2" <<<"$1" || fail "$3 (unexpected '$2')"; }
assert_le() { [ "$1" -le "$2" ] || fail "$3 ($1 > $2)"; }
noise_count() { grep -oE 'CFF-NOISE-[0-9]+' <<<"$1" | sort -u | wc -l | tr -d ' '; }
ceil_log2() { local n=$1 b=0 p=1; while [ "$p" -lt "$n" ]; do p=$((p*2)); b=$((b+1)); done; echo "$b"; }
jq_r() { jq -r "$1" <<<"$2"; }

# ---- Fixtures -------------------------------------------------------------
noise() { local from=$1 to=$2 i; for i in $(seq -w "$from" "$to"); do printf 'cff-fixtures/cff-noise-%02d ' "$((10#$i))"; done; }

# reset <plugin...>: only Culprit Finder + the given plugins active, no session, no TTL override.
reset_site() {
  wpcli culprit-finder exit >/dev/null 2>&1 || true
  wpcli config delete CULPRIT_FINDER_SESSION_TTL >/dev/null 2>&1 || true
  # `wp plugin deactivate --all` mishandles several plugins in one folder (cff-fixtures/*), so:
  # run our own deactivation (removes the loader), then clear the list directly. Disposable site only.
  wpcli plugin deactivate culprit-finder >/dev/null 2>&1 || true
  wpcli option update active_plugins '[]' --format=json >/dev/null
  wpcli plugin activate culprit-finder >/dev/null
  local p
  for p in "$@"; do wpcli plugin activate "$p" >/dev/null; done
  wpcli option get active_plugins --format=json
}

# ---- Session --------------------------------------------------------------
# start_session [pins] → sets START, TOKEN, KEY
start_session() {
  if [ -n "${1:-}" ]; then START=$(admin_cli start --pin="$1"); else START=$(admin_cli start); fi
  TOKEN=$(jq_r .token "$START"); KEY=$(jq_r .recovery_key "$START")
  [ ${#TOKEN} -eq 64 ] || fail "start failed: $START"
}

# symptom_<kind> <jar> → exit 0 if the problem is visible to that browser
symptom_marker() { grep -qF 'CFF-SYMPTOM' <<<"$(fetch "$1" /)"; }
symptom_500() { [ "$(code "$1" /)" = 500 ]; }

# answer_loop <jar> <symptom-fn> [step-hook] → sets STEP (final), ANSWERS
answer_loop() {
  local jar=$1 check=$2 hook=${3:-} i
  STEP=$(wpjson culprit-finder status --format=json)
  for i in $(seq 1 40); do
    [ "$(jq_r .status "$STEP")" = done ] && break
    [ -n "$hook" ] && "$hook" "$jar"
    if "$check" "$jar"; then STEP=$(admin_cli answer yes); else STEP=$(admin_cli answer no); fi
  done
  [ "$(jq_r .status "$STEP")" = done ] || fail "loop did not finish"
  ANSWERS=$(jq_r .answers_used "$STEP")
}

# assert_result <type> <culprit...>  (order-insensitive)
assert_result() {
  local type=$1; shift
  assert_eq "$(jq_r .result.type "$STEP")" "$type" "result type"
  local want got
  want=$(printf '%s\n' "$@" | sed '/^$/d' | sort | tr '\n' ' ')
  got=$(jq -r '.result.culprits[]' <<<"$STEP" | sort | tr '\n' ' ')
  assert_eq "$got" "$want" "culprits"
}
single_bound() { echo $(( $(ceil_log2 "$1") + 2 )); }
any_bound() { echo $(( 2 * $(ceil_log2 "$1") + 3 )); }
