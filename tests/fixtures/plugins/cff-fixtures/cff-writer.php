<?php
/**
 * Plugin Name: CFF Writer (DANGEROUS, test only)
 * Description: Culprit Finder test fixture. With ?cff_write=i-know it tries to wipe active_plugins, to prove the write guard. Never install on a real site.
 * Version: 1.0.0
 * License: GPLv2 or later
 *
 * @package CulpritFinderFixtures
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	function () {
		if ( isset( $_GET['cff_write'] ) && 'i-know' === $_GET['cff_write'] ) {
			update_option( 'active_plugins', array() );
			add_action( 'wp_head', function () { echo "<!-- CFF-WROTE -->\n"; } );
		}
	}
);
