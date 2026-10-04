<?php
/**
 * Plugin Name: CFF Dep Child
 * Description: Culprit Finder test fixture. Requires CFF Dep Parent and fatals without it; prints CFF-SYMPTOM. Never install on a real site.
 * Version: 1.0.0
 * Requires Plugins: cff-dep-parent
 * License: GPLv2 or later
 *
 * @package CulpritFinderFixtures
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'plugins_loaded',
	function () {
		cff_dep_parent_ready(); // Unguarded on purpose: fatals if the parent isn't loaded.
	}
);

add_action( 'wp_head', function () { echo "<!-- CFF-SYMPTOM -->\n"; } );
