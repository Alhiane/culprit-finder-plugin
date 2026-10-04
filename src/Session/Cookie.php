<?php
/**
 * The session cookie.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Session;

defined( 'ABSPATH' ) || exit;

/**
 * Cookie `wp-culprit-finder` holding the session token (ADR-0002).
 */
final class Cookie {

	const NAME = 'wp-culprit-finder';

	/**
	 * Whether the request carries our cookie at all (cheap; no DB).
	 *
	 * @return bool
	 */
	public function present() {
		return isset( $_COOKIE[ self::NAME ] );
	}

	/**
	 * Token from the request cookie when well-formed, else null.
	 *
	 * @return string|null
	 */
	public function token() {
		if ( ! $this->present() ) {
			return null;
		}
		$token = sanitize_text_field( wp_unslash( $_COOKIE[ self::NAME ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- isset() checked in present().
		return Token::is_valid_format( $token ) ? $token : null;
	}

	/**
	 * Set or refresh the cookie.
	 *
	 * @param string $token      Token.
	 * @param int    $expires_at Unix time.
	 */
	public function set( $token, $expires_at ) {
		$this->send( $token, (int) $expires_at );
		$_COOKIE[ self::NAME ] = $token;
	}

	/**
	 * Expire the cookie in this browser.
	 */
	public function expire() {
		$this->send( '', 1 );
		unset( $_COOKIE[ self::NAME ] );
	}

	/**
	 * Send Set-Cookie on COOKIEPATH and SITECOOKIEPATH.
	 *
	 * @param string $value   Value.
	 * @param int    $expires Unix time.
	 */
	private function send( $value, $expires ) {
		if ( headers_sent() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		$paths = array_unique( array( COOKIEPATH, SITECOOKIEPATH ) );
		foreach ( $paths as $path ) {
			setcookie(
				self::NAME,
				$value,
				array(
					'expires'  => $expires,
					'path'     => $path,
					'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}
	}
}
