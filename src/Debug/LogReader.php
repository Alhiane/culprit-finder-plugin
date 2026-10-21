<?php
/**
 * Read the end of a PHP error log and group it into entries.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Debug;

defined( 'ABSPATH' ) || exit;

/**
 * Pure PHP, no WordPress calls (unit-tested). Never reads more than a byte cap from the end of the
 * file, and never writes to it (ADR-0031).
 */
final class LogReader {

	const MAX_BYTES    = 524288;
	const MAX_ENTRIES  = 100;
	const LEVEL_FATAL  = 'fatal';
	const LEVEL_WARN   = 'warning';
	const LEVEL_NOTICE = 'notice';
	const LEVEL_DB     = 'database';
	const LEVEL_OTHER  = 'other';

	/**
	 * The last lines of a file, read from at most $max_bytes before its end. The first line is
	 * dropped when the read starts mid-file, since it is usually cut.
	 *
	 * @param string $path      File path.
	 * @param int    $max_bytes Byte cap.
	 * @return array{lines: string[], size: int, partial: bool}|null Null when the file can't be read.
	 */
	public static function tail( $path, $max_bytes = self::MAX_BYTES ) {
		$handle = @fopen( $path, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- read-only; a missing or blocked file is reported on the page.
		if ( false === $handle ) {
			return null;
		}
		$stat  = fstat( $handle );
		$size  = is_array( $stat ) ? (int) $stat['size'] : 0;
		$start = max( 0, $size - max( 1, (int) $max_bytes ) );
		if ( $start > 0 ) {
			fseek( $handle, $start );
		}
		$data = '';
		$read = 0;
		while ( ! feof( $handle ) && $read < $max_bytes ) {
			$chunk = fread( $handle, 65536 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- read-only.
			if ( false === $chunk || '' === $chunk ) {
				break;
			}
			$data .= $chunk;
			$read += strlen( $chunk );
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- read-only.

		$lines = preg_split( '/\r\n|\n|\r/', $data );
		if ( $start > 0 && count( $lines ) > 1 ) {
			array_shift( $lines );
		}
		while ( $lines && '' === end( $lines ) ) {
			array_pop( $lines );
		}
		return array(
			'lines'   => array_values( $lines ),
			'size'    => $size,
			'partial' => $start > 0,
		);
	}

	/**
	 * Group lines into entries: a line starting with a "[date]" stamp opens an entry, and the lines
	 * after it without a stamp (stack traces) belong to it. Oldest first.
	 *
	 * @param string[] $lines Lines.
	 * @return array<int, array{time: int|null, level: string, lines: string[]}>
	 */
	public static function entries( array $lines ) {
		$entries = array();
		foreach ( $lines as $line ) {
			$line = (string) $line;
			$time = self::time( $line );
			if ( null !== $time || ! $entries ) {
				$entries[] = array(
					'time'  => $time,
					'level' => self::level( $line ),
					'lines' => array( $line ),
				);
				continue;
			}
			$entries[ count( $entries ) - 1 ]['lines'][] = $line;
		}
		return $entries;
	}

	/**
	 * Entries stamped at or after a time. Entries without a stamp are left out.
	 *
	 * @param array $entries From entries().
	 * @param int   $since   Unix time.
	 * @return array
	 */
	public static function since( array $entries, $since ) {
		return array_values(
			array_filter(
				$entries,
				function ( $entry ) use ( $since ) {
					return null !== $entry['time'] && $entry['time'] >= (int) $since;
				}
			)
		);
	}

	/**
	 * How many entries of each level.
	 *
	 * @param array $entries From entries().
	 * @return array<string, int>
	 */
	public static function counts( array $entries ) {
		$counts = array(
			self::LEVEL_FATAL  => 0,
			self::LEVEL_WARN   => 0,
			self::LEVEL_NOTICE => 0,
			self::LEVEL_DB     => 0,
			self::LEVEL_OTHER  => 0,
		);
		foreach ( $entries as $entry ) {
			++$counts[ $entry['level'] ];
		}
		return $counts;
	}

	/**
	 * Unix time of a line's "[20-Oct-2026 12:35:59 UTC]" stamp, in the stamp's own timezone.
	 *
	 * @param string $line Line.
	 * @return int|null
	 */
	public static function time( $line ) {
		if ( ! preg_match( '/^\[(\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2})(?: ([A-Za-z0-9_\/+:-]+))?\]/', $line, $m ) ) {
			return null;
		}
		try {
			$zone = new \DateTimeZone( isset( $m[2] ) && '' !== $m[2] ? $m[2] : 'UTC' );
		} catch ( \Exception $e ) {
			$zone = new \DateTimeZone( 'UTC' );
		}
		$date = \DateTime::createFromFormat( '!d-M-Y H:i:s', $m[1], $zone );
		return false === $date ? null : $date->getTimestamp();
	}

	/**
	 * A line with every invalid UTF-8 byte removed and the valid text around it kept, so escaping
	 * never turns a whole line into an empty string.
	 *
	 * @param string $line Line.
	 * @return string
	 */
	public static function scrub( $line ) {
		$line = (string) $line;
		if ( preg_match( '//u', $line ) ) {
			return $line;
		}
		return (string) preg_replace( '/((?:[\x00-\x7F]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})+)|./s', '$1', $line );
	}

	/**
	 * Level of an entry from its first line.
	 *
	 * @param string $line Line.
	 * @return string One of the LEVEL_* constants.
	 */
	public static function level( $line ) {
		if ( preg_match( '/PHP (?:Fatal error|Parse error|Recoverable fatal error)|Uncaught /', $line ) ) {
			return self::LEVEL_FATAL;
		}
		if ( false !== strpos( $line, 'PHP Warning' ) ) {
			return self::LEVEL_WARN;
		}
		if ( preg_match( '/PHP (?:Notice|Deprecated|Strict Standards)/', $line ) ) {
			return self::LEVEL_NOTICE;
		}
		if ( false !== strpos( $line, 'WordPress database error' ) ) {
			return self::LEVEL_DB;
		}
		return self::LEVEL_OTHER;
	}
}
