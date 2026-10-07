<?php
/**
 * MU loader logic with stubbed WordPress functions (skill culprit-loader).
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Tests;

use PHPUnit\Framework\TestCase;

final class LoaderTest extends TestCase {

	const SELF = 'culprit-finder/culprit-finder.php';

	private $token;

	public static function setUpBeforeClass(): void {
		require_once __DIR__ . '/Support/wp-stubs.php';
		$_COOKIE = array();
		$_GET    = array();
		require_once dirname( __DIR__, 2 ) . '/mu-loader/culprit-finder-loader.php';
	}

	protected function setUp(): void {
		$this->token                = str_repeat( 'ab', 32 );
		$_COOKIE                    = array();
		$_GET                       = array();
		$GLOBALS['cf_stub_calls']   = array();
		$GLOBALS['cf_stub_filters'] = array();
		$GLOBALS['cf_stub_options'] = array(
			'culprit_finder_session' => array(
				'v'             => 1,
				'token_hash'    => hash( 'sha256', $this->token ),
				'recovery_hash' => hash( 'sha256', str_repeat( 'cd', 32 ) ),
				'created_at'    => time() - 60,
				'expires_at'    => time() + 600,
				'ends_at'       => time() + 3600,
				'self'          => self::SELF,
				'fixed'         => array( 'pinned/pinned.php' ),
				'enabled_now'   => array( 'b/b.php' ),
			),
		);
		$GLOBALS['cf_stub_multisite'] = false;
		$this->reset_loader();
	}

	private function reset_loader(): void {
		$ref = new \ReflectionClass( \Culprit_Finder_Loader::class );
		foreach ( array( 'session' => null, 'safe' => false, 'bypass' => false ) as $prop => $value ) {
			$p = $ref->getProperty( $prop );
			$p->setAccessible( true );
			$p->setValue( null, $value );
		}
	}

	private function login(): void {
		$_COOKIE['wordpress_logged_in_abc'] = 'admin|123|x';
		$_COOKIE['wp-culprit-finder']       = $this->token;
	}

	private function filter( array $plugins ) {
		return \Culprit_Finder_Loader::filter_active_plugins( $plugins );
	}

	public function test_no_cookie_means_zero_calls(): void {
		\Culprit_Finder_Loader::boot();
		$this->assertSame( array(), $GLOBALS['cf_stub_calls'], 'AC-14: no function calls or DB reads without the cookie.' );
		$this->assertFalse( \Culprit_Finder_Loader::is_filtering() );
	}

	public function test_valid_session_filters_in_real_order_with_self(): void {
		$this->login();
		\Culprit_Finder_Loader::boot();
		$this->assertTrue( \Culprit_Finder_Loader::is_filtering() );
		$this->assertFalse( \Culprit_Finder_Loader::is_safe_mode() );
		$real = array( 'a/a.php', 'b/b.php', self::SELF, 'pinned/pinned.php' );
		$this->assertSame( array( 'b/b.php', self::SELF ), $this->filter( $real ) );
		$this->assertArrayHasKey( 'pre_update_option_active_plugins', $GLOBALS['cf_stub_filters'] );
		$this->assertTrue( defined( 'DONOTCACHEPAGE' ) );
	}

	public function test_safe_mode_uses_fixed_set(): void {
		$this->login();
		$_GET['culprit_safe'] = '1';
		\Culprit_Finder_Loader::boot();
		$this->assertTrue( \Culprit_Finder_Loader::is_safe_mode() );
		$this->assertSame( array( self::SELF, 'pinned/pinned.php' ), $this->filter( array( 'a/a.php', 'b/b.php', self::SELF, 'pinned/pinned.php' ) ) );
	}

	public function test_write_guard_returns_old_value(): void {
		$this->assertSame( array( 'x' ), \Culprit_Finder_Loader::block_write( array(), array( 'x' ) ) );
	}

	public function test_real_active_plugins_bypasses_filter(): void {
		$this->login();
		\Culprit_Finder_Loader::boot();
		$GLOBALS['cf_stub_options']['active_plugins'] = array( 'a/a.php', 'b/b.php', self::SELF );
		$this->assertSame( array( 'a/a.php', 'b/b.php', self::SELF ), \Culprit_Finder_Loader::real_active_plugins() );
		$this->assertSame( array( 'b/b.php', self::SELF ), $this->filter( array( 'a/a.php', 'b/b.php', self::SELF ) ) );
	}

	/**
	 * @dataProvider rejected_requests
	 */
	public function test_rejected_requests_do_not_filter( callable $arrange ): void {
		$this->login();
		$arrange( $this );
		\Culprit_Finder_Loader::boot();
		$this->assertFalse( \Culprit_Finder_Loader::is_filtering() );
		$this->assertSame( array( 'a/a.php' ), $this->filter( array( 'a/a.php' ) ) );
	}

	public function rejected_requests(): array {
		return array(
			'no login cookie'   => array( function () { unset( $_COOKIE['wordpress_logged_in_abc'] ); } ),
			'short token'       => array( function () { $_COOKIE['wp-culprit-finder'] = 'abc'; } ),
			'non-hex token'     => array( function () { $_COOKIE['wp-culprit-finder'] = str_repeat( 'zz', 32 ); } ),
			'array token'       => array( function () { $_COOKIE['wp-culprit-finder'] = array( 'x' ); } ),
			'wrong token'       => array( function () { $_COOKIE['wp-culprit-finder'] = str_repeat( 'ef', 32 ); } ),
			'expired'           => array( function () { $GLOBALS['cf_stub_options']['culprit_finder_session']['expires_at'] = time() - 1; } ),
			'past max length'   => array( function () { $GLOBALS['cf_stub_options']['culprit_finder_session']['ends_at'] = time() - 1; } ),
			'0.1.0 over 3 h'    => array(
				function () {
					unset( $GLOBALS['cf_stub_options']['culprit_finder_session']['ends_at'] );
					$GLOBALS['cf_stub_options']['culprit_finder_session']['created_at'] = time() - 10801;
				},
			),
			'no start time'     => array(
				function () {
					unset( $GLOBALS['cf_stub_options']['culprit_finder_session']['ends_at'], $GLOBALS['cf_stub_options']['culprit_finder_session']['created_at'] );
				},
			),
			'wrong version'     => array( function () { $GLOBALS['cf_stub_options']['culprit_finder_session']['v'] = 2; } ),
			'malformed session' => array( function () { $GLOBALS['cf_stub_options']['culprit_finder_session'] = 'garbage'; } ),
			'no session'        => array( function () { unset( $GLOBALS['cf_stub_options']['culprit_finder_session'] ); } ),
			'multisite'         => array( function () { $GLOBALS['cf_stub_multisite'] = true; } ),
			'bad recovery key'  => array( function () { $_COOKIE = array(); $_GET['culprit-finder-exit'] = array( 'x' ); } ),
		);
	}

	public function test_session_from_0_1_0_without_ends_at_still_filters(): void {
		unset( $GLOBALS['cf_stub_options']['culprit_finder_session']['ends_at'] );
		$GLOBALS['cf_stub_options']['culprit_finder_session']['created_at'] = time() - 10700;
		$this->login();
		\Culprit_Finder_Loader::boot();
		$this->assertTrue( \Culprit_Finder_Loader::is_filtering(), 'A running 0.1.0 session keeps working until three hours after its start.' );
	}

	public function test_wrong_recovery_key_keeps_session(): void {
		$_GET['culprit-finder-exit'] = str_repeat( '00', 32 );
		\Culprit_Finder_Loader::boot();
		$this->assertArrayHasKey( 'culprit_finder_session', $GLOBALS['cf_stub_options'] );
	}
}
