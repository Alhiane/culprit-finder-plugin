<?php
/**
 * Dashboard widget.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Admin;

use CulpritFinder\Plugin;
use CulpritFinder\Report\Builder;

defined( 'ABSPATH' ) || exit;

/**
 * Small widget: a way in when idle, the current question during a session, the last result after (ADR-0018).
 * Users can hide it from Screen Options like any dashboard widget.
 */
final class DashboardWidget {

	const ID = 'culprit_finder_widget';

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
	 * Hook in (fires only on the Dashboard screen).
	 */
	public function register() {
		add_action( 'wp_dashboard_setup', array( $this, 'add' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'styles' ) );
	}

	/**
	 * A few lines of CSS, on the Dashboard only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function styles( $hook_suffix ) {
		if ( 'index.php' !== $hook_suffix || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		wp_add_inline_style( 'dashboard', '.culprit-finder-widget .cf-w-bar{display:grid;grid-auto-flow:column;gap:3px;margin:4px 0 12px}.culprit-finder-widget .cf-bar__seg{height:6px;border-radius:3px;background:#dcdcde}.culprit-finder-widget .cf-bar__seg--done{background:#925FBB}.culprit-finder-widget .cf-bar__seg--now{background:#c9a9e3}.culprit-finder-widget .cf-w-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center}#culprit_finder_widget .button-primary{background:#925FBB;border-color:#925FBB}#culprit_finder_widget .button-primary:hover,#culprit_finder_widget .button-primary:focus{background:#7C4DA6;border-color:#7C4DA6}#culprit_finder_widget .button:not(.button-primary){color:#5e3a80;border-color:#925FBB}.culprit-finder-widget .cf-w-links{display:flex;gap:14px;margin-top:10px}' );
	}

	/**
	 * Register the widget for users who can manage plugins, near the top until the user moves it.
	 */
	public function add() {
		if ( ! current_user_can( 'activate_plugins' ) || is_multisite() ) {
			return;
		}
		wp_add_dashboard_widget( self::ID, esc_html__( 'Culprit Finder', 'culprit-finder' ), array( $this, 'render' ), null, null, 'normal', 'high' );
	}

	/**
	 * Render the widget in the right state.
	 */
	public function render() {
		$manager = $this->plugin->manager();
		$session = $manager->current();
		$owned   = null !== $session && null !== $this->plugin->owned_session();

		echo '<div class="culprit-finder-widget">';

		if ( $owned ) {
			$step = $manager->step( $session );
			if ( ! $step->is_done() ) {
				$this->render_running( $session, $step );
				echo '</div>';
				return;
			}
		}

		if ( null !== $session && ! $owned ) {
			echo '<p>' . esc_html__( 'A troubleshooting session is running in another browser.', 'culprit-finder' ) . ' <a href="' . esc_url( Links::tools() ) . '">' . esc_html__( 'Details', 'culprit-finder' ) . '</a></p>';
		}

		$record = $this->plugin->store()->last_result();
		if ( is_array( $record ) && isset( $record['result'] ) ) {
			$labels = array();
			foreach ( $record['result']['culprits'] as $culprit ) {
				$labels[] = Builder::label( $culprit, isset( $record['plugins'] ) ? $record['plugins'] : array() );
			}
			echo '<p><strong>' . esc_html(
				sprintf(
					/* translators: %s: date */
					__( 'Last result · %s', 'culprit-finder' ),
					wp_date( get_option( 'date_format' ), (int) $record['finished_at'] )
				)
			) . '</strong><br>' . esc_html( Page::verdict_heading( $record['result']['type'] ) ) . ( $labels ? ': ' . esc_html( implode( ', ', $labels ) ) : '' ) . '</p>';
			echo '<div class="cf-w-actions"><a class="button" href="' . esc_url( Links::tools() ) . '">' . esc_html( $owned ? __( 'View result', 'culprit-finder' ) : __( 'View report', 'culprit-finder' ) ) . '</a>';
			if ( ! $owned ) {
				echo '<a href="' . esc_url( Links::tools() ) . '">' . esc_html__( 'Run a new search', 'culprit-finder' ) . '</a>';
			}
			echo '</div></div>';
			return;
		}

		$count = max( 0, count( $manager->real_active_plugins() ) - 1 );
		echo '<p><strong>' . esc_html__( 'Something broken after an update?', 'culprit-finder' ) . '</strong></p>';
		echo '<p>' . esc_html__( 'Find the plugin behind it in a few questions. Plugins are switched off for you only; visitors never notice.', 'culprit-finder' ) . '</p>';
		echo '<div class="cf-w-actions"><a class="button button-primary" href="' . esc_url( Links::tools() ) . '">' . esc_html__( 'Find the culprit', 'culprit-finder' ) . '</a>';
		/* translators: %d: number of active plugins */
		echo '<span class="description">' . esc_html( sprintf( _n( '%d active plugin', '%d active plugins', $count, 'culprit-finder' ), $count ) ) . '</span></div>';
		echo '</div>';
	}

