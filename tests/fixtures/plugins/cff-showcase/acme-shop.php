<?php
/**
 * Plugin Name: Acme Shop
 * Description: Culprit Finder showcase fixture (fictional plugin for screenshots). A shop. Kept on in the screenshots. Never install on a real site.
 * Version: 9.1.0
 * Author: Acme Labs
 * License: GPLv2 or later
 *
 * @package CulpritFinderFixtures
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', function () { echo "<!-- CFF-SHOW-SHOP -->\n"; } );
