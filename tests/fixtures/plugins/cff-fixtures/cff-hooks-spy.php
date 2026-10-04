<?php
/**
 * Plugin Name: CFF Hooks Spy
 * Description: Culprit Finder test fixture. Logs every Culprit Finder hook to the cff_hooks_spy_log option and misbehaves on purpose in the modes set in cff_hooks_spy_mode. Never install on a real site.
 * Version: 1.0.0
 * License: GPLv2 or later
 *
 * @package CulpritFinderFixtures
 */

defined( 'ABSPATH' ) || exit;

function cff_spy_log( $hook, $data ) {
	$log   = get_option( 'cff_hooks_spy_log', array() );
	$log[] = array( 'hook' => $hook, 'data' => $data );
	update_option( 'cff_hooks_spy_log', $log, false );
}

function cff_spy_mode( $mode ) {
	return in_array( $mode, explode( ',', (string) get_option( 'cff_hooks_spy_mode', '' ) ), true );
}

function cff_spy_misbehave() {
	if ( cff_spy_mode( 'wipe' ) ) {
		update_option( 'active_plugins', array() );
	}
	if ( cff_spy_mode( 'throw' ) ) {
		throw new RuntimeException( 'cff spy throws on purpose' );
	}
}

add_action(
	'culprit_finder_session_started',
	function ( $view, $step ) {
		cff_spy_log( 'session_started', array( 'keys' => array_keys( $view ), 'status' => $step['status'], 'question' => $step['question'], 'args' => func_num_args() ) );
		cff_spy_misbehave();
	},
	10,
	2
);

add_action(
	'culprit_finder_step_changed',
	function ( $step, $view, $cause ) {
		cff_spy_log( 'step_changed', array( 'status' => $step['status'], 'question' => $step['question'], 'cause' => $cause, 'keys' => array_keys( $view ) ) );
		cff_spy_misbehave();
	},
	10,
	3
);

add_action(
	'culprit_finder_result_found',
	function ( $record, $view ) {
		cff_spy_log( 'result_found', array( 'type' => $record['result']['type'], 'has_id' => isset( $record['id'] ), 'has_user_id' => isset( $record['user_id'] ), 'keys' => array_keys( $view ) ) );
		cff_spy_misbehave();
	},
	10,
	2
);

add_action(
	'culprit_finder_session_ended',
	function ( $reason, $view ) {
		cff_spy_log( 'session_ended', array( 'reason' => $reason, 'keys' => is_array( $view ) ? array_keys( $view ) : null ) );
		cff_spy_misbehave();
	},
	10,
	2
);

add_filter(
	'culprit_finder_report_sections',
	function ( $sections, $record ) {
		cff_spy_log( 'report_sections', array( 'sections' => array_keys( $sections ), 'type' => $record['result']['type'], 'has_user_id' => isset( $record['user_id'] ) ) );
		cff_spy_misbehave();
		if ( cff_spy_mode( 'section' ) ) {
			$sections['spy'] = 'CFF-SPY-SECTION';
		}
		return $sections;
	},
	10,
	2
);

add_filter(
	'culprit_finder_admin_tabs',
	function ( $tabs ) {
		cff_spy_log( 'admin_tabs', array( 'tabs' => array_keys( $tabs ) ) );
		if ( cff_spy_mode( 'tab' ) ) {
			$tabs['results'] = 'Hijacked';
			$tabs['spy']     = 'Spy tab';
		}
		return $tabs;
	}
);

add_action(
	'culprit_finder_render_tab_spy',
	function ( $view ) {
		cff_spy_log( 'render_tab_spy', array( 'has_view' => is_array( $view ) ) );
		echo '<p>CFF-SPY-TAB</p>';
	}
);

add_filter(
	'culprit_finder_auto_answer',
	function ( $answer, $step, $view ) {
		cff_spy_log( 'auto_answer', array( 'in' => $answer, 'question' => $step['question'], 'keys' => array_keys( $view ) ) );
		if ( cff_spy_mode( 'auto-yes' ) ) {
			return 'yes';
		}
		if ( cff_spy_mode( 'auto-bad' ) ) {
			return true;
		}
		return $answer;
	},
	10,
	3
);
