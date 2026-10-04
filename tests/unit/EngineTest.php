<?php
/**
 * Exhaustive simulator tests for the bisect engine (skill bisect-engine).
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Tests;

use CulpritFinder\Engine\Engine;
use CulpritFinder\Engine\Result;
use CulpritFinder\Tests\Support\Oracle;
use PHPUnit\Framework\TestCase;

final class EngineTest extends TestCase {

	const SELF = 'culprit-finder/culprit-finder.php';

	/**
	 * n suspects + self. Names are not in topo/alphabetical relation to anything special.
	 *
	 * @return string[]
	 */
	private static function plugins( int $n ): array {
		$list = array();
		for ( $i = 0; $i < $n; $i++ ) {
			$list[] = sprintf( 'plugin-%02d/plugin-%02d.php', $i, $i );
		}
		return $list;
	}

	private static function single_bound( int $n ): int {
		return Engine::ceil_log2( $n ) + 2;
	}

	private static function any_bound( int $n ): int {
		return 2 * Engine::ceil_log2( $n ) + 3;
	}

	public function test_single_culprit_exhaustive_with_bounds(): void {
		for ( $n = 1; $n <= 40; $n++ ) {
			$suspects = self::plugins( $n );
			$snapshot = array_merge( $suspects, array( self::SELF ) );
			$engine   = new Engine( $snapshot, self::SELF );
			foreach ( $suspects as $culprit ) {
				$oracle             = new Oracle( array( $culprit ) );
				list( $step, $ans ) = Oracle::drive( $engine, array( $oracle, 'answer' ), self::SELF, array(), $snapshot );
				$result             = $step->result();
				$this->assertSame( Result::SINGLE, $result['type'], "n=$n culprit=$culprit" );
				$this->assertSame( array( $culprit ), $result['culprits'] );
				$this->assertLessThanOrEqual( self::single_bound( $n ), count( $ans ), "AC-3 bound n=$n" );
				$this->assertSame( count( $ans ), $step->answers_used() );
			}
		}
	}

	public function test_pairs_exhaustive_with_bounds(): void {
		for ( $n = 2; $n <= 14; $n++ ) {
			$suspects = self::plugins( $n );
			$snapshot = array_merge( $suspects, array( self::SELF ) );
			$engine   = new Engine( $snapshot, self::SELF );
			for ( $i = 0; $i < $n; $i++ ) {
				for ( $j = $i + 1; $j < $n; $j++ ) {
					$pair               = array( $suspects[ $i ], $suspects[ $j ] );
					list( $step, $ans ) = Oracle::drive( $engine, array( new Oracle( $pair ), 'answer' ), self::SELF, array(), $snapshot );
					$result             = $step->result();
					$this->assertSame( Result::PAIR, $result['type'], "n=$n pair=$i,$j" );
					$this->assertSame( Oracle::sorted( $pair ), Oracle::sorted( $result['culprits'] ) );
					$this->assertLessThanOrEqual( self::any_bound( $n ), count( $ans ), "AC-4 bound n=$n" );
				}
			}
		}
	}

	public function test_triples_sampled_are_complex(): void {
		mt_srand( 20261004 );
		for ( $case = 0; $case < 300; $case++ ) {
			$n        = mt_rand( 3, 20 );
			$suspects = self::plugins( $n );
			$snapshot = array_merge( $suspects, array( self::SELF ) );
			$picked   = (array) array_rand( array_flip( $suspects ), 3 );
			list( $step, $ans ) = Oracle::drive( new Engine( $snapshot, self::SELF ), array( new Oracle( $picked ), 'answer' ), self::SELF, array(), $snapshot );
			$result   = $step->result();
			$this->assertSame( Result::COMPLEX, $result['type'] );
			$this->assertCount( 2, $result['culprits'] );
			$this->assertSame( array(), array_diff( $result['culprits'], $picked ) );
			$this->assertLessThanOrEqual( self::any_bound( $n ), count( $ans ) );
		}
	}

	public function test_not_plugin_after_exactly_one_answer(): void {
		foreach ( array( 1, 2, 7, 30 ) as $n ) {
			$snapshot           = array_merge( self::plugins( $n ), array( self::SELF ) );
			list( $step, $ans ) = Oracle::drive( new Engine( $snapshot, self::SELF ), array( new Oracle( array() ), 'answer' ), self::SELF, array(), $snapshot );
			$this->assertSame( Result::NOT_PLUGIN, $step->result()['type'] );
			$this->assertCount( 1, $ans, 'AC-5' );
		}
	}

	public function test_first_question_is_baseline_with_everything_off(): void {
		$snapshot = array_merge( self::plugins( 5 ), array( self::SELF ) );
		$step     = ( new Engine( $snapshot, self::SELF ) )->step( array() );
		$this->assertSame( Engine::PHASE_BASELINE, $step->phase() );
		$this->assertSame( array( self::SELF ), $step->enabled() );
		$this->assertSame( 1, $step->question() );
		$this->assertSame( 2 + 3, $step->estimated_total() );
	}

	public function test_pinned_culprit_reports_not_plugin(): void {
		$suspects           = self::plugins( 10 );
		$snapshot           = array_merge( $suspects, array( self::SELF ) );
		$pinned             = array( $suspects[3], $suspects[7] );
		list( $step, $ans ) = Oracle::drive( new Engine( $snapshot, self::SELF, $pinned ), array( new Oracle( array( $suspects[7] ) ), 'answer' ), self::SELF, $pinned, $snapshot );
		$this->assertSame( Result::NOT_PLUGIN, $step->result()['type'] );
		$this->assertSame( Oracle::sorted( $pinned ), $step->result()['kept_on'] );
		$this->assertCount( 1, $ans );
	}

	public function test_nothing_to_test_when_everything_pinned(): void {
		$suspects = self::plugins( 4 );
		$snapshot = array_merge( $suspects, array( self::SELF ) );
		$step     = ( new Engine( $snapshot, self::SELF, $suspects ) )->step( array() );
		$this->assertTrue( $step->is_done() );
		$this->assertSame( Result::NOTHING_TO_TEST, $step->result()['type'] );
		$this->assertSame( 0, $step->answers_used() );

		$alone = ( new Engine( array( self::SELF ), self::SELF ) )->step( array() );
		$this->assertSame( Result::NOTHING_TO_TEST, $alone->result()['type'] );
	}

	/**
	 * Random DAGs with random pins and a single culprit (AC-6, AC-7).
	 */
	public function test_dependencies_random_dags(): void {
		mt_srand( 4242 );
		for ( $case = 0; $case < 200; $case++ ) {
			$n     = mt_rand( 2, 16 );
			$names = self::plugins( $n );
			shuffle( $names ); // Name order unrelated to dependency order.
			$deps = array();
			for ( $i = 1; $i < $n; $i++ ) {
				for ( $j = 0; $j < $i; $j++ ) {
					if ( mt_rand( 1, 100 ) <= 20 ) {
						$deps[ $names[ $i ] ][] = $names[ $j ];
					}
				}
			}
			$snapshot = array_merge( $names, array( self::SELF ) );
			$pinned   = array();
			foreach ( $names as $name ) {
				if ( mt_rand( 1, 100 ) <= 10 ) {
					$pinned[] = $name;
				}
			}
			$culprit = $names[ mt_rand( 0, $n - 1 ) ];
			$engine  = new Engine( $snapshot, self::SELF, $pinned, $deps );
			$fixed   = $engine->closure( array_merge( $pinned, array( self::SELF ) ) );

			list( $step ) = Oracle::drive( $engine, array( new Oracle( array( $culprit ) ), 'answer' ), self::SELF, $pinned, $snapshot, $deps );
			$result       = $step->result();
			if ( in_array( $culprit, $fixed, true ) ) {
				$this->assertContains( $result['type'], array( Result::NOT_PLUGIN, Result::NOTHING_TO_TEST ), "case $case" );
			} else {
				$this->assertSame( Result::SINGLE, $result['type'], "case $case" );
				$this->assertSame( array( $culprit ), $result['culprits'], "case $case" );
			}
		}
	}

	public function test_dependency_named_cases(): void {
		$parent   = 'parent/parent.php';
		$child    = 'child/child.php';
		$other    = 'aaa/aaa.php';
		$snapshot = array( $child, $other, $parent, self::SELF );
		$deps     = array( $child => array( 'parent/parent.php' ) );

		// Culprit is a dependent: the child never loads without its parent.
		list( $step ) = Oracle::drive( new Engine( $snapshot, self::SELF, array(), $deps ), array( new Oracle( array( $child ) ), 'answer' ), self::SELF, array(), $snapshot, $deps );
		$this->assertSame( array( $child ), $step->result()['culprits'] );

		// Culprit is a dependency.
		list( $step ) = Oracle::drive( new Engine( $snapshot, self::SELF, array(), $deps ), array( new Oracle( array( $parent ) ), 'answer' ), self::SELF, array(), $snapshot, $deps );
		$this->assertSame( array( $parent ), $step->result()['culprits'] );

		// Pinned plugin requiring a suspect: the suspect becomes fixed.
		$engine = new Engine( $snapshot, self::SELF, array( $child ), $deps );
		$this->assertSame( array( $parent ), $engine->kept_for_dependencies() );
		$this->assertNotContains( $parent, $engine->suspects() );
		list( $step ) = Oracle::drive( $engine, array( new Oracle( array( $parent ) ), 'answer' ), self::SELF, array( $child ), $snapshot, $deps );
		$this->assertSame( Result::NOT_PLUGIN, $step->result()['type'] );
		$this->assertSame( array( $child, $parent ), $step->result()['kept_on'] );
	}

	public function test_topological_order_and_cycles(): void {
		$snapshot = array( 'a/a.php', 'b/b.php', 'c/c.php', 'd/d.php', 'e/e.php', self::SELF );
		// a needs d; c and e need each other (cycle); b needs e.
		$deps   = array(
			'a/a.php' => array( 'd/d.php' ),
			'c/c.php' => array( 'e/e.php' ),
			'e/e.php' => array( 'c/c.php' ),
			'b/b.php' => array( 'e/e.php', 'missing/missing.php' ),
		);
		$engine = new Engine( $snapshot, self::SELF, array(), $deps );
		$this->assertSame( array( 'c/c.php', 'e/e.php', 'b/b.php', 'd/d.php', 'a/a.php' ), $engine->suspects() );
		$this->assertSame( array( 'b/b.php', 'c/c.php', 'e/e.php' ), $engine->closure( array( 'b/b.php' ) ) );

		foreach ( array( 'a/a.php', 'b/b.php', 'c/c.php', 'd/d.php', 'e/e.php' ) as $culprit ) {
			list( $step ) = Oracle::drive( $engine, array( new Oracle( array( $culprit ) ), 'answer' ), self::SELF, array(), $snapshot, $deps );
			$this->assertSame( Result::SINGLE, $step->result()['type'] );
		}
	}

	public function test_undo_equals_replay_of_prefix(): void {
		$snapshot = array_merge( self::plugins( 20 ), array( self::SELF ) );
		$engine   = new Engine( $snapshot, self::SELF );
		$this->assertEquals( $engine->step( array( false, true ) )->to_array(), $engine->step( array_slice( array( false, true, false ), 0, -1 ) )->to_array() );

		// Undo after the result (AC-10): dropping the last answer asks the last question again.
		$oracle             = new Oracle( array( 'plugin-05/plugin-05.php' ) );
		list( $done, $ans ) = Oracle::drive( $engine, array( $oracle, 'answer' ), self::SELF, array(), $snapshot );
		$this->assertTrue( $done->is_done() );
		array_pop( $ans );
		$undone = $engine->step( $ans );
		$this->assertFalse( $undone->is_done() );
		$this->assertSame( count( $ans ) + 1, $undone->question() );
	}

	public function test_extra_answers_after_done_are_ignored(): void {
		$snapshot = array_merge( self::plugins( 3 ), array( self::SELF ) );
		$step     = ( new Engine( $snapshot, self::SELF ) )->step( array( true, false, true ) );
		$this->assertSame( Result::NOT_PLUGIN, $step->result()['type'] );
		$this->assertSame( 1, $step->answers_used() );
	}

	public function test_fuzz_random_answers_terminate(): void {
		mt_srand( 777 );
		for ( $case = 0; $case < 1000; $case++ ) {
			$n        = mt_rand( 1, 30 );
			$snapshot = array_merge( self::plugins( $n ), array( self::SELF ) );
			list( $step, $ans ) = Oracle::drive(
				new Engine( $snapshot, self::SELF ),
				function () {
					return 1 === mt_rand( 0, 1 );
				},
				self::SELF,
				array(),
				$snapshot
			);
			$this->assertTrue( $step->is_done() );
			$this->assertLessThanOrEqual( self::any_bound( $n ), count( $ans ), 'Invariant 5' );
			$this->assertContains( $step->result()['type'], array( Result::NOT_PLUGIN, Result::SINGLE, Result::PAIR, Result::COMPLEX, Result::INCONCLUSIVE ) );
		}
	}

	public function test_inconclusive_when_answers_contradict(): void {
		// b requires a. With everything on the problem is assumed present, but {a} is fine and
		// {a, b} (= everything) is reported fine too: contradictory, so no confident verdict.
		$snapshot = array( 'a/a.php', self::SELF, 'b/b.php' );
		$deps     = array( 'b/b.php' => array( 'a/a.php' ) );
		$step     = ( new Engine( $snapshot, self::SELF, array(), $deps ) )->step( array( false, false, false ) );
		$this->assertTrue( $step->is_done() );
		$this->assertSame( Result::INCONCLUSIVE, $step->result()['type'] );
		$this->assertSame( array( 'b/b.php' ), $step->result()['culprits'] );
	}

	public function test_determinism_under_input_shuffles(): void {
		mt_srand( 99 );
		$suspects = self::plugins( 12 );
		$deps     = array( $suspects[5] => array( $suspects[2] ), $suspects[9] => array( $suspects[5] ) );
		$pinned   = array( $suspects[1], $suspects[11] );
		$snapshot = array_merge( $suspects, array( self::SELF ) );
		$answers  = array( false, true, false, true, false );
		$expected = ( new Engine( $snapshot, self::SELF, $pinned, $deps ) )->step( $answers )->to_array();
		for ( $i = 0; $i < 20; $i++ ) {
			shuffle( $snapshot );
			shuffle( $pinned );
			$this->assertSame( $expected, ( new Engine( $snapshot, self::SELF, $pinned, $deps ) )->step( $answers )->to_array() );
		}
	}

	public function test_invalid_input_throws(): void {
		$this->expectException( \InvalidArgumentException::class );
		new Engine( array( 'a/a.php' ), self::SELF );
	}

	public function test_pinned_outside_snapshot_throws(): void {
		$this->expectException( \InvalidArgumentException::class );
		new Engine( array( self::SELF ), self::SELF, array( 'x/x.php' ) );
	}

	public function test_non_bool_answer_throws(): void {
		$this->expectException( \InvalidArgumentException::class );
		( new Engine( array( self::SELF, 'a/a.php' ), self::SELF ) )->step( array( 1 ) );
	}
}
