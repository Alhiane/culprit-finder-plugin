<?php
/**
 * Report text as shown and downloaded.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Report;

use CulpritFinder\Hooks;

defined( 'ABSPATH' ) || exit;

/**
 * The support report after the `culprit_finder_report_sections` filter (ADR-0021).
 */
final class Report {

	/**
	 * Report text for a result record.
	 *
	 * @param array $record Result record.
	 * @return string
	 */
	public static function text( array $record ) {
		$sections = Builder::sections( $record );
		$filtered = Hooks::filter( 'culprit_finder_report_sections', $sections, self::public_record( $record ) );
		if ( ! is_array( $filtered ) ) {
			$filtered = $sections;
		}
		return Builder::join( $filtered );
	}

	/**
	 * Result record without the owner's user id.
	 *
	 * @param array $record Result record.
	 * @return array
	 */
	public static function public_record( array $record ) {
		unset( $record['user_id'], $record['session_expired_at'], $record['session_max_reached'] );
		return $record;
	}
}
