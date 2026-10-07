<?php
/**
 * Always-on add-ons.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Session;

defined( 'ABSPATH' ) || exit;

/**
 * Pure validation of the `culprit_finder_always_on` filter result (ADR-0024).
 *
 * A plugin is kept on as an add-on only when it is active at Start (in the snapshot), is not
 * Culprit Finder itself, and declares `Requires Plugins: culprit-finder` in its header. Anything
 * else a callback returns is ignored, so a plugin can't hide from the search by asking.
 */
final class AddOns {

	const SLUG = 'culprit-finder';

	/**
	 * Accepted add-on basenames, in snapshot order.
	 *
	 * @param mixed    $requested Filter result (anything).
	 * @param string[] $snapshot  Active plugin basenames at Start.
	 * @param string   $self_basename Culprit Finder's basename.
	 * @param array    $headers   get_plugins() data (basename => header fields).
	 * @return string[]
	 */
	public static function accept( $requested, array $snapshot, $self_basename, array $headers ) {
		if ( ! is_array( $requested ) ) {
			return array();
		}
		$requested = array_filter( $requested, 'is_string' );
		$accepted  = array();
		foreach ( $snapshot as $basename ) {
			if ( $basename !== $self_basename && in_array( $basename, $requested, true ) && self::requires_us( $basename, $headers ) ) {
				$accepted[] = $basename;
			}
		}
		return $accepted;
	}

	/**
	 * Whether a plugin's `Requires Plugins` header lists culprit-finder.
	 *
	 * @param string $basename Basename.
	 * @param array  $headers  get_plugins() data.
	 * @return bool
	 */
	public static function requires_us( $basename, array $headers ) {
		$raw = isset( $headers[ $basename ]['RequiresPlugins'] ) ? $headers[ $basename ]['RequiresPlugins'] : '';
		if ( is_array( $raw ) ) {
			$raw = implode( ',', $raw );
		}
		$slugs = array_map( 'strtolower', array_map( 'trim', explode( ',', (string) $raw ) ) );
		return in_array( self::SLUG, $slugs, true );
	}

	/**
	 * Valid always-on add-ons stored in a session (sessions from 0.1.0 have none).
	 *
	 * @param array $session Session.
	 * @return string[]
	 */
	public static function of( array $session ) {
		if ( ! isset( $session['always_on'], $session['snapshot'] ) || ! is_array( $session['always_on'] ) || ! is_array( $session['snapshot'] ) ) {
			return array();
		}
		$self = isset( $session['self'] ) ? $session['self'] : '';
		return array_values( array_diff( array_intersect( $session['snapshot'], $session['always_on'] ), array( $self ) ) );
	}

	/**
	 * Everything the engine keeps on besides Culprit Finder: the user's pins plus the add-ons.
	 *
	 * @param array $session Session.
	 * @return string[]
	 */
	public static function kept( array $session ) {
		$pinned = isset( $session['pinned'] ) && is_array( $session['pinned'] ) ? $session['pinned'] : array();
		return array_values( array_unique( array_merge( $pinned, self::of( $session ) ) ) );
	}
}
