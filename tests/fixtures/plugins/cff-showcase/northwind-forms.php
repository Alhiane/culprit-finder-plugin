<?php
/**
 * Plugin Name: Northwind Forms
 * Description: Culprit Finder showcase fixture (fictional plugin for screenshots). Contact forms. Never install on a real site.
 * Version: 5.4.0
 * Author: Northwind Traders
 * License: GPLv2 or later
 *
 * @package CulpritFinderFixtures
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', function () { echo "<!-- CFF-SHOW-FORMS -->\n"; } );
