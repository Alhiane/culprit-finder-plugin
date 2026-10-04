<?php
/**
 * Test oracle that answers engine questions automatically.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Tests\Support;

use CulpritFinder\Engine\Engine;
use CulpritFinder\Engine\Step;
use PHPUnit\Framework\Assert;

/**
 * Problem is present iff every culprit is enabled (empty culprit set: always present).
 */
final class Oracle {

	/** @var string[] */
	private $culprits;

	/**
	 * @param string[] $culprits Culprit basenames.
	 */
	public function __construct( array $culprits ) {
		$this->culprits = $culprits;
	}

	/**
	 * @param string[] $enabled Enabled set.
	 */
	public function answer( array $enabled ): bool {
		return ! array_diff( $this->culprits, $enabled );
	}

	/**
	 * Drive the engine to completion, asserting invariants 1-3 and 6 on every emitted set.
	 *
	 * @param Engine                  $engine   Engine.
	 * @param callable                $answerer fn(string[] $enabled): bool.
	 * @param string                  $self     Self basename.
	 * @param string[]                $pinned   Pinned basenames.
	 * @param string[]                $snapshot Snapshot.
	 * @param array<string, string[]> $deps     Dependencies.
	 * @return array{0: Step, 1: bool[]} Final step and the answers given.
	 */
	public static function drive( Engine $engine, callable $answerer, string $self, array $pinned, array $snapshot, array $deps = array(), int $max = 60 ): array {
		$answers = array();
		$seen    = array();
		for ( $i = 0; $i <= $max; $i++ ) {
			$step = $engine->step( $answers );
			if ( $step->is_done() ) {
				Assert::assertSame( $snapshot_sorted = self::sorted( $snapshot ), $step->enabled(), 'Done step re-enables the snapshot.' );
				return array( $step, $answers );
			}
			$enabled = $step->enabled();
			self::assert_set_invariants( $enabled, $self, $pinned, $snapshot, $deps );
			Assert::assertSame( count( $answers ) + 1, $step->question() );
			Assert::assertGreaterThanOrEqual( $step->question(), $step->estimated_total() );
			$key = implode( "\n", $enabled );
			Assert::assertArrayNotHasKey( $key, $seen, 'Invariant 3: a set was asked twice.' );
			$seen[ $key ] = true;
			$answers[]    = (bool) $answerer( $enabled );
		}
		Assert::fail( 'Engine did not terminate within ' . $max . ' answers.' );
	}

	/**
	 * Invariants 1 and 2.
	 */
	public static function assert_set_invariants( array $enabled, string $self, array $pinned, array $snapshot, array $deps ): void {
		Assert::assertContains( $self, $enabled, 'Invariant 1: self is enabled.' );
		Assert::assertSame( array(), array_values( array_diff( $pinned, $enabled ) ), 'Invariant 1: pinned are enabled.' );
		Assert::assertSame( array(), array_values( array_diff( $enabled, $snapshot ) ), 'Invariant 2: enabled within snapshot.' );
		foreach ( $enabled as $plugin ) {
			foreach ( $deps[ $plugin ] ?? array() as $dep ) {
				if ( in_array( $dep, $snapshot, true ) ) {
					Assert::assertContains( $dep, $enabled, "Invariant 2: $plugin needs $dep." );
				}
			}
		}
	}

	public static function sorted( array $list ): array {
		$list = array_values( array_unique( $list ) );
		sort( $list, SORT_STRING );
		return $list;
	}
}
