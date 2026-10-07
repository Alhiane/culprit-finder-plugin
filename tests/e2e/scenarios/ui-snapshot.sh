#!/usr/bin/env bash
# Without add-ons every screen renders exactly as in 0.1.0 (ADR-0020, ADR-0024): normalized HTML of each
# screen must match tests/ui-snapshot/baseline (captured from v0.1.0).
source "$(dirname "$0")/../lib.sh"
OUT=$TMP/ui-snapshot
bash "$ROOT/tests/ui-snapshot/run.sh" "$OUT" >/dev/null
diff -ru "$ROOT/tests/ui-snapshot/baseline" "$OUT" || fail "UI differs from the 0.1.0 baseline"
