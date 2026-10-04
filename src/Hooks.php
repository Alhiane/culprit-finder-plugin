<?php
/**
 * Guarded extension points.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder;

defined( 'ABSPATH' ) || exit;

/**
 * Fires actions and applies filters for add-ons without letting them break the invariants (ADR-0021).
 *
 * While a callback runs, writes to `active_plugins` are turned into no-ops, even in requests the
 * loader doesn't filter (Start, WP-CLI). A callback that throws is skipped: an action simply ends,
 * a filter falls back to its unfiltered value.
 */
final class Hooks {

	/**
	 * Fire an action.
	 *
	 * @param string $hook    Hook name.
	 * @param mixed  ...$args Arguments.
	 */
	public static function action( $hook, ...$args ) {
		$guard = self::guard_on();
		try {
			do_action( $hook, ...$args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- callers pass culprit_finder_* names.
		} catch ( \Throwable $e ) {
			self::report( $hook, $e );
		} finally {
			self::guard_off( $guard );
		}
	}

	/**
	 * Apply a filter, falling back to the unfiltered value when a callback throws.
	 *
	 * @param string $hook    Hook name.
	 * @param mixed  $value   Value to filter.
	 * @param mixed  ...$args Extra arguments.
	 * @return mixed
	 */
	public static function filter( $hook, $value, ...$args ) {
		$guard = self::guard_on();
		try {
			$filtered = apply_filters( $hook, $value, ...$args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- callers pass culprit_finder_* names.
		} catch ( \Throwable $e ) {
			self::report( $hook, $e );
			$filtered = $value;
		} finally {
			self::guard_off( $guard );
		}
		return $filtered;
	}

	/**
	 * Block writes to active_plugins for the duration of a callback.
	 *
	 * @return callable
	 */
	private static function guard_on() {
		$guard = static function ( $new_value, $old_value ) {
			return $old_value;
		};
		add_filter( 'pre_update_option_active_plugins', $guard, PHP_INT_MAX, 2 );
		return $guard;
	}

	/**
	 * Remove the write guard.
	 *
	 * @param callable $guard Guard callback.
	 */
	private static function guard_off( $guard ) {
		remove_filter( 'pre_update_option_active_plugins', $guard, PHP_INT_MAX );
	}

	/**
	 * Note a failing callback in the debug log.
	 *
	 * @param string     $hook Hook name.
	 * @param \Throwable $e    Error.
	 */
	private static function report( $hook, \Throwable $e ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'Culprit Finder: a callback on %s failed and was skipped: %s', $hook, $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- debug-only diagnostics for add-on authors.
		}
	}
}
