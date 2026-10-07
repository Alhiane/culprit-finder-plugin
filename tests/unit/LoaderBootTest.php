<?php
/**
 * Regression: including the loader file must boot it (a class_exists() guard never did).
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Tests;

use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class LoaderBootTest extends TestCase {

	public function test_including_the_file_filters_a_valid_session(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', __DIR__ . '/' );
		}
		require __DIR__ . '/Support/wp-stubs.php';
		$token                      = str_repeat( 'ab', 32 );
		$_COOKIE                    = array(
			'wordpress_logged_in_x' => 'admin|1|x',
			'wp-culprit-finder'     => $token,
		);
		$GLOBALS['cf_stub_options'] = array(
			'culprit_finder_session' => array(
				'v'           => 1,
				'token_hash'  => hash( 'sha256', $token ),
				'created_at'  => time(),
				'expires_at'  => time() + 60,
				'self'        => 'culprit-finder/culprit-finder.php',
				'fixed'       => array(),
				'enabled_now' => array(),
			),
		);

		require dirname( __DIR__, 2 ) . '/mu-loader/culprit-finder-loader.php';

		$this->assertTrue( \Culprit_Finder_Loader::is_filtering(), 'boot() must run when the file is included.' );
		$this->assertSame( array( 'culprit-finder/culprit-finder.php' ), \Culprit_Finder_Loader::filter_active_plugins( array( 'a/a.php', 'culprit-finder/culprit-finder.php' ) ) );
	}

	public function test_second_include_is_a_no_op(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', __DIR__ . '/' );
		}
		require __DIR__ . '/Support/wp-stubs.php';
		$_COOKIE = array();
		require dirname( __DIR__, 2 ) . '/mu-loader/culprit-finder-loader.php';
		require dirname( __DIR__, 2 ) . '/mu-loader/culprit-finder-loader.php';
		$this->assertTrue( class_exists( 'Culprit_Finder_Loader', false ) );
	}
}
