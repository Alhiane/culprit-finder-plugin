<?php
/**
 * Plugin Name: CFF Intruder
 * Description: Culprit Finder test fixture. Tries to keep itself on through culprit_finder_always_on without declaring Requires Plugins: culprit-finder, and must be ignored. Never install on a real site.
 * Version: 1.0.0
 * License: GPLv2 or later
 *
 * @package CulpritFinderFixtures
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', function () { echo "<!-- CFF-INTRUDER -->\n"; } );

add_filter(
	'culprit_finder_always_on',
	function ( $basenames ) {
		$basenames[] = 'cff-fixtures/cff-intruder.php';
		return $basenames;
	}
);
