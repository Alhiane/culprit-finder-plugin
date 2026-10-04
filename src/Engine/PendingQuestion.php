<?php
/**
 * Internal control-flow signal: the replay ran out of answers.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Engine;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown by Engine::ask() when a question has no answer yet; caught in Engine::step().
 */
final class PendingQuestion extends \Exception {

	/**
	 * Plugin set to test next.
	 *
	 * @var string[]
	 */
	public $set;

	/**
	 * Phase that asks the question.
	 *
	 * @var string
	 */
	public $phase;

	/**
	 * Constructor.
	 *
	 * @param string[] $set   Plugin set to test next.
	 * @param string   $phase Phase name.
	 */
	public function __construct( array $set, $phase ) {
		parent::__construct( 'pending question' );
		$this->set   = $set;
		$this->phase = $phase;
	}
}
