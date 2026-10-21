<?php
/**
 * Renders the support report.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Report;

use CulpritFinder\Engine\Engine;
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
	 * The report as ordered sections (`result`, `environment`, `steps` when the record can be
	 * replayed, `footer`), each a block of lines.
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

		$addons = self::addons( $record );
		$kept   = array();
		$extra  = array();
		foreach ( $result['kept_on'] as $basename ) {
			if ( ! in_array( $basename, $addons, true ) ) {
				$kept[] = self::label( $basename, $plugins );
			}
		}
		if ( $kept ) {
			$extra[] = 'kept on: ' . implode( ', ', $kept );
		}
		if ( $addons ) {
			$labels = array();
			foreach ( $addons as $basename ) {
				$labels[] = self::label( $basename, $plugins );
			}
			$extra[] = 'Culprit Finder add-ons kept on: ' . implode( ', ', $labels );
		}
		$environment = array(
			'Environment',
			'- WordPress ' . $env['wp'] . ', PHP ' . $env['php'],
			'- Theme: ' . trim( $env['theme'] . ' ' . $env['theme_version'] ),
			'- Plugins tested: ' . (int) $record['tested'] . ( $extra ? ' (' . implode( '; ', $extra ) . ')' : '' ),
			'- Not tested: must-use plugins, drop-ins, theme',
			'- Multisite: ' . ( ! empty( $env['multisite'] ) ? 'yes' : 'no' ),
		);
		$environment = array_merge( $environment, self::more_env( $env ) );

		$answers  = (int) $record['answers'];
		$sections = array(
			'result'      => implode( "\n", $lines ),
			'environment' => implode( "\n", $environment ),
		);
		$steps    = self::steps( $record );
		if ( $steps ) {
			$sections['steps'] = self::steps_text( $steps, $record );
		}
		$sections['footer'] = 'Found in ' . $answers . ' ' . ( 1 === $answers ? 'answer' : 'answers' ) . ' on ' . gmdate( 'Y-m-d', (int) $record['finished_at'] ) . ' (UTC).';
		return $sections;
	}

	/**
	 * Environment lines added in 0.4.0, only for facts the record has (ADR-0031): reports saved
	 * before then stay as they were.
	 *
	 * @param array $env Record environment.
	 * @return string[]
	 */
	public static function more_env( array $env ) {
		$lines = array();
		if ( ! empty( $env['parent_theme'] ) ) {
			$lines[] = '- Parent theme: ' . $env['parent_theme'];
		}
		if ( ! empty( $env['memory_limit'] ) ) {
			$lines[] = '- PHP memory limit: ' . $env['memory_limit'] . ( ! empty( $env['wp_memory_limit'] ) ? ' (WordPress: ' . $env['wp_memory_limit'] . ')' : '' );
		}
		if ( ! empty( $env['server'] ) ) {
			$lines[] = '- Server: ' . $env['server'];
		}
		if ( isset( $env['object_cache'] ) ) {
			$lines[] = '- Persistent object cache: ' . ( $env['object_cache'] ? 'yes' : 'no' );
		}
		if ( isset( $env['debug'] ) ) {
			$lines[] = '- WP_DEBUG: ' . ( $env['debug'] ? 'on' : 'off' );
		}
		return $lines;
	}

	/**
	 * Every answered step, rebuilt by replaying the stored answers through the engine (ADR-0028).
	 *
	 * Empty when the record has no replay inputs (results saved before 0.3.1), when they are
	 * malformed, or when the replay does not end in exactly the stored result: nothing is guessed.
	 *
	 * @param array $record Result record.
	 * @return array<int, array{question: int, phase: string, off: string[], answer: bool}>
	 */
	public static function steps( array $record ) {
		$replay = isset( $record['replay'] ) && is_array( $record['replay'] ) ? $record['replay'] : array();
		foreach ( array( 'snapshot', 'kept', 'deps', 'answers' ) as $key ) {
			if ( ! isset( $replay[ $key ] ) || ! is_array( $replay[ $key ] ) ) {
				return array();
			}
		}
		if ( ! isset( $replay['self'] ) || ! is_string( $replay['self'] ) || count( $replay['answers'] ) !== (int) $record['answers'] ) {
			return array();
		}
		$answers = array_values( $replay['answers'] );
		try {
			$engine = new Engine( $replay['snapshot'], $replay['self'], $replay['kept'], $replay['deps'] );
			$steps  = array();
			foreach ( $answers as $index => $answer ) {
				$step = $engine->step( array_slice( $answers, 0, $index ) );
				if ( $step->is_done() || $step->question() !== $index + 1 ) {
					return array();
				}
				$steps[] = array(
					'question' => $index + 1,
					'phase'    => $step->phase(),
					'off'      => $step->disabled(),
					'answer'   => (bool) $answer,
				);
			}
			$final = $engine->step( $answers );
		} catch ( \InvalidArgumentException $e ) {
			return array();
		}
		$result = $final->result();
		if ( ! $final->is_done() || $final->answers_used() !== count( $answers ) || $result['type'] !== $record['result']['type'] || $result['culprits'] !== $record['result']['culprits'] ) {
			return array();
		}
		return $steps;
	}

	/**
	 * Plain words for an engine phase.
	 *
	 * @param string $phase Engine phase.
	 * @return string
	 */
	public static function phase_label( $phase ) {
		switch ( $phase ) {
			case Engine::PHASE_BASELINE:
				return 'Baseline';
			case Engine::PHASE_FIND_FIRST:
				return 'Find first';
			case Engine::PHASE_FIND_PARTNER:
				return 'Pair search';
			default:
				return 'Confirm';
		}
	}

	/**
	 * "Name Version" of a plugin in a step: the replay names first, then the result's plugin facts.
	 *
	 * @param string $basename Basename.
	 * @param array  $record   Result record.
	 * @return string
	 */
	public static function step_label( $basename, array $record ) {
		if ( ! empty( $record['replay']['names'][ $basename ]['name'] ) ) {
			$info = $record['replay']['names'][ $basename ];
			return trim( $info['name'] . ' ' . ( isset( $info['version'] ) ? $info['version'] : '' ) );
		}
		return self::label( $basename, isset( $record['plugins'] ) ? $record['plugins'] : array() );
	}

	/**
	 * The Steps section text.
	 *
	 * @param array $steps  From steps().
	 * @param array $record Result record.
	 * @return string
	 */
	private static function steps_text( array $steps, array $record ) {
		$lines = array( 'Steps (each answer, replayed in order)' );
		foreach ( $steps as $step ) {
			$off = array();
			foreach ( $step['off'] as $basename ) {
				$off[] = self::step_label( $basename, $record );
			}
			$count   = count( $off );
			$lines[] = $step['question'] . '. ' . self::phase_label( $step['phase'] ) . '. Off: '
				. ( $off ? implode( ', ', $off ) . ' (' . $count . ' ' . ( 1 === $count ? 'plugin' : 'plugins' ) . ')' : 'none, all plugins on' )
				. '. Answer: ' . ( $step['answer'] ? 'problem still there' : 'problem gone' );
		}
		return implode( "\n", $lines );
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
	 * Add-ons that were kept on automatically (`always_on` in records from 0.2.0 on).
	 *
	 * @param array $record Result record.
	 * @return string[]
	 */
	public static function addons( array $record ) {
		return isset( $record['always_on'] ) && is_array( $record['always_on'] ) ? array_values( array_filter( $record['always_on'], 'is_string' ) ) : array();
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
