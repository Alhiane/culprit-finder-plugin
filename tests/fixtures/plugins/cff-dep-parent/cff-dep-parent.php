<?php
/**
 * Plugin Name: CFF Dep Parent
 * Description: Culprit Finder test fixture. Defines cff_dep_parent_ready() for CFF Dep Child. Never install on a real site.
 * Version: 1.0.0
 * License: GPLv2 or later
 *
 * @package CulpritFinderFixtures
 */

defined( 'ABSPATH' ) || exit;

function cff_dep_parent_ready() {
	return true;
}

add_action( 'wp_head', function () { echo "<!-- CFF-PARENT -->\n"; } );
