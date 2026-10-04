<?php
/**
 * Immutable engine step.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Engine;

defined( 'ABSPATH' ) || exit;

/**
 * What to do next: the plugin set for the current question, or the final result.
 */
final class Step {

	const ASKING = 'asking';
	const DONE   = 'done';

	/**
	 * Step data, in the shape returned by to_array().
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * @param string      $status          ASKING or DONE.
	 * @param string      $phase           Phase name.
	 * @param int         $question        1-based question number (answers used + 1 while asking, answers used when done).
	 * @param int         $estimated_total Estimated number of answers in total.
	 * @param string[]    $enabled         Plugins to load for this step.
	 * @param string[]    $disabled        Snapshot minus enabled.
	 * @param Result|null $result          Final result when done.
	 * @param int         $answers_used    Answers consumed.
	 */
	public function __construct( $status, $phase, $question, $estimated_total, array $enabled, array $disabled, $result, $answers_used ) {
		$this->data = array(
			'status'          => $status,
			'phase'           => $phase,
			'question'        => (int) $question,
			'estimated_total' => (int) $estimated_total,
			'enabled'         => array_values( $enabled ),
			'disabled'        => array_values( $disabled ),
			'result'          => $result instanceof Result ? $result->to_array() : null,
			'answers_used'    => (int) $answers_used,
		);
	}

	/**
	 * Whether the search has finished.
	 *
	 * @return bool
	 */
	public function is_done() {
		return self::DONE === $this->data['status'];
	}

	/**
	 * Current phase.
	 *
	 * @return string
	 */
	public function phase() {
		return $this->data['phase'];
	}

	/**
	 * 1-based question number.
	 *
	 * @return int
	 */
	public function question() {
		return $this->data['question'];
	}

	/**
	 * Estimated total answers ("about").
	 *
	 * @return int
	 */
	public function estimated_total() {
		return $this->data['estimated_total'];
	}

	/**
	 * Plugins enabled for this step.
	 *
	 * @return string[]
	 */
	public function enabled() {
		return $this->data['enabled'];
	}

	/**
	 * Plugins disabled for this step.
	 *
	 * @return string[]
	 */
	public function disabled() {
		return $this->data['disabled'];
	}

	/**
	 * Result as an array, or null while asking.
	 *
	 * @return array|null
	 */
	public function result() {
		return $this->data['result'];
	}

	/**
	 * Answers consumed.
	 *
	 * @return int
	 */
	public function answers_used() {
		return $this->data['answers_used'];
	}

	/**
	 * Plain array form for JSON, CLI and storage.
	 *
	 * @return array
	 */
	public function to_array() {
		return $this->data;
	}
}
