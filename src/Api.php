<?php
/**
 * Public PHP API for add-ons (wrapped by the functions in src/functions.php).
 *
 * @package CulpritFinder
 */

namespace CulpritFinder;

use CulpritFinder\Admin\Links;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Read the current step and answer it from an add-on's own request (ADR-0024, docs/hooks.md).
 *
 * Everything here works only for the administrator who owns the running session, in the browser
 * that holds the session cookie, and never in WP-CLI. Callers check their own nonce first.
 */
final class Api {

	/**
	 * Plugin.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * The current step (same shape as in the hooks), or null when this browser owns no session.
	 *
	 * @return array|null
	 */
	public function current_step() {
		$session = $this->plugin->verified_session();
		return null === $session ? null : $this->plugin->manager()->step( $session )->to_array();
	}

	/**
	 * Record an answer to question number `$question`, exactly like the Yes / No buttons.
	 *
	 * @param mixed $answer   'yes' (the problem is still there) or 'no'.
	 * @param mixed $question Number of the question the answer is for (the step's `question`).
	 * @return array|WP_Error The new step, or an error and no change.
	 */
	public function submit_answer( $answer, $question ) {
		$session = $this->plugin->verified_session();
		if ( null === $session ) {
			return new WP_Error( 'culprit_finder_not_owner', __( 'There is no troubleshooting session for you in this browser.', 'culprit-finder' ), array( 'status' => 403 ) );
		}
		if ( 'yes' !== $answer && 'no' !== $answer ) {
			return new WP_Error( 'culprit_finder_invalid_answer', __( 'The answer must be yes or no.', 'culprit-finder' ), array( 'status' => 400 ) );
		}
		if ( ! is_int( $question ) && ! ( is_string( $question ) && ctype_digit( $question ) ) ) {
			return new WP_Error( 'culprit_finder_stale_question', __( 'That question has already been answered.', 'culprit-finder' ), array( 'status' => 409 ) );
		}
		$step = $this->plugin->manager()->answer( 'yes' === $answer, (int) $question );
		if ( $step instanceof WP_Error ) {
			$step->add_data( array( 'status' => 409 ) );
			return $step;
		}
		$this->plugin->refresh_cookie();
		return $step->to_array();
	}

	/**
	 * Nonced link that answers or undoes like the built-in buttons, for the session owner only.
	 *
	 * @param string $action 'yes', 'no' or 'undo'.
	 * @return string URL, or '' when this browser owns no session or the action is unknown.
	 */
	public function action_url( $action ) {
		if ( null === $this->plugin->owned_session() || ! in_array( $action, array( 'yes', 'no', 'undo' ), true ) ) {
			return '';
		}
		if ( 'undo' === $action ) {
			return Links::action( 'undo', array(), Links::control_panel() );
		}
		return Links::action( 'answer', array( 'answer' => $action ), Links::control_panel() );
	}
}
