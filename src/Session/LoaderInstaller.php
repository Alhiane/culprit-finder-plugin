<?php
/**
 * Install and remove the MU loader around a troubleshooting session.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Session;

defined( 'ABSPATH' ) || exit;

/**
 * Copies mu-loader/culprit-finder-loader.php into WPMU_PLUGIN_DIR when the user starts troubleshooting,
 * and removes it when the session ends (ADR-0001, ADR-0023).
 *
 * Only a file carrying the marker line is ever replaced or deleted: a different file with the same
 * name is never touched, and Start explains the conflict instead.
 */
final class LoaderInstaller {

	const FILE   = 'culprit-finder-loader.php';
	const MARKER = 'culprit-finder-loader-marker';

	const PROBLEM_MULTISITE  = 'multisite';
	const PROBLEM_FOREIGN    = 'foreign';
	const PROBLEM_UNWRITABLE = 'unwritable';

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
	 * Whether a file exists at the target and carries our marker line.
	 *
	 * @return bool
	 */
	public static function is_ours() {
		$target = self::target();
		if ( ! is_file( $target ) ) {
			return false;
		}
		$contents = file_get_contents( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file in mu-plugins.
		return is_string( $contents ) && false !== strpos( $contents, self::MARKER );
	}

	/**
	 * Installed, ours, and identical to the bundled loader.
	 *
	 * @return bool
	 */
	public static function is_current() {
		return self::is_ours() && md5_file( self::target() ) === md5_file( self::source() );
	}

	/**
	 * Why the loader can't be installed when the user presses Start, or '' when it can.
	 *
	 * @return string One of the PROBLEM_* constants, or ''.
	 */
	public static function problem() {
		if ( is_multisite() ) {
			return self::PROBLEM_MULTISITE;
		}
		if ( self::is_current() ) {
			return '';
		}
		if ( file_exists( self::target() ) && ! self::is_ours() ) {
			return self::PROBLEM_FOREIGN;
		}
		$dir      = WPMU_PLUGIN_DIR;
		$writable = is_dir( $dir ) ? wp_is_writable( $dir ) : wp_is_writable( dirname( $dir ) );
		return $writable ? '' : self::PROBLEM_UNWRITABLE;
	}

	/**
	 * Make sure the current loader is installed; called when the user starts a session.
	 *
	 * @return bool Installed and current.
	 */
	public static function prepare() {
		if ( self::is_current() ) {
			delete_option( Store::LOADER_ERROR );
			return true;
		}
		if ( '' !== self::problem() ) {
			return false;
		}
		return self::install();
	}

	/**
	 * Temporary copy next to the target. Not a .php file, so WordPress never loads it half-written.
	 *
	 * @return string
	 */
	public static function temp() {
		return self::target() . '.tmp';
	}

	/**
	 * Copy to a temporary file, verify, then rename it over the target in one step, so a crash never
	 * leaves a half-written loader in mu-plugins. Record or clear the error for the Culprit Finder
	 * page. Never replaces a file that isn't ours.
	 *
	 * @return bool
	 */
	private static function install() {
		if ( file_exists( self::target() ) && ! self::is_ours() ) {
			return false;
		}
		$temp = self::temp();
		$ok   = wp_mkdir_p( WPMU_PLUGIN_DIR )
			&& @copy( self::source(), $temp ) // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- failure is reported on the Culprit Finder page.
			&& md5_file( self::source() ) === md5_file( $temp )
			&& @rename( $temp, self::target() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- atomic swap in the same folder; failure is reported on the Culprit Finder page.
		self::remove_temp();
		if ( $ok ) {
			delete_option( Store::LOADER_ERROR );
			return true;
		}
		update_option(
			Store::LOADER_ERROR,
			sprintf(
				/* translators: %s: directory path */
				__( 'Could not copy the helper file into %s (the folder may not be writable).', 'culprit-finder' ),
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
		if ( self::is_ours() ) {
			wp_delete_file( self::target() );
		}
		self::remove_temp();
	}

	/**
	 * Delete a temporary copy left by an interrupted install.
	 */
	private static function remove_temp() {
		if ( is_file( self::temp() ) ) {
			wp_delete_file( self::temp() );
		}
	}
}
