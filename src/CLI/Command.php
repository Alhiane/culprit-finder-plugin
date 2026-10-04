<?php
/**
 * WP-CLI command `wp culprit-finder`.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\CLI;

use CulpritFinder\Report\Report;
use CulpritFinder\Session\Manager;
use CulpritFinder\Session\Store;
use WP_CLI;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Drives a troubleshooting session from the command line (also used by the E2E tests).
 *
 * Run as a user with the activate_plugins capability: `wp --user=admin culprit-finder start`.
 */
final class Command {

	/**
	 * Session manager.
	 *
	 * @var Manager
	 */
	private $manager;

	/**
	 * Storage.
	 *
	 * @var Store
	 */
	private $store;

	/**
	 * Constructor.
	 *
	 * @param Manager $manager Session manager.
	 * @param Store   $store   Storage.
	 */
	public function __construct( Manager $manager, Store $store ) {
		$this->manager = $manager;
		$this->store   = $store;
	}

	/**
	 * Start a session for the current user (global --user), replacing any running one.
	 *
	 * Prints JSON: {"token": "...", "recovery_key": "...", "step": {...}}. The token goes into
	 * the `wp-culprit-finder` cookie of a browser logged in as that user.
	 *
	 * ## OPTIONS
	 *
	 * [--pin=<basenames>]
	 * : Comma-separated plugin basenames to keep on in every step.
	 *
	 * ## EXAMPLES
	 *
	 *     wp --user=admin culprit-finder start --pin=woocommerce/woocommerce.php
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Options.
	 */
	public function start( $args, $assoc_args ) {
		$this->require_capability();
		$pins  = isset( $assoc_args['pin'] ) ? array_filter( array_map( 'trim', explode( ',', (string) $assoc_args['pin'] ) ) ) : array();
		$start = $this->manager->start( get_current_user_id(), $pins );
		$this->bail_on_error( $start );
		$this->json(
			array(
				'token'        => $start['token'],
				'recovery_key' => $start['recovery_key'],
				'step'         => $start['step']->to_array(),
			)
		);
	}

	/**
	 * Show the current step.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: json
	 * options:
	 *   - json
	 * ---
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Options.
	 */
	public function status( $args, $assoc_args ) {
		$session = $this->manager->current();
		if ( null === $session ) {
			WP_CLI::error( 'No troubleshooting session is running.' );
		}
		$this->json( $this->manager->step( $session )->to_array() );
	}

	/**
	 * Answer the current question.
	 *
	 * ## OPTIONS
	 *
	 * <answer>
	 * : "yes" = the problem is still there, "no" = it's gone.
	 * ---
	 * options:
	 *   - yes
	 *   - no
	 * ---
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Options.
	 */
	public function answer( $args, $assoc_args ) {
		$this->require_capability();
		$step = $this->manager->answer( 'yes' === $args[0] );
		$this->bail_on_error( $step );
		$this->json( $step->to_array() );
	}

	/**
	 * Undo the last answer.
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Options.
	 */
	public function undo( $args, $assoc_args ) {
		$this->require_capability();
		$step = $this->manager->undo();
		$this->bail_on_error( $step );
		$this->json( $step->to_array() );
	}

	/**
	 * Show the last result, with the support report.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: json
	 * options:
	 *   - json
	 *   - report
	 * ---
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Options.
	 */
	public function result( $args, $assoc_args ) {
		$record = $this->store->last_result();
		if ( null === $record || ! isset( $record['result'] ) ) {
			WP_CLI::error( 'No result yet.' );
		}
		$report = Report::text( $record );
		if ( isset( $assoc_args['format'] ) && 'report' === $assoc_args['format'] ) {
			WP_CLI::line( $report );
			return;
		}
		unset( $record['user_id'] );
		$record['report'] = $report;
		$this->json( $record );
	}

	/**
	 * End the session. The site is unchanged for everyone; the troubleshooting browser goes back to normal.
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Options.
	 *
	 * @subcommand exit
	 */
	public function end( $args, $assoc_args ) {
		$this->manager->end( 'exit' );
		WP_CLI::success( 'Session ended.' );
	}

	/**
	 * Require a user who can manage plugins.
	 */
	private function require_capability() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			WP_CLI::error( 'Run as an administrator, e.g. `wp --user=admin culprit-finder ...`.' );
		}
	}

	/**
	 * Exit with an error for a WP_Error.
	 *
	 * @param mixed $value Value.
	 */
	private function bail_on_error( $value ) {
		if ( $value instanceof WP_Error ) {
			WP_CLI::error( $value->get_error_message() );
		}
	}

	/**
	 * Print one line of JSON.
	 *
	 * @param array $data Data.
	 */
	private function json( array $data ) {
		WP_CLI::line( (string) wp_json_encode( $data, JSON_UNESCAPED_SLASHES ) );
	}
}
