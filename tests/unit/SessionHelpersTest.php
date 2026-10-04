<?php
/**
 * Pure session helpers: start guard (AC-13), tokens, dependency map.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Tests;

use CulpritFinder\Session\Dependencies;
use CulpritFinder\Session\StartGuard;
use CulpritFinder\Session\Token;
use PHPUnit\Framework\TestCase;

final class SessionHelpersTest extends TestCase {

	public function test_multisite_refuses_to_start(): void {
		$this->assertSame( array( StartGuard::MULTISITE ), StartGuard::errors( true, true, true ), 'AC-13' );
		$this->assertSame( array( StartGuard::MULTISITE ), StartGuard::errors( true, false, false ) );
	}

	public function test_loader_and_self_preconditions(): void {
		$this->assertSame( array(), StartGuard::errors( false, true, true ) );
		$this->assertSame( array( StartGuard::LOADER_MISSING ), StartGuard::errors( false, false, true ) );
		$this->assertSame( array( StartGuard::LOADER_MISSING, StartGuard::SELF_INACTIVE ), StartGuard::errors( false, false, false ) );
	}

	public function test_tokens(): void {
		$token = Token::generate();
		$this->assertTrue( Token::is_valid_format( $token ) );
		$this->assertNotSame( $token, Token::generate() );
		$this->assertTrue( Token::matches( $token, Token::hash( $token ) ) );
		$this->assertFalse( Token::matches( Token::generate(), Token::hash( $token ) ) );
		$this->assertFalse( Token::matches( 'short', Token::hash( 'short' ) ) );
		$this->assertFalse( Token::matches( $token, null ) );
	}

	public function test_dependency_map_from_headers(): void {
		$snapshot = array( 'cff-dep-child/cff-dep-child.php', 'cff-dep-parent/cff-dep-parent.php', 'hello.php', 'x/x.php' );
		$headers  = array(
			'cff-dep-child/cff-dep-child.php' => array( 'RequiresPlugins' => 'cff-dep-parent, missing-plugin' ),
			'x/x.php'                         => array( 'RequiresPlugins' => 'Hello' ),
			'hello.php'                       => array( 'RequiresPlugins' => '' ),
		);
		$this->assertSame(
			array(
				'cff-dep-child/cff-dep-child.php' => array( 'cff-dep-parent/cff-dep-parent.php' ),
				'x/x.php'                         => array( 'hello.php' ),
			),
			Dependencies::map( $snapshot, $headers )
		);
		$this->assertSame( 'hello', Dependencies::slug( 'hello.php' ) );
	}
}
