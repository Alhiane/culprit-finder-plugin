<?php
/**
 * Tools → Culprit Finder.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Admin;

use CulpritFinder\Engine\Result;
use CulpritFinder\Engine\Step;
use CulpritFinder\Plugin;
use CulpritFinder\Report\Builder;
use CulpritFinder\Session\LoaderInstaller;
use CulpritFinder\Session\Store;
use CulpritFinder\Session\Token;

defined( 'ABSPATH' ) || exit;

/**
 * The one management page: idle, running, result (ADR-0006).
 */
final class Page {

	const SLUG = 'culprit-finder';

	/**
	 * Plugin.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Hook suffix returned by add_management_page().
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Hook the menu and assets.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Add Tools → Culprit Finder.
	 */
	public function add_page() {
		$hook       = add_management_page( __( 'Culprit Finder', 'culprit-finder' ), __( 'Culprit Finder', 'culprit-finder' ), 'activate_plugins', self::SLUG, array( $this, 'render' ) );
		$this->hook = is_string( $hook ) ? $hook : '';
	}

	/**
	 * CSS and JS only on our page.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue( $hook_suffix ) {
		if ( '' === $this->hook || $hook_suffix !== $this->hook ) {
			return;
		}
		$url = plugin_dir_url( CULPRIT_FINDER_FILE );
		wp_enqueue_style( 'culprit-finder-admin', $url . 'assets/admin.css', array(), CULPRIT_FINDER_VERSION );
		wp_enqueue_script( 'culprit-finder-admin', $url . 'assets/admin.js', array(), CULPRIT_FINDER_VERSION, true );
	}

	/**
	 * Render the page in the right state.
	 */
	public function render() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$manager = $this->plugin->manager();
		$session = $manager->current(); // Deletes an expired session and remembers it.
		$owned   = $this->plugin->owned_session();

		echo '<div class="wrap culprit-finder">';
		echo '<h1>' . esc_html__( 'Culprit Finder', 'culprit-finder' ) . '</h1>';
		$this->render_notices();

