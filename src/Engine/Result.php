<?php
/**
 * Search result value object.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Engine;

defined( 'ABSPATH' ) || exit;

/**
 * Final verdict of a search (ADR-0004).
 */
final class Result {

	const NOT_PLUGIN      = 'NOT_PLUGIN';
	const NOTHING_TO_TEST = 'NOTHING_TO_TEST';
	const SINGLE          = 'SINGLE';
	const PAIR            = 'PAIR';
	const COMPLEX         = 'COMPLEX';
	const INCONCLUSIVE    = 'INCONCLUSIVE';

	/**
	 * One of the type constants.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * Culprit basenames.
	 *
	 * @var string[]
	 */
	private $culprits;

	/**
	 * Plugins kept on in every step (fixed set without self).
	 *
	 * @var string[]
	 */
	private $kept_on;

	/**
	 * Constructor.
	 *
	 * @param string   $type     Type constant.
	 * @param string[] $culprits Culprit basenames.
	 * @param string[] $kept_on  Fixed set without self.
	 */
	public function __construct( $type, array $culprits, array $kept_on ) {
		$this->type     = $type;
		$this->culprits = array_values( $culprits );
		$this->kept_on  = array_values( $kept_on );
	}

	/**
	 * Result type.
	 *
	 * @return string
	 */
	public function type() {
		return $this->type;
	}

	/**
	 * Culprit basenames.
	 *
	 * @return string[]
	 */
	public function culprits() {
		return $this->culprits;
	}

	/**
	 * Plugins kept on in every step.
	 *
	 * @return string[]
	 */
	public function kept_on() {
		return $this->kept_on;
	}

	/**
	 * Plain array form.
	 *
	 * @return array{type: string, culprits: string[], kept_on: string[]}
	 */
	public function to_array() {
		return array(
			'type'     => $this->type,
			'culprits' => $this->culprits,
			'kept_on'  => $this->kept_on,
		);
	}
}
