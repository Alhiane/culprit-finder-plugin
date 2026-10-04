<?php
/**
 * Plugin Name: Litware Backup
 * Description: Culprit Finder showcase fixture (fictional plugin for screenshots). Backups. Never install on a real site.
 * Version: 6.2.0
 * Author: Litware Inc
 * License: GPLv2 or later
 *
 * @package CulpritFinderFixtures
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', function () { echo "<!-- CFF-SHOW-BACKUP -->\n"; } );
