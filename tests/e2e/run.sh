#!/usr/bin/env bash
# Run E2E scenarios against wp-env: bash tests/e2e/run.sh [scenario...]
set -uo pipefail
DIR=$(cd "$(dirname "$0")" && pwd)
BASE=${BASE:-http://localhost:8888}
export BASE

if ! curl -s -o /dev/null --max-time 5 "$BASE/wp-login.php"; then
  echo "WordPress is not reachable at $BASE. Run 'make up' first." >&2
  exit 2
fi

if [ $# -gt 0 ]; then SCENARIOS=("$@"); else
  SCENARIOS=(solo pair dep pinned writer isolation undo exit recovery expiry safe)
  for f in "$DIR"/scenarios/*.sh; do
    id=$(basename "$f" .sh)
    [[ " ${SCENARIOS[*]} " == *" $id "* ]] || SCENARIOS+=("$id")
  done
fi

declare -a RESULTS
failed=0
for id in "${SCENARIOS[@]}"; do
  file="$DIR/scenarios/$id.sh"
  [ -f "$file" ] || { RESULTS+=("FAIL  $id (no such scenario)"); failed=1; continue; }
  start=$(date +%s)
  echo "... $id" >&2
  log=$(mktemp)
  if TMP_E2E=$(mktemp -d) bash "$file" >"$log" 2>&1; then
    RESULTS+=("PASS  $id ($(( $(date +%s) - start ))s)")
  else
    RESULTS+=("FAIL  $id ($(( $(date +%s) - start ))s)")
    failed=1
    sed 's/^/    /' "$log" | tail -20
  fi
  rm -f "$log"
done

# Leave the site clean.
source "$DIR/lib.sh"
wpcli culprit-finder exit >/dev/null 2>&1 || true
wpcli config delete CULPRIT_FINDER_SESSION_TTL >/dev/null 2>&1 || true
wpcli config delete CULPRIT_FINDER_PRO_VERSION >/dev/null 2>&1 || true
wpcli user meta delete admin culprit_finder_hide_pro_tips >/dev/null 2>&1 || true
wpcli plugin deactivate culprit-finder-pro/culprit-finder-pro.php >/dev/null 2>&1 || true
[ -n "$CLI_CONTAINER" ] && docker exec -w /var/www/html "$CLI_CONTAINER" sh -c 'grep -qs "Culprit Finder Pro (test stub)" wp-content/plugins/culprit-finder-pro/culprit-finder-pro.php && rm -rf wp-content/plugins/culprit-finder-pro' >/dev/null 2>&1 || true

echo
printf '%s\n' "${RESULTS[@]}"
exit $failed
