<?php
/**
 * Plugin Name:       Culprit Finder
 * Plugin URI:        https://getculpritfinder.com
 * Description:       Find the plugin that broke your site. Plugins are switched off for your browser session only, so visitors never notice.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Alhiane Lahcen
 * Author URI:        https://profiles.wordpress.org/alhiane/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       culprit-finder
 *
 * @package CulpritFinder
 */

defined( 'ABSPATH' ) || exit;

define( 'CULPRIT_FINDER_VERSION', '0.1.0' );
define( 'CULPRIT_FINDER_LOADER_VERSION', '0.1.1' );
define( 'CULPRIT_FINDER_FILE', __FILE__ );

spl_autoload_register(
	function ( $class_name ) {
		$prefix = 'CulpritFinder\\';
		if ( 0 !== strncmp( $class_name, $prefix, strlen( $prefix ) ) ) {
			return;
		}
		$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

$culprit_finder_plugin = new CulpritFinder\Plugin( __FILE__ );
$culprit_finder_plugin->register();
register_activation_hook( __FILE__, array( $culprit_finder_plugin, 'activate' ) );
register_deactivation_hook( __FILE__, array( $culprit_finder_plugin, 'deactivate' ) );
