<?php
/**
 * Session lifecycle: start, answer, undo, end.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Session;

use CulpritFinder\Engine\Engine;
use CulpritFinder\Engine\Step;
use CulpritFinder\Report\Collector;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the `culprit_finder_session` option. Never touches active_plugins (ADR-0003).
 */
final class Manager {

	const DEFAULT_TTL = 3600;

	/**
	 * Storage.
	 *
	 * @var Store
	 */
	private $store;

	/**
	 * Our basename.
	 *
	 * @var string
	 */
	private $self;

	/**
	 * Constructor.
	 *
	 * @param Store  $store         Storage.
	 * @param string $self_basename Our basename.
	 */
	public function __construct( Store $store, $self_basename ) {
		$this->store = $store;
		$this->self  = $self_basename;
	}

	/**
	 * Idle TTL in seconds (CULPRIT_FINDER_SESSION_TTL overrides).
	 *
	 * @return int
	 */
	public function ttl() {
		return defined( 'CULPRIT_FINDER_SESSION_TTL' ) ? max( 1, (int) CULPRIT_FINDER_SESSION_TTL ) : self::DEFAULT_TTL;
	}

	/**
	 * Real active plugins, bypassing a filtering loader.
	 *
	 * @return string[]
	 */
	public function real_active_plugins() {
		if ( class_exists( 'Culprit_Finder_Loader', false ) ) {
			return \Culprit_Finder_Loader::real_active_plugins();
		}
		$plugins = get_option( 'active_plugins', array() );
		return is_array( $plugins ) ? $plugins : array();
	}

	/**
	 * Whether the loader is installed and matches the bundled version.
	 *
	 * @return bool
	 */
	public function loader_ready() {
		return LoaderInstaller::is_current();
	}

	/**
	 * Start a new session, replacing any existing one (ADR-0002).
	 *
	 * @param int      $user_id Session owner.
	 * @param string[] $pinned  Requested keep-on basenames (validated against the snapshot).
	 * @return array{token: string, recovery_key: string, session: array, step: Step}|WP_Error
	 */
	public function start( $user_id, array $pinned = array() ) {
		$snapshot = array_values( array_unique( array_filter( $this->real_active_plugins(), 'is_string' ) ) );
		$errors   = StartGuard::errors( is_multisite(), $this->loader_ready(), in_array( $this->self, $snapshot, true ) );
		if ( $errors ) {
			return new WP_Error( 'culprit_finder_' . $errors[0], self::error_message( $errors[0] ) );
		}

		$pinned = array_values( array_diff( array_intersect( $snapshot, $pinned ), array( $this->self ) ) );
		$deps   = Dependencies::map( $snapshot );
		$engine = new Engine( $snapshot, $this->self, $pinned, $deps );
		$step   = $engine->step( array() );

		$token    = Token::generate();
		$recovery = Token::generate();
		$now      = time();
		$session  = array(
			'v'             => 1,
			'user_id'       => (int) $user_id,
			'token_hash'    => Token::hash( $token ),
			'recovery_hash' => Token::hash( $recovery ),
			'created_at'    => $now,
			'expires_at'    => $now + $this->ttl(),
			'self'          => $this->self,
			'snapshot'      => $snapshot,
			'pinned'        => $pinned,
			'deps'          => $deps,
			'answers'       => array(),
			'fixed'         => $engine->fixed(),
			'enabled_now'   => $step->enabled(),
		);
		$this->store->save_session( $session );
		if ( $step->is_done() ) {
			$this->record_result( $session, $engine, $step );
		}

		return array(
			'token'        => $token,
			'recovery_key' => $recovery,
			'session'      => $session,
			'step'         => $step,
		);
	}

	/**
	 * The current, unexpired session. An expired one is deleted and remembered for a notice.
	 *
	 * @return array|null
	 */
	public function current() {
		$session = $this->store->session();
		if ( null === $session ) {
			return null;
		}
		if ( ! self::is_valid( $session ) ) {
			$this->store->delete_session();
			return null;
		}
		if ( (int) $session['expires_at'] <= time() ) {
			$this->store->delete_session();
			$last                       = (array) $this->store->last_result();
			$last['session_expired_at'] = time();
			$this->store->save_last_result( $last );
			return null;
		}
		return $session;
	}

