<?php
/**
 * Prefix-bisection search with pair detection.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Engine;

defined( 'ABSPATH' ) || exit;

/**
 * Pure replay engine (ADR-0004, ADR-0005, skill bisect-engine).
 *
 * The state is a pure function of (snapshot, self, pinned, deps, answers): step() replays
 * the algorithm from the start, consuming answers, until it needs an answer it doesn't have.
 */
final class Engine {

	const PHASE_BASELINE     = 'baseline';
	const PHASE_FIND_FIRST   = 'find_first';
	const PHASE_SOLO         = 'solo';
	const PHASE_FIND_PARTNER = 'find_partner';
	const PHASE_VERIFY       = 'verify';
	const PHASE_DONE         = 'done';

	/**
	 * Snapshot, sorted by strcmp (canonical order).
	 *
	 * @var string[]
	 */
	private $snapshot;

	/**
	 * Our own basename.
	 *
	 * @var string
	 */
	private $self;

	/**
	 * Pinned basenames, sorted.
	 *
	 * @var string[]
	 */
	private $pinned;

	/**
	 * Dependency graph.
	 *
	 * @var Graph
	 */
	private $graph;

	/**
	 * Fixed set F0 = closure(pinned + self), sorted.
	 *
	 * @var string[]
	 */
	private $fixed;

	/**
	 * Suspects in topological order.
	 *
	 * @var string[]
	 */
	private $order;

	/**
	 * Replay state: answers being replayed.
	 *
	 * @var bool[]
	 */
	private $answers = array();

	/**
	 * Replay state: next answer index.
	 *
	 * @var int
	 */
	private $cursor = 0;

	/**
	 * Replay state: set key => answer.
	 *
	 * @var array<string, bool>
	 */
	private $memo = array();

	/**
	 * Replay state: size of the partner search range, once that phase starts.
	 *
	 * @var int|null
	 */
	private $rest_count = null;

	/**
	 * Constructor.
	 *
	 * @param string[]                $snapshot Real active plugins at session start.
	 * @param string                  $self_basename Our basename; must be in the snapshot.
	 * @param string[]                $pinned   Keep-on plugins; must be in the snapshot.
	 * @param array<string, string[]> $deps     Basename => required basenames (edges leaving the snapshot are ignored).
	 *
	 * @throws \InvalidArgumentException On invalid input.
	 */
	public function __construct( array $snapshot, $self_basename, array $pinned = array(), array $deps = array() ) {
		$self = $self_basename;
		foreach ( array_merge( $snapshot, $pinned, array( $self ) ) as $plugin ) {
			if ( ! is_string( $plugin ) || '' === $plugin ) {
				throw new \InvalidArgumentException( 'Plugin basenames must be non-empty strings.' );
			}
		}
		$snapshot = array_values( array_unique( $snapshot ) );
		sort( $snapshot, SORT_STRING );
		if ( ! in_array( $self, $snapshot, true ) ) {
			throw new \InvalidArgumentException( 'Self must be in the snapshot.' );
		}
		$pinned = array_values( array_unique( $pinned ) );
		sort( $pinned, SORT_STRING );
		if ( array_diff( $pinned, $snapshot ) ) {
			throw new \InvalidArgumentException( 'Pinned plugins must be in the snapshot.' );
		}

		$this->snapshot = $snapshot;
		$this->self     = $self;
		$this->pinned   = $pinned;
		$this->graph    = new Graph( $snapshot, $deps );
		$this->fixed    = $this->graph->closure( array_merge( $pinned, array( $self ) ) );
		$this->order    = $this->graph->topo_order( array_diff( $snapshot, $this->fixed ) );
	}

	/**
	 * Fixed set (pinned + self + their dependencies), used in safe mode.
	 *
	 * @return string[]
	 */
	public function fixed() {
		return $this->fixed;
	}

	/**
	 * Plugins kept on only because a pinned plugin (or self) requires them.
	 *
	 * @return string[]
	 */
	public function kept_for_dependencies() {
		return array_values( array_diff( $this->fixed, $this->pinned, array( $this->self ) ) );
	}

	/**
	 * Suspects in test order.
	 *
	 * @return string[]
	 */
	public function suspects() {
		return $this->order;
	}

	/**
	 * Dependency closure of a set within the snapshot.
	 *
	 * @param string[] $set Basenames.
	 * @return string[]
	 */
	public function closure( array $set ) {
		return $this->graph->closure( $set );
	}

