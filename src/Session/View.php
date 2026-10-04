<?php
/**
 * Redacted session data for hooks.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Session;

defined( 'ABSPATH' ) || exit;

/**
 * The session as add-ons see it: no token or recovery hashes (ADR-0021).
 */
final class View {

	const KEYS = array( 'user_id', 'created_at', 'expires_at', 'self', 'snapshot', 'pinned', 'deps', 'answers', 'fixed', 'enabled_now', 'problem_url' );

	/**
	 * Redacted copy of a session.
	 *
	 * @param array $session Session option.
	 * @return array
	 */
	public static function of( array $session ) {
		$view = array();
		foreach ( self::KEYS as $key ) {
			if ( array_key_exists( $key, $session ) ) {
				$view[ $key ] = $session[ $key ];
			}
		}
		return $view;
	}
}
