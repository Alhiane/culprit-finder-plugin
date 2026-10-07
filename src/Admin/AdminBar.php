<?php
/**
 * Admin bar controls during a session.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Admin;

use CulpritFinder\Hooks;
use CulpritFinder\Plugin;
use CulpritFinder\Session\View;

defined( 'ABSPATH' ) || exit;

/**
 * Colored "Culprit Finder" node with Yes / No / Undo / Control panel / Exit, front end and admin (ADR-0006).
 */
final class AdminBar {

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
	 * Hook in. Callbacks return immediately unless this browser owns a session.
	 */
	public function register() {
		add_filter( 'show_admin_bar', array( $this, 'force_show' ), PHP_INT_MAX );
		add_action( 'admin_bar_menu', array( $this, 'add_nodes' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'styles' ) );
	}

	/**
	 * Show the toolbar for the session owner even if they turned it off on the front end.
	 *
	 * @param bool $show Current value.
	 * @return bool
	 */
	public function force_show( $show ) {
		return null !== $this->plugin->owned_session() ? true : $show;
	}

	/**
	 * Add the nodes.
	 *
	 * @param \WP_Admin_Bar $bar Admin bar.
	 */
	public function add_nodes( $bar ) {
		$session = $this->plugin->owned_session();
		if ( null === $session || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$step = $this->plugin->manager()->step( $session );
		$here = Links::current_url();

		if ( $step->is_done() ) {
			$title = __( 'Culprit Finder · result ready', 'culprit-finder' );
		} else {
			$title = sprintf(
				/* translators: 1: current step number, 2: estimated number of steps */
				__( 'Culprit Finder · Step %1$d of ~%2$d · still broken?', 'culprit-finder' ),
				$step->question(),
				$step->estimated_total()
			);
		}
		$label = Hooks::filter( 'culprit_finder_admin_bar_label', $title, $step->to_array(), View::of( $session ) );
		if ( is_string( $label ) && '' !== trim( $label ) ) {
			$title = $label;
		}
		$bar->add_node(
			array(
				'id'    => 'culprit-finder',
				'title' => esc_html( $title ),
				'href'  => Links::control_panel(),
				'meta'  => array( 'class' => 'culprit-finder-bar' ),
			)
		);

		$children = array();
		if ( ! $step->is_done() ) {
			$children['yes'] = array( __( 'Yes, the problem is still here', 'culprit-finder' ), Links::action( 'answer', array( 'answer' => 'yes' ), $here ) );
			$children['no']  = array( __( 'No, it’s gone', 'culprit-finder' ), Links::action( 'answer', array( 'answer' => 'no' ), $here ) );
		}
		if ( $step->answers_used() > 0 ) {
			$children['undo'] = array( __( 'Undo last answer', 'culprit-finder' ), Links::action( 'undo', array(), $here ) );
		}
		$children['panel'] = array( $step->is_done() ? __( 'View result', 'culprit-finder' ) : __( 'Control panel', 'culprit-finder' ), Links::control_panel() );
		$children['exit']  = array( __( 'Exit troubleshooting', 'culprit-finder' ), Links::action( 'exit' ) );

		foreach ( $children as $id => $child ) {
			$bar->add_node(
				array(
					'parent' => 'culprit-finder',
					'id'     => 'culprit-finder-' . $id,
					'title'  => esc_html( $child[0] ),
					'href'   => $child[1],
				)
			);
		}
		if ( ! $step->is_done() ) {
			$bar->add_node(
				array(
					'parent' => 'culprit-finder',
					'id'     => 'culprit-finder-info',
					'title'  => esc_html(
						sprintf(
							/* translators: 1: plugins switched off, 2: plugins in total */
							__( '%1$d of %2$d plugins off, for you only', 'culprit-finder' ),
							count( $step->disabled() ),
							count( $session['snapshot'] ) - 1
						)
					),
					'meta'   => array( 'class' => 'culprit-finder-info' ),
				)
			);
		}
	}

	/**
	 * A few inline lines of CSS, only during a session. Targets the node id because core's
	 * top-level hover and focus rules outrank a class selector.
	 */
	public function styles() {
		if ( null === $this->plugin->owned_session() || ! is_admin_bar_showing() ) {
			return;
		}
		$glyph = esc_url_raw( plugins_url( 'assets/images/glyph-white.svg', CULPRIT_FINDER_FILE ) );
		wp_add_inline_style(
			'admin-bar',
			'#wpadminbar #wp-admin-bar-culprit-finder > .ab-item{background:#925FBB url("' . $glyph . '") no-repeat 8px center/16px 16px;padding-left:30px;color:#fff;font-weight:600}'
			. '#wpadminbar #wp-admin-bar-culprit-finder:hover > .ab-item,#wpadminbar #wp-admin-bar-culprit-finder.hover > .ab-item,#wpadminbar #wp-admin-bar-culprit-finder > .ab-item:focus{background-color:#7C4DA6;color:#fff}'
		);
	}
}