	/**
	 * The current question with Yes / No.
	 *
	 * @param array                      $session Session.
	 * @param \CulpritFinder\Engine\Step $step    Step.
	 */
	private function render_running( array $session, $step ) {
		$here = admin_url( 'index.php' );
		echo '<p><strong>' . esc_html(
			sprintf(
				/* translators: 1: current step number, 2: estimated number of steps */
				__( 'Step %1$d of about %2$d', 'culprit-finder' ),
				$step->question(),
				$step->estimated_total()
			)
		) . '</strong></p>';
		echo '<div class="cf-w-bar">';
		$total = max( 1, $step->estimated_total() );
		for ( $i = 1; $i <= $total; $i++ ) {
			$state = $i <= $step->answers_used() ? 'done' : ( $i === $step->question() ? 'now' : 'todo' );
			echo '<span class="cf-bar__seg cf-bar__seg--' . esc_attr( $state ) . '"></span>';
		}
		echo '</div>';
		echo '<p><strong>' . esc_html__( 'Is the problem still there?', 'culprit-finder' ) . '</strong><br>';
		echo esc_html(
			sprintf(
				/* translators: 1: plugins switched off, 2: plugins in total */
				__( '%1$d of %2$d plugins are off for you.', 'culprit-finder' ),
				count( $step->disabled() ),
				count( $session['snapshot'] ) - 1
			)
		);
		$problem = ! empty( $session['problem_url'] ) ? $session['problem_url'] : home_url( '/' );
		echo ' <a href="' . esc_url( $problem ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open the broken page', 'culprit-finder' ) . ' <span aria-hidden="true">↗</span></a></p>';
		echo '<div class="cf-w-actions">';
		echo '<a class="button button-primary" href="' . esc_url( Links::action( 'answer', array( 'answer' => 'yes' ), $here ) ) . '">' . esc_html__( 'Yes, still there', 'culprit-finder' ) . '</a>';
		echo '<a class="button" href="' . esc_url( Links::action( 'answer', array( 'answer' => 'no' ), $here ) ) . '">' . esc_html__( 'No, it’s gone', 'culprit-finder' ) . '</a>';
		echo '</div><div class="cf-w-links">';
		if ( $step->answers_used() > 0 ) {
			echo '<a href="' . esc_url( Links::action( 'undo', array(), $here ) ) . '">' . esc_html__( 'Undo', 'culprit-finder' ) . '</a>';
		}
		echo '<a href="' . esc_url( Links::control_panel() ) . '">' . esc_html__( 'Open control panel', 'culprit-finder' ) . '</a>';
		echo '<a href="' . esc_url( Links::action( 'exit' ) ) . '" style="color:#b32d2e">' . esc_html__( 'Exit', 'culprit-finder' ) . '</a>';
		echo '</div>';
	}
}
