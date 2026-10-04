<?php
/**
 * Plugin Name: Acme Gallery
 * Description: Culprit Finder showcase fixture (fictional plugin for screenshots). Image galleries. Never install on a real site.
 * Version: 2.0.3
 * Author: Acme Labs
 * License: GPLv2 or later
 *
 * @package CulpritFinderFixtures
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', function () { echo "<!-- CFF-SHOW-GALLERY -->\n"; } );
