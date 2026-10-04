<?php
/**
 * Plugin Name: Proseware Redirects
 * Description: Culprit Finder showcase fixture (fictional plugin for screenshots). Redirect manager. Never install on a real site.
 * Version: 1.3.0
 * Author: Proseware Inc
 * License: GPLv2 or later
 *
 * @package CulpritFinderFixtures
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', function () { echo "<!-- CFF-SHOW-REDIRECTS -->\n"; } );
