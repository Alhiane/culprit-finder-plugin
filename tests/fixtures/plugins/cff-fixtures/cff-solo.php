<?php
/**
 * Plugin Name: CFF Solo
 * Description: Culprit Finder test fixture. Prints the CFF-SYMPTOM marker (the "problem"). Never install on a real site.
 * Version: 1.0.0
 * License: GPLv2 or later
 *
 * @package CulpritFinderFixtures
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', function () { echo "<!-- CFF-SYMPTOM -->
"; } );
