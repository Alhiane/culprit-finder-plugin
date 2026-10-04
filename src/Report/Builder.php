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

		$lines[] = '';
		$lines[] = 'Environment';
		$lines[] = '- WordPress ' . $env['wp'] . ', PHP ' . $env['php'];
		$lines[] = '- Theme: ' . trim( $env['theme'] . ' ' . $env['theme_version'] );
		$kept    = array();
		foreach ( $result['kept_on'] as $basename ) {
			$kept[] = self::label( $basename, $plugins );
		}
		$lines[] = '- Plugins tested: ' . (int) $record['tested'] . ( $kept ? ' (kept on: ' . implode( ', ', $kept ) . ')' : '' );
		$lines[] = '- Not tested: must-use plugins, drop-ins, theme';
		$lines[] = '- Multisite: ' . ( ! empty( $env['multisite'] ) ? 'yes' : 'no' );
		$lines[] = '';
		$answers = (int) $record['answers'];
		$lines[] = 'Found in ' . $answers . ' ' . ( 1 === $answers ? 'answer' : 'answers' ) . ' on ' . gmdate( 'Y-m-d', (int) $record['finished_at'] ) . ' (UTC).';

		return implode( "\n", $lines ) . "\n";
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
