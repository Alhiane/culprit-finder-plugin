<?php
/**
 * Plugin Name: CFF Noise 16
 * Description: Culprit Finder test fixture. Prints a marker, nothing else. Never install on a real site.
 * Version: 1.0.0
 * License: GPLv2 or later
 *
 * @package CulpritFinderFixtures
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', function () { echo "<!-- CFF-NOISE-16 -->
"; } );
