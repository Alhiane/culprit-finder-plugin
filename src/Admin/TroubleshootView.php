<?php
/**
 * Troubleshoot tab.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Admin;

use CulpritFinder\Engine\Engine;
use CulpritFinder\Engine\Step;
use CulpritFinder\Hooks;
use CulpritFinder\Plugin;
use CulpritFinder\Session\AddOns;
use CulpritFinder\Session\LoaderInstaller;
use CulpritFinder\Session\Store;
use CulpritFinder\Session\Token;
use CulpritFinder\Session\View;

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
	 * Setup (ADR-0026): numbered sections on the left (problem page, add-on section, plugins to keep
	 * on), a sidebar on the right with the summary, the saved-links box, Start and the safety links.
	 * Both columns sit in one form. After a start an add-on refused, the form keeps what the user
	 * entered, including the emergency exit link they saved.
	 */
	private function render_setup() {
		$manager  = $this->plugin->manager();
		$self     = plugin_basename( CULPRIT_FINDER_FILE );
		$active   = array_values( array_diff( $manager->real_active_plugins(), array( $self ) ) );
		$always   = $manager->always_on( $manager->real_active_plugins() );
		$plugins  = self::plugin_headers();
		$problems = $this->environment_problems();
		$kept     = $this->plugin->start_input();
		$key      = null !== $kept && ! empty( $kept['recovery'] ) && Token::is_valid_format( $kept['recovery'] ) ? $kept['recovery'] : Token::generate();
		$typed    = null !== $kept && isset( $kept['problem_url'] ) && is_string( $kept['problem_url'] ) ? $kept['problem_url'] : '';
		$ticked   = null !== $kept && isset( $kept['pinned'] ) && is_array( $kept['pinned'] ) ? $kept['pinned'] : array();
		$required = true === Hooks::filter( 'culprit_finder_problem_url_required', false, null );
		$count    = count( $active ) - count( $always );
		$steps    = $count > 0 ? 2 + Engine::ceil_log2( $count ) : 0;
		$minutes  = (int) ceil( $manager->ttl() / 60 );
		$addon    = $this->setup_fields( null !== $kept && isset( $kept['addon'] ) && is_array( $kept['addon'] ) ? $kept['addon'] : array() );
		$number   = 0;

		foreach ( $problems as $problem ) {
			echo '<div class="notice notice-error inline"><p>' . wp_kses_post( $problem ) . '</p></div>';
		}

		echo '<form class="cf-setup" method="post" action="' . esc_url( Links::form_action() ) . '">';
		wp_nonce_field( 'culprit_finder_start' );
		echo '<input type="hidden" name="action" value="culprit_finder_start">';
		echo '<input type="hidden" name="culprit_finder_recovery" value="' . esc_attr( $key ) . '">';
		echo '<div class="cf-setup__grid"><div class="cf-card cf-setup__main">';

		$this->section_start( ++$number );
		echo '<fieldset class="cf-field"><legend>' . esc_html__( 'Where do you see the problem?', 'culprit-finder' ) . ' <span class="cf-optional">' . ( $required ? esc_html__( 'Required', 'culprit-finder' ) : esc_html__( 'Optional', 'culprit-finder' ) ) . '</span></legend>';
		echo '<label class="cf-help" for="culprit-finder-problem-url">' . esc_html__( 'Paste the address of the broken page. Every step gets a one-click link to it.', 'culprit-finder' ) . '</label>';
		echo '<input type="url" class="regular-text cf-input" id="culprit-finder-problem-url" name="culprit_finder_problem_url" placeholder="' . esc_attr( home_url( '/' ) ) . '"' . ( '' !== $typed ? ' value="' . esc_attr( $typed ) . '"' : '' ) . ( $required ? ' required' : '' ) . '>';
		echo '</fieldset></div>';

		if ( self::has_visible_content( $addon ) ) {
			$this->section_start( ++$number, 'cf-section--addon' );
			echo $addon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- add-on output from culprit_finder_setup_fields; callbacks escape their own output (docs/hooks.md).
			echo '</div>';
		} elseif ( '' !== trim( $addon ) ) {
			echo $addon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- add-on output from culprit_finder_setup_fields (hidden fields only); callbacks escape their own output (docs/hooks.md).
		}

		$this->section_start( ++$number );
		echo '<fieldset class="cf-field"><legend>' . esc_html__( 'Keep any plugins on?', 'culprit-finder' ) . ' <span class="cf-optional">' . esc_html__( 'Optional', 'culprit-finder' ) . '</span></legend>';
		echo '<p class="cf-help">' . esc_html__( 'Tick plugins the problem needs to show up, like your shop plugin for a checkout problem. They stay on in every step and are never blamed. Plugins they require stay on too.', 'culprit-finder' ) . '</p>';
		if ( $active ) {
			$this->plugin_tiles( $active, $always, $ticked, $plugins );
		} else {
			echo '<p class="cf-help">' . esc_html__( 'No other plugins are active, so there is nothing to test.', 'culprit-finder' ) . '</p>';
		}
		echo '</fieldset></div>';

		echo '</div><div class="cf-setup__side">';
		$this->ready_card( $count, $steps, $minutes, null !== $kept, (bool) $problems );
		$this->safety_card( $key );
		echo '<p class="cf-setup__more"><a href="' . esc_url( Links::tools( array( 'tab' => Page::TAB_HELP ) ) ) . '">' . esc_html__( 'How it works and how to get out', 'culprit-finder' ) . '</a></p>';
		echo '</div></div></form>';
	}

	/**
	 * Open one numbered section row (closed by the caller with `</div>`).
	 *
	 * @param int    $number    Section number.
	 * @param string $modifier  Extra class.
	 */
	private function section_start( $number, $modifier = '' ) {
		echo '<div class="cf-section' . ( '' !== $modifier ? ' ' . esc_attr( $modifier ) : '' ) . '"><span class="cf-section__num" aria-hidden="true">' . esc_html( number_format_i18n( $number ) ) . '</span>';
	}

	/**
	 * Output of `culprit_finder_setup_fields` callbacks (all of them, in one buffer).
	 *
	 * @param array $values Kept add-on values after a refused start, else an empty array.
	 * @return string
	 */
	private function setup_fields( array $values ) {
		ob_start();
		Hooks::action( 'culprit_finder_setup_fields', $values );
		return (string) ob_get_clean();
	}

	/**
	 * Whether add-on markup shows anything: text, or a control other than a hidden input. Comments,
	 * scripts, styles, templates and hidden inputs alone don't count, so they never make an empty
	 * numbered section. Pure, for unit tests.
	 *
	 * @param string $html Markup.
	 * @return bool
	 */
	public static function has_visible_content( $html ) {
		$html = preg_replace( '#<!--.*?-->|<(script|style|template|noscript)\b[^>]*>.*?</\1\s*>#is', '', (string) $html );
		$text = html_entity_decode( strip_tags( (string) $html ), ENT_QUOTES, 'UTF-8' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- pure function for unit tests; scripts and styles are removed above.
		if ( '' !== (string) preg_replace( '/[\s\x{00A0}]+/u', '', $text ) ) {
			return true;
		}
		if ( preg_match( '#<(select|textarea|button|img|svg|video|audio|iframe|canvas|progress|meter|object|embed)\b#i', (string) $html ) ) {
			return true;
		}
		if ( preg_match_all( '#<input\b[^>]*>#i', (string) $html, $inputs ) ) {
			foreach ( $inputs[0] as $input ) {
				if ( ! preg_match( '#\btype\s*=\s*(["\']?)hidden\1#i', $input ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Plugins to keep on as tiles; add-ons that stay on by themselves as grey rows under them.
	 *
	 * @param string[] $active  Active plugins without Culprit Finder.
	 * @param string[] $always  Add-ons that stay on.
	 * @param string[] $ticked  Plugins ticked before a refused start.
	 * @param array    $plugins Basename => array( name, version ).
	 */
	private function plugin_tiles( array $active, array $always, array $ticked, array $plugins ) {
		$tiles = array_values( array_diff( $active, $always ) );
		if ( $tiles ) {
			echo '<div class="cf-tiles">';
			foreach ( $tiles as $basename ) {
				$data = isset( $plugins[ $basename ] ) ? $plugins[ $basename ] : array( $basename, '' );
				printf(
					'<label class="cf-tile"><input type="checkbox" name="culprit_finder_pin[]" value="%1$s"%2$s> <span class="cf-tile__name">%3$s</span>%4$s</label>',
					esc_attr( $basename ),
					in_array( $basename, $ticked, true ) ? ' checked' : '',
					esc_html( $data[0] ),
					'' !== $data[1] ? ' <span class="cf-tile__version">' . esc_html( $data[1] ) . '</span>' : ''
				);
			}
			echo '</div>';
		}
		foreach ( array_values( array_intersect( $active, $always ) ) as $basename ) {
			$data = isset( $plugins[ $basename ] ) ? $plugins[ $basename ] : array( $basename, '' );
			printf(
				'<label class="cf-always"><input type="checkbox" checked disabled> <span class="cf-always__name">%1$s</span> <span class="cf-always__note">%2$s</span></label>',
				esc_html( trim( $data[0] . ' ' . $data[1] ) ),
				esc_html__( 'Always on · Culprit Finder add-on', 'culprit-finder' )
			);
		}
	}

	/**
	 * Sidebar card: what will happen, the saved-links box and Start.
	 *
	 * @param int  $count    Plugins to test.
	 * @param int  $steps    Estimated steps (0 when there is nothing to test).
	 * @param int  $minutes  Idle minutes before the session ends by itself.
	 * @param bool $saved    Tick the saved-links box (kept input after a refused start).
	 * @param bool $blocked  An environment problem (shown above the form) keeps Start off.
	 */
	private function ready_card( $count, $steps, $minutes, $saved, $blocked ) {
		$disabled = $blocked || ! $count;
		echo '<section class="cf-card cf-ready" aria-labelledby="culprit-finder-ready">';
		echo '<h2 class="cf-ready__title" id="culprit-finder-ready">' . esc_html__( 'Ready to start', 'culprit-finder' ) . '</h2>';
		echo '<div class="cf-stats">';
		echo '<p class="cf-stat"><strong class="cf-stat__num">' . esc_html( number_format_i18n( $count ) ) . '</strong> <span class="cf-stat__label">' . esc_html( _n( 'plugin to test', 'plugins to test', $count, 'culprit-finder' ) ) . '</span></p>';
		if ( $steps > 0 ) {
			echo '<p class="cf-stat"><strong class="cf-stat__num" aria-hidden="true">~' . esc_html( number_format_i18n( $steps ) ) . '</strong> <span class="cf-stat__label" aria-hidden="true">' . esc_html( _n( 'step', 'steps', $steps, 'culprit-finder' ) ) . '</span>';
			/* translators: %d: estimated number of steps */
			echo '<span class="screen-reader-text">' . esc_html( sprintf( _n( 'about %d step', 'about %d steps', $steps, 'culprit-finder' ), $steps ) ) . '</span></p>';
		} else {
			echo '<p class="cf-stat"><strong class="cf-stat__num">0</strong> <span class="cf-stat__label">' . esc_html__( 'steps', 'culprit-finder' ) . '</span></p>';
		}
		echo '</div>';
		echo '<ul class="cf-checks">';
		echo '<li>' . esc_html__( 'Visitors and other admins never see a change', 'culprit-finder' ) . '</li>';
		echo '<li>' . esc_html__( 'Your plugin settings are never modified', 'culprit-finder' ) . '</li>';
		/* translators: %d: minutes */
		echo '<li>' . esc_html( sprintf( _n( 'Ends by itself after %d idle minute', 'Ends by itself after %d idle minutes', $minutes, 'culprit-finder' ), $minutes ) ) . '</li>';
		echo '</ul>';
		echo '<label class="cf-saved"><input type="checkbox" name="culprit_finder_saved" value="1" required' . ( $saved ? ' checked' : '' ) . '> <span><strong>' . esc_html__( 'I’ve saved both safety links', 'culprit-finder' ) . '</strong> <span class="cf-saved__help">' . esc_html__( 'They’re below and still work if a step makes a page crash.', 'culprit-finder' ) . '</span></span></label>';
		echo '<div class="cf-start">';
		echo '<button type="submit" class="button cf-button cf-button--primary cf-button--large" aria-describedby="culprit-finder-start-help"' . ( $disabled ? ' disabled' : '' ) . '>' . esc_html__( 'Start troubleshooting', 'culprit-finder' ) . '</button>';
		if ( $blocked ) {
			$help = __( 'Start stays off until the problem shown above is fixed.', 'culprit-finder' );
		} elseif ( ! $count ) {
			$help = __( 'There are no other active plugins to test.', 'culprit-finder' );
		} else {
			$help = __( 'Press Start without reloading this page: the emergency exit is made for this session only.', 'culprit-finder' );
		}
		echo '<p class="cf-start__help" id="culprit-finder-start-help" data-locked="' . esc_attr__( 'Tick the box above to enable Start.', 'culprit-finder' ) . '">' . esc_html( $help ) . '</p>';
		echo '</div>';
		$this->plugin->pro_discovery()->render_setup_line();
		echo '</section>';
	}

	/**
	 * Sidebar card: the control panel and emergency exit links.
	 *
	 * @param string $key Recovery key of this page load.
	 */
	private function safety_card( $key ) {
		echo '<section class="cf-card cf-safety" aria-labelledby="culprit-finder-safety">';
		echo '<h2 class="cf-safety__title" id="culprit-finder-safety">' . esc_html__( 'Safety links', 'culprit-finder' ) . '</h2>';
		echo '<p class="cf-help">' . esc_html__( 'Bookmark or copy these before you start.', 'culprit-finder' ) . '</p>';
		echo '<div class="cf-links">';
		$this->safety_link( 'culprit-finder-panel-url', __( 'Control panel', 'culprit-finder' ), __( 'Answer questions even when pages are broken.', 'culprit-finder' ), Links::control_panel() );
		$this->safety_link( 'culprit-finder-exit-url', __( 'Emergency exit', 'culprit-finder' ), __( 'Ends troubleshooting at once, even when logged out. Keep it private.', 'culprit-finder' ), Links::recovery( $key ), 'cf-link--exit' );
		echo '</div></section>';
	}

	/**
	 * One safety link with a copy button.
	 *
	 * @param string $id          Element id.
	 * @param string $title       Title.
	 * @param string $description Description.
	 * @param string $url         URL.
	 * @param string $modifier    Extra class.
	 */
	private function safety_link( $id, $title, $description, $url, $modifier = '' ) {
		echo '<div class="cf-link' . ( '' !== $modifier ? ' ' . esc_attr( $modifier ) : '' ) . '">';
		echo '<div class="cf-link__head"><strong id="' . esc_attr( $id . '-title' ) . '">' . esc_html( $title ) . '</strong>';
		echo '<button type="button" class="button culprit-finder-copy" data-target="' . esc_attr( $id ) . '" data-done="' . esc_attr__( 'Copied', 'culprit-finder' ) . '" aria-describedby="' . esc_attr( $id . '-title' ) . '" hidden>' . esc_html__( 'Copy', 'culprit-finder' ) . '</button></div>';
		echo '<span>' . esc_html( $description ) . '</span>';
		echo '<code id="' . esc_attr( $id ) . '">' . esc_html( $url ) . '</code>';
		echo '</div>';
	}

	/**
	 * Name and version of every installed plugin, by basename.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	private static function plugin_headers() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$headers = array();
		foreach ( get_plugins() as $basename => $data ) {
			$headers[ $basename ] = array( (string) $data['Name'], (string) $data['Version'] );
		}
		return $headers;
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
	 * Running: progress and the question. An add-on that prints into `culprit_finder_step_panel`
	 * replaces the question, its buttons and Undo; progress, Stop and exit and the plugin lists stay.
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
		$always   = AddOns::of( $session );
		$panel    = $this->step_panel( $session, $step );

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
		if ( '' !== $panel ) {
			echo $panel; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- add-on output from culprit_finder_step_panel; callbacks escape their own output (docs/hooks.md).
		} else {
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
		}

		echo '<div class="cf-secondary">';
		if ( '' === $panel && $step->answers_used() > 0 ) {
			echo '<a class="button" href="' . esc_url( Links::action( 'undo', array(), $redirect ) ) . '">' . esc_html__( 'Undo last answer', 'culprit-finder' ) . '</a>';
		}
		echo '<a class="button cf-button--danger" href="' . esc_url( Links::action( 'exit' ) ) . '">' . esc_html__( 'Stop and exit', 'culprit-finder' ) . '</a>';
		echo '<span class="cf-muted">' . esc_html__( 'You can also answer from the purple toolbar on any page. If WordPress emails you about a critical error, that is expected while troubleshooting.', 'culprit-finder' ) . '</span>';
		echo '</div></section>';

		echo '<details class="cf-card cf-details"><summary>' . esc_html__( 'Which plugins are on in this step', 'culprit-finder' ) . '</summary><div class="cf-lists">';
		$on = array();
		foreach ( $enabled as $basename ) {
			$label = isset( $names[ $basename ] ) ? $names[ $basename ] : $basename;
			if ( in_array( $basename, $always, true ) ) {
				$label .= ' ' . __( '(always on · Culprit Finder add-on)', 'culprit-finder' );
			} elseif ( in_array( $basename, $session['pinned'], true ) ) {
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
	 * Output of `culprit_finder_step_panel` callbacks, or '' when none printed anything.
	 *
	 * @param array $session Session.
	 * @param Step  $step    Current step.
	 * @return string
	 */
	private function step_panel( array $session, Step $step ) {
		ob_start();
		Hooks::action( 'culprit_finder_step_panel', $step->to_array(), View::of( $session ) );
		$panel = (string) ob_get_clean();
		return '' === trim( $panel ) ? '' : $panel;
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
		$problem = LoaderInstaller::problem();
		if ( LoaderInstaller::PROBLEM_MULTISITE === $problem ) {
			return array( esc_html__( 'Culprit Finder does not support multisite networks yet, so troubleshooting cannot start here.', 'culprit-finder' ) );
		}
		if ( LoaderInstaller::PROBLEM_FOREIGN === $problem ) {
			return array(
				sprintf(
					/* translators: %s: file path */
					esc_html__( 'A different file already exists at %s. Culprit Finder never overwrites files it didn’t create. If that file isn’t needed, rename or remove it with FTP or your host’s file manager, then reload this page.', 'culprit-finder' ),
					'<code>' . esc_html( LoaderInstaller::target() ) . '</code>'
				),
			);
		}
		if ( '' === $problem ) {
			return array();
		}
		$error   = get_option( Store::LOADER_ERROR );
		$message = esc_html__( 'When you press Start, Culprit Finder adds a small helper file to wp-content/mu-plugins and removes it when you’re done. That folder isn’t writable on this site.', 'culprit-finder' );
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
