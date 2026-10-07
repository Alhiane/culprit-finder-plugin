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
			),
		);
		$always = AddOns::of( $session );
		if ( $always ) {
			$record['always_on'] = $always;
		}
		return $record;
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
