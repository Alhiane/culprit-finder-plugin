<?php
/**
 * Pro discovery visibility and copy rules (ADR-0025).
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Tests;

use CulpritFinder\Admin\ProDiscovery;
use PHPUnit\Framework\TestCase;

final class ProDiscoveryTest extends TestCase {

	private function source(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/Admin/ProDiscovery.php' );
	}

	public function test_tips_show_only_without_pro_unhidden_for_managers_on_single_sites(): void {
		$this->assertTrue( ProDiscovery::visible( false, false, true, false ) );
		foreach ( array( array( true, false, true, false ), array( false, true, true, false ), array( false, false, false, false ), array( false, false, true, true ) ) as $case ) {
			$this->assertFalse( ProDiscovery::visible( ...$case ), 'hidden for ' . json_encode( $case ) );
		}
	}

	public function test_pro_is_detected_by_its_basename_or_its_constant(): void {
		$this->assertFalse( ProDiscovery::pro_present_in( array( 'culprit-finder/culprit-finder.php', 'culprit-finder-pro-extra/culprit-finder-pro.php' ), false ) );
		$this->assertTrue( ProDiscovery::pro_present_in( array( 'culprit-finder/culprit-finder.php', 'culprit-finder-pro/culprit-finder-pro.php' ), false ) );
		$this->assertTrue( ProDiscovery::pro_present_in( array(), true ) );
		$this->assertSame( 'culprit-finder-pro/culprit-finder-pro.php', ProDiscovery::PRO_BASENAME );
		$this->assertSame( 'pro', ProDiscovery::TAB );
	}

	public function test_discovery_copy_has_no_exclamation_marks_or_urgency(): void {
		preg_match_all( "/__\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", $this->source(), $m );
		$this->assertNotEmpty( $m[1] );
		foreach ( $m[1] as $text ) {
			$this->assertStringNotContainsString( '!', $text, $text );
			$this->assertDoesNotMatchRegularExpression( '/\b(hurry|now|today|limited|last chance|only \d|countdown|upgrade|premium|coming soon)\b/i', $text, $text );
		}
	}

	public function test_the_only_url_is_the_pro_page_and_every_link_has_a_placement(): void {
		preg_match_all( '#https?://[^\'"\s]+#', $this->source(), $m );
		$this->assertSame( array( 'https://getculpritfinder.com/pro/' ), array_values( array_unique( $m[0] ) ) );
		preg_match_all( '/self::url\(\s*([^)]*)\)/', $this->source(), $calls );
		foreach ( $calls[1] as $arg ) {
			$this->assertMatchesRegularExpression( '/^(self::PLACEMENT_[A-Z_]+|\$placement)\s*$/', $arg );
		}
		$this->assertStringContainsString( "'utm_source' => 'plugin'", $this->source() );
		$this->assertStringContainsString( "'utm_medium' => \$placement", $this->source() );
	}
}

