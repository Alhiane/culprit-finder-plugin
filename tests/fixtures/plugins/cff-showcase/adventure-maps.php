<?php
/**
 * Plugin Name: Adventure Maps
 * Description: Culprit Finder showcase fixture (fictional plugin for screenshots). Store locator maps. Never install on a real site.
 * Version: 0.9.4
 * Author: Adventure Works
 * License: GPLv2 or later
 *
 * @package CulpritFinderFixtures
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', function () { echo "<!-- CFF-SHOW-MAPS -->\n"; } );
