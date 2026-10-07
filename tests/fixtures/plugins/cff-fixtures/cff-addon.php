<?php
/**
 * Plugin Name: CFF Add-on
 * Description: Culprit Finder test fixture. A well-behaved add-on: declares the dependency, asks to stay on through culprit_finder_always_on, and uses every add-on hook (modes in the cff_addon_mode option). Never install on a real site.
 * Version: 1.0.0
 * Requires Plugins: culprit-finder
 * License: GPLv2 or later
 *
 * @package CulpritFinderFixtures
 */

defined( 'ABSPATH' ) || exit;

function cff_addon_mode( $mode ) {
	return in_array( $mode, explode( ',', (string) get_option( 'cff_addon_mode', '' ) ), true );
}

function cff_addon_log( $hook, $data ) {
	$log   = get_option( 'cff_addon_log', array() );
	$log[] = array( 'hook' => $hook, 'data' => $data );
	update_option( 'cff_addon_log', $log, false );
}

add_action( 'wp_head', function () { echo "<!-- CFF-ADDON -->\n"; } );

add_action(
	'admin_footer',
	function () {
		if ( current_user_can( 'activate_plugins' ) ) {
			echo '<!-- CFF-ADDON-ADMIN CFF-REST-NONCE:' . esc_html( wp_create_nonce( 'wp_rest' ) ) . " -->\n";
		}
	}
);

add_filter(
	'culprit_finder_always_on',
	function ( $basenames, $snapshot ) {
		cff_addon_log( 'always_on', array( 'snapshot' => $snapshot ) );
		if ( cff_addon_mode( 'throw-always' ) ) {
			throw new RuntimeException( 'cff add-on throws on purpose' );
		}
		$basenames[] = 'cff-fixtures/cff-addon.php';
		$basenames[] = 'cff-fixtures/cff-noise-01.php';
		$basenames[] = 'not/in-snapshot.php';
		$basenames[] = 42;
		return $basenames;
	},
	10,
	2
);

add_action(
	'culprit_finder_setup_fields',
	function ( $values ) {
		$note = isset( $values['cff']['note'] ) ? $values['cff']['note'] : '';
		echo '<fieldset class="cf-field"><legend>CFF-ADDON-SETUP</legend>';
		echo '<input type="text" name="culprit_finder_addon[cff][note]" value="' . esc_attr( $note ) . '">';
		echo '<span>CFF-ADDON-KEPT:' . esc_html( $note ) . '</span></fieldset>';
	}
);

add_filter(
	'culprit_finder_problem_url_required',
	function ( $required, $input ) {
		cff_addon_log( 'problem_url_required', array( 'input' => $input ) );
		return cff_addon_mode( 'require-url' ) ? true : $required;
	},
	10,
	2
);

add_filter(
	'culprit_finder_before_start',
	function ( $ok, $input ) {
		cff_addon_log( 'before_start', array( 'input' => $input ) );
		if ( cff_addon_mode( 'block' ) ) {
			return new WP_Error( 'cff_blocked', 'CFF-ADDON-BLOCKED: <b>the add-on said no</b>' );
		}
		return $ok;
	},
	10,
	2
);

add_action(
	'culprit_finder_step_panel',
	function ( $step, $session ) {
		if ( ! cff_addon_mode( 'panel' ) ) {
			return;
		}
		echo '<div class="cff-panel">CFF-ADDON-PANEL q=' . (int) $step['question'] . ' always=' . esc_html( implode( ',', $session['always_on'] ) );
		echo ' <a href="' . esc_url( culprit_finder_action_url( 'yes' ) ) . '">CFF-YES</a></div>';
	},
	10,
	2
);

add_filter(
	'culprit_finder_admin_bar_label',
	function ( $label, $step ) {
		return cff_addon_mode( 'label' ) ? 'CFF-ADDON-LABEL step ' . (int) $step['question'] : $label;
	},
	10,
	2
);

add_action(
	'culprit_finder_result_actions',
	function ( $result, $session ) {
		echo '<a class="button" href="#">CFF-ADDON-ACTION ' . ( is_array( $session ) ? 'with-session' : 'no-session' ) . ' ' . ( isset( $result['user_id'] ) ? 'LEAK' : 'clean' ) . '</a>';
	},
	10,
	2
);

add_filter(
	'culprit_finder_max_session_length',
	function ( $seconds ) {
		if ( cff_addon_mode( 'max-high' ) ) {
			return 999999;
		}
		if ( cff_addon_mode( 'max-low' ) ) {
			return 120;
		}
		if ( cff_addon_mode( 'max-tiny' ) ) {
			return 5;
		}
		return $seconds;
	}
);

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'cff-addon/v1',
			'/answer',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => function ( WP_REST_Request $request ) {
					$result = culprit_finder_submit_answer( $request->get_param( 'answer' ), $request->get_param( 'question' ) );
					return is_wp_error( $result ) ? $result : array( 'step' => $result );
				},
			)
		);
		register_rest_route(
			'cff-addon/v1',
			'/step',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function () {
					return array( 'step' => culprit_finder_current_step() );
				},
			)
		);
	}
);
