<?php
/**
 * Troubleshoot tab.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Admin;

use CulpritFinder\Engine\Engine;
use CulpritFinder\Engine\Step;
use CulpritFinder\Plugin;
use CulpritFinder\Session\LoaderInstaller;
use CulpritFinder\Session\Store;
use CulpritFinder\Session\Token;

defined( 'ABSPATH' ) || exit;

/**
 * Setup form, the current step, or a "result ready" panel (ADR-0018, ADR-0019).
 */
final class TroubleshootView {

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
	 * Render the tab.
	 *
	 * @param array|null $session Current session.
	 * @param bool       $owned   Whether this browser owns it.
	 */
	public function render( $session, $owned ) {
		if ( $owned ) {
			$step = $this->plugin->manager()->step( $session );
			if ( $step->is_done() ) {
				$this->render_done( $step );
			} else {
				$this->render_running( $session, $step );
			}
			return;
		}
		if ( null !== $session ) {
			$this->render_foreign_session( $session );
		}
		$this->render_setup();
	}

	/**
	 * Setup: how it works, the start form, and a one-line safety note.
	 */
	private function render_setup() {
		$manager  = $this->plugin->manager();
		$self     = plugin_basename( CULPRIT_FINDER_FILE );
		$active   = array_values( array_diff( $manager->real_active_plugins(), array( $self ) ) );
		$names    = Page::plugin_names();
		$problems = $this->environment_problems();
		$key      = Token::generate();
		$count    = count( $active );
		$steps    = 2 + Engine::ceil_log2( max( 1, $count ) );
		$minutes  = (int) ceil( $manager->ttl() / 60 );

		foreach ( $problems as $problem ) {
			echo '<div class="notice notice-error inline"><p>' . wp_kses_post( $problem ) . '</p></div>';
		}

		echo '<ol class="cf-how" aria-label="' . esc_attr__( 'How it works', 'culprit-finder' ) . '">';
		echo '<li>' . esc_html__( 'We switch plugins off, for you only', 'culprit-finder' ) . '</li>';
		echo '<li>' . esc_html__( 'You check the broken page and answer', 'culprit-finder' ) . '</li>';
		echo '<li>' . esc_html__( 'We name the culprit', 'culprit-finder' ) . '</li>';
		echo '</ol>';

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
		echo '<p class="cf-help">' . esc_html__( 'They still work if a step makes a page crash. The emergency exit is made for this session only, so press Start without reloading this page.', 'culprit-finder' ) . '</p>';
		echo '<div class="cf-links">';
		$this->safety_link( 'culprit-finder-panel-url', __( 'Control panel', 'culprit-finder' ), __( 'Answer questions even when pages are broken.', 'culprit-finder' ), Links::control_panel() );
		$this->safety_link( 'culprit-finder-exit-url', __( 'Emergency exit', 'culprit-finder' ), __( 'Ends troubleshooting at once, even when logged out. Keep it private.', 'culprit-finder' ), Links::recovery( $key ) );
		echo '</div>';
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
		echo '</div>';
		echo '<p class="cf-reassure">' . esc_html__( 'Visitors and other admins never see a change, and your plugin settings are never modified.', 'culprit-finder' ) . ' <a href="' . esc_url( Links::tools( array( 'tab' => Page::TAB_HELP ) ) ) . '">' . esc_html__( 'How it works and how to get out', 'culprit-finder' ) . '</a></p>';
		echo '</fieldset></form>';
	}

	/**
	 * One safety link with a copy button.
	 *
	 * @param string $id          Element id.
	 * @param string $title       Title.
	 * @param string $description Description.
	 * @param string $url         URL.
	 */
	private function safety_link( $id, $title, $description, $url ) {
		echo '<div class="cf-link">';
		echo '<strong>' . esc_html( $title ) . '</strong><span>' . esc_html( $description ) . '</span>';
		echo '<code id="' . esc_attr( $id ) . '">' . esc_html( $url ) . '</code>';
		echo '<button type="button" class="button culprit-finder-copy" data-target="' . esc_attr( $id ) . '" data-done="' . esc_attr__( 'Copied', 'culprit-finder' ) . '" hidden>' . esc_html__( 'Copy', 'culprit-finder' ) . '</button>';
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
		$names    = Page::plugin_names();
		$enabled  = array_values( array_diff( $step->enabled(), array( $session['self'] ) ) );
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
		Page::progress_bar( $step );
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
		$open_url   = '' !== $problem ? $problem : home_url( '/' );
		$open_label = '' !== $problem
			/* translators: %s: address of the broken page */
			? sprintf( __( 'Open %s', 'culprit-finder' ), preg_replace( '#^https?://#', '', $problem ) )
			: __( 'Open your site', 'culprit-finder' );
		echo '<p><a class="button cf-button cf-button--soft" href="' . esc_url( $open_url ) . '" target="_blank" rel="noopener">' . esc_html( $open_label ) . ' <span aria-hidden="true">↗</span></a></p>';

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
		$on = array();
		foreach ( $enabled as $basename ) {
			$label = isset( $names[ $basename ] ) ? $names[ $basename ] : $basename;
			if ( in_array( $basename, $session['pinned'], true ) ) {
				$label .= ' ' . __( '(kept on)', 'culprit-finder' );
			} elseif ( in_array( $basename, $session['fixed'], true ) ) {
				$label .= ' ' . __( '(needed by a kept-on plugin)', 'culprit-finder' );
			}
			$on[] = $label;
		}
		/* translators: %d: number of plugins */
		$this->plugin_list( sprintf( __( 'On (%d)', 'culprit-finder' ), count( $enabled ) ), $on );
		$off = array();
		foreach ( $disabled as $basename ) {
			$off[] = isset( $names[ $basename ] ) ? $names[ $basename ] : $basename;
		}
		/* translators: %d: number of plugins */
		$this->plugin_list( sprintf( __( 'Off, for you only (%d)', 'culprit-finder' ), count( $disabled ) ), $off );
		echo '</div></details>';
	}

	/**
	 * The search finished: point to the result, keep the session controls at hand.
	 *
	 * @param Step $step Done step.
	 */
	private function render_done( Step $step ) {
		$result = $step->result();
		$last   = $this->plugin->store()->last_result();
		$url    = is_array( $last ) && isset( $last['id'] ) ? Links::result( $last['id'] ) : Links::tools( array( 'tab' => Page::TAB_RESULTS ) );
		echo '<section class="cf-card cf-question">';
		echo '<p class="cf-eyebrow' . ( Page::is_found( $result['type'] ) ? ' cf-eyebrow--success' : '' ) . '">' . esc_html__( 'Result ready', 'culprit-finder' ) . '</p>';
		echo '<h2>' . esc_html( Page::verdict_heading( $result['type'] ) ) . '</h2>';
		echo '<p>' . esc_html__( 'Your site is back to normal for you while you read the result.', 'culprit-finder' ) . '</p>';
		echo '<div class="cf-secondary">';
		echo '<a class="button cf-button cf-button--primary" href="' . esc_url( $url ) . '">' . esc_html__( 'View result', 'culprit-finder' ) . '</a>';
		echo '<a class="button" href="' . esc_url( Links::action( 'undo', array(), Links::control_panel() ) ) . '">' . esc_html__( 'Undo last answer', 'culprit-finder' ) . '</a>';
		echo '<a class="button cf-button--danger" href="' . esc_url( Links::action( 'exit' ) ) . '">' . esc_html__( 'Done, exit', 'culprit-finder' ) . '</a>';
		echo '</div></section>';
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
}
