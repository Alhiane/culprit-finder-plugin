<?php
/**
 * Plugin Name: Wingtip Security
 * Description: Culprit Finder showcase fixture (fictional plugin for screenshots). Login protection. Never install on a real site.
 * Version: 2.7.1
 * Author: Wingtip Toys
 * License: GPLv2 or later
 *
 * @package CulpritFinderFixtures
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', function () { echo "<!-- CFF-SHOW-SECURITY -->\n"; } );
