<?php
/**
 * Option storage for the session and the last result.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Session;

defined( 'ABSPATH' ) || exit;

/**
 * Non-autoloaded options (ADR-0001, ADR-0008).
 */
final class Store {

	const SESSION      = 'culprit_finder_session';
	const LAST_RESULT  = 'culprit_finder_last_result';
	const LOADER_ERROR = 'culprit_finder_loader_error';

	/**
	 * Raw session option, or null.
	 *
	 * @return array|null
	 */
	public function session() {
		$session = get_option( self::SESSION );
		return is_array( $session ) ? $session : null;
	}

	/**
	 * Save the session.
	 *
	 * @param array $session Session data (contract in ADR-0001).
	 */
	public function save_session( array $session ) {
		update_option( self::SESSION, $session, false );
	}

	/**
	 * Delete the session.
	 */
	public function delete_session() {
		delete_option( self::SESSION );
	}

	/**
	 * Last result record, or null.
	 *
	 * @return array|null
	 */
	public function last_result() {
		$result = get_option( self::LAST_RESULT );
		return is_array( $result ) ? $result : null;
	}

	/**
	 * Save the last result record.
	 *
	 * @param array $result Record.
	 */
	public function save_last_result( array $result ) {
		update_option( self::LAST_RESULT, $result, false );
	}

	/**
	 * Delete the last result record.
	 */
	public function delete_last_result() {
		delete_option( self::LAST_RESULT );
	}
}