	/**
	 * Engine for a session.
	 *
	 * @param array $session Session.
	 * @return Engine
	 */
	public function engine( array $session ) {
		return new Engine( $session['snapshot'], $session['self'], $session['pinned'], $session['deps'] );
	}

	/**
	 * Current step of a session.
	 *
	 * @param array $session Session.
	 * @return Step
	 */
	public function step( array $session ) {
		return $this->engine( $session )->step( $session['answers'] );
	}

	/**
	 * Record an answer to the current question.
	 *
	 * @param bool $problem_present True = "Yes, the problem is still here".
	 * @return Step|WP_Error
	 */
	public function answer( $problem_present ) {
		$session = $this->current();
		if ( null === $session ) {
			return self::no_session();
		}
		$engine = $this->engine( $session );
		$step   = $engine->step( $session['answers'] );
		if ( $step->is_done() ) {
			return $step; // Nothing to answer; ignore.
		}
		$session['answers'][] = (bool) $problem_present;
		return $this->persist( $session, $engine );
	}

	/**
	 * Drop the last answer (AC-10), including the one that produced the result.
	 *
	 * @return Step|WP_Error
	 */
	public function undo() {
		$session = $this->current();
		if ( null === $session ) {
			return self::no_session();
		}
		$engine = $this->engine( $session );
		$was    = $engine->step( $session['answers'] );
		if ( $was->is_done() ) {
			// The result this session produced no longer stands.
			$this->store->delete_last_result();
		}
		$session['answers'] = array_slice( $session['answers'], 0, -1 );
		return $this->persist( $session, $engine );
	}

	/**
	 * End the session (Exit, deactivation).
	 */
	public function end() {
		$this->store->delete_session();
	}

	/**
	 * Save answers, the new enabled set, and the refreshed expiry; record the result when done.
	 *
	 * @param array  $session Session with updated answers.
	 * @param Engine $engine  Engine.
	 * @return Step
	 */
	private function persist( array $session, Engine $engine ) {
		$step                   = $engine->step( $session['answers'] );
		$session['answers']     = array_slice( $session['answers'], 0, $step->is_done() ? $step->answers_used() : count( $session['answers'] ) );
		$session['enabled_now'] = $step->enabled();
		$session['expires_at']  = time() + $this->ttl();
		$this->store->save_session( $session );
		if ( $step->is_done() ) {
			$this->record_result( $session, $engine, $step );
		}
		return $step;
	}

	/**
	 * Store the result and report data so it survives Exit.
	 *
	 * @param array  $session Session.
	 * @param Engine $engine  Engine.
	 * @param Step   $step    Done step.
	 */
	private function record_result( array $session, Engine $engine, Step $step ) {
		$this->store->save_last_result( Collector::collect( $session, $engine, $step ) );
	}

	/**
	 * Basic shape check of a stored session.
	 *
	 * @param array $session Session.
	 * @return bool
	 */
	private static function is_valid( array $session ) {
		foreach ( array( 'snapshot', 'pinned', 'deps', 'answers', 'fixed', 'enabled_now' ) as $key ) {
			if ( ! isset( $session[ $key ] ) || ! is_array( $session[ $key ] ) ) {
				return false;
			}
		}
		return isset( $session['v'], $session['user_id'], $session['self'], $session['expires_at'] ) && 1 === $session['v'];
	}

	/**
	 * Error for "no running session".
	 *
	 * @return WP_Error
	 */
	private static function no_session() {
		return new WP_Error( 'culprit_finder_no_session', __( 'No troubleshooting session is running.', 'culprit-finder' ) );
	}

	/**
	 * Human message for a StartGuard error code.
	 *
	 * @param string $code Code.
	 * @return string
	 */
	public static function error_message( $code ) {
		switch ( $code ) {
			case StartGuard::MULTISITE:
				return __( 'Culprit Finder does not support multisite networks yet. No session was started.', 'culprit-finder' );
			case StartGuard::LOADER_MISSING:
				return __( 'The Culprit Finder loader is not installed in wp-content/mu-plugins, so plugins cannot be switched off for your session. See the manual install steps on the Tools page.', 'culprit-finder' );
			default:
				return __( 'Culprit Finder must be active to start a session.', 'culprit-finder' );
		}
	}
}
