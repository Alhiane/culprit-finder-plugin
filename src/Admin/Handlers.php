<?php
/**
 * Handlers for admin-post.php: start, answer, undo, exit.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Admin;

use CulpritFinder\Hooks;
use CulpritFinder\Plugin;
use CulpritFinder\Report\Builder;
use CulpritFinder\Report\Report;
use CulpritFinder\Session\Token;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Every state change goes through here (ADR-0006, skill wp-plugin-security).
 */
final class Handlers {

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
	 * Hook the admin-post actions.
	 */
	public function register() {
		foreach ( array( 'start', 'answer', 'undo', 'exit', 'download', 'delete_result', 'clear_results' ) as $action ) {
			add_action( 'admin_post_culprit_finder_' . $action, array( $this, 'handle_' . $action ) );
		}
	}

	/**
	 * Start a session. Refused unless the user confirmed saving the safety links (ADR-0018);
	 * pinned basenames are validated against the snapshot in Manager::start(). Add-ons can require
	 * the problem page address and refuse the start (`culprit_finder_problem_url_required`,
	 * `culprit_finder_before_start`); the setup screen then shows why and keeps the input.
	 */
	public function handle_start() {
		$this->guard( 'start' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce checked in guard().
		$pins = array();
		if ( isset( $_POST['culprit_finder_pin'] ) && is_array( $_POST['culprit_finder_pin'] ) ) {
			$pins = array_map( 'sanitize_text_field', wp_unslash( $_POST['culprit_finder_pin'] ) );
		}
		$key     = isset( $_POST['culprit_finder_recovery'] ) ? sanitize_text_field( wp_unslash( $_POST['culprit_finder_recovery'] ) ) : '';
		$typed   = isset( $_POST['culprit_finder_problem_url'] ) ? sanitize_text_field( wp_unslash( $_POST['culprit_finder_problem_url'] ) ) : '';
		$problem = Links::problem_url( $typed );
		$saved   = ! empty( $_POST['culprit_finder_saved'] );
		$addon   = isset( $_POST['culprit_finder_addon'] ) ? self::addon_input( wp_unslash( $_POST['culprit_finder_addon'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized recursively by addon_input().
		// phpcs:enable

		if ( ! $saved ) {
			$this->redirect( Links::tools( array( 'culprit_notice' => 'unsaved' ) ) );
		}

		$input   = array(
			'problem_url' => $problem,
			'pinned'      => array_values( $pins ),
			'addon'       => $addon,
		);
		$refusal = $this->start_refusal( $input );
		if ( '' !== $refusal ) {
			$this->plugin->keep_start_input(
				array(
					'message'     => $refusal,
					'problem_url' => $typed,
					'pinned'      => $input['pinned'],
					'recovery'    => Token::is_valid_format( $key ) ? strtolower( $key ) : '',
					'addon'       => $addon,
				)
			);
			$this->redirect( Links::tools() );
		}

		$start = $this->plugin->manager()->start( get_current_user_id(), $pins, Token::is_valid_format( $key ) ? $key : null, $problem );
		if ( $start instanceof WP_Error ) {
			$this->die_with( $start );
		}
		$this->plugin->cookie()->set( $start['token'], $start['session']['expires_at'] );
		$this->redirect( Links::tools( array( 'culprit_notice' => 'started' ) ) );
	}

	/**
	 * Answer the current question, then return to the page the user came from.
	 */
	public function handle_answer() {
		$this->guard( 'answer' );
		$this->require_owner();
		$answer = isset( $_GET['answer'] ) ? sanitize_key( wp_unslash( $_GET['answer'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked in guard().
		if ( ! in_array( $answer, array( 'yes', 'no' ), true ) ) {
			wp_die( esc_html__( 'Invalid answer.', 'culprit-finder' ), '', array( 'response' => 400 ) );
		}
		$step = $this->plugin->manager()->answer( 'yes' === $answer );
		$this->after_change( $step );
	}

	/**
	 * Undo the last answer.
	 */
	public function handle_undo() {
		$this->guard( 'undo' );
		$this->require_owner();
		$this->after_change( $this->plugin->manager()->undo() );
	}

	/**
	 * End the session.
	 */
	public function handle_exit() {
		$this->guard( 'exit' );
		$this->plugin->manager()->end();
		$this->plugin->cookie()->expire();
		$this->redirect( Links::tools( array( 'culprit_notice' => 'exited' ) ) );
	}

	/**
	 * Send a saved report as a .md or .txt file.
	 */
	public function handle_download() {
		$this->guard( 'download' );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- nonce checked in guard().
		$id     = isset( $_GET['result'] ) ? sanitize_key( wp_unslash( $_GET['result'] ) ) : '';
		$format = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : 'md';
		// phpcs:enable
		$record = $this->plugin->store()->result( $id );
		if ( null === $record || ! in_array( $format, array( 'md', 'txt' ), true ) ) {
			wp_die( esc_html__( 'That result no longer exists.', 'culprit-finder' ), '', array( 'response' => 404 ) );
		}
		$report   = Report::text( $record );
		$body     = 'txt' === $format ? Builder::to_plain( $report ) : $report;
		$filename = 'culprit-finder-report-' . gmdate( 'Y-m-d-His', (int) $record['finished_at'] ) . '.' . $format;
		nocache_headers();
		header( 'Content-Type: ' . ( 'txt' === $format ? 'text/plain' : 'text/markdown' ) . '; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text file download, not HTML.
		exit;
	}

	/**
	 * Delete one saved result.
	 */
	public function handle_delete_result() {
		$this->guard( 'delete_result' );
		$id = isset( $_GET['result'] ) ? sanitize_key( wp_unslash( $_GET['result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked in guard().
		$this->plugin->store()->delete_result( $id );
		$this->redirect(
			Links::tools(
				array(
					'tab'            => Page::TAB_RESULTS,
					'culprit_notice' => 'deleted',
				)
			)
		);
	}

	/**
	 * Delete all saved results.
	 */
	public function handle_clear_results() {
		$this->guard( 'clear_results' );
		$this->plugin->store()->clear_results();
		$this->redirect(
			Links::tools(
				array(
					'tab'            => Page::TAB_RESULTS,
					'culprit_notice' => 'cleared',
				)
			)
		);
	}

	/**
	 * Refresh the cookie and redirect: to the saved result when done, else back where the user was.
	 *
	 * @param mixed $step Step or WP_Error.
	 */
	private function after_change( $step ) {
		if ( $step instanceof WP_Error ) {
			$this->die_with( $step );
		}
		$this->plugin->refresh_cookie();
		if ( $step->is_done() ) {
			$last = $this->plugin->store()->last_result();
			$this->redirect( is_array( $last ) && isset( $last['id'] ) ? Links::result( $last['id'] ) : Links::tools() );
		}
		$this->redirect( $this->redirect_target() );
	}

	/**
	 * Why an add-on refuses this start, or '' to go ahead.
	 *
	 * @param array $input Start input (problem_url, pinned, addon).
	 * @return string Plain-text message.
	 */
	private function start_refusal( array $input ) {
		$required = Hooks::filter( 'culprit_finder_problem_url_required', false, $input );
		if ( true === $required && '' === $input['problem_url'] ) {
			return __( 'Please paste the address of the page where you see the problem. It must be a page of this site.', 'culprit-finder' );
		}
		$check = Hooks::filter( 'culprit_finder_before_start', true, $input );
		if ( $check instanceof WP_Error ) {
			$message = $check->get_error_message();
			return is_string( $message ) && '' !== trim( $message ) ? wp_strip_all_tags( $message ) : __( 'Troubleshooting didn’t start. Check the options above and try again.', 'culprit-finder' );
		}
		return '';
	}

	/**
	 * Sanitize add-on fields (`culprit_finder_addon[...]`): keys with sanitize_key(), values with
	 * sanitize_textarea_field(), at most three levels deep.
	 *
	 * @param mixed $value Unslashed input.
	 * @param int   $depth Current depth.
	 * @return array
	 */
	private static function addon_input( $value, $depth = 0 ) {
		if ( ! is_array( $value ) || $depth > 2 ) {
			return array();
		}
		$clean = array();
		foreach ( $value as $key => $item ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key ) {
				continue;
			}
			if ( is_array( $item ) ) {
				$clean[ $key ] = self::addon_input( $item, $depth + 1 );
			} elseif ( is_scalar( $item ) ) {
				$clean[ $key ] = sanitize_textarea_field( (string) $item );
			}
		}
		return $clean;
	}

	/**
	 * Capability, then nonce (handler order from skill wp-plugin-security).
	 *
	 * @param string $action Action name.
	 */
	private function guard( $action ) {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'culprit-finder' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'culprit_finder_' . $action );
	}

	/**
	 * The session must belong to this user in this browser (ADR-0002).
	 */
	private function require_owner() {
		if ( null === $this->plugin->owned_session() ) {
			wp_die(
				wp_kses_post(
					sprintf(
						/* translators: %s: Tools page URL */
						__( 'There is no troubleshooting session for you in this browser. <a href="%s">Go to Culprit Finder</a>.', 'culprit-finder' ),
						esc_url( Links::tools() )
					)
				),
				'',
				array( 'response' => 409 )
			);
		}
	}

	/**
	 * Validated redirect_to, defaulting to the control panel.
	 *
	 * @return string
	 */
	private function redirect_target() {
		$fallback = Links::control_panel();
		if ( empty( $_REQUEST['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked in guard().
			return $fallback;
		}
		$raw = esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked in guard().
		return wp_validate_redirect( $raw, $fallback );
	}

	/**
	 * Show an error with a way back.
	 *
	 * @param WP_Error $error Error.
	 */
	private function die_with( WP_Error $error ) {
		wp_die(
			esc_html( $error->get_error_message() ),
			esc_html__( 'Culprit Finder', 'culprit-finder' ),
			array(
				'response'  => 409,
				'link_url'  => esc_url( Links::tools() ),
				'link_text' => esc_html__( 'Back to Culprit Finder', 'culprit-finder' ),
			)
		);
	}

	/**
	 * Safe redirect and exit.
	 *
	 * @param string $url URL.
	 */
	private function redirect( $url ) {
		wp_safe_redirect( $url );
		exit;
	}
}