		if ( null !== $session && null !== $owned ) {
			$step = $manager->step( $session );
			if ( $step->is_done() ) {
				$this->render_result( $session, $step );
			} else {
				$this->render_running( $session, $step );
			}
		} else {
			if ( null !== $session ) {
				$this->render_foreign_session( $session );
			}
			$this->render_idle();
		}
		echo '</div>';
	}

	/**
	 * One-time notices (query arg) and the expiry notice.
	 */
	private function render_notices() {
		$notice   = isset( $_GET['culprit_notice'] ) ? sanitize_key( wp_unslash( $_GET['culprit_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$messages = array(
			'started' => __( 'Troubleshooting started. Plugins are switched off for you only; visitors see the normal site.', 'culprit-finder' ),
			'exited'  => __( 'Troubleshooting ended. The site is back to normal for you.', 'culprit-finder' ),
		);
		if ( isset( $messages[ $notice ] ) ) {
			printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html( $messages[ $notice ] ) );
		}

		$store = $this->plugin->store();
		$last  = $store->last_result();
		if ( is_array( $last ) && ! empty( $last['session_expired_at'] ) ) {
			printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html__( 'Your troubleshooting session expired after an hour without answers and was ended. The site is back to normal for you.', 'culprit-finder' ) );
			unset( $last['session_expired_at'] );
			if ( isset( $last['result'] ) ) {
				$store->save_last_result( $last );
			} else {
				$store->delete_last_result();
			}
		}
	}

	/**
	 * Idle: checks, plugin count, pins, bookmark URLs, Start.
	 */
	private function render_idle() {
		$manager  = $this->plugin->manager();
		$self     = plugin_basename( CULPRIT_FINDER_FILE );
		$active   = array_values( array_diff( $manager->real_active_plugins(), array( $self ) ) );
		$names    = self::plugin_names();
		$problems = $this->environment_problems();
		$key      = Token::generate();

		echo '<p class="culprit-finder-lead">' . esc_html__( 'Find the plugin that causes a problem on your site. Culprit Finder switches plugins off for your browser only, asks whether the problem is still there, and narrows it down in a few steps. Visitors always see the normal site.', 'culprit-finder' ) . '</p>';

		foreach ( $problems as $problem ) {
			echo '<div class="notice notice-error inline"><p>' . wp_kses_post( $problem ) . '</p></div>';
		}

		echo '<form method="post" action="' . esc_url( Links::form_action() ) . '">';
		wp_nonce_field( 'culprit_finder_start' );
		echo '<input type="hidden" name="action" value="culprit_finder_start">';
		echo '<input type="hidden" name="culprit_finder_recovery" value="' . esc_attr( $key ) . '">';

		echo '<h2>' . esc_html(
			sprintf(
				/* translators: %d: number of active plugins */
				_n( '%d active plugin will be tested', '%d active plugins will be tested', count( $active ), 'culprit-finder' ),
				count( $active )
			)
		) . '</h2>';

		if ( $active ) {
			echo '<details class="culprit-finder-pins"><summary>' . esc_html__( 'Keep some plugins on (optional)', 'culprit-finder' ) . '</summary>';
			echo '<p class="description">' . esc_html__( 'Tick plugins the problem needs, for example WooCommerce for a checkout problem. They stay on in every step and are not suspected.', 'culprit-finder' ) . '</p>';
			echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Plugins to keep on', 'culprit-finder' ) . '</legend>';
			foreach ( $active as $basename ) {
				printf(
					'<label><input type="checkbox" name="culprit_finder_pin[]" value="%1$s"> %2$s</label><br>',
					esc_attr( $basename ),
					esc_html( isset( $names[ $basename ] ) ? $names[ $basename ] : $basename )
				);
			}
			echo '</fieldset></details>';
		}

		echo '<h2>' . esc_html__( 'Bookmark these two links first', 'culprit-finder' ) . '</h2>';
		echo '<p>' . esc_html__( 'If a step makes your site show an error, these still work:', 'culprit-finder' ) . '</p>';
		echo '<dl class="culprit-finder-links">';
		echo '<dt>' . esc_html__( 'Control panel (answer questions even when pages are broken)', 'culprit-finder' ) . '</dt>';
		echo '<dd><code>' . esc_html( Links::control_panel() ) . '</code></dd>';
		echo '<dt>' . esc_html__( 'Emergency exit (ends troubleshooting, works even when logged out)', 'culprit-finder' ) . '</dt>';
		echo '<dd><code>' . esc_html( Links::recovery( $key ) ) . '</code></dd>';
		echo '</dl>';
		echo '<p class="description">' . esc_html__( 'The emergency exit link becomes active when you press Start. Keep it private.', 'culprit-finder' ) . '</p>';

		$disabled = $problems ? ' disabled' : '';
		echo '<p><button type="submit" class="button button-primary button-hero"' . esc_attr( $disabled ) . '>' . esc_html__( 'Start troubleshooting', 'culprit-finder' ) . '</button></p>';
		echo '</form>';

		$this->render_last_result();
	}

	/**
	 * Notice for a session owned by another user or another browser.
	 *
	 * @param array $session Session.
	 */
	private function render_foreign_session( array $session ) {
		$user = get_userdata( (int) $session['user_id'] );
		$who  = $user ? $user->display_name : __( 'another administrator', 'culprit-finder' );
		echo '<div class="notice notice-warning inline"><p>';
		echo esc_html(
			sprintf(
				/* translators: %s: user display name */
				__( 'A troubleshooting session started by %s is running in another browser. It only affects that browser. Starting a new session here ends it.', 'culprit-finder' ),
				$who
			)
		);
		echo ' <a class="button" href="' . esc_url( Links::action( 'exit' ) ) . '">' . esc_html__( 'End that session', 'culprit-finder' ) . '</a>';
		echo '</p></div>';
	}

	/**
	 * Running: the question.
	 *
	 * @param array $session Session.
	 * @param Step  $step    Current step.
	 */
	private function render_running( array $session, Step $step ) {
		$names    = self::plugin_names();
		$self     = $session['self'];
		$enabled  = array_values( array_diff( $step->enabled(), array( $self ) ) );
		$disabled = $step->disabled();
		$minutes  = max( 1, (int) ceil( ( (int) $session['expires_at'] - time() ) / 60 ) );

		echo '<div class="culprit-finder-card culprit-finder-running">';
		echo '<p class="culprit-finder-step">' . esc_html(
			sprintf(
				/* translators: 1: current step number, 2: estimated number of steps */
				__( 'Step %1$d of about %2$d', 'culprit-finder' ),
				$step->question(),
				$step->estimated_total()
			)
		) . '</p>';
		echo '<h2>' . esc_html(
			sprintf(
				/* translators: %d: number of plugins switched off */
				_n( 'We switched off %d plugin for you only.', 'We switched off %d plugins for you only.', count( $disabled ), 'culprit-finder' ),
				count( $disabled )
			)
		) . '</h2>';
		echo '<p>' . wp_kses_post(
			sprintf(
				/* translators: %s: site home URL */
				__( 'Open the page where you saw the problem (for example <a href="%s" target="_blank">your home page</a>) and check it. <strong>Is the problem still there?</strong>', 'culprit-finder' ),
				esc_url( home_url( '/' ) )
			)
		) . '</p>';

		$redirect = Links::control_panel();
		echo '<p class="culprit-finder-answers">';
		echo '<a class="button button-primary button-hero" href="' . esc_url( Links::action( 'answer', array( 'answer' => 'yes' ), $redirect ) ) . '">' . esc_html__( 'Yes, the problem is still here', 'culprit-finder' ) . '</a> ';
		echo '<a class="button button-primary button-hero" href="' . esc_url( Links::action( 'answer', array( 'answer' => 'no' ), $redirect ) ) . '">' . esc_html__( 'No, it’s gone', 'culprit-finder' ) . '</a>';
		echo '</p><p>';
		if ( $step->answers_used() > 0 ) {
			echo '<a class="button" href="' . esc_url( Links::action( 'undo', array(), $redirect ) ) . '">' . esc_html__( 'Undo last answer', 'culprit-finder' ) . '</a> ';
		}
		echo '<a class="button" href="' . esc_url( Links::action( 'exit' ) ) . '">' . esc_html__( 'Exit', 'culprit-finder' ) . '</a>';
		echo '</p>';

		echo '<details><summary>' . esc_html(
			sprintf(
				/* translators: 1: plugins on, 2: plugins off */
				__( 'Plugins in this step: %1$d on, %2$d off', 'culprit-finder' ),
				count( $enabled ),
				count( $disabled )
			)
		) . '</summary><div class="culprit-finder-lists">';
		$this->plugin_list( __( 'On', 'culprit-finder' ), $enabled, $names );
		$this->plugin_list( __( 'Off (for you only)', 'culprit-finder' ), $disabled, $names );
		echo '</div></details>';

		echo '<p class="description">' . esc_html(
			sprintf(
				/* translators: %d: minutes */
				_n( 'Troubleshooting ends automatically after %d minute without an answer.', 'Troubleshooting ends automatically after %d minutes without an answer.', $minutes, 'culprit-finder' ),
				$minutes
			)
		) . ' ' . esc_html__( 'If a page shows a critical error, open your bookmarked control panel to answer. WordPress may email you about the error; that is expected during troubleshooting.', 'culprit-finder' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Result: verdict, culprit cards, report, Undo / Exit / Run again.
	 *
	 * @param array $session Session.
	 * @param Step  $step    Done step.
	 */
	private function render_result( array $session, Step $step ) {
		$record = $this->plugin->store()->last_result();
		$result = $step->result();

		echo '<div class="culprit-finder-card culprit-finder-result">';
		echo '<p class="culprit-finder-step">' . esc_html__( 'Result', 'culprit-finder' ) . '</p>';
		echo '<h2>' . esc_html( self::verdict_text( $result['type'] ) ) . '</h2>';
		echo '<p>' . esc_html__( 'Your site is back to normal for you while you read this.', 'culprit-finder' ) . '</p>';

		if ( is_array( $record ) && isset( $record['plugins'] ) ) {
			foreach ( $result['culprits'] as $culprit ) {
				$this->culprit_card( $culprit, $record['plugins'] );
			}
		}

		echo '<p>';
		echo '<a class="button" href="' . esc_url( Links::action( 'undo', array(), Links::control_panel() ) ) . '">' . esc_html__( 'Undo last answer', 'culprit-finder' ) . '</a> ';
		echo '<a class="button button-primary" href="' . esc_url( Links::action( 'exit' ) ) . '">' . esc_html__( 'Exit', 'culprit-finder' ) . '</a>';
		echo '</p>';

		echo '<form method="post" action="' . esc_url( Links::form_action() ) . '">';
		wp_nonce_field( 'culprit_finder_start' );
		echo '<input type="hidden" name="action" value="culprit_finder_start">';
		foreach ( $session['pinned'] as $basename ) {
			echo '<input type="hidden" name="culprit_finder_pin[]" value="' . esc_attr( $basename ) . '">';
		}
		echo '<p><button type="submit" class="button">' . esc_html__( 'Run again', 'culprit-finder' ) . '</button></p>';
		echo '</form>';
		echo '</div>';

		if ( is_array( $record ) && isset( $record['result'] ) ) {
			$this->report_box( Builder::build( $record ) );
		}
	}

	/**
	 * Last result after Exit.
	 */
	private function render_last_result() {
		$record = $this->plugin->store()->last_result();
		if ( ! is_array( $record ) || ! isset( $record['result'] ) ) {
			return;
		}
		echo '<h2>' . esc_html__( 'Last result', 'culprit-finder' ) . '</h2>';
		echo '<p><strong>' . esc_html( self::verdict_text( $record['result']['type'] ) ) . '</strong></p>';
		$this->report_box( Builder::build( $record ) );
	}

	/**
	 * Report textarea + Copy button (works without JS: the text is selectable).
	 *
	 * @param string $report Report text.
	 */
	private function report_box( $report ) {
		echo '<h3><label for="culprit-finder-report">' . esc_html__( 'Support report', 'culprit-finder' ) . '</label></h3>';
		echo '<p class="description">' . esc_html__( 'Safe to paste in a support forum: it contains no site address, user names, or emails.', 'culprit-finder' ) . '</p>';
		echo '<textarea id="culprit-finder-report" class="large-text code" rows="14" readonly>' . esc_textarea( $report ) . '</textarea>';
		echo '<p><button type="button" class="button culprit-finder-copy" data-target="culprit-finder-report" data-done="' . esc_attr__( 'Copied!', 'culprit-finder' ) . '" hidden>' . esc_html__( 'Copy report', 'culprit-finder' ) . '</button></p>';
	}

	/**
	 * Card for one culprit.
	 *
	 * @param string $basename Basename.
	 * @param array  $plugins  Plugin facts from the result record.
	 */
	private function culprit_card( $basename, array $plugins ) {
		$info = isset( $plugins[ $basename ] ) ? $plugins[ $basename ] : array(
			'name'     => $basename,
			'version'  => '',
			'author'   => '',
			'uri'      => '',
			'requires' => array(),
		);
		echo '<div class="culprit-finder-culprit">';
		echo '<h3>' . esc_html( trim( $info['name'] . ' ' . $info['version'] ) ) . '</h3>';
		if ( '' !== $info['author'] ) {
			/* translators: %s: plugin author */
			echo '<p>' . esc_html( sprintf( __( 'By %s', 'culprit-finder' ), $info['author'] ) ) . '</p>';
		}
		if ( ! empty( $info['requires'] ) ) {
			$labels = array();
			foreach ( $info['requires'] as $dep ) {
				$labels[] = Builder::label( $dep, $plugins );
			}
			/* translators: %s: list of plugin names */
			echo '<p>' . esc_html( sprintf( __( 'Tested together with its required plugins: %s', 'culprit-finder' ), implode( ', ', $labels ) ) ) . '</p>';
		}
		if ( '' !== $info['uri'] ) {
			echo '<p><a href="' . esc_url( $info['uri'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Plugin website', 'culprit-finder' ) . '</a></p>';
		}
		echo '</div>';
	}

	/**
	 * A titled list of plugin names.
	 *
	 * @param string   $title     Heading.
	 * @param string[] $basenames Basenames.
	 * @param array    $names     Basename => name.
	 */
	private function plugin_list( $title, array $basenames, array $names ) {
		echo '<div><h4>' . esc_html( $title ) . '</h4><ul>';
		if ( ! $basenames ) {
			echo '<li>' . esc_html__( '(none)', 'culprit-finder' ) . '</li>';
		}
		foreach ( $basenames as $basename ) {
			echo '<li>' . esc_html( isset( $names[ $basename ] ) ? $names[ $basename ] : $basename ) . '</li>';
		}
		echo '</ul></div>';
	}

	/**
	 * Reasons Start is disabled (with manual instructions), as trusted HTML.
	 *
	 * @return string[]
	 */
	private function environment_problems() {
		if ( is_multisite() ) {
			return array( esc_html__( 'Culprit Finder does not support multisite networks yet, so troubleshooting cannot start here.', 'culprit-finder' ) );
		}
		if ( LoaderInstaller::is_current() ) {
			return array();
		}
		$error   = get_option( Store::LOADER_ERROR );
		$message = esc_html__( 'The Culprit Finder loader is missing or out of date, so plugins cannot be switched off for your session yet.', 'culprit-finder' );
		if ( is_string( $error ) && '' !== $error ) {
			$message .= ' ' . esc_html( $error );
		}
		$message .= '<br>' . sprintf(
			/* translators: 1: source file path, 2: target folder path */
			esc_html__( 'To fix it, copy %1$s into %2$s (create the folder if needed) with FTP or your host’s file manager, then reload this page.', 'culprit-finder' ),
			'<code>' . esc_html( LoaderInstaller::source() ) . '</code>',
			'<code>' . esc_html( WPMU_PLUGIN_DIR . '/' ) . '</code>'
		);
		return array( $message );
	}

	/**
	 * Plain-English verdict for the UI (translatable).
	 *
	 * @param string $type Result type.
	 * @return string
	 */
	public static function verdict_text( $type ) {
		switch ( $type ) {
			case Result::SINGLE:
				return __( 'Found it: one plugin causes the problem.', 'culprit-finder' );
			case Result::PAIR:
				return __( 'Found it: two plugins conflict with each other. The problem appears only when both are on.', 'culprit-finder' );
			case Result::COMPLEX:
				return __( 'Three or more plugins are involved. These two are part of it; test further with both of them on.', 'culprit-finder' );
			case Result::NOT_PLUGIN:
				return __( 'The problem is not caused by the plugins tested: it stayed with all of them off. Check the theme, must-use plugins, or server.', 'culprit-finder' );
			case Result::NOTHING_TO_TEST:
				return __( 'There was nothing to test: every active plugin was kept on.', 'culprit-finder' );
			default:
				return __( 'The answers were inconsistent, so the problem may come and go. Try again, checking each step carefully.', 'culprit-finder' );
		}
	}

	/**
	 * Basename => plugin name for installed plugins.
	 *
	 * @return array<string, string>
	 */
	public static function plugin_names() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$names = array();
		foreach ( get_plugins() as $basename => $data ) {
			$names[ $basename ] = trim( $data['Name'] . ' ' . $data['Version'] );
		}
		return $names;
	}
}
