<?php
/**
 * Install, upgrade and remove the MU loader.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Session;

defined( 'ABSPATH' ) || exit;

/**
 * Copies mu-loader/culprit-finder-loader.php into WPMU_PLUGIN_DIR (ADR-0001, skill culprit-loader).
 */
final class LoaderInstaller {

	const FILE   = 'culprit-finder-loader.php';
	const MARKER = 'culprit-finder-loader-marker';

	/**
	 * Installed loader path.
	 *
	 * @return string
	 */
	public static function target() {
		return WPMU_PLUGIN_DIR . '/' . self::FILE;
	}

	/**
	 * Bundled loader path.
	 *
	 * @return string
	 */
	public static function source() {
		return dirname( CULPRIT_FINDER_FILE ) . '/mu-loader/' . self::FILE;
	}

	/**
	 * Installed and the running class (when loaded) has the bundled version.
	 *
	 * @return bool
	 */
	public static function is_current() {
		if ( ! file_exists( self::target() ) ) {
			return false;
		}
		if ( class_exists( 'Culprit_Finder_Loader', false ) ) {
			return CULPRIT_FINDER_LOADER_VERSION === \Culprit_Finder_Loader::VERSION;
		}
		return md5_file( self::target() ) === md5_file( self::source() );
	}

	/**
	 * Install when missing or outdated (admin_init). Activation passes $force to verify the copy.
	 *
	 * @param bool $force Copy unless the installed file is byte-identical.
	 * @return bool Installed and current.
	 */
	public static function maybe_install( $force = false ) {
		if ( is_multisite() ) {
			return false;
		}
		$needs = ! file_exists( self::target() )
			|| ( class_exists( 'Culprit_Finder_Loader', false ) && CULPRIT_FINDER_LOADER_VERSION !== \Culprit_Finder_Loader::VERSION )
			|| ( $force && md5_file( self::target() ) !== md5_file( self::source() ) );
		if ( ! $needs ) {
			return true;
		}
		return self::install();
	}

	/**
	 * Copy and verify; record or clear the error for the Tools page.
	 *
	 * @return bool
	 */
	public static function install() {
		$ok = wp_mkdir_p( WPMU_PLUGIN_DIR )
			&& @copy( self::source(), self::target() ) // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- failure is reported on the Tools page.
			&& md5_file( self::source() ) === md5_file( self::target() );
		if ( $ok ) {
			delete_option( Store::LOADER_ERROR );
			return true;
		}
		update_option(
			Store::LOADER_ERROR,
			sprintf(
				/* translators: %s: directory path */
				__( 'Could not copy the loader into %s (the folder may not be writable).', 'culprit-finder' ),
				WPMU_PLUGIN_DIR
			),
			false
		);
		return false;
	}

	/**
	 * Delete the installed loader, only if it is ours (marker line).
	 */
	public static function remove() {
		$target = self::target();
		if ( ! is_file( $target ) ) {
			return;
		}
		$contents = file_get_contents( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file in mu-plugins.
		if ( is_string( $contents ) && false !== strpos( $contents, self::MARKER ) ) {
			wp_delete_file( $target );
		}
	}
}
