<?php
/**
 * The Culprit Finder admin page (top-level menu) and its tabs.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Admin;

use CulpritFinder\Engine\Result;
use CulpritFinder\Engine\Step;
use CulpritFinder\Hooks;
use CulpritFinder\Plugin;
use CulpritFinder\Session\View;

defined( 'ABSPATH' ) || exit;

/**
 * Menu, assets, header, tabs and notices; each tab renders through its own view (ADR-0018, ADR-0019).
 */
final class Page {

	const SLUG = 'culprit-finder';

	const TAB_TROUBLESHOOT = 'troubleshoot';
	const TAB_RESULTS      = 'results';
	const TAB_HELP         = 'help';

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
		if ( '' !== $this->hook ) {
			add_action( 'load-' . $this->hook, array( $this, 'auto_answer' ) );
		}
	}

	/**
	 * Let an auto-answer provider answer steps (`culprit_finder_auto_answer`, ADR-0021).
	 * Honored only on the Troubleshoot tab of the browser that owns the session, and only for
	 * exactly 'yes' or 'no'; anything else leaves the question to the user. After automatic
	 * answers the browser's cookie gets the session's new expiry, like after a manual answer.
	 */
	public function auto_answer() {
		$owned = $this->plugin->owned_session();
		if ( null === $owned || ! current_user_can( 'activate_plugins' ) || self::TAB_TROUBLESHOOT !== $this->current_tab() ) {
			return;
		}
		$manager  = $this->plugin->manager();
		$answered = false;
		for ( $i = 0; $i < 64; $i++ ) {
			$session = $manager->current();
			if ( null === $session ) {
				break;
			}
			$step = $manager->step( $session );
			if ( $step->is_done() ) {
				break;
			}
			$answer = Hooks::filter( 'culprit_finder_auto_answer', null, $step->to_array(), View::of( $session ) );
			if ( 'yes' !== $answer && 'no' !== $answer ) {
				break;
			}
			$manager->answer( 'yes' === $answer );
			$answered = true;
		}
		if ( $answered ) {
			$this->plugin->refresh_cookie();
		}
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
	 * Tabs: the three core tabs first, then add-on tabs from `culprit_finder_admin_tabs` (ADR-0021).
	 *
	 * @return array<string, string> Tab id => label.
	 */
	public function tabs() {
		$core  = array(
			self::TAB_TROUBLESHOOT => __( 'Troubleshoot', 'culprit-finder' ),
			self::TAB_RESULTS      => __( 'Results', 'culprit-finder' ),
			self::TAB_HELP         => __( 'Help', 'culprit-finder' ),
		);
		$extra = Hooks::filter( 'culprit_finder_admin_tabs', $core );
		$tabs  = $core;
		if ( is_array( $extra ) ) {
			foreach ( $extra as $id => $label ) {
				$id = sanitize_key( (string) $id );
				if ( '' !== $id && ! isset( $core[ $id ] ) && is_string( $label ) && '' !== $label ) {
					$tabs[ $id ] = $label;
				}
			}
		}
		return $tabs;
	}

	/**
	 * Current tab from the request (core or add-on), defaulting to Troubleshoot.
	 *
	 * @return string
	 */
	public function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		return isset( $this->tabs()[ $tab ] ) ? $tab : self::TAB_TROUBLESHOOT;
	}

	/**
	 * Render the page.
	 */
	public function render() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$session = $this->plugin->manager()->current();
		$owned   = null !== $session && null !== $this->plugin->owned_session();
		$tab     = $this->current_tab();

		echo '<div class="wrap culprit-finder">';
		$this->render_header();
		echo '<hr class="wp-header-end">';
		$this->render_notices();
		$this->render_recovery_mode_notice( $owned );
		$this->render_tabs( $tab, $owned );

		echo '<div class="cf-panel">';
		if ( self::TAB_RESULTS === $tab ) {
			( new ResultsView( $this->plugin ) )->render( $session, $owned );
		} elseif ( self::TAB_HELP === $tab ) {
			( new HelpView() )->render();
		} elseif ( self::TAB_TROUBLESHOOT !== $tab ) {
			Hooks::action( 'culprit_finder_render_tab_' . $tab, null !== $session && $owned ? View::of( $session ) : null );
		} else {
			( new TroubleshootView( $this->plugin ) )->render( $session, $owned );
		}
		echo '</div></div>';
	}

	/**
	 * Title row with the symbol and tagline.
	 */
	private function render_header() {
		echo '<header class="cf-header">';
		echo '<img class="culprit-finder-symbol" src="' . esc_url( plugins_url( 'assets/images/symbol.svg', CULPRIT_FINDER_FILE ) ) . '" alt="" height="40">';
		echo '<div class="cf-header__text"><h1>' . esc_html__( 'Culprit Finder', 'culprit-finder' ) . '</h1>';
		echo '<p>' . esc_html__( 'Find the plugin that broke your site. Visitors never notice.', 'culprit-finder' ) . '</p></div>';
		echo '</header>';
	}

	/**
	 * The tab bar: a dot on Troubleshoot during a session, a count on Results.
	 *
	 * @param string $current Current tab.
	 * @param bool   $running Whether this browser has a session.
	 */
	private function render_tabs( $current, $running ) {
		$count = count( $this->plugin->store()->results() );
		$tabs  = $this->tabs();
		echo '<nav class="cf-tabs" aria-label="' . esc_attr__( 'Culprit Finder sections', 'culprit-finder' ) . '">';
		foreach ( $tabs as $id => $label ) {
			$args = self::TAB_TROUBLESHOOT === $id ? array() : array( 'tab' => $id );
			if ( $running ) {
				$args['culprit_safe'] = 1;
			}
			$active = $id === $current;
			echo '<a class="cf-tab' . ( $active ? ' cf-tab--active' : '' ) . '" href="' . esc_url( Links::tools( $args ) ) . '"' . ( $active ? ' aria-current="page"' : '' ) . '>' . esc_html( $label );
			if ( self::TAB_TROUBLESHOOT === $id && $running ) {
				echo '<span class="cf-tab__dot" role="img" aria-label="' . esc_attr__( 'Troubleshooting is running', 'culprit-finder' ) . '"></span>';
			}
			if ( self::TAB_RESULTS === $id && $count ) {
				echo '<span class="cf-tab__count">' . esc_html( number_format_i18n( $count ) ) . '</span>';
			}
			echo '</a>';
		}
		echo '</nav>';
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
			'deleted' => array( 'success', __( 'Result deleted.', 'culprit-finder' ) ),
			'cleared' => array( 'success', __( 'All results deleted.', 'culprit-finder' ) ),
		);
		if ( isset( $messages[ $notice ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $messages[ $notice ][0] ), esc_html( $messages[ $notice ][1] ) );
		}

		$kept = $this->plugin->start_input();
		if ( null !== $kept && isset( $kept['message'] ) && is_string( $kept['message'] ) ) {
			printf( '<div class="notice notice-error culprit-finder-not-started"><p>%s</p></div>', esc_html( $kept['message'] ) );
		}

		$store = $this->plugin->store();
		$last  = $store->last_result();
		if ( is_array( $last ) && ! empty( $last['session_expired_at'] ) ) {
			$message = ! empty( $last['session_max_reached'] )
				? __( 'Your troubleshooting session reached its maximum length and was ended. The site is back to normal for you.', 'culprit-finder' )
				: __( 'Your troubleshooting session expired after an hour without answers and was ended. The site is back to normal for you.', 'culprit-finder' );
			printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( $message ) );
			unset( $last['session_expired_at'], $last['session_max_reached'] );
			if ( isset( $last['result'] ) ) {
				$store->save_last_result( $last );
			} else {
				$store->delete_last_result();
			}
		}
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
	 * Short label for the results table badge (translatable).
	 *
	 * @param string $type Result type.
	 * @return string
	 */
	public static function verdict_badge( $type ) {
		switch ( $type ) {
			case Result::SINGLE:
				return __( 'One plugin', 'culprit-finder' );
			case Result::PAIR:
				return __( 'Two plugins conflict', 'culprit-finder' );
			case Result::COMPLEX:
				return __( 'Three or more plugins', 'culprit-finder' );
			case Result::NOT_PLUGIN:
				return __( 'Not a plugin', 'culprit-finder' );
			case Result::NOTHING_TO_TEST:
				return __( 'Nothing to test', 'culprit-finder' );
			default:
				return __( 'Inconsistent answers', 'culprit-finder' );
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
	 * Whether a result type names its culprits with confidence.
	 *
	 * @param string $type Result type.
	 * @return bool
	 */
	public static function is_found( $type ) {
		return in_array( $type, array( Result::SINGLE, Result::PAIR ), true );
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
