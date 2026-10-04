<?php
/**
 * Renders the support report.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Report;

use CulpritFinder\Engine\Result;

defined( 'ABSPATH' ) || exit;

/**
 * Pure renderer of the plain-text/Markdown report (ADR-0010). English on purpose: it is pasted
 * into public English-language support forums. Never includes URLs, user names, or emails.
 */
final class Builder {

	/**
	 * Report text from a result record (see Collector).
	 *
	 * @param array $record Result record.
	 * @return string
	 */
	public static function build( array $record ) {
		return self::join( self::sections( $record ) );
	}

	/**
	 * The report as ordered sections (`result`, `environment`, `footer`), each a block of lines.
	 * Add-ons change them through the `culprit_finder_report_sections` filter (ADR-0021).
	 *
	 * @param array $record Result record.
	 * @return array<string, string>
	 */
	public static function sections( array $record ) {
		$result  = $record['result'];
		$plugins = isset( $record['plugins'] ) ? $record['plugins'] : array();
		$env     = $record['env'];

		$lines   = array();
		$lines[] = '### Plugin conflict report (Culprit Finder ' . $record['version'] . ')';
		$lines[] = 'Result: ' . self::verdict( $result, $plugins );
		foreach ( $result['culprits'] as $culprit ) {
			$lines[] = '- ' . self::label( $culprit, $plugins );
		}
		$after = self::after_line( $result['type'] );
		if ( '' !== $after ) {
			$lines[] = $after;
		}

		$kept = array();
		foreach ( $result['kept_on'] as $basename ) {
			$kept[] = self::label( $basename, $plugins );
		}
		$environment = array(
			'Environment',
			'- WordPress ' . $env['wp'] . ', PHP ' . $env['php'],
			'- Theme: ' . trim( $env['theme'] . ' ' . $env['theme_version'] ),
			'- Plugins tested: ' . (int) $record['tested'] . ( $kept ? ' (kept on: ' . implode( ', ', $kept ) . ')' : '' ),
			'- Not tested: must-use plugins, drop-ins, theme',
			'- Multisite: ' . ( ! empty( $env['multisite'] ) ? 'yes' : 'no' ),
		);

		$answers = (int) $record['answers'];
		return array(
			'result'      => implode( "\n", $lines ),
			'environment' => implode( "\n", $environment ),
			'footer'      => 'Found in ' . $answers . ' ' . ( 1 === $answers ? 'answer' : 'answers' ) . ' on ' . gmdate( 'Y-m-d', (int) $record['finished_at'] ) . ' (UTC).',
		);
	}

	/**
	 * Join sections into the report text, separated by a blank line.
	 *
	 * @param array<string, string> $sections Sections in order.
	 * @return string
	 */
	public static function join( array $sections ) {
		$blocks = array();
		foreach ( $sections as $block ) {
			if ( is_string( $block ) && '' !== trim( $block ) ) {
				$blocks[] = rtrim( $block, "\n" );
			}
		}
		return implode( "\n\n", $blocks ) . "\n";
	}

	/**
	 * Plain-text version of the report (Markdown heading markers removed).
	 *
	 * @param string $markdown Report from build().
	 * @return string
	 */
	public static function to_plain( $markdown ) {
		return (string) preg_replace( '/^#{1,6}\s+/m', '', $markdown );
	}

	/**
	 * Verdict line per result type.
	 *
	 * @param array $result  Result.
	 * @param array $plugins Plugin facts.
	 * @return string
	 */
	public static function verdict( array $result, array $plugins ) {
		switch ( $result['type'] ) {
			case Result::SINGLE:
				$requires = array();
				$culprit  = $result['culprits'][0];
				if ( ! empty( $plugins[ $culprit ]['requires'] ) ) {
					foreach ( $plugins[ $culprit ]['requires'] as $dep ) {
						$requires[] = self::label( $dep, $plugins );
					}
				}
				return 'Caused by one plugin' . ( $requires ? ' (together with its required plugins ' . implode( ', ', $requires ) . ')' : '' );
			case Result::PAIR:
				return 'Conflict between two plugins';
			case Result::COMPLEX:
				return 'Involves three or more plugins; these two are part of it';
			case Result::NOT_PLUGIN:
				return 'Not caused by the plugins tested (the problem remained with all of them off)';
			case Result::NOTHING_TO_TEST:
				return 'Nothing to test (every active plugin was kept on)';
			default:
				return 'Answers were inconsistent; the problem may be intermittent';
		}
	}

	/**
	 * Explanation line after the culprit list.
	 *
	 * @param string $type Result type.
	 * @return string
	 */
	private static function after_line( $type ) {
		switch ( $type ) {
			case Result::PAIR:
				return 'The problem appears only when both are active.';
			case Result::COMPLEX:
				return 'At least one more plugin is involved; continue testing manually with these two active.';
			case Result::INCONCLUSIVE:
				return 'The plugin above was the last one examined; it may not be the cause.';
			default:
				return '';
		}
	}

	/**
	 * "Name Version" for a basename.
	 *
	 * @param string $basename Basename.
	 * @param array  $plugins  Plugin facts.
	 * @return string
	 */
	public static function label( $basename, array $plugins ) {
		if ( empty( $plugins[ $basename ] ) ) {
			return $basename;
		}
		return trim( $plugins[ $basename ]['name'] . ' ' . $plugins[ $basename ]['version'] );
	}
}