	/**
	 * Replay the answers and return the current step.
	 *
	 * @param bool[] $answers True = "problem still there". Extra answers after the result are ignored.
	 * @return Step
	 *
	 * @throws \InvalidArgumentException When an answer is not a bool.
	 */
	public function step( array $answers ) {
		foreach ( $answers as $answer ) {
			if ( ! is_bool( $answer ) ) {
				throw new \InvalidArgumentException( 'Answers must be booleans.' );
			}
		}
		$this->answers    = array_values( $answers );
		$this->cursor     = 0;
		$this->memo       = array();
		$this->rest_count = null;

		try {
			$result = $this->run();
		} catch ( PendingQuestion $pending ) {
			$question = $this->cursor + 1;
			return new Step(
				Step::ASKING,
				$pending->phase,
				$question,
				max( $question, $this->estimate() ),
				$pending->set,
				array_values( array_diff( $this->snapshot, $pending->set ) ),
				null,
				$this->cursor
			);
		}

		return new Step( Step::DONE, self::PHASE_DONE, $this->cursor, $this->cursor, $this->snapshot, array(), $result, $this->cursor );
	}

	/**
	 * The algorithm, top to bottom. ask() throws PendingQuestion when answers run out.
	 *
	 * Both binary searches look for the smallest prefix that reproduces the problem: prefix 0 is
	 * known "no" (baseline) and the full prefix is assumed "yes" (the problem was seen with
	 * everything on; for the partner search it equals the first search's reproducing prefix).
	 *
	 * @return Result
	 */
	private function run() {
		$f0      = $this->fixed;
		$order   = $this->order;
		$n       = count( $order );
		$kept_on = array_values( array_diff( $f0, array( $this->self ) ) );

		if ( 0 === $n ) {
			return new Result( Result::NOTHING_TO_TEST, array(), $kept_on );
		}
		if ( $this->ask( $f0, self::PHASE_BASELINE ) ) {
			return new Result( Result::NOT_PLUGIN, array(), $kept_on );
		}

		$lo = 0;
		$hi = $n;
		while ( $hi - $lo > 1 ) {
			$mid = intdiv( $lo + $hi, 2 );
			if ( $this->ask( array_merge( $f0, array_slice( $order, 0, $mid ) ), self::PHASE_FIND_FIRST ) ) {
				$hi = $mid;
			} else {
				$lo = $mid;
			}
		}
		$first = $order[ $hi - 1 ];

		$f1 = $this->graph->closure( array_merge( $f0, array( $first ) ) );
		if ( $this->ask( $f1, self::PHASE_SOLO ) ) {
			return new Result( Result::SINGLE, array( $first ), $kept_on );
		}

		$rest = array_values( array_diff( array_slice( $order, 0, $hi - 1 ), $f1 ) );
		if ( ! $rest ) {
			return new Result( Result::INCONCLUSIVE, array( $first ), $kept_on );
		}

		$this->rest_count = count( $rest );
		$lo               = 0;
		$hi2              = count( $rest );
		while ( $hi2 - $lo > 1 ) {
			$mid = intdiv( $lo + $hi2, 2 );
			if ( $this->ask( array_merge( $f1, array_slice( $rest, 0, $mid ) ), self::PHASE_FIND_PARTNER ) ) {
				$hi2 = $mid;
			} else {
				$lo = $mid;
			}
		}
		$partner = $rest[ $hi2 - 1 ];

		$pair = array( $first, $partner );
		if ( $this->ask( array_merge( $f0, $pair ), self::PHASE_VERIFY ) ) {
			return new Result( Result::PAIR, $pair, $kept_on );
		}
		return new Result( Result::COMPLEX, $pair, $kept_on );
	}

	/**
	 * Ask about a set (canonicalised by closure). Memoised: never asks the same set twice.
	 *
	 * @param string[] $set   Plugins to enable.
	 * @param string   $phase Phase asking.
	 * @return bool
	 *
	 * @throws PendingQuestion When no answer is left.
	 */
	private function ask( array $set, $phase ) {
		$set = $this->graph->closure( $set );
		$key = implode( "\n", $set );
		if ( array_key_exists( $key, $this->memo ) ) {
			return $this->memo[ $key ];
		}
		if ( $this->cursor >= count( $this->answers ) ) {
			throw new PendingQuestion( $set, $phase ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- internal control flow, caught in step(), never printed.
		}
		$this->memo[ $key ] = $this->answers[ $this->cursor ];
		++$this->cursor;
		return $this->memo[ $key ];
	}

	/**
	 * Estimated total answers: 2 + ceil(log2 n), plus 1 + ceil(log2 |rest|) once the partner search starts.
	 *
	 * @return int
	 */
	private function estimate() {
		$estimate = 2 + self::ceil_log2( count( $this->order ) );
		if ( null !== $this->rest_count ) {
			$estimate += 1 + self::ceil_log2( $this->rest_count );
		}
		return $estimate;
	}

	/**
	 * Integer ceil(log2(x)) for x >= 1; 0 for x <= 1.
	 *
	 * @param int $x Value.
	 * @return int
	 */
	public static function ceil_log2( $x ) {
		$bits = 0;
		$pow  = 1;
		while ( $pow < $x ) {
			$pow *= 2;
			++$bits;
		}
		return $bits;
	}
}
