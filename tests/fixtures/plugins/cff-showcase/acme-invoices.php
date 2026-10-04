<?php
/**
 * Plugin Name: Acme Invoices
 * Description: Culprit Finder showcase fixture (fictional plugin for screenshots). PDF invoices for your shop. In the screenshots it is the culprit. Never install on a real site.
 * Version: 4.2.1
 * Author: Acme Labs
 * License: GPLv2 or later
 *
 * @package CulpritFinderFixtures
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', function () { echo "<!-- CFF-SYMPTOM -->\n"; } );
