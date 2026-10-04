<?php
/**
 * Plugin dependency map from plugin headers.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Session;

defined( 'ABSPATH' ) || exit;

/**
 * Reads `Requires Plugins` (WordPress 6.5+) for the snapshot (ADR-0005).
 */
final class Dependencies {

	/**
	 * Basename => required basenames, only edges inside the snapshot.
	 *
	 * @param string[]                  $snapshot Active plugin basenames.
	 * @param array<string, array>|null $headers  get_plugins() data; read from disk when null.
	 * @return array<string, string[]>
	 */
	public static function map( array $snapshot, $headers = null ) {
		if ( null === $headers ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$headers = get_plugins();
		}

		$by_slug = array();
		foreach ( $snapshot as $basename ) {
			$by_slug[ self::slug( $basename ) ][] = $basename;
		}

		$deps = array();
		foreach ( $snapshot as $basename ) {
			$raw = isset( $headers[ $basename ]['RequiresPlugins'] ) ? $headers[ $basename ]['RequiresPlugins'] : '';
			if ( is_array( $raw ) ) {
				$raw = implode( ',', $raw );
			}
			foreach ( array_filter( array_map( 'trim', explode( ',', (string) $raw ) ) ) as $slug ) {
				$slug = strtolower( $slug );
				if ( isset( $by_slug[ $slug ] ) ) {
					foreach ( $by_slug[ $slug ] as $dep ) {
						if ( $dep !== $basename ) {
							$deps[ $basename ][] = $dep;
						}
					}
				}
			}
		}
		return $deps;
	}

	/**
	 * WordPress.org-style slug of a basename: the folder, or the file name for single-file plugins.
	 *
	 * @param string $basename Basename.
	 * @return string
	 */
	public static function slug( $basename ) {
		$dir = dirname( $basename );
		return '.' === $dir ? basename( $basename, '.php' ) : $dir;
	}
}
