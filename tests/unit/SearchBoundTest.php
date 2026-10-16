<?php
/**
 * Published worst-case number of questions (ADR-0027).
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Tests;

use CulpritFinder\Engine\Engine;
use CulpritFinder\Engine\Result;
use PHPUnit\Framework\TestCase;

/**
 * Measures the real engine for n = 1..64 suspects, every single culprit and every pair, and asserts
 * the measured worst case equals the published bound exactly (so the bound is tight, not just safe):
 *
 *   one plugin:  2 + ceil(log2 n)
 *   two plugins: 3 + ceil(log2 n) + ceil(log2 (n - 1))
 *
 * The engine is driven only through its public replay API. Every culprit set is walked down one
 * shared decision tree (each answer prefix is replayed once), which keeps the 44,000+ runs fast.
 */
final class SearchBoundTest extends TestCase {

	const SELF  = 'culprit-finder/culprit-finder.php';
	const MAX_N = 64;

	/**
	 * Published bound for one culprit.
	 *
	 * @param int $n Suspects.
	 * @return int
	 */
	public static function solo_bound( int $n ): int {
		return 2 + Engine::ceil_log2( $n );
	}

	/**
	 * Published bound for a pair of culprits.
	 *
	 * @param int $n Suspects (at least 2).
	 * @return int
	 */
	public static function pair_bound( int $n ): int {
		return 3 + Engine::ceil_log2( $n ) + Engine::ceil_log2( $n - 1 );
	}

	public function test_measured_worst_case_equals_published_bound(): void {
		$table = array();
		for ( $n = 1; $n <= self::MAX_N; $n++ ) {
			$suspects = array();
			for ( $i = 0; $i < $n; $i++ ) {
				$suspects[] = sprintf( 'plugin-%02d/plugin-%02d.php', $i, $i );
			}
			$engine     = new Engine( array_merge( $suspects, array( self::SELF ) ), self::SELF );
			$candidates = array();
			foreach ( $suspects as $culprit ) {
				$candidates[] = array( $culprit );
			}
			for ( $i = 0; $i < $n; $i++ ) {
				for ( $j = $i + 1; $j < $n; $j++ ) {
					$candidates[] = array( $suspects[ $i ], $suspects[ $j ] );
				}
			}

			$worst = array(
				1 => 0,
				2 => 0,
			);
			$this->walk( $engine, array(), $candidates, $worst, $n );

			$this->assertSame( self::solo_bound( $n ), $worst[1], "one culprit, n=$n" );
			if ( $n >= 2 ) {
				$this->assertSame( self::pair_bound( $n ), $worst[2], "two culprits, n=$n" );
			}
			$table[ $n ] = $worst;
		}

		$this->assertSame( array( 1 => 7, 2 => 13 ), $table[30], 'ADR-0004 example: 30 plugins' );
		$this->assertSame( array( 1 => 8, 2 => 15 ), $table[64], 'n = 64' );
	}

	/**
	 * Replay one answer prefix and split the culprit sets still possible by the answer each gives.
	 *
	 * @param Engine     $engine     Engine.
	 * @param bool[]     $prefix     Answers so far.
	 * @param string[][] $candidates Culprit sets that produce exactly this prefix.
	 * @param int[]      $worst      Culprit-set size => most answers seen.
	 * @param int        $n          Suspects.
	 */
	private function walk( Engine $engine, array $prefix, array $candidates, array &$worst, int $n ): void {
		$step = $engine->step( $prefix );
		if ( $step->is_done() ) {
			$this->assertCount( 1, $candidates, "n=$n: different culprits gave the same answers" );
			$culprits = $candidates[0];
			$result   = $step->result();
			$sorted   = $result['culprits'];
			sort( $sorted, SORT_STRING );
			$this->assertSame( 1 === count( $culprits ) ? Result::SINGLE : Result::PAIR, $result['type'], "n=$n" );
			$this->assertSame( $culprits, $sorted, "n=$n" );
			$worst[ count( $culprits ) ] = max( $worst[ count( $culprits ) ], count( $prefix ) );
			return;
		}

		$enabled = array_flip( $step->enabled() );
		$split   = array(
			1 => array(),
			0 => array(),
		);
		foreach ( $candidates as $culprits ) {
			$present = true;
			foreach ( $culprits as $culprit ) {
				if ( ! isset( $enabled[ $culprit ] ) ) {
					$present = false;
					break;
				}
			}
			$split[ (int) $present ][] = $culprits;
		}
		foreach ( $split as $answer => $group ) {
			if ( $group ) {
				$next   = $prefix;
				$next[] = (bool) $answer;
				$this->walk( $engine, $next, $group, $worst, $n );
			}
		}
	}
}
