<?php
/**
 * The Culprit Finder admin page (top-level menu).
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Admin;

use CulpritFinder\Engine\Engine;
use CulpritFinder\Engine\Result;
use CulpritFinder\Engine\Step;
use CulpritFinder\Plugin;
use CulpritFinder\Report\Builder;
use CulpritFinder\Session\LoaderInstaller;
use CulpritFinder\Session\Store;
use CulpritFinder\Session\Token;

defined( 'ABSPATH' ) || exit;

/**
 * The management page: setup, running step, result (ADR-0006, ADR-0018).
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
	 * Hook suffix returned by add_menu_page().
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
	 * Add the top-level "Culprit Finder" menu item, right after Plugins.
	 */
	public function add_page() {
		$hook       = add_menu_page(
			__( 'Culprit Finder', 'culprit-finder' ),
			__( 'Culprit Finder', 'culprit-finder' ),
			'activate_plugins',
			self::SLUG,
			array( $this, 'render' ),
			self::menu_icon(),
			66
		);
		$this->hook = is_string( $hook ) ? $hook : '';
	}

	/**
	 * Menu icon: the brand glyph as an SVG data URI (WordPress recolors it to match the admin scheme).
	 *
	 * @return string
	 */
	public static function menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><g fill="black"><rect x="7" y="5" width="18" height="3" rx="1.2"/><path fill-rule="evenodd" d="M7 10h18v14a3 3 0 0 1-3 3H10a3 3 0 0 1-3-3z M9 10v14a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V10z"/><rect x="12.5" y="11.5" width="2" height="4.5" rx="1"/><rect x="17.5" y="11.5" width="2" height="4.5" rx="1"/><path d="M11 15.5h10v2.5c0 2-1.6 3.6-3.6 3.6h-2.8c-2 0-3.6-1.6-3.6-3.6z"/><rect x="15" y="20.5" width="2" height="3"/></g></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- menu icon data URI, the format add_menu_page() expects.
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
		$session = $manager->current();
		$owned   = null !== $session && null !== $this->plugin->owned_session();

		echo '<div class="wrap culprit-finder">';
		$this->render_header( $owned );
		$this->render_notices();
		$this->render_recovery_mode_notice( $owned );

		if ( $owned ) {
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
			$this->render_setup();
		}
		echo '</div>';
	}

	/**
	 * Title row with the symbol, tagline and session pill.
	 *
	 * @param bool $running Whether this browser has a session.
	 */
	private function render_header( $running ) {
		echo '<header class="cf-header">';
		echo '<img class="culprit-finder-symbol" src="' . esc_url( plugins_url( 'assets/images/symbol.svg', CULPRIT_FINDER_FILE ) ) . '" alt="" height="44">';
		echo '<div class="cf-header__text"><h1>' . esc_html__( 'Culprit Finder', 'culprit-finder' ) . '</h1>';
		echo '<p>' . esc_html__( 'Find the plugin that broke your site. Visitors never notice.', 'culprit-finder' ) . '</p></div>';
		if ( $running ) {
			echo '<span class="cf-pill"><span class="cf-pill__dot" aria-hidden="true"></span>' . esc_html__( 'Troubleshooting is on for you only', 'culprit-finder' ) . '</span>';
		}
		echo '</header>';
		echo '<hr class="wp-header-end">';
	}

	/**
	 * WordPress recovery mode keeps the crashed plugin paused for this browser, which would skew
	 * every answer. Tell the user to start here, then exit recovery mode (readme FAQ, ADR-0017).
	 *
	 * @param bool $running Whether this browser has a running session.
	 */
	private function render_recovery_mode_notice( $running ) {
		if ( ! function_exists( 'wp_is_recovery_mode' ) || ! wp_is_recovery_mode() ) {
			return;
		}
		$exit_url = wp_nonce_url( add_query_arg( 'action', 'exit_recovery_mode', wp_login_url() ), 'exit_recovery_mode' );
		if ( $running ) {
			/* translators: %s: URL that exits WordPress recovery mode */
			$message = __( '<strong>You are still in WordPress recovery mode.</strong> WordPress keeps the plugin that crashed paused for you, so your answers would be wrong. <a href="%s">Exit recovery mode</a> before you answer; Culprit Finder keeps your site usable, and your bookmarked control panel always works.', 'culprit-finder' );
		} else {
			/* translators: %s: URL that exits WordPress recovery mode */
			$message = __( '<strong>You are in WordPress recovery mode.</strong> Bookmark the two links below and press Start, then <a href="%s">exit recovery mode</a> before you answer any question.', 'culprit-finder' );
		}
		printf( '<div class="notice notice-warning culprit-finder-recovery-mode"><p>%s</p></div>', wp_kses_post( sprintf( $message, esc_url( $exit_url ) ) ) );
	}

	/**
	 * One-time notices (query arg) and the expiry notice.
	 */
	private function render_notices() {
		$notice   = isset( $_GET['culprit_notice'] ) ? sanitize_key( wp_unslash( $_GET['culprit_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$messages = array(
			'started' => array( 'success', __( 'Troubleshooting started. Plugins are switched off for you only; visitors see the normal site.', 'culprit-finder' ) ),
			'exited'  => array( 'success', __( 'Troubleshooting ended. The site is back to normal for you.', 'culprit-finder' ) ),
			'unsaved' => array( 'error', __( 'Please save both safety links and tick “I’ve saved both links” before you start.', 'culprit-finder' ) ),
		);
		if ( isset( $messages[ $notice ] ) ) {
			printf( '<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr( $messages[ $notice ][0] ), esc_html( $messages[ $notice ][1] ) );
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
	 * Setup: how it works, the start form, and the side panel.
	 */
	private function render_setup() {
		$manager  = $this->plugin->manager();
		$self     = plugin_basename( CULPRIT_FINDER_FILE );
		$active   = array_values( array_diff( $manager->real_active_plugins(), array( $self ) ) );
		$names    = self::plugin_names();
		$problems = $this->environment_problems();
		$key      = Token::generate();
		$count    = count( $active );
		$steps    = 2 + Engine::ceil_log2( max( 1, $count ) );
		$minutes  = (int) ceil( $manager->ttl() / 60 );

		foreach ( $problems as $problem ) {
			echo '<div class="notice notice-error inline"><p>' . wp_kses_post( $problem ) . '</p></div>';
		}

		echo '<section class="cf-how" aria-label="' . esc_attr__( 'How it works', 'culprit-finder' ) . '"><ol>';
		$how = array(
			array( __( 'We switch plugins off, for you only', 'culprit-finder' ), __( 'Only your browser sees the change. Visitors and other admins get the normal site.', 'culprit-finder' ) ),
			array( __( 'You check the broken page', 'culprit-finder' ), __( 'Is the problem still there? Answer Yes or No after each change.', 'culprit-finder' ) ),
			array( __( 'We name the culprit', 'culprit-finder' ), __( 'One plugin, or two that clash, with a report you can paste into a support forum.', 'culprit-finder' ) ),
		);
		foreach ( $how as $item ) {
			echo '<li><strong>' . esc_html( $item[0] ) . '</strong><span>' . esc_html( $item[1] ) . '</span></li>';
		}
		echo '</ol></section>';

		echo '<div class="cf-columns">';
		echo '<form class="cf-card cf-setup" method="post" action="' . esc_url( Links::form_action() ) . '">';
		wp_nonce_field( 'culprit_finder_start' );
		echo '<input type="hidden" name="action" value="culprit_finder_start">';
		echo '<input type="hidden" name="culprit_finder_recovery" value="' . esc_attr( $key ) . '">';

		echo '<fieldset class="cf-field"><legend>' . esc_html__( 'Where do you see the problem?', 'culprit-finder' ) . ' <span class="cf-optional">' . esc_html__( 'Optional', 'culprit-finder' ) . '</span></legend>';
		echo '<label for="culprit-finder-problem-url">' . esc_html__( 'Paste the address of the broken page. Every step gets a one-click link to it.', 'culprit-finder' ) . '</label>';
		echo '<input type="url" class="regular-text cf-input" id="culprit-finder-problem-url" name="culprit_finder_problem_url" placeholder="' . esc_attr( home_url( '/' ) ) . '">';
		echo '</fieldset>';

		echo '<fieldset class="cf-field"><legend>' . esc_html__( 'Keep any plugins on?', 'culprit-finder' ) . ' <span class="cf-optional">' . esc_html__( 'Optional', 'culprit-finder' ) . '</span></legend>';
		echo '<p class="cf-help">' . esc_html__( 'Tick plugins the problem needs to show up, like your shop plugin for a checkout problem. They stay on in every step and are never blamed. Plugins they require stay on too.', 'culprit-finder' ) . '</p>';
		if ( $active ) {
			echo '<div class="cf-pins">';
			foreach ( $active as $basename ) {
				printf(
					'<label><input type="checkbox" name="culprit_finder_pin[]" value="%1$s"> %2$s</label>',
					esc_attr( $basename ),
					esc_html( isset( $names[ $basename ] ) ? $names[ $basename ] : $basename )
				);
			}
			echo '</div>';
		} else {
			echo '<p class="cf-help">' . esc_html__( 'No other plugins are active, so there is nothing to test.', 'culprit-finder' ) . '</p>';
		}
		echo '</fieldset>';

		echo '<fieldset class="cf-field"><legend>' . esc_html__( 'Save your two safety links', 'culprit-finder' ) . '</legend>';
		echo '<p class="cf-help">' . esc_html__( 'If a step makes a page crash, these still work. Bookmark them now: the emergency exit is made for this session only, so press Start without reloading this page.', 'culprit-finder' ) . '</p>';
		$this->safety_link( 'culprit-finder-panel-url', __( 'Control panel', 'culprit-finder' ), __( 'Answer questions even when pages are broken.', 'culprit-finder' ), Links::control_panel() );
		$this->safety_link( 'culprit-finder-exit-url', __( 'Emergency exit', 'culprit-finder' ), __( 'Ends troubleshooting at once, even when logged out. Keep it private.', 'culprit-finder' ), Links::recovery( $key ) );
		echo '<label class="cf-saved"><input type="checkbox" name="culprit_finder_saved" value="1" required> ' . esc_html__( 'I’ve saved both links', 'culprit-finder' ) . '</label>';

		echo '<div class="cf-start">';
		echo '<button type="submit" class="button cf-button cf-button--primary cf-button--large"' . ( $problems || ! $count ? ' disabled' : '' ) . '>' . esc_html__( 'Start troubleshooting', 'culprit-finder' ) . '</button>';
		echo '<span class="cf-muted">' . esc_html(
			sprintf(
				/* translators: 1: number of plugins, 2: estimated steps, 3: minutes */
				_n( '%1$d plugin to test · about %2$d steps · ends by itself after %3$d idle minutes', '%1$d plugins to test · about %2$d steps · ends by itself after %3$d idle minutes', $count, 'culprit-finder' ),
				$count,
				$steps,
				$minutes
			)
		) . '</span>';
		echo '</div></fieldset></form>';

		echo '<aside class="cf-aside">';
		echo '<div class="cf-card cf-card--small"><h2>' . esc_html__( 'What happens to my site?', 'culprit-finder' ) . '</h2><ul>';
		foreach (
			array(
				__( 'Visitors and other admins always see every plugin.', 'culprit-finder' ),
				__( 'Your real plugin settings never change. Nothing is deactivated.', 'culprit-finder' ),
				__( 'Plugin management is paused for you while it runs.', 'culprit-finder' ),
				__( 'No data leaves your site.', 'culprit-finder' ),
			) as $line
		) {
			echo '<li>' . esc_html( $line ) . '</li>';
		}
		echo '</ul></div>';
		echo '<div class="cf-card cf-card--small"><h2>' . esc_html__( 'Whole site down?', 'culprit-finder' ) . '</h2><p>' . wp_kses_post( __( 'Open the recovery link WordPress emailed you, start here, then click <strong>Exit Recovery Mode</strong> in the toolbar before you answer.', 'culprit-finder' ) ) . '</p></div>';
		$this->render_last_result_card();
		echo '</aside></div>';
	}

	/**
	 * One safety link row with a copy button.
	 *
	 * @param string $id          Element id.
	 * @param string $title       Title.
	 * @param string $description Description.
	 * @param string $url         URL.
	 */
	private function safety_link( $id, $title, $description, $url ) {
		echo '<div class="cf-link">';
		echo '<div class="cf-link__text"><strong>' . esc_html( $title ) . '</strong><span>' . esc_html( $description ) . '</span>';
		echo '<code id="' . esc_attr( $id ) . '">' . esc_html( $url ) . '</code></div>';
		echo '<button type="button" class="button cf-button cf-button--outline culprit-finder-copy" data-target="' . esc_attr( $id ) . '" data-done="' . esc_attr__( 'Copied', 'culprit-finder' ) . '" hidden>' . esc_html__( 'Copy', 'culprit-finder' ) . '</button>';
		echo '</div>';
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
	 * Running: progress and the question.
	 *
	 * @param array $session Session.
	 * @param Step  $step    Current step.
	 */
	private function render_running( array $session, Step $step ) {
		$names    = self::plugin_names();
		$self     = $session['self'];
		$enabled  = array_values( array_diff( $step->enabled(), array( $self ) ) );
		$disabled = $step->disabled();
		$total    = count( $session['snapshot'] ) - 1;
		$minutes  = max( 1, (int) ceil( ( (int) $session['expires_at'] - time() ) / 60 ) );
		$redirect = Links::control_panel();
		$problem  = isset( $session['problem_url'] ) && is_string( $session['problem_url'] ) ? $session['problem_url'] : '';

		echo '<section class="cf-progress" aria-label="' . esc_attr__( 'Progress', 'culprit-finder' ) . '">';
		echo '<div class="cf-progress__row"><strong>' . esc_html(
			sprintf(
				/* translators: 1: current step number, 2: estimated number of steps */
				__( 'Step %1$d of about %2$d', 'culprit-finder' ),
				$step->question(),
				$step->estimated_total()
			)
		) . '</strong><span class="cf-muted">' . esc_html(
			sprintf(
				/* translators: %d: minutes */
				_n( '%d min left · resets with every answer', '%d min left · resets with every answer', $minutes, 'culprit-finder' ),
				$minutes
			)
		) . '</span></div>';
		self::progress_bar( $step );
		echo '</section>';

		echo '<section class="cf-card cf-question culprit-finder-running">';
		echo '<p class="cf-eyebrow">' . esc_html(
			sprintf(
				/* translators: 1: plugins switched off, 2: plugins in total */
				__( 'For you only: %1$d of %2$d plugins switched off', 'culprit-finder' ),
				count( $disabled ),
				$total
			)
		) . '</p>';
		echo '<h2>' . esc_html__( 'Is the problem still there?', 'culprit-finder' ) . '</h2>';
		echo '<p>' . esc_html__( 'Open the broken page, reload it, and look again. A critical error page counts as Yes.', 'culprit-finder' ) . '</p>';
		if ( '' !== $problem ) {
			echo '<p><a class="button cf-button cf-button--soft" href="' . esc_url( $problem ) . '" target="_blank" rel="noopener">' . esc_html(
				sprintf(
					/* translators: %s: address of the broken page */
					__( 'Open %s', 'culprit-finder' ),
					preg_replace( '#^https?://#', '', $problem )
				)
			) . ' <span aria-hidden="true">↗</span></a></p>';
		} else {
			echo '<p><a class="button cf-button cf-button--soft" href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open your site', 'culprit-finder' ) . ' <span aria-hidden="true">↗</span></a></p>';
		}

		echo '<div class="cf-answers">';
		echo '<a class="button cf-button cf-button--primary cf-button--answer" href="' . esc_url( Links::action( 'answer', array( 'answer' => 'yes' ), $redirect ) ) . '">' . esc_html__( 'Yes, it’s still there', 'culprit-finder' ) . '</a>';
		echo '<a class="button cf-button cf-button--outline cf-button--answer" href="' . esc_url( Links::action( 'answer', array( 'answer' => 'no' ), $redirect ) ) . '">' . esc_html__( 'No, it’s gone', 'culprit-finder' ) . '</a>';
		echo '</div>';

		echo '<div class="cf-secondary">';
		if ( $step->answers_used() > 0 ) {
			echo '<a class="button" href="' . esc_url( Links::action( 'undo', array(), $redirect ) ) . '">' . esc_html__( 'Undo last answer', 'culprit-finder' ) . '</a>';
		}
		echo '<a class="button cf-button--danger" href="' . esc_url( Links::action( 'exit' ) ) . '">' . esc_html__( 'Stop and exit', 'culprit-finder' ) . '</a>';
		echo '<span class="cf-muted">' . esc_html__( 'You can also answer from the purple toolbar on any page. If WordPress emails you about a critical error, that is expected while troubleshooting.', 'culprit-finder' ) . '</span>';
		echo '</div></section>';

		echo '<details class="cf-card cf-details"><summary>' . esc_html__( 'Which plugins are on in this step', 'culprit-finder' ) . '</summary><div class="cf-lists">';
		$labels = array();
		foreach ( $enabled as $basename ) {
			$label = isset( $names[ $basename ] ) ? $names[ $basename ] : $basename;
			if ( in_array( $basename, $session['pinned'], true ) ) {
				$label .= ' ' . __( '(kept on)', 'culprit-finder' );
			} elseif ( in_array( $basename, $session['fixed'], true ) ) {
				$label .= ' ' . __( '(needed by a kept-on plugin)', 'culprit-finder' );
			}
			$labels[] = $label;
		}
		/* translators: %d: number of plugins */
		$this->plugin_list( sprintf( __( 'On (%d)', 'culprit-finder' ), count( $enabled ) ), $labels );
		$off = array();
		foreach ( $disabled as $basename ) {
			$off[] = isset( $names[ $basename ] ) ? $names[ $basename ] : $basename;
		}
		/* translators: %d: number of plugins */
		$this->plugin_list( sprintf( __( 'Off, for you only (%d)', 'culprit-finder' ), count( $disabled ) ), $off );
		echo '</div></details>';
	}

	/**
	 * Segmented progress bar for a step.
	 *
	 * @param Step $step Step.
	 */
	public static function progress_bar( Step $step ) {
		$total = max( 1, $step->estimated_total() );
		echo '<div class="cf-bar" role="progressbar" aria-valuemin="0" aria-valuemax="' . esc_attr( (string) $total ) . '" aria-valuenow="' . esc_attr( (string) $step->answers_used() ) . '">';
		for ( $i = 1; $i <= $total; $i++ ) {
			$state = $i <= $step->answers_used() ? 'done' : ( $i === $step->question() ? 'now' : 'todo' );
			echo '<span class="cf-bar__seg cf-bar__seg--' . esc_attr( $state ) . '"></span>';
		}
		echo '</div>';
	}

	/**
	 * Result: verdict, culprit cards, next steps, report. Run again starts a new session, so it carries
	 * its own emergency exit key and shows that link.
	 *
	 * @param array $session Session.
	 * @param Step  $step    Done step.
	 */
	private function render_result( array $session, Step $step ) {
		$record = $this->plugin->store()->last_result();
		$result = $step->result();
		$found  = in_array( $result['type'], array( Result::SINGLE, Result::PAIR ), true );

		echo '<section class="cf-card cf-question culprit-finder-result">';
		echo '<p class="cf-eyebrow' . ( $found ? ' cf-eyebrow--success' : '' ) . '">' . esc_html(
			sprintf(
				/* translators: 1: "Found it" or "Result", 2: number of answers */
				_n( '%1$s · %2$d answer', '%1$s · %2$d answers', $step->answers_used(), 'culprit-finder' ),
				$found ? __( 'Found it', 'culprit-finder' ) : __( 'Result', 'culprit-finder' ),
				$step->answers_used()
			)
		) . '</p>';
		echo '<h2>' . esc_html( self::verdict_heading( $result['type'] ) ) . '</h2>';
		echo '<p>' . esc_html( self::verdict_text( $result['type'] ) ) . ' ' . esc_html__( 'Your site is back to normal for you while you read this.', 'culprit-finder' ) . '</p>';

		if ( is_array( $record ) && isset( $record['plugins'] ) ) {
			foreach ( $result['culprits'] as $culprit ) {
				$this->culprit_card( $culprit, $record['plugins'], $result['type'] );
			}
		}

		echo '<div class="cf-secondary">';
		echo '<a class="button cf-button cf-button--primary" href="' . esc_url( Links::action( 'exit' ) ) . '">' . esc_html__( 'Done, exit', 'culprit-finder' ) . '</a>';
		echo '<a class="button" href="' . esc_url( Links::action( 'undo', array(), Links::control_panel() ) ) . '">' . esc_html__( 'Undo last answer', 'culprit-finder' ) . '</a>';
		echo '</div>';

		$key = Token::generate();
		echo '<form class="cf-rerun" method="post" action="' . esc_url( Links::form_action() ) . '">';
		wp_nonce_field( 'culprit_finder_start' );
		echo '<input type="hidden" name="action" value="culprit_finder_start">';
		echo '<input type="hidden" name="culprit_finder_saved" value="1">';
		echo '<input type="hidden" name="culprit_finder_recovery" value="' . esc_attr( $key ) . '">';
		if ( ! empty( $session['problem_url'] ) ) {
			echo '<input type="hidden" name="culprit_finder_problem_url" value="' . esc_attr( $session['problem_url'] ) . '">';
		}
		foreach ( $session['pinned'] as $basename ) {
			echo '<input type="hidden" name="culprit_finder_pin[]" value="' . esc_attr( $basename ) . '">';
		}
		echo '<p class="cf-help">' . esc_html__( 'Want to double-check? Running again starts a new session with a new emergency exit link. Bookmark it first:', 'culprit-finder' ) . '<br><code class="culprit-finder-exit-url">' . esc_html( Links::recovery( $key ) ) . '</code></p>';
		echo '<p><button type="submit" class="button">' . esc_html__( 'Run again', 'culprit-finder' ) . '</button></p>';
		echo '</form>';
		echo '</section>';

		if ( is_array( $record ) && isset( $record['result'] ) ) {
			$this->report_box( Builder::build( $record ) );
		}
	}

	/**
	 * Last result card in the setup side panel, with the report behind a disclosure.
	 */
	private function render_last_result_card() {
		$record = $this->plugin->store()->last_result();
		if ( ! is_array( $record ) || ! isset( $record['result'] ) ) {
			return;
		}
		echo '<div class="cf-card cf-card--small"><h2>' . esc_html(
			sprintf(
				/* translators: %s: date */
				__( 'Last result · %s', 'culprit-finder' ),
				wp_date( get_option( 'date_format' ), (int) $record['finished_at'] )
			)
		) . '</h2>';
		echo '<p>' . esc_html( self::verdict_heading( $record['result']['type'] ) );
		$labels = array();
		foreach ( $record['result']['culprits'] as $culprit ) {
			$labels[] = Builder::label( $culprit, isset( $record['plugins'] ) ? $record['plugins'] : array() );
		}
		if ( $labels ) {
			echo ': <strong>' . esc_html( implode( ', ', $labels ) ) . '</strong>';
		}
		echo '</p>';
		echo '<details><summary>' . esc_html__( 'View report', 'culprit-finder' ) . '</summary>';
		$this->report_box( Builder::build( $record ), false );
		echo '</details></div>';
	}

	/**
	 * Report textarea + Copy button (works without JS: the text is selectable).
	 *
	 * @param string $report Report text.
	 * @param bool   $card   Wrap in a card with a heading.
	 */
	private function report_box( $report, $card = true ) {
		if ( $card ) {
			echo '<section class="cf-card cf-report"><div class="cf-report__head"><div><h2><label for="culprit-finder-report">' . esc_html__( 'Support report', 'culprit-finder' ) . '</label></h2>';
			echo '<p class="cf-help">' . esc_html__( 'Safe to paste publicly: no site address, user names or emails.', 'culprit-finder' ) . '</p></div>';
			echo '<button type="button" class="button cf-button cf-button--primary culprit-finder-copy" data-target="culprit-finder-report" data-done="' . esc_attr__( 'Copied!', 'culprit-finder' ) . '" hidden>' . esc_html__( 'Copy report', 'culprit-finder' ) . '</button></div>';
			echo '<textarea id="culprit-finder-report" class="cf-report__text" rows="13" readonly>' . esc_textarea( $report ) . '</textarea></section>';
			return;
		}
		echo '<textarea id="culprit-finder-last-report" class="cf-report__text" rows="10" readonly aria-label="' . esc_attr__( 'Support report', 'culprit-finder' ) . '">' . esc_textarea( $report ) . '</textarea>';
		echo '<button type="button" class="button culprit-finder-copy" data-target="culprit-finder-last-report" data-done="' . esc_attr__( 'Copied!', 'culprit-finder' ) . '" hidden>' . esc_html__( 'Copy report', 'culprit-finder' ) . '</button>';
	}

	/**
	 * Card for one culprit with next steps.
	 *
	 * @param string $basename Basename.
	 * @param array  $plugins  Plugin facts from the result record.
	 * @param string $type     Result type.
	 */
	private function culprit_card( $basename, array $plugins, $type ) {
		$info = isset( $plugins[ $basename ] ) ? $plugins[ $basename ] : array(
			'name'     => $basename,
			'version'  => '',
			'author'   => '',
			'uri'      => '',
			'requires' => array(),
		);
		echo '<div class="cf-culprit">';
		echo '<div class="cf-culprit__who"><h3>' . esc_html( $info['name'] ) . ' <span class="cf-muted">' . esc_html( $info['version'] ) . '</span></h3>';
		$meta = array();
		if ( '' !== $info['author'] ) {
			/* translators: %s: plugin author */
			$meta[] = esc_html( sprintf( __( 'By %s', 'culprit-finder' ), $info['author'] ) );
		}
		if ( '' !== $info['uri'] ) {
			$meta[] = '<a href="' . esc_url( $info['uri'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Plugin website', 'culprit-finder' ) . '</a>';
		}
		if ( $meta ) {
			echo '<p class="cf-muted">' . wp_kses_post( implode( ' · ', $meta ) ) . '</p>';
		}
		if ( ! empty( $info['requires'] ) ) {
			$labels = array();
			foreach ( $info['requires'] as $dep ) {
				$labels[] = Builder::label( $dep, $plugins );
			}
			/* translators: %s: list of plugin names */
			echo '<p class="cf-muted">' . esc_html( sprintf( __( 'Tested together with its required plugins: %s', 'culprit-finder' ), implode( ', ', $labels ) ) ) . '</p>';
		}
		echo '</div>';
		if ( in_array( $type, array( Result::SINGLE, Result::PAIR ), true ) ) {
			echo '<div class="cf-culprit__next"><strong>' . esc_html__( 'What you can do next', 'culprit-finder' ) . '</strong><ul>';
			/* translators: %s: Plugins screen URL */
			echo '<li>' . wp_kses_post( sprintf( __( 'Check for an update on the <a href="%s">Plugins screen</a> (after you exit).', 'culprit-finder' ), esc_url( admin_url( 'plugins.php' ) ) ) ) . '</li>';
			echo '<li>' . esc_html__( 'Send the report below to the plugin’s support forum.', 'culprit-finder' ) . '</li>';
			echo '<li>' . esc_html__( 'If you can live without it, deactivate it for everyone from the Plugins screen.', 'culprit-finder' ) . '</li>';
			echo '</ul></div>';
		}
		echo '</div>';
	}

	/**
	 * A titled list of plugin names.
	 *
	 * @param string   $title  Heading.
	 * @param string[] $labels Labels.
	 */
	private function plugin_list( $title, array $labels ) {
		echo '<div><h3>' . esc_html( $title ) . '</h3><ul>';
		if ( ! $labels ) {
			echo '<li>' . esc_html__( '(none)', 'culprit-finder' ) . '</li>';
		}
		foreach ( $labels as $label ) {
			echo '<li>' . esc_html( $label ) . '</li>';
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
	 * Short verdict heading (translatable).
	 *
	 * @param string $type Result type.
	 * @return string
	 */
	public static function verdict_heading( $type ) {
		switch ( $type ) {
			case Result::SINGLE:
				return __( 'One plugin causes the problem', 'culprit-finder' );
			case Result::PAIR:
				return __( 'Two plugins conflict with each other', 'culprit-finder' );
			case Result::COMPLEX:
				return __( 'Three or more plugins are involved', 'culprit-finder' );
			case Result::NOT_PLUGIN:
				return __( 'It’s not one of the plugins tested', 'culprit-finder' );
			case Result::NOTHING_TO_TEST:
				return __( 'There was nothing to test', 'culprit-finder' );
			default:
				return __( 'The answers didn’t add up', 'culprit-finder' );
		}
	}

	/**
	 * Plain-English explanation of a verdict (translatable).
	 *
	 * @param string $type Result type.
	 * @return string
	 */
	public static function verdict_text( $type ) {
		switch ( $type ) {
			case Result::SINGLE:
				return __( 'The problem appears whenever this plugin is on.', 'culprit-finder' );
			case Result::PAIR:
				return __( 'The problem appears only when both of these are on.', 'culprit-finder' );
			case Result::COMPLEX:
				return __( 'These two are part of it; test further with both of them on.', 'culprit-finder' );
			case Result::NOT_PLUGIN:
				return __( 'The problem stayed with all of them off. Check the theme, must-use plugins, or the server.', 'culprit-finder' );
			case Result::NOTHING_TO_TEST:
				return __( 'Every active plugin was kept on.', 'culprit-finder' );
			default:
				return __( 'The problem may come and go. Try again and check each step carefully.', 'culprit-finder' );
		}
	}

	/**
	 * Basename => "Name Version" for installed plugins.
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
