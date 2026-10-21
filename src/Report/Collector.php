<?php
/**
 * Gathers the facts for the support report.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Report;

use CulpritFinder\Engine\Engine;
use CulpritFinder\Engine\Step;
use CulpritFinder\Session\AddOns;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the `culprit_finder_last_result` record (ADR-0010). No URLs, users, or full plugin list.
 */
final class Collector {

	/**
	 * Result record for a finished session.
	 *
	 * @param array  $session Session.
	 * @param Engine $engine  Engine.
	 * @param Step   $step    Done step.
	 * @return array
	 */
	public static function collect( array $session, Engine $engine, Step $step ) {
		$result   = $step->result();
		$involved = array_merge( $result['culprits'], $result['kept_on'] );
		foreach ( $result['culprits'] as $culprit ) {
			$involved = array_merge( $involved, $engine->closure( array( $culprit ) ) );
		}

		$plugins = array();
		foreach ( array_unique( $involved ) as $basename ) {
			$plugins[ $basename ] = self::plugin_info( $basename, $engine );
		}

		$theme  = wp_get_theme();
		$record = array(
			'v'           => 1,
			'version'     => CULPRIT_FINDER_VERSION,
			'result'      => $result,
			'answers'     => $step->answers_used(),
			'tested'      => count( $engine->suspects() ),
			'finished_at' => time(),
			'user_id'     => (int) $session['user_id'],
			'plugins'     => $plugins,
			'env'         => array(
				'wp'            => get_bloginfo( 'version' ),
				'php'           => PHP_VERSION,
				'theme'         => $theme->exists() ? $theme->get( 'Name' ) : '',
				'theme_version' => $theme->exists() ? $theme->get( 'Version' ) : '',
				'multisite'     => is_multisite(),
			) + self::more_env( $theme ),
		);
		$always = AddOns::of( $session );
		if ( $always ) {
			$record['always_on'] = $always;
		}
		$record['replay'] = self::replay( $session, $engine, $step );
		return $record;
	}

	/**
	 * The engine inputs that replay the search step by step for the report's Steps section
	 * (ADR-0028): the same snapshot, kept-on list, dependencies and answers the session used, plus
	 * the name and version of every suspect. No step list is stored; it is derived on display.
	 *
	 * @param array  $session Session.
	 * @param Engine $engine  Engine.
	 * @param Step   $step    Done step.
	 * @return array{snapshot: string[], self: string, kept: string[], deps: array, answers: bool[], names: array}
	 */
	private static function replay( array $session, Engine $engine, Step $step ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$headers = get_plugins();
		$names   = array();
		foreach ( $engine->suspects() as $basename ) {
			$names[ $basename ] = array(
				'name'    => ! empty( $headers[ $basename ]['Name'] ) ? $headers[ $basename ]['Name'] : $basename,
				'version' => isset( $headers[ $basename ]['Version'] ) ? $headers[ $basename ]['Version'] : '',
			);
		}
		return array(
			'snapshot' => array_values( $session['snapshot'] ),
			'self'     => $session['self'],
			'kept'     => AddOns::kept( $session ),
			'deps'     => $session['deps'],
			'answers'  => array_map( 'boolval', array_slice( $session['answers'], 0, $step->answers_used() ) ),
			'names'    => $names,
		);
	}

	/**
	 * Environment facts added in 0.4.0 (ADR-0031). A fact that isn't known is left out, never
	 * guessed; no file paths.
	 *
	 * @param \WP_Theme $theme Active theme.
	 * @return array
	 */
	private static function more_env( $theme ) {
		$env    = array();
		$parent = $theme->exists() ? $theme->parent() : false;
		if ( $parent ) {
			$env['parent_theme'] = trim( $parent->get( 'Name' ) . ' ' . $parent->get( 'Version' ) );
		}
		$memory = (string) ini_get( 'memory_limit' );
		if ( '' !== $memory ) {
			$env['memory_limit'] = $memory;
		}
		if ( defined( 'WP_MEMORY_LIMIT' ) ) {
			$env['wp_memory_limit'] = (string) WP_MEMORY_LIMIT;
		}
		if ( ! empty( $_SERVER['SERVER_SOFTWARE'] ) ) {
			$env['server'] = sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) );
		}
		$env['object_cache'] = (bool) wp_using_ext_object_cache();
		$env['debug']        = defined( 'WP_DEBUG' ) && WP_DEBUG;
		return $env;
	}

	/**
	 * Public header facts of one plugin.
	 *
	 * @param string $basename Basename.
	 * @param Engine $engine   Engine (for dependencies).
	 * @return array{name: string, version: string, author: string, uri: string, requires: string[]}
	 */
	private static function plugin_info( $basename, Engine $engine ) {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$file = WP_PLUGIN_DIR . '/' . $basename;
		$data = is_readable( $file ) ? get_plugin_data( $file, false, false ) : array();
		return array(
			'name'     => ! empty( $data['Name'] ) ? $data['Name'] : $basename,
			'version'  => isset( $data['Version'] ) ? $data['Version'] : '',
			'author'   => isset( $data['Author'] ) ? wp_strip_all_tags( $data['Author'] ) : '',
			'uri'      => isset( $data['PluginURI'] ) ? $data['PluginURI'] : '',
			'requires' => array_values( array_diff( $engine->closure( array( $basename ) ), array( $basename ) ) ),
		);
	}
}
