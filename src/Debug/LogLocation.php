<?php
/**
 * Where WordPress writes its debug log, and whether we can read it.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Debug;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the debug constants and PHP's error_log setting; never changes either (ADR-0031).
 */
final class LogLocation {

	const STATE_ACTIVE     = 'active';
	const STATE_OLD        = 'old';
	const STATE_OFF        = 'off';
	const STATE_SYSLOG     = 'syslog';
	const STATE_UNREADABLE = 'unreadable';
	const STATE_EMPTY      = 'empty';

	/**
	 * Debug settings and the log file to show.
	 *
	 * @return array{debug: bool, log: bool, display: bool, path: string, state: string, modified: int}
	 */
	public static function status() {
		$debug   = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$log     = $debug && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
		$display = $debug && ( ! defined( 'WP_DEBUG_DISPLAY' ) || WP_DEBUG_DISPLAY );
		$status  = array(
			'debug'    => $debug,
			'log'      => $log,
			'display'  => $display,
			'path'     => '',
			'state'    => self::STATE_OFF,
			'modified' => 0,
		);

		if ( $log ) {
			$path = (string) ini_get( 'error_log' );
			if ( 'syslog' === strtolower( $path ) ) {
				$status['state'] = self::STATE_SYSLOG;
				return $status;
			}
			$status['path']  = $path;
			$status['state'] = self::STATE_ACTIVE;
		} else {
			$path = WP_CONTENT_DIR . '/debug.log';
			if ( ! @is_file( $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- open_basedir may warn.
				return $status;
			}
			$status['path']  = $path;
			$status['state'] = self::STATE_OLD;
		}

		if ( '' === $status['path'] || ! @is_file( $status['path'] ) || ! @is_readable( $status['path'] ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- open_basedir may warn.
			$status['state'] = '' !== $status['path'] && ! @file_exists( $status['path'] ) ? self::STATE_EMPTY : self::STATE_UNREADABLE; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- open_basedir may warn.
			return $status;
		}
		$modified           = @filemtime( $status['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- open_basedir may warn.
		$status['modified'] = false === $modified ? 0 : (int) $modified;
		return $status;
	}
}
