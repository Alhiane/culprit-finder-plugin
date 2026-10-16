<?php
/**
 * The report's Steps section, rebuilt by replaying the stored answers (ADR-0028).
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Tests;

use CulpritFinder\Engine\Engine;
use CulpritFinder\Engine\Result;
use CulpritFinder\Report\Builder;
use CulpritFinder\Tests\Support\Oracle;
use PHPUnit\Framework\TestCase;

final class ReportStepsTest extends TestCase {

	const SELF = 'culprit-finder/culprit-finder.php';

	/**
	 * Run a search to the end with an oracle and build the record the Collector would save.
	 *
	 * @param string[]                $suspects Suspects.
	 * @param string[]                $culprits Culprits for the oracle.
	 * @param string[]                $kept     Kept-on plugins.
	 * @param array<string, string[]> $deps     Dependencies.
	 * @return array
	 */
	private function record( array $suspects, array $culprits, array $kept = array(), array $deps = array() ): array {
		$snapshot           = array_merge( $suspects, array( self::SELF ) );
		$engine             = new Engine( $snapshot, self::SELF, $kept, $deps );
		list( $step, $ans ) = Oracle::drive( $engine, array( new Oracle( $culprits ), 'answer' ), self::SELF, $kept, $snapshot, $deps );
		$names              = array();
		foreach ( $engine->suspects() as $basename ) {
			$names[ $basename ] = array(
				'name'    => 'Name ' . strtok( $basename, '/' ),
				'version' => '1.0',
			);
		}
		return array(
			'v'           => 1,
			'version'     => '0.3.1',
			'result'      => $step->result(),
			'answers'     => $step->answers_used(),
			'tested'      => count( $engine->suspects() ),
			'finished_at' => gmmktime( 12, 0, 0, 10, 16, 2026 ),
			'plugins'     => array(),
			'env'         => array( 'wp' => '6.8', 'php' => '8.2.0', 'theme' => 'T', 'theme_version' => '1', 'multisite' => false ),
			'replay'      => array(
				'snapshot' => $snapshot,
				'self'     => self::SELF,
				'kept'     => $kept,
				'deps'     => $deps,
				'answers'  => $ans,
				'names'    => $names,
			),
		);
	}

	private static function plugins( int $n ): array {
		$list = array();
		for ( $i = 0; $i < $n; $i++ ) {
			$list[] = sprintf( 'p%02d/p%02d.php', $i, $i );
		}
		return $list;
	}

	/**
	 * Every step's off set, checked against the engine's own replay and the oracle's answer.
	 */
	private function assert_steps_match( array $record, array $culprits ): array {
		$steps = Builder::steps( $record );
		$this->assertCount( $record['answers'], $steps );
		foreach ( $steps as $index => $step ) {
			$this->assertSame( $index + 1, $step['question'] );
			$enabled = array_values( array_diff( $record['replay']['snapshot'], $step['off'] ) );
			$this->assertSame( ! array_diff( $culprits, $enabled ), $step['answer'], 'answer matches the plugins that were on' );
			$this->assertSame( $record['replay']['answers'][ $index ], $step['answer'] );
			$this->assertNotContains( self::SELF, $step['off'] );
		}
		return $steps;
	}

	public function test_solo_steps_and_text(): void {
		$suspects = self::plugins( 8 );
		$record   = $this->record( $suspects, array( 'p05/p05.php' ) );
		$this->assertSame( Result::SINGLE, $record['result']['type'] );
		$steps = $this->assert_steps_match( $record, array( 'p05/p05.php' ) );

		$this->assertSame( Engine::PHASE_BASELINE, $steps[0]['phase'] );
		$this->assertSame( $suspects, $steps[0]['off'], 'baseline: every suspect off' );
		$this->assertFalse( $steps[0]['answer'] );
		$this->assertSame( Engine::PHASE_SOLO, end( $steps )['phase'] );
		$this->assertSame( array_values( array_diff( $suspects, array( 'p05/p05.php' ) ) ), end( $steps )['off'] );

		$sections = Builder::sections( $record );
		$this->assertSame( array( 'result', 'environment', 'steps', 'footer' ), array_keys( $sections ) );
		$lines = explode( "\n", $sections['steps'] );
		$this->assertSame( 'Steps (each answer, replayed in order)', $lines[0] );
		$this->assertSame( '1. Baseline. Off: Name p00 1.0, Name p01 1.0, Name p02 1.0, Name p03 1.0, Name p04 1.0, Name p05 1.0, Name p06 1.0, Name p07 1.0 (8 plugins). Answer: problem gone', $lines[1] );
		$this->assertStringStartsWith( '2. Find first. Off: ', $lines[2] );
		$this->assertStringEndsWith( '(7 plugins). Answer: problem still there', end( $lines ) );
		$this->assertCount( $record['answers'] + 1, $lines );

		$text = Builder::build( $record );
		$this->assertStringNotContainsString( '.php', $sections['steps'], 'names and versions only, no file paths' );
		$this->assertStringContainsString( "\n\nSteps (each answer, replayed in order)\n1. Baseline.", $text );
		$this->assertStringContainsString( 'Answer: problem still there', Builder::to_plain( $text ) );
	}

	public function test_pair_steps_cover_every_phase(): void {
		$suspects = self::plugins( 10 );
		$pair     = array( 'p02/p02.php', 'p07/p07.php' );
		$record   = $this->record( $suspects, $pair );
		$this->assertSame( Result::PAIR, $record['result']['type'] );
		$steps  = $this->assert_steps_match( $record, $pair );
		$phases = array_values( array_unique( array_column( $steps, 'phase' ) ) );
		$this->assertSame( array( Engine::PHASE_BASELINE, Engine::PHASE_FIND_FIRST, Engine::PHASE_SOLO, Engine::PHASE_FIND_PARTNER, Engine::PHASE_VERIFY ), $phases );
		$this->assertSame( array_values( array_diff( $suspects, $pair ) ), end( $steps )['off'], 'confirm: only the pair on' );

		$text = Builder::sections( $record )['steps'];
		$this->assertStringContainsString( '. Pair search. Off: ', $text );
		$this->assertStringContainsString( '. Confirm. Off: ', $text );
	}

	public function test_dependency_steps_are_closed_and_keep_requirements_on(): void {
		$suspects = array( 'base/base.php', 'child/child.php', 'other/other.php', 'pinned/pinned.php', 'lib/lib.php' );
		$deps     = array(
			'child/child.php'   => array( 'base/base.php' ),
			'pinned/pinned.php' => array( 'lib/lib.php' ),
		);
		$record   = $this->record( $suspects, array( 'child/child.php' ), array( 'pinned/pinned.php' ), $deps );
		$this->assertSame( Result::SINGLE, $record['result']['type'] );
		$steps = $this->assert_steps_match( $record, array( 'child/child.php' ) );
		foreach ( $steps as $step ) {
			$this->assertNotContains( 'pinned/pinned.php', $step['off'], 'kept on in every step' );
			$this->assertNotContains( 'lib/lib.php', $step['off'], 'requirement of a kept plugin stays on' );
			if ( ! in_array( 'child/child.php', $step['off'], true ) ) {
				$this->assertNotContains( 'base/base.php', $step['off'], 'a plugin is never on without what it requires' );
			}
		}
		$this->assertSame( array( 'base/base.php', 'child/child.php', 'other/other.php' ), $steps[0]['off'] );
	}

	public function test_undo_shows_only_the_answers_that_stand(): void {
		$suspects = self::plugins( 6 );
		$culprit  = array( 'p01/p01.php' );
		$engine   = new Engine( array_merge( $suspects, array( self::SELF ) ), self::SELF );
		$oracle   = new Oracle( $culprit );
		$answers  = array( $oracle->answer( $engine->step( array() )->enabled() ) );
		$answers[] = ! $oracle->answer( $engine->step( $answers )->enabled() );
		$answers   = array_slice( $answers, 0, -1 );
		while ( ! ( $step = $engine->step( $answers ) )->is_done() ) {
			$answers[] = $oracle->answer( $step->enabled() );
		}

		$record = $this->record( $suspects, $culprit );
		$this->assertSame( $record['replay']['answers'], $answers, 'answers after undo replay like a run without the mistake' );
		$this->assertSame( $record['result'], $step->result() );
		$this->assert_steps_match( $record, $culprit );
	}

	public function test_old_or_inconsistent_records_get_no_steps(): void {
		$record = $this->record( self::plugins( 4 ), array( 'p02/p02.php' ) );

		$old = $record;
		unset( $old['replay'] );
		$this->assertSame( array(), Builder::steps( $old ), 'results saved before 0.3.1' );
		$this->assertArrayNotHasKey( 'steps', Builder::sections( $old ) );
		$this->assertSame( array( 'result', 'environment', 'footer' ), array_keys( Builder::sections( $old ) ) );

		$flipped                         = $record;
		$flipped['replay']['answers'][1] = ! $flipped['replay']['answers'][1];
		$this->assertSame( array(), Builder::steps( $flipped ), 'answers that do not lead to the stored result' );

		$short                      = $record;
		$short['replay']['answers'] = array_slice( $short['replay']['answers'], 0, -1 );
		$this->assertSame( array(), Builder::steps( $short ), 'answer count differs from the record' );

		$bad                   = $record;
		$bad['replay']['self'] = 'missing/missing.php';
		$this->assertSame( array(), Builder::steps( $bad ), 'invalid engine input' );

		$nameless = $record;
		unset( $nameless['replay']['names'] );
		$this->assertStringContainsString( 'p00/p00.php', Builder::sections( $nameless )['steps'], 'falls back to the file name' );
	}

	public function test_nothing_to_test_has_no_steps(): void {
		$record = $this->record( array( 'a/a.php' ), array( 'a/a.php' ), array( 'a/a.php' ) );
		$this->assertSame( Result::NOTHING_TO_TEST, $record['result']['type'] );
		$this->assertArrayNotHasKey( 'steps', Builder::sections( $record ) );
	}
}
