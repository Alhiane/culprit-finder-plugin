<?php
/**
 * Wiring, lifecycle, and per-request session binding.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder;

use CulpritFinder\Session\Cookie;
use CulpritFinder\Session\LoaderInstaller;
use CulpritFinder\Session\Manager;
use CulpritFinder\Session\Store;
use CulpritFinder\Session\Token;
use CulpritFinder\Session\View;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin root object.
 */
final class Plugin {

	/**
	 * Main file.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Storage.
	 *
	 * @var Store
	 */
	private $store;

	/**
	 * Cookie.
	 *
	 * @var Cookie
	 */
	private $cookie;

	/**
	 * Session manager.
	 *
	 * @var Manager
	 */
	private $manager;

	/**
	 * Add-on API.
	 *
	 * @var Api
	 */
	private $api;

	/**
	 * The current user's verified session for this request (set at init), or null.
	 *
	 * @var array|null
	 */
	private $owned_session = null;

	/**
	 * Input of a Start that an add-on refused, read once per request (false until read).
	 *
	 * @var array|null|false
	 */
	private $start_input = false;

	/**
	 * Constructor.
	 *
	 * @param string $file Main plugin file.
	 */
	public function __construct( $file ) {
		$this->file    = $file;
		$this->store   = new Store();
		$this->cookie  = new Cookie();
		$this->manager = new Manager( $this->store, plugin_basename( $file ) );
		$this->api     = new Api( $this );
	}

	/**
	 * Hook everything. Requests without our cookie do no DB work here (AC-14).
	 */
	public function register() {
		add_action( 'init', array( $this, 'bind_session' ), 1 );
		add_action( 'init', array( $this, 'report_recovery' ), 1 );
		( new Admin\Page( $this ) )->register();
		( new Admin\Handlers( $this ) )->register();
		( new Admin\AdminBar( $this ) )->register();
		( new Admin\PluginsLock( $this ) )->register();
		( new Admin\DashboardWidget( $this ) )->register();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'culprit-finder', new CLI\Command( $this->manager, $this->store ) );
		}
	}

	/**
	 * Session manager.
	 *
	 * @return Manager
	 */
	public function manager() {
		return $this->manager;
	}

	/**
	 * Add-on API (src/functions.php).
	 *
	 * @return Api
	 */
	public function api() {
		return $this->api;
	}

	/**
	 * Cookie handler.
	 *
	 * @return Cookie
	 */
	public function cookie() {
		return $this->cookie;
	}

	/**
	 * Storage.
	 *
	 * @return Store
	 */
	public function store() {
		return $this->store;
	}

	/**
	 * The verified session of the current user in this browser, or null.
	 *
	 * @return array|null
	 */
	public function owned_session() {
		return $this->owned_session;
	}

	/**
	 * The running session, verified again right now: our cookie's token matches, the current user
	 * owns it and can manage plugins. Never in WP-CLI. Use this, not owned_session(), in requests
	 * where the user can change after init (REST cookie authentication without a nonce).
	 *
	 * @return array|null
	 */
	public function verified_session() {
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || ! $this->cookie->present() ) {
			return null;
		}
		$session = $this->manager->current();
		if ( null === $session || ! Token::matches( $this->cookie->token(), $session['token_hash'] ) ) {
			return null;
		}
		if ( get_current_user_id() !== (int) $session['user_id'] || ! current_user_can( 'activate_plugins' ) ) {
			return null;
		}
		return $session;
	}

	/**
	 * Move this browser's cookie expiry to the session's current expiry (after any answer).
	 */
	public function refresh_cookie() {
		$session = $this->manager->current();
		$token   = $this->cookie->token();
		if ( null !== $session && null !== $token && Token::matches( $token, $session['token_hash'] ) ) {
			$this->cookie->set( $token, $session['expires_at'] );
		}
	}

	/**
	 * Remember the input of a Start that didn't happen, so the setup screen can show the reason
	 * and keep what the user typed (including the emergency exit key they already saved).
	 *
	 * @param array $input Input: message, problem_url, pinned, recovery, addon.
	 */
	public function keep_start_input( array $input ) {
		set_transient( self::start_input_key(), $input, 10 * MINUTE_IN_SECONDS );
	}

	/**
	 * The kept Start input for the current user, removed on first read; null when there is none.
	 *
	 * @return array|null
	 */
	public function start_input() {
		if ( false === $this->start_input ) {
			$input = get_transient( self::start_input_key() );
			if ( false !== $input ) {
				delete_transient( self::start_input_key() );
			}
			$this->start_input = is_array( $input ) ? $input : null;
		}
		return $this->start_input;
	}

	/**
	 * Transient name for the kept Start input of the current user.
	 *
	 * @return string
	 */
	private static function start_input_key() {
		return 'culprit_finder_start_input_' . get_current_user_id();
	}

	/**
	 * Activation: refuse network activation. Nothing is written: the loader is installed only when
	 * the user starts troubleshooting (ADR-0023).
	 *
	 * @param bool $network_wide Network activation.
	 */
	public function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			wp_die(
				esc_html__( 'Culprit Finder does not support multisite networks yet. Please do not network-activate it.', 'culprit-finder' ),
				esc_html__( 'Culprit Finder', 'culprit-finder' ),
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * Deactivation: end the session, expire the cookie, remove the loader (ADR-0007).
	 */
	public function deactivate() {
		$this->manager->end( 'deactivated' );
		$this->cookie->expire();
		LoaderInstaller::remove();
	}

	/**
	 * Report a session the recovery URL ended in this request (the loader ran before us), and remove
	 * the loader now that no session needs it.
	 */
	public function report_recovery() {
		if ( ! class_exists( 'Culprit_Finder_Loader', false ) || ! method_exists( 'Culprit_Finder_Loader', 'recovered_session' ) ) {
			return;
		}
		$session = \Culprit_Finder_Loader::recovered_session();
		if ( is_array( $session ) ) {
			LoaderInstaller::remove();
			Hooks::action( 'culprit_finder_session_ended', 'recovery', View::of( $session ) );
		}
	}

	/**
	 * User binding at init priority 1 (ADR-0002): the cookie is honoured only for its owner.
	 */
	public function bind_session() {
		if ( ! $this->cookie->present() ) {
			return;
		}
		$session = $this->manager->current();
		$valid   = null !== $session && Token::matches( $this->cookie->token(), $session['token_hash'] );
		if ( ! $valid || get_current_user_id() !== (int) $session['user_id'] || ! current_user_can( 'activate_plugins' ) ) {
			$this->cookie->expire();
			return;
		}
		$this->owned_session = $session;
	}
}
