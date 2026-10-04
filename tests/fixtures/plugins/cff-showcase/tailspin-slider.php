<?php
/**
 * Plugin Name: Tailspin Slider
 * Description: Culprit Finder showcase fixture (fictional plugin for screenshots). Image sliders. Never install on a real site.
 * Version: 1.8.5
 * Author: Tailspin Toys
 * License: GPLv2 or later
 *
 * @package CulpritFinderFixtures
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', function () { echo "<!-- CFF-SHOW-SLIDER -->\n"; } );
