<?php
/**
 * Plugin Name: Contoso SEO
 * Description: Culprit Finder showcase fixture (fictional plugin for screenshots). Search engine settings. Never install on a real site.
 * Version: 12.0.2
 * Author: Contoso Ltd
 * License: GPLv2 or later
 *
 * @package CulpritFinderFixtures
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', function () { echo "<!-- CFF-SHOW-SEO -->\n"; } );
