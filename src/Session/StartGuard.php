<?php
/**
 * Preconditions for starting a session.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Session;

defined( 'ABSPATH' ) || exit;

/**
 * Pure checks (unit-tested) for whether a session may start.
 */
final class StartGuard {

	const MULTISITE      = 'multisite';
	const LOADER_MISSING = 'loader_missing';
	const SELF_INACTIVE  = 'self_inactive';

	/**
	 * Reasons a session can't start, in display order. Empty means OK.
	 *
	 * @param bool $is_multisite  Multisite install (AC-13).
	 * @param bool $loader_ready  Loader installed and current.
	 * @param bool $self_in_list  Culprit Finder is in the real active_plugins list.
	 * @return string[]
	 */
	public static function errors( $is_multisite, $loader_ready, $self_in_list ) {
		if ( $is_multisite ) {
			return array( self::MULTISITE );
		}
		$errors = array();
		if ( ! $loader_ready ) {
			$errors[] = self::LOADER_MISSING;
		}
		if ( ! $self_in_list ) {
			$errors[] = self::SELF_INACTIVE;
		}
		return $errors;
	}
}
