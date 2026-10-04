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
	 * The current user's verified session for this request (set at init), or null.
	 *
	 * @var array|null
	 */
	private $owned_session = null;

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
	}

	/**
	 * Hook everything. Requests without our cookie do no DB work here (AC-14).
	 */
	public function register() {
		add_action( 'init', array( $this, 'bind_session' ), 1 );
		add_action( 'admin_init', array( $this, 'ensure_loader' ) );
		( new Admin\Page( $this ) )->register();
		( new Admin\Handlers( $this ) )->register();
		( new Admin\AdminBar( $this ) )->register();
		( new Admin\PluginsLock( $this ) )->register();

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
	 * Activation: refuse network activation, install the loader.
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
		LoaderInstaller::maybe_install( true );
	}

	/**
	 * Deactivation: end the session, expire the cookie, remove the loader (ADR-0007).
	 */
	public function deactivate() {
		$this->manager->end();
		$this->cookie->expire();
		LoaderInstaller::remove();
	}

	/**
	 * Reinstall the loader when missing or outdated.
	 */
	public function ensure_loader() {
		if ( current_user_can( 'activate_plugins' ) ) {
			LoaderInstaller::maybe_install();
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
