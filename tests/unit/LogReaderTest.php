<?php
/**
 * Reading the end of the PHP error log (ADR-0031).
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Tests;

use CulpritFinder\Debug\LogReader;
use PHPUnit\Framework\TestCase;

final class LogReaderTest extends TestCase {

	/** @var string[] */
	private $files = array();

	protected function tearDown(): void {
		foreach ( $this->files as $file ) {
			@unlink( $file );
		}
	}

	private function file( string $contents ): string {
		$file          = tempnam( sys_get_temp_dir(), 'cf-log' );
		$this->files[] = $file;
		file_put_contents( $file, $contents );
		return $file;
	}

	public function test_tail_reads_a_small_file_whole(): void {
		$tail = LogReader::tail( $this->file( "one\ntwo\nthree\n" ) );
		$this->assertSame( array( 'one', 'two', 'three' ), $tail['lines'] );
		$this->assertFalse( $tail['partial'] );
	}

	public function test_tail_reads_only_the_end_and_drops_the_cut_line(): void {
		$lines = array();
		for ( $i = 1; $i <= 1000; $i++ ) {
			$lines[] = sprintf( 'line %04d', $i );
		}
		$tail = LogReader::tail( $this->file( implode( "\n", $lines ) . "\n" ), 100 );
		$this->assertTrue( $tail['partial'] );
		$this->assertSame( 'line 1000', end( $tail['lines'] ) );
		$this->assertLessThanOrEqual( 10, count( $tail['lines'] ) );
		foreach ( $tail['lines'] as $line ) {
			$this->assertMatchesRegularExpression( '/^line \d{4}$/', $line, 'No cut first line.' );
		}
	}

	public function test_tail_of_a_missing_file_is_null(): void {
		$this->assertNull( LogReader::tail( sys_get_temp_dir() . '/cf-log-missing-' . uniqid() ) );
	}

	public function test_tail_of_an_empty_file(): void {
		$this->assertSame( array(), LogReader::tail( $this->file( '' ) )['lines'] );
	}

	public function test_stack_traces_stay_with_their_entry(): void {
		$entries = LogReader::entries(
			array(
				'[20-Oct-2026 12:00:00 UTC] PHP Fatal error:  Uncaught Error: Call to undefined function foo()',
				'Stack trace:',
				'#0 {main}',
				'  thrown in /var/www/x.php on line 3',
				'[20-Oct-2026 12:00:05 UTC] PHP Warning:  Undefined variable $a',
			)
		);
		$this->assertCount( 2, $entries );
		$this->assertSame( LogReader::LEVEL_FATAL, $entries[0]['level'] );
		$this->assertCount( 4, $entries[0]['lines'] );
		$this->assertSame( LogReader::LEVEL_WARN, $entries[1]['level'] );
	}

	public function test_lines_before_the_first_stamp_form_their_own_entry(): void {
		$entries = LogReader::entries( array( 'leftover', '[20-Oct-2026 12:00:00 UTC] PHP Notice:  x' ) );
		$this->assertCount( 2, $entries );
		$this->assertNull( $entries[0]['time'] );
		$this->assertSame( LogReader::LEVEL_NOTICE, $entries[1]['level'] );
	}

	public function test_time_reads_the_stamp_timezone(): void {
		$this->assertSame( gmmktime( 12, 35, 59, 10, 20, 2026 ), LogReader::time( '[20-Oct-2026 12:35:59 UTC] PHP Warning: x' ) );
		$this->assertSame( gmmktime( 10, 35, 59, 10, 20, 2026 ), LogReader::time( '[20-Oct-2026 12:35:59 Europe/Paris] PHP Warning: x' ) );
		$this->assertSame( gmmktime( 12, 0, 0, 10, 20, 2026 ), LogReader::time( '[20-Oct-2026 12:00:00 Not/AZone] x' ), 'Unknown zones read as UTC.' );
		$this->assertNull( LogReader::time( 'PHP Warning: no stamp' ) );
	}

	public function test_since_keeps_whole_entries_from_that_time(): void {
		$entries = LogReader::entries(
			array(
				'[20-Oct-2026 11:59:59 UTC] PHP Warning:  before',
				'[20-Oct-2026 12:00:00 UTC] PHP Fatal error:  at',
				'#0 {main}',
				'[20-Oct-2026 12:00:01 UTC] PHP Notice:  after',
			)
		);
		$since = LogReader::since( $entries, gmmktime( 12, 0, 0, 10, 20, 2026 ) );
		$this->assertCount( 2, $since );
		$this->assertSame( array( '[20-Oct-2026 12:00:00 UTC] PHP Fatal error:  at', '#0 {main}' ), $since[0]['lines'] );
	}

	public function test_levels_and_counts(): void {
		$this->assertSame( LogReader::LEVEL_FATAL, LogReader::level( '[x] PHP Parse error:  syntax error' ) );
		$this->assertSame( LogReader::LEVEL_NOTICE, LogReader::level( '[x] PHP Deprecated:  y' ) );
		$this->assertSame( LogReader::LEVEL_DB, LogReader::level( '[x] WordPress database error Table missing' ) );
		$this->assertSame( LogReader::LEVEL_OTHER, LogReader::level( '[x] custom message' ) );
		$counts = LogReader::counts( LogReader::entries( array( '[20-Oct-2026 12:00:00 UTC] PHP Warning: a', '[20-Oct-2026 12:00:00 UTC] PHP Warning: b' ) ) );
		$this->assertSame( 2, $counts[ LogReader::LEVEL_WARN ] );
		$this->assertSame( 0, $counts[ LogReader::LEVEL_FATAL ] );
	}

	public function test_scrub_drops_only_invalid_bytes(): void {
		$this->assertSame( 'bad  bytes kept', LogReader::scrub( "bad \xff\xfe bytes kept" ) );
		$this->assertSame( 'café ✓', LogReader::scrub( 'café ✓' ) );
		$this->assertSame( 'cut ', LogReader::scrub( "cut \xe2\x9c" ) );
	}
}
