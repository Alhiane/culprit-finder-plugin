<?php
/**
 * Uninstall: delete all Culprit Finder data and the loader (ADR-0007).
 *
 * @package CulpritFinder
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'culprit_finder_session' );
delete_option( 'culprit_finder_last_result' );
delete_option( 'culprit_finder_loader_error' );
delete_option( 'culprit_finder_results' );

$culprit_finder_loader = WPMU_PLUGIN_DIR . '/culprit-finder-loader.php';
if ( is_file( $culprit_finder_loader ) ) {
	$culprit_finder_contents = file_get_contents( $culprit_finder_loader ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file in mu-plugins.
	if ( is_string( $culprit_finder_contents ) && false !== strpos( $culprit_finder_contents, 'culprit-finder-loader-marker' ) ) {
		wp_delete_file( $culprit_finder_loader );
	}
}
