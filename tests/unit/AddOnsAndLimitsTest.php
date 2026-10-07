<?php
/**
 * Always-on add-on validation and session time limits (ADR-0024).
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Tests;

use CulpritFinder\Report\Builder;
use CulpritFinder\Session\AddOns;
use CulpritFinder\Session\Limits;
use PHPUnit\Framework\TestCase;

final class AddOnsAndLimitsTest extends TestCase {

	const SELF = 'culprit-finder/culprit-finder.php';

	private function headers(): array {
		return array(
			'addon/addon.php'       => array( 'RequiresPlugins' => 'woocommerce, Culprit-Finder' ),
			'intruder/intruder.php' => array( 'RequiresPlugins' => '' ),
			'other/other.php'       => array( 'RequiresPlugins' => 'culprit-finder-extra' ),
			'array/array.php'       => array( 'RequiresPlugins' => array( 'culprit-finder' ) ),
			self::SELF              => array( 'RequiresPlugins' => 'culprit-finder' ),
		);
	}

	public function test_only_active_plugins_that_require_culprit_finder_are_accepted(): void {
		$snapshot  = array( 'intruder/intruder.php', 'addon/addon.php', 'other/other.php', self::SELF, 'array/array.php' );
		$requested = array( 'array/array.php', 'intruder/intruder.php', 'not/in-snapshot.php', 42, null, array( 'x' ), 'other/other.php', self::SELF, 'addon/addon.php' );
		$this->assertSame( array( 'addon/addon.php', 'array/array.php' ), AddOns::accept( $requested, $snapshot, self::SELF, $this->headers() ) );
	}

	public function test_garbage_filter_results_are_ignored(): void {
		$snapshot = array( 'addon/addon.php' );
		foreach ( array( null, false, 'addon/addon.php', 42, new \stdClass() ) as $garbage ) {
			$this->assertSame( array(), AddOns::accept( $garbage, $snapshot, self::SELF, $this->headers() ) );
		}
		$this->assertSame( array(), AddOns::accept( array( 'addon/addon.php' ), $snapshot, self::SELF, array() ), 'No header data, no add-on.' );
	}

	public function test_session_helpers_tolerate_sessions_from_0_1_0(): void {
		$old = array(
			'self'     => self::SELF,
			'snapshot' => array( 'a/a.php', self::SELF ),
			'pinned'   => array( 'a/a.php' ),
		);
		$this->assertSame( array(), AddOns::of( $old ) );
		$this->assertSame( array( 'a/a.php' ), AddOns::kept( $old ) );

		$new = $old + array( 'always_on' => array( 'b/b.php', 'not/in-snapshot.php', self::SELF ) );
		$new['snapshot'][] = 'b/b.php';
		$this->assertSame( array( 'b/b.php' ), AddOns::of( $new ) );
		$this->assertSame( array( 'a/a.php', 'b/b.php' ), AddOns::kept( $new ) );
		$this->assertSame( array(), AddOns::of( array( 'always_on' => 'b/b.php', 'snapshot' => array( 'b/b.php' ) ) ) );
	}

	public function test_max_length_can_be_lowered_but_never_raised(): void {
		$this->assertSame( 10800, Limits::max_length( 10800 ) );
		$this->assertSame( 10800, Limits::max_length( 999999 ) );
		$this->assertSame( 5400, Limits::max_length( 5400 ) );
		$this->assertSame( 5400, Limits::max_length( '5400' ) );
		$this->assertSame( 60, Limits::max_length( 5 ) );
		foreach ( array( 0, -5, null, false, 'abc', array( 1 ) ) as $garbage ) {
			$this->assertSame( 10800, Limits::max_length( $garbage ) );
		}
	}

	public function test_deadline_and_idle_expiry(): void {
		$this->assertSame( 2000, Limits::deadline( array( 'ends_at' => 2000, 'created_at' => 100 ), 10800 ) );
		$this->assertSame( 100 + 10800, Limits::deadline( array( 'created_at' => 100 ), 10800 ), 'Sessions from 0.1.0 count from their start.' );
		$this->assertSame( 10800, Limits::deadline( array(), 10800 ) );
		$this->assertSame( 1000 + 3600, Limits::expires_at( 1000, 3600, 99999 ) );
		$this->assertSame( 2000, Limits::expires_at( 1000, 3600, 2000 ), 'The idle timeout never runs past the maximum length.' );
	}

	public function test_report_lists_add_ons_separately_and_only_when_present(): void {
		$record = array(
			'version'     => '0.2.0',
			'result'      => array(
				'type'     => 'SINGLE',
				'culprits' => array( 'c/c.php' ),
				'kept_on'  => array( 'shop/shop.php', 'addon/addon.php' ),
			),
			'answers'     => 3,
			'tested'      => 5,
			'finished_at' => 1790000000,
			'plugins'     => array(
				'addon/addon.php' => array( 'name' => 'Acme Helper', 'version' => '1.0', 'requires' => array() ),
				'shop/shop.php'   => array( 'name' => 'Acme Shop', 'version' => '2.0', 'requires' => array() ),
			),
			'env'         => array( 'wp' => '7.1', 'php' => '8.2', 'theme' => 'T', 'theme_version' => '1', 'multisite' => false ),
		);
		$this->assertStringContainsString( '- Plugins tested: 5 (kept on: Acme Shop 2.0, Acme Helper 1.0)', Builder::build( $record ) );
		$record['always_on'] = array( 'addon/addon.php' );
		$this->assertStringContainsString( '- Plugins tested: 5 (kept on: Acme Shop 2.0; Culprit Finder add-ons kept on: Acme Helper 1.0)', Builder::build( $record ) );
		$record['result']['kept_on'] = array( 'addon/addon.php' );
		$this->assertStringContainsString( '- Plugins tested: 5 (Culprit Finder add-ons kept on: Acme Helper 1.0)', Builder::build( $record ) );
	}
}
