<?php
/**
 * Plugin Name: CFF Pair B
 * Description: Culprit Finder test fixture. Declares cff_shared_helper() unguarded; with the other pair plugin active this is a "Cannot redeclare" fatal. Never install on a real site.
 * Version: 1.0.0
 * License: GPLv2 or later
 *
 * @package CulpritFinderFixtures
 */

defined( 'ABSPATH' ) || exit;

// Declared at runtime (inside a block) so WP-CLI, which loads every active plugin, can still run
// with both pair plugins active. Web requests with both active fatal with "Cannot redeclare".
if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	function cff_shared_helper() {
		return 'b';
	}
}

add_action( 'wp_head', function () { echo "<!-- CFF-PAIR-B -->\n"; } );
