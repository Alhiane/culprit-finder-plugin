<?php
/**
 * Session time limits.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Session;

defined( 'ABSPATH' ) || exit;

/**
 * Pure arithmetic for the idle timeout and the hard maximum session length (ADR-0024).
 *
 * The idle timeout (`expires_at`) moves forward with every answer; the hard maximum (`ends_at`)
 * is fixed at Start and never moves, so a session always ends by itself, even when an add-on
 * answers for the user.
 */
final class Limits {

	const MAX_LENGTH = 10800;
	const MIN_LENGTH = 60;

	/**
	 * Clamp a filtered maximum length: it can be lowered (down to MIN_LENGTH) but never raised.
	 *
	 * @param mixed $value Filter result.
	 * @return int Seconds.
	 */
	public static function max_length( $value ) {
		$value = is_numeric( $value ) ? (int) $value : 0;
		if ( $value <= 0 ) {
			return self::MAX_LENGTH;
		}
		return max( self::MIN_LENGTH, min( self::MAX_LENGTH, $value ) );
	}

	/**
	 * When a session must end at the latest. Sessions started by 0.1.0 have no `ends_at`: their
	 * limit counts from `created_at`.
	 *
	 * @param array $session    Session.
	 * @param int   $max_length Maximum length for sessions without `ends_at`.
	 * @return int Unix time.
	 */
	public static function deadline( array $session, $max_length ) {
		if ( isset( $session['ends_at'] ) && is_numeric( $session['ends_at'] ) ) {
			return (int) $session['ends_at'];
		}
		$created = isset( $session['created_at'] ) && is_numeric( $session['created_at'] ) ? (int) $session['created_at'] : 0;
		return $created + (int) $max_length;
	}

	/**
	 * Idle expiry after an answer: now + TTL, but never past the deadline.
	 *
	 * @param int $now      Unix time.
	 * @param int $ttl      Idle TTL in seconds.
	 * @param int $deadline Hard deadline.
	 * @return int
	 */
	public static function expires_at( $now, $ttl, $deadline ) {
		return min( (int) $now + (int) $ttl, (int) $deadline );
	}
}
