<?php
/**
 * URLs used by the admin UI.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Control-panel, recovery, and admin-post action URLs (ADR-0006, ADR-0007).
 */
final class Links {

	/**
	 * The Culprit Finder page (top-level menu, ADR-0018).
	 *
	 * @param array $args Extra query args.
	 * @return string
	 */
	public static function tools( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => Page::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Validated address of the broken page: same site only, else ''.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	public static function problem_url( $url ) {
		$url = esc_url_raw( trim( (string) $url ), array( 'http', 'https' ) );
		if ( '' === $url ) {
			return '';
		}
		return (string) wp_validate_redirect( $url, '' );
	}

	/**
	 * Control panel: our page in safe mode (loads with only the fixed set).
	 *
	 * @return string
	 */
	public static function control_panel() {
		return self::tools( array( 'culprit_safe' => 1 ) );
	}

	/**
	 * Emergency exit URL (works logged out).
	 *
	 * @param string $key Recovery key.
	 * @return string
	 */
	public static function recovery( $key ) {
		return add_query_arg( 'culprit-finder-exit', $key, home_url( '/' ) );
	}

	/**
	 * Nonced admin-post link for a state change, always in safe mode.
	 *
	 * @param string $action      start|answer|undo|exit.
	 * @param array  $args        Extra query args.
	 * @param string $redirect_to Where to go afterwards.
	 * @return string
	 */
	public static function action( $action, array $args = array(), $redirect_to = '' ) {
		$args = array_merge(
			array(
				'action'       => 'culprit_finder_' . $action,
				'culprit_safe' => 1,
			),
			$args
		);
		if ( '' !== $redirect_to ) {
			$args['redirect_to'] = rawurlencode( $redirect_to );
		}
		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'culprit_finder_' . $action );
	}

	/**
	 * Form action URL for admin-post (safe mode).
	 *
	 * @return string
	 */
	public static function form_action() {
		return add_query_arg( 'culprit_safe', 1, admin_url( 'admin-post.php' ) );
	}

	/**
	 * The URL of the current request, without our safe-mode flag.
	 *
	 * @return string
	 */
	public static function current_url() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		return remove_query_arg( array( 'culprit_safe', '_wpnonce' ), set_url_scheme( 'http' . ( is_ssl() ? 's' : '' ) . '://' . self::host() . $uri ) );
	}

	/**
	 * Host of the site (from home_url, never from the request).
	 *
	 * @return string
	 */
	private static function host() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$port = wp_parse_url( home_url(), PHP_URL_PORT );
		return $host . ( $port ? ':' . $port : '' );
	}
}
