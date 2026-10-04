<?php
/**
 * Plugins screen lock during a session.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Admin;

use CulpritFinder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Pauses plugin management for the session owner, so the filtered list can never be saved (ADR-0003).
 */
final class PluginsLock {

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
	 * Hook the Plugins screen only.
	 */
	public function register() {
		add_action( 'load-plugins.php', array( $this, 'lock' ), 0 );
	}

	/**
	 * Refuse actions and show the notice.
	 */
	public function lock() {
		if ( null === $this->plugin->owned_session() ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- we refuse the request; nothing is processed.
		$action  = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		$action2 = isset( $_REQUEST['action2'] ) ? sanitize_key( wp_unslash( $_REQUEST['action2'] ) ) : '';
		// phpcs:enable
		if ( ( '' !== $action && '-1' !== $action ) || ( '' !== $action2 && '-1' !== $action2 ) ) {
			wp_die(
				wp_kses_post( $this->message() ),
				esc_html__( 'Plugin management is paused', 'culprit-finder' ),
				array( 'response' => 409 )
			);
		}
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/**
	 * Notice on the Plugins screen.
	 */
	public function notice() {
		echo '<div class="notice notice-warning"><p>' . wp_kses_post( $this->message() ) . '</p></div>';
	}

	/**
	 * Message with Exit and control-panel links.
	 *
	 * @return string
	 */
	private function message() {
		return sprintf(
			/* translators: 1: Exit URL, 2: control panel URL */
			__( '<strong>Plugin management is paused while Culprit Finder is running.</strong> The list below shows only the plugins switched on for you in this step; nothing has changed for visitors. <a href="%1$s">Exit troubleshooting</a> or go to the <a href="%2$s">control panel</a>.', 'culprit-finder' ),
			esc_url( Links::action( 'exit' ) ),
			esc_url( Links::control_panel() )
		);
	}
}
