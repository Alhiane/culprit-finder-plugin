<?php
/**
 * Plugin Name: CFF Query Probe
 * Description: Culprit Finder test fixture. With SAVEQUERIES on, prints the query count and how many queries mention culprit_finder. Never install on a real site.
 * Version: 1.0.0
 * License: GPLv2 or later
 *
 * @package CulpritFinderFixtures
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'shutdown',
	function () {
		global $wpdb;
		if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES || is_admin() ) {
			return;
		}
		$ours = 0;
		foreach ( (array) $wpdb->queries as $q ) {
			if ( false !== strpos( $q[0], 'culprit_finder' ) ) {
				++$ours;
			}
		}
		echo "\n<!-- CFF-QUERIES total=" . count( (array) $wpdb->queries ) . ' ours=' . $ours . " -->\n";
	},
	PHP_INT_MAX
);
