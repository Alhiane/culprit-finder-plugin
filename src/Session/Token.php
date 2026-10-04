<?php
/**
 * Secret tokens.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Session;

defined( 'ABSPATH' ) || exit;

/**
 * Random 64-hex tokens; only their sha256 hashes are stored (ADR-0002).
 */
final class Token {

	/**
	 * New random token (64 hex chars).
	 *
	 * @return string
	 */
	public static function generate() {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Storage hash of a token.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	public static function hash( $token ) {
		return hash( 'sha256', $token );
	}

	/**
	 * Exactly 64 hex characters.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function is_valid_format( $value ) {
		return is_string( $value ) && 64 === strlen( $value ) && ctype_xdigit( $value );
	}

	/**
	 * Constant-time check of a token against a stored hash.
	 *
	 * @param mixed $token Token.
	 * @param mixed $hash  Stored hash.
	 * @return bool
	 */
	public static function matches( $token, $hash ) {
		return self::is_valid_format( $token ) && is_string( $hash ) && hash_equals( $hash, self::hash( $token ) );
	}
}
