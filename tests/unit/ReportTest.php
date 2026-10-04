<?php
/**
 * Support report format and privacy (ADR-0010, AC-11).
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Tests;

use CulpritFinder\Engine\Result;
use CulpritFinder\Report\Builder;
use PHPUnit\Framework\TestCase;

final class ReportTest extends TestCase {

	private function record( array $result, array $extra_plugins = array() ): array {
		return array(
			'v'           => 1,
			'version'     => '0.1.0',
			'result'      => $result,
			'answers'     => 11,
			'tested'      => 27,
			'finished_at' => gmmktime( 12, 0, 0, 10, 4, 2026 ),
			'user_id'     => 1,
			'plugins'     => $extra_plugins + array(
				'example-forms/forms.php' => array( 'name' => 'Example Forms', 'version' => '3.2.1', 'author' => 'Forms Inc', 'uri' => 'https://forms.example', 'requires' => array() ),
				'example-seo/seo.php'     => array( 'name' => 'Example SEO', 'version' => '21.4', 'author' => 'SEO Ltd', 'uri' => '', 'requires' => array() ),
				'woocommerce/woocommerce.php' => array( 'name' => 'WooCommerce', 'version' => '9.8.1', 'author' => 'Automattic', 'uri' => '', 'requires' => array() ),
			),
			'env'         => array( 'wp' => '7.0.1', 'php' => '8.2.20', 'theme' => 'Twenty Twenty-Five', 'theme_version' => '1.2', 'multisite' => false ),
		);
	}

	public function test_pair_report_matches_adr_example(): void {
		$text = Builder::build( $this->record( array( 'type' => Result::PAIR, 'culprits' => array( 'example-forms/forms.php', 'example-seo/seo.php' ), 'kept_on' => array( 'woocommerce/woocommerce.php' ) ) ) );
		$expected = "### Plugin conflict report (Culprit Finder 0.1.0)\n"
			. "Result: Conflict between two plugins\n"
			. "- Example Forms 3.2.1\n"
			. "- Example SEO 21.4\n"
			. "The problem appears only when both are active.\n"
			. "\n"
			. "Environment\n"
			. "- WordPress 7.0.1, PHP 8.2.20\n"
			. "- Theme: Twenty Twenty-Five 1.2\n"
			. "- Plugins tested: 27 (kept on: WooCommerce 9.8.1)\n"
			. "- Not tested: must-use plugins, drop-ins, theme\n"
			. "- Multisite: no\n"
			. "\n"
			. "Found in 11 answers on 2026-10-04 (UTC).\n";
		$this->assertSame( $expected, $text );
	}

	public function test_verdict_lines_per_type(): void {
		$cases = array(
			Result::SINGLE       => 'Caused by one plugin',
			Result::COMPLEX      => 'Involves three or more plugins; these two are part of it',
			Result::NOT_PLUGIN   => 'Not caused by the plugins tested (the problem remained with all of them off)',
			Result::INCONCLUSIVE => 'Answers were inconsistent; the problem may be intermittent',
		);
		foreach ( $cases as $type => $line ) {
			$this->assertSame( $line, Builder::verdict( array( 'type' => $type, 'culprits' => array( 'example-seo/seo.php' ), 'kept_on' => array() ), array() ) );
		}
	}

	public function test_single_with_dependencies(): void {
		$record                                         = $this->record( array( 'type' => Result::SINGLE, 'culprits' => array( 'example-seo/seo.php' ), 'kept_on' => array() ) );
		$record['plugins']['example-seo/seo.php']['requires'] = array( 'example-forms/forms.php' );
		$this->assertStringContainsString( "Result: Caused by one plugin (together with its required plugins Example Forms 3.2.1)\n- Example SEO 21.4\n", Builder::build( $record ) );
		$this->assertStringContainsString( "- Plugins tested: 27\n", Builder::build( $record ) );
	}

	public function test_report_contains_required_facts_and_no_private_data(): void {
		$record = $this->record( array( 'type' => Result::SINGLE, 'culprits' => array( 'example-forms/forms.php' ), 'kept_on' => array( 'woocommerce/woocommerce.php' ) ) );
		$text   = Builder::build( $record );
		foreach ( array( '7.0.1', '8.2.20', 'Twenty Twenty-Five', 'Example Forms 3.2.1', 'WooCommerce 9.8.1', '11 answers', 'Caused by one plugin' ) as $needle ) {
			$this->assertStringContainsString( $needle, $text );
		}
		// AC-11: no URLs (not even plugin URIs), emails, user ids/names, or authors.
		$this->assertDoesNotMatchRegularExpression( '#https?://|www\.|@#', $text );
		$this->assertStringNotContainsString( 'Forms Inc', $text );
		$this->assertStringNotContainsString( 'user', strtolower( $text ) );
		$this->assertStringNotContainsString( 'example-forms/forms.php', $text, 'No raw plugin paths.' );
	}

	public function test_singular_answer(): void {
		$record            = $this->record( array( 'type' => Result::NOT_PLUGIN, 'culprits' => array(), 'kept_on' => array() ) );
		$record['answers'] = 1;
		$this->assertStringContainsString( 'Found in 1 answer on', Builder::build( $record ) );
	}
}
