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
	const RESULTS      = 'culprit_finder_results';
	const MAX_RESULTS  = 10;

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

	/**
	 * Saved results, newest first (ADR-0019).
	 *
	 * @return array[]
	 */
	public function results() {
		$results = get_option( self::RESULTS );
		if ( ! is_array( $results ) ) {
			return array();
		}
		return array_values(
			array_filter(
				$results,
				function ( $record ) {
					return is_array( $record ) && isset( $record['id'], $record['result'] ) && is_string( $record['id'] );
				}
			)
		);
	}

	/**
	 * One saved result by id, or null.
	 *
	 * @param string $id Result id.
	 * @return array|null
	 */
	public function result( $id ) {
		foreach ( $this->results() as $record ) {
			if ( $record['id'] === $id ) {
				return $record;
			}
		}
		return null;
	}

	/**
	 * Add a result to the front of the history, keeping at most MAX_RESULTS.
	 *
	 * @param array $record Result record with an `id`.
	 */
	public function add_result( array $record ) {
		$results = array_slice( array_merge( array( $record ), $this->results() ), 0, self::MAX_RESULTS );
		update_option( self::RESULTS, $results, false );
	}

	/**
	 * Remove one result from the history.
	 *
	 * @param string $id Result id.
	 * @return bool Whether it existed.
	 */
	public function delete_result( $id ) {
		$results = $this->results();
		$kept    = array_values(
			array_filter(
				$results,
				function ( $record ) use ( $id ) {
					return $record['id'] !== $id;
				}
			)
		);
		if ( count( $kept ) === count( $results ) ) {
			return false;
		}
		update_option( self::RESULTS, $kept, false );
		$last = $this->last_result();
		if ( is_array( $last ) && isset( $last['id'] ) && $last['id'] === $id ) {
			$this->delete_last_result();
		}
		return true;
	}

	/**
	 * Delete the whole history and the last result.
	 */
	public function clear_results() {
		delete_option( self::RESULTS );
		$this->delete_last_result();
	}
}
