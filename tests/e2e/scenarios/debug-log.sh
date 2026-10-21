#!/usr/bin/env bash
# The Debug log tab: read-only, admins only, safe output, whole entries, the end of big files (ADR-0031).
source "$(dirname "$0")/../lib.sh"
TAB="/wp-admin/admin.php?page=culprit-finder&tab=debug-log"
LOG='WP_CONTENT_DIR . "/debug.log"'
restore() {
  wpcli config set WP_DEBUG_LOG true --raw >/dev/null 2>&1 || true
  wpcli eval '$f = WP_CONTENT_DIR . "/debug.log"; if ( file_exists( "$f.cf-bak" ) ) { rename( "$f.cf-bak", $f ); }' >/dev/null 2>&1 || true
}
trap restore EXIT
reset_site $(noise 1 2) cff-fixtures/cff-solo >/dev/null
wpcli eval '$f = WP_CONTENT_DIR . "/debug.log"; if ( file_exists( $f ) ) { copy( $f, "$f.cf-bak" ); }' >/dev/null
plant() { wpcli eval "file_put_contents( $LOG, base64_decode( '$(printf '%b' "$1" | base64 | tr -d '\n')' ) );" >/dev/null; }
JAR=$TMP/admin.jar; login "$JAR"

plant '[19-Oct-2026 08:00:00 UTC] PHP Fatal error:  Uncaught Error: Call to undefined function cff_missing()\nStack trace:\n#0 {main}\n  thrown in /var/www/html/wp-content/plugins/x.php on line 3\n[19-Oct-2026 08:00:01 UTC] PHP Warning:  <script>alert(1)</script> in x.php\n[19-Oct-2026 08:00:02 UTC] PHP Notice:  bad \xff\xfe bytes kept\n'
PAGE=$(fetch "$JAR" "$TAB")
assert_contains "$PAGE" ">Debug log<" "the tab is in the tab bar"
assert_contains "$PAGE" ">1 fatal error, " "summary counts entries by level"
assert_contains "$PAGE" "Stack trace:" "stack trace lines are shown with their entry"
assert_contains "$PAGE" "&lt;script&gt;alert(1)&lt;/script&gt;" "HTML in the log is escaped"
assert_not_contains "$PAGE" "<script>alert(1)" "HTML in the log is never output raw"
assert_contains "$PAGE" "bad  bytes kept" "a line with invalid UTF-8 is still shown"
assert_contains "$PAGE" "Read-only." "the tab says it is read-only"
BEFORE_MD5=$(wpcli eval 'echo md5_file( WP_CONTENT_DIR . "/debug.log" );')
fetch "$JAR" "$TAB" >/dev/null
assert_eq "$(wpcli eval 'echo md5_file( WP_CONTENT_DIR . "/debug.log" );')" "$BEFORE_MD5" "viewing never changes the log"

# Only admins see it.
wpcli user get cfeditor >/dev/null 2>&1 || wpcli user create cfeditor cfeditor@example.test --role=editor --user_pass=password >/dev/null
EJAR=$TMP/editor.jar; login "$EJAR" cfeditor
assert_not_contains "$(fetch "$EJAR" "$TAB")" "cff_missing" "an editor never sees the log"
assert_not_contains "$(fetch - "$TAB")" "cff_missing" "a logged-out visitor never sees the log"

# During a session: entries since it started by default, everything on request.
start_session
add_token "$JAR" "$TOKEN"
wpcli eval 'file_put_contents( WP_CONTENT_DIR . "/debug.log", "[" . gmdate( "d-M-Y H:i:s", time() + 60 ) . " UTC] PHP Warning:  cff-during-session\n", FILE_APPEND );' >/dev/null
PAGE=$(fetch "$JAR" "$TAB&culprit_safe=1")
assert_contains "$PAGE" "cff-during-session" "session range: the new entry is shown"
assert_not_contains "$PAGE" "cff_missing" "session range: older entries are left out"
assert_contains "$PAGE" "All recent entries" "session range: link to every entry"
PAGE=$(fetch "$JAR" "$TAB&culprit_safe=1&range=all")
assert_contains "$PAGE" "cff_missing" "all range: older entries are shown"
wpcli culprit-finder exit >/dev/null

# A big file: only its end is read.
wpcli eval '$f = WP_CONTENT_DIR . "/debug.log"; $line = "[19-Oct-2026 09:00:00 UTC] PHP Notice:  " . str_repeat( "x", 200 ) . "\n"; file_put_contents( $f, str_repeat( $line, 30000 ) ); file_put_contents( $f, "[19-Oct-2026 10:00:00 UTC] PHP Warning:  cff-last-line\n", FILE_APPEND );' >/dev/null
PAGE=$(fetch "$JAR" "$TAB")
assert_contains "$PAGE" "cff-last-line" "big file: the newest entry is shown"
assert_contains "$PAGE" "Only the end of the" "big file: says only the end is shown"
assert_contains "$PAGE" "Showing the 100 newest of" "big file: entries are capped"

# Logging off: an existing file is shown as an older log; no file shows how to turn logging on.
wpcli config set WP_DEBUG_LOG false --raw >/dev/null
# PHP's opcache may serve the previous wp-config.php for up to revalidate_freq seconds.
for i in $(seq 1 10); do grep -qF "<dt>WP_DEBUG_LOG</dt><dd>Off" <<<"$(fetch "$JAR" "$TAB")" && break; sleep 1; done
assert_contains "$(fetch "$JAR" "$TAB")" "This is an older log" "logging off: an existing file is labelled as old"
wpcli eval '@unlink( WP_CONTENT_DIR . "/debug.log" );' >/dev/null
PAGE=$(fetch "$JAR" "$TAB")
assert_contains "$PAGE" "Debug logging is off" "logging off: explains it"
assert_contains "$PAGE" "define( &#039;WP_DEBUG_DISPLAY&#039;, false );" "logging off: the snippet keeps errors off pages"
assert_eq "$(wpcli config get WP_DEBUG_LOG)" "" "the tab never turns logging on by itself"
echo "debug-log: ok"
