<?php
/**
 * Plugin Name: Fabrikam Cache
 * Description: Culprit Finder showcase fixture (fictional plugin for screenshots). Page caching. Never install on a real site.
 * Version: 3.1.0
 * Author: Fabrikam Inc
 * License: GPLv2 or later
 *
 * @package CulpritFinderFixtures
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', function () { echo "<!-- CFF-SHOW-CACHE -->\n"; } );
