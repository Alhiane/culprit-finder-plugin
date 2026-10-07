<?php
/**
 * Public functions for add-ons (docs/hooks.md, ADR-0024).
 *
 * @package CulpritFinder
 */

defined( 'ABSPATH' ) || exit;

/**
 * The add-on API, or null before Culprit Finder is loaded.
 *
 * @return CulpritFinder\Api|null
 */
function culprit_finder_api() {
	$plugin = isset( $GLOBALS['culprit_finder_plugin'] ) ? $GLOBALS['culprit_finder_plugin'] : null;
	return $plugin instanceof CulpritFinder\Plugin ? $plugin->api() : null;
}

/**
 * The current step when the current user owns the running session in this browser, else null.
 *
 * @return array|null
 */
function culprit_finder_current_step() {
	$api = culprit_finder_api();
	return null === $api ? null : $api->current_step();
}

/**
 * Answer question number `$question` with 'yes' (the problem is still there) or 'no'.
 *
 * @param string $answer   'yes' or 'no'.
 * @param int    $question The `question` of the step being answered.
 * @return array|WP_Error The new step, or an error and no change.
 */
function culprit_finder_submit_answer( $answer, $question ) {
	$api = culprit_finder_api();
	if ( null === $api ) {
		return new WP_Error( 'culprit_finder_not_owner', __( 'There is no troubleshooting session for you in this browser.', 'culprit-finder' ) );
	}
	return $api->submit_answer( $answer, $question );
}

/**
 * Link that answers ('yes', 'no') or undoes ('undo') like the built-in buttons, or ''.
 *
 * @param string $action 'yes', 'no' or 'undo'.
 * @return string
 */
function culprit_finder_action_url( $action ) {
	$api = culprit_finder_api();
	return null === $api ? '' : $api->action_url( $action );
}
