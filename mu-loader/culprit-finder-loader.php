<?php
/**
 * Plugin Name: Culprit Finder Loader
 * Description: Lets Culprit Finder switch plugins off for one admin's browser session only. Deleting this file ends any troubleshooting session.
 * Version: 0.1.0
 * License: GPLv2 or later
 *
 * Marker: culprit-finder-loader-marker (deactivating Culprit Finder deletes this file only when this line is present).
 *
 * @package CulpritFinder
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'CULPRIT_FINDER_LOADER_FILE' ) ) {
	return;
}
define( 'CULPRIT_FINDER_LOADER_FILE', __FILE__ );

/**
 * Session-scoped active_plugins filter. Must stay dependency-free (skill culprit-loader, ADR-0001/2/3/7).
 *
 * Without our cookie or the recovery argument, boot() returns before any call (AC-14); any error
 * fails open to the normal site. The re-include guard above uses a constant, not class_exists(),
 * because PHP declares a top-level class before the file's first statement runs.
 */
final class Culprit_Finder_Loader {

	const VERSION  = '0.1.0';
	const COOKIE   = 'wp-culprit-finder';
	const OPTION   = 'culprit_finder_session';
	const SAFE_ARG = 'culprit_safe';
	const EXIT_ARG = 'culprit-finder-exit';

	/**
	 * Verified session for this request, or null when not filtering.
	 *
	 * @var array|null
	 */
	private static $session = null;

	/**
	 * Safe-mode request (only the fixed set loads).
	 *
	 * @var bool
	 */
	private static $safe = false;

	/**
	 * True while real_active_plugins() reads the unfiltered option.
	 *
	 * @var bool
	 */
	private static $bypass = false;

	/**
	 * Entry point. Does nothing unless our cookie or the recovery argument is present.
	 */
	public static function boot() {
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification -- runs before pluggables; values are format-checked hex and only hashed.
		$has_cookie = isset( $_COOKIE[ self::COOKIE ] );
		$has_exit   = isset( $_GET[ self::EXIT_ARG ] );
		if ( ! $has_cookie && ! $has_exit ) {
			return;
		}

		try {
			if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() || wp_installing() || is_multisite() ) {
				return;
			}

			if ( $has_exit && self::handle_recovery( $_GET[ self::EXIT_ARG ] ) ) {
				return;
			}

			if ( ! $has_cookie ) {
				return;
			}

			$token = $_COOKIE[ self::COOKIE ];
			if ( ! self::is_hex64( $token ) || ! self::has_logged_in_cookie() ) {
				return;
			}

			$session = get_option( self::OPTION );
			if ( ! self::is_valid( $session ) || ! hash_equals( $session['token_hash'], hash( 'sha256', $token ) ) ) {
				return;
			}

			self::$session = $session;
			self::$safe    = isset( $_GET[ self::SAFE_ARG ] );
			// phpcs:enable

			add_filter( 'option_active_plugins', array( __CLASS__, 'filter_active_plugins' ), PHP_INT_MAX );
			add_filter( 'pre_update_option_active_plugins', array( __CLASS__, 'block_write' ), PHP_INT_MAX, 2 );

			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- standard page-cache constant (ADR-0007).
			}
			add_action( 'send_headers', 'nocache_headers' );
		} catch ( \Throwable $e ) {
			self::$session = null;
		}
	}

	/**
	 * Keep only the plugins allowed for this step (or the fixed set in safe mode), in the real order.
	 *
	 * @param mixed $plugins Value of active_plugins.
	 * @return mixed
	 */
	public static function filter_active_plugins( $plugins ) {
		if ( self::$bypass || null === self::$session || ! is_array( $plugins ) ) {
			return $plugins;
		}
		$allowed   = self::$safe ? self::$session['fixed'] : self::$session['enabled_now'];
		$allowed[] = self::$session['self'];
		return array_values( array_intersect( $plugins, $allowed ) );
	}

	/**
	 * Write guard: update_option() sees new === old and writes nothing (ADR-0003).
	 *
	 * @param mixed $new_value New value.
	 * @param mixed $old_value Old value.
	 * @return mixed
	 */
	public static function block_write( $new_value, $old_value ) {
		return $old_value;
	}

	/**
	 * The real, unfiltered active_plugins list.
	 *
	 * @return string[]
	 */
	public static function real_active_plugins() {
		self::$bypass = true;
		$plugins      = get_option( 'active_plugins', array() );
		self::$bypass = false;
		return is_array( $plugins ) ? $plugins : array();
	}

	/**
	 * Whether this request is filtered by a verified session.
	 *
	 * @return bool
	 */
	public static function is_filtering() {
		return null !== self::$session;
	}

	/**
	 * Whether this request is a filtered safe-mode request.
	 *
	 * @return bool
	 */
	public static function is_safe_mode() {
		return self::is_filtering() && self::$safe;
	}

	/**
	 * Recovery URL: end the session without login when the key matches.
	 *
	 * @param mixed $key Raw recovery key from the query string.
	 * @return bool True when the session was ended.
	 */
	private static function handle_recovery( $key ) {
		if ( ! self::is_hex64( $key ) ) {
			return false;
		}
		$session = get_option( self::OPTION );
		if ( ! is_array( $session ) || empty( $session['recovery_hash'] ) || ! is_string( $session['recovery_hash'] ) || ! hash_equals( $session['recovery_hash'], hash( 'sha256', $key ) ) ) {
			return false;
		}
		delete_option( self::OPTION );
		self::expire_cookie();
		return true;
	}

	/**
	 * Expire our cookie on the home and site paths (cookie constants may not exist yet).
	 */
	private static function expire_cookie() {
		$paths = array_unique( array( self::path_of( get_option( 'home' ) ), self::path_of( get_option( 'siteurl' ) ) ) );
		foreach ( $paths as $path ) {
			setcookie(
				self::COOKIE,
				'',
				array(
					'expires'  => 1,
					'path'     => $path,
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}
	}

	/**
	 * Path component of a URL with a trailing slash.
	 *
	 * @param mixed $url URL.
	 * @return string
	 */
	private static function path_of( $url ) {
		$path = (string) parse_url( (string) $url, PHP_URL_PATH ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- keep the loader dependency-free.
		return rtrim( $path, '/' ) . '/';
	}

	/**
	 * Whether any logged-in cookie is present (not validated; pluggables aren't loaded yet).
	 *
	 * @return bool
	 */
	private static function has_logged_in_cookie() {
		if ( defined( 'LOGGED_IN_COOKIE' ) ) {
			return ! empty( $_COOKIE[ LOGGED_IN_COOKIE ] );
		}
		foreach ( array_keys( $_COOKIE ) as $name ) {
			if ( 0 === strpos( (string) $name, 'wordpress_logged_in_' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Exactly 64 hex characters.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function is_hex64( $value ) {
		return is_string( $value ) && 64 === strlen( $value ) && ctype_xdigit( $value );
	}

	/**
	 * Session option matches the v1 contract and hasn't expired.
	 *
	 * @param mixed $session Option value.
	 * @return bool
	 */
	private static function is_valid( $session ) {
		return is_array( $session )
			&& isset( $session['v'], $session['expires_at'], $session['token_hash'], $session['self'], $session['fixed'], $session['enabled_now'] )
			&& 1 === $session['v']
			&& (int) $session['expires_at'] > time()
			&& is_string( $session['token_hash'] )
			&& is_string( $session['self'] )
			&& is_array( $session['fixed'] )
			&& is_array( $session['enabled_now'] );
	}
}

Culprit_Finder_Loader::boot();
