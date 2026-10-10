<?php
/**
 * Pro discovery: the Pro tab, three tips and a Plugins screen link (ADR-0025).
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Admin;

use CulpritFinder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Tells free users that Culprit Finder Pro exists, only on Culprit Finder's own screens and in one
 * Plugins screen row link. Everything here is hidden when Culprit Finder Pro is present, on
 * multisite, for users who can't manage plugins, and for users who chose "Hide Pro tips".
 * All text ships in the plugin; links only, never remote content.
 *
 * Every placement carries the attribute `data-culprit-finder-pro-tip="<placement>"` so tests can
 * find it and strip it.
 */
final class ProDiscovery {

	const PRO_BASENAME = 'culprit-finder-pro/culprit-finder-pro.php';
	const PRO_CONSTANT = 'CULPRIT_FINDER_PRO_VERSION';
	const TAB          = 'pro';
	const META         = 'culprit_finder_hide_pro_tips';
	const URL          = 'https://getculpritfinder.com/pro/';
	const ATTRIBUTE    = 'data-culprit-finder-pro-tip';

	const PLACEMENT_RESULT      = 'result';
	const PLACEMENT_PRO_TAB     = 'pro_tab';
	const PLACEMENT_SETUP       = 'setup';
	const PLACEMENT_HELP        = 'help';
	const PLACEMENT_PLUGINS_ROW = 'plugins_row';

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
	 * Hook the Plugins screen row link. The other placements are rendered by the views.
	 */
	public function register() {
		add_filter( 'plugin_action_links_' . plugin_basename( CULPRIT_FINDER_FILE ), array( $this, 'row_link' ) );
	}

	/**
	 * The plans shown on the Pro tab. The single place to edit tiers and prices.
	 * Each plan: name, sites (0 = unlimited), yearly price in US dollars, and a one-line note.
	 * All plans have the same features; only the number of sites differs.
	 *
	 * @return array<int, array{name: string, sites: int, price: int, note: string}>
	 */
	public static function plans() {
		return array(
			array(
				'name'  => __( 'Single Site', 'culprit-finder' ),
				'sites' => 1,
				'price' => 29,
				'note'  => __( 'One license for one site.', 'culprit-finder' ),
			),
			array(
				'name'  => __( '5 Sites', 'culprit-finder' ),
				'sites' => 5,
				'price' => 99,
				'note'  => __( 'One license for up to five sites.', 'culprit-finder' ),
			),
			array(
				'name'  => __( 'Unlimited Sites', 'culprit-finder' ),
				'sites' => 0,
				'price' => 199,
				'note'  => __( 'One license for every site you manage.', 'culprit-finder' ),
			),
		);
	}

	/**
	 * Whether the tips may show. Pure, so it can be unit-tested.
	 *
	 * @param bool $pro_present Culprit Finder Pro is active or its version constant is defined.
	 * @param bool $hidden      The user chose "Hide Pro tips".
	 * @param bool $can_manage  The user can manage plugins.
	 * @param bool $multisite   The site is a multisite network.
	 * @return bool
	 */
	public static function visible( $pro_present, $hidden, $can_manage, $multisite ) {
		return ! $pro_present && ! $hidden && $can_manage && ! $multisite;
	}

	/**
	 * Whether Culprit Finder Pro is present. Pure.
	 *
	 * @param string[] $active_plugins   Real (unfiltered) active plugin basenames.
	 * @param bool     $constant_defined Whether CULPRIT_FINDER_PRO_VERSION is defined.
	 * @return bool
	 */
	public static function pro_present_in( array $active_plugins, $constant_defined ) {
		return $constant_defined || in_array( self::PRO_BASENAME, $active_plugins, true );
	}

	/**
	 * Whether Culprit Finder Pro is present on this site: its version constant is defined, or it is
	 * in the real active plugin list (not the list filtered for a troubleshooting step).
	 * Call it at admin_menu time or later: Culprit Finder Pro loads after this plugin.
	 *
	 * @return bool
	 */
	public function pro_present() {
		if ( self::pro_present_in( $this->plugin->manager()->real_active_plugins(), defined( self::PRO_CONSTANT ) ) ) {
			return true;
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( self::PRO_BASENAME );
	}

	/**
	 * Whether the current user chose "Hide Pro tips".
	 *
	 * @return bool
	 */
	public function hidden() {
		$user = get_current_user_id();
		return $user > 0 && '1' === (string) get_user_meta( $user, self::META, true );
	}

	/**
	 * Whether the tips show for the current user on this request.
	 *
	 * @return bool
	 */
	public function show() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}
		return self::visible( $this->pro_present(), $this->hidden(), current_user_can( 'activate_plugins' ), is_multisite() );
	}

	/**
	 * Hide the tips for the current user, permanently.
	 */
	public function hide() {
		update_user_meta( get_current_user_id(), self::META, '1' );
	}

	/**
	 * Link to the Culprit Finder Pro page with the placement's utm parameters (unescaped).
	 *
	 * @param string $placement Placement id.
	 * @return string
	 */
	public static function url( $placement ) {
		return add_query_arg(
			array(
				'utm_source' => 'plugin',
				'utm_medium' => $placement,
			),
			self::URL
		);
	}

	/**
	 * "Get Pro" on the Plugins screen row, while the tips show.
	 *
	 * @param mixed $links Action links.
	 * @return mixed
	 */
	public function row_link( $links ) {
		if ( ! is_array( $links ) || ! $this->show() ) {
			return $links;
		}
		$links['culprit_finder_get_pro'] = '<a ' . esc_attr( self::ATTRIBUTE ) . '="' . esc_attr( self::PLACEMENT_PLUGINS_ROW ) . '" href="' . esc_url( self::url( self::PLACEMENT_PLUGINS_ROW ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Get Pro', 'culprit-finder' ) . '</a>';
		return $links;
	}

	/**
	 * Card under a result.
	 */
	public function render_result_card() {
		if ( ! $this->show() ) {
			return;
		}
		echo '<aside class="cf-card cf-tip" ' . esc_attr( self::ATTRIBUTE ) . '="' . esc_attr( self::PLACEMENT_RESULT ) . '">';
		echo '<p>' . esc_html__( 'Fixing this for a client? Culprit Finder Pro turns this result into a branded report you can send them, and next time it finds the culprit without you answering.', 'culprit-finder' ) . '</p>';
		echo '<p class="cf-tip__links">' . $this->link( self::PLACEMENT_RESULT, __( 'See Pro', 'culprit-finder' ) ) . $this->hide_link() . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- link() and hide_link() escape their parts.
		echo '</aside>';
	}

	/**
	 * One quiet line near Start on the setup screen.
	 */
	public function render_setup_line() {
		if ( ! $this->show() ) {
			return;
		}
		echo '<p class="cf-tip-line" ' . esc_attr( self::ATTRIBUTE ) . '="' . esc_attr( self::PLACEMENT_SETUP ) . '">' . esc_html__( 'Prefer hands-free? Culprit Finder Pro checks the page at every step for you.', 'culprit-finder' ) . ' ' . $this->link( self::PLACEMENT_SETUP, __( 'Learn more', 'culprit-finder' ) ) . $this->hide_link() . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- link() and hide_link() escape their parts.
	}

	/**
	 * FAQ entry on the Help tab.
	 */
	public function render_help_entry() {
		if ( ! $this->show() ) {
			return;
		}
		echo '<section ' . esc_attr( self::ATTRIBUTE ) . '="' . esc_attr( self::PLACEMENT_HELP ) . '">';
		echo '<h2>' . esc_html__( 'Is there a Pro version?', 'culprit-finder' ) . '</h2>';
		echo '<p>' . esc_html__( 'Yes. Culprit Finder Pro is an add-on that checks the page for you at every step, tests your theme, and turns results into branded client reports. This plugin stays free and works fully without it.', 'culprit-finder' ) . '</p>';
		echo '<p class="cf-tip__links">' . $this->link( self::PLACEMENT_HELP, __( 'About Culprit Finder Pro', 'culprit-finder' ) ) . $this->hide_link() . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- link() and hide_link() escape their parts.
		echo '</section>';
	}

	/**
	 * Whether the free Pro tab should be added to these tabs (none with id `pro` yet, tips showing).
	 *
	 * @param array $tabs Tab id => label, after the add-on filter.
	 * @return bool
	 */
	public function adds_tab( array $tabs ) {
		return ! isset( $tabs[ self::TAB ] ) && $this->show();
	}

	/**
	 * The Pro tab: what Culprit Finder Pro adds, the plans, and one Get Pro button.
	 */
	public function render_tab() {
		if ( ! $this->show() ) {
			return;
		}
		$features = array(
			'auto'    => array( __( 'Check it for me', 'culprit-finder' ), __( 'Describe what broken looks like, and Culprit Finder Pro opens the page at every step and answers for you.', 'culprit-finder' ) ),
			'theme'   => array( __( 'Theme check', 'culprit-finder' ), __( 'One extra check tells you whether your theme is involved, without changing your real theme.', 'culprit-finder' ) ),
			'reports' => array( __( 'Client reports', 'culprit-finder' ), __( 'Turn any result into a branded, plain-English report you can print or save as a PDF for your client.', 'culprit-finder' ) ),
		);

		echo '<div class="cf-pro" ' . esc_attr( self::ATTRIBUTE ) . '="' . esc_attr( self::PLACEMENT_PRO_TAB ) . '">';
		echo '<section class="cf-card cf-pro__intro">';
		echo '<h2>' . esc_html__( 'Culprit Finder Pro', 'culprit-finder' ) . '</h2>';
		echo '<p>' . esc_html__( 'An add-on for this plugin, for people who troubleshoot often or for clients.', 'culprit-finder' ) . '</p>';
		echo '</section>';

		echo '<div class="cf-pro__features">';
		foreach ( $features as $id => $feature ) {
			echo '<section class="cf-card cf-pro__feature">';
			$this->illustration( $id );
			echo '<h3>' . esc_html( $feature[0] ) . '</h3><p>' . esc_html( $feature[1] ) . '</p>';
			echo '</section>';
		}
		echo '</div>';

		echo '<section class="cf-card cf-pro__plans" aria-labelledby="culprit-finder-plans">';
		echo '<h2 id="culprit-finder-plans">' . esc_html__( 'Plans', 'culprit-finder' ) . '</h2>';
		echo '<p class="cf-pro__price">' . esc_html__( 'From $29/year per site', 'culprit-finder' ) . '</p>';
		echo '<p class="cf-help">' . esc_html__( 'Every plan has the same features. Only the number of sites differs.', 'culprit-finder' ) . '</p>';
		echo '<ul class="cf-pro__plan-list">';
		foreach ( self::plans() as $plan ) {
			echo '<li class="cf-pro__plan"><strong>' . esc_html( $plan['name'] ) . '</strong>';
			/* translators: %d: price in US dollars */
			echo '<span class="cf-pro__amount">' . esc_html( sprintf( __( '$%d/year', 'culprit-finder' ), (int) $plan['price'] ) ) . '</span>';
			echo '<span class="cf-muted">' . esc_html( $plan['note'] ) . '</span></li>';
		}
		echo '</ul>';
		echo '<ul class="cf-pro__facts"><li>' . esc_html__( '14-day refund', 'culprit-finder' ) . '</li><li>' . esc_html__( 'Your settings and history stay. Pro adds to this plugin.', 'culprit-finder' ) . '</li></ul>';
		echo '<p class="cf-pro__cta"><a class="button cf-button cf-button--primary cf-button--large" href="' . esc_url( self::url( self::PLACEMENT_PRO_TAB ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Get Pro', 'culprit-finder' ) . '</a>' . $this->hide_link() . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hide_link() escapes its parts.
		echo '</section>';
		echo '</div>';
	}

	/**
	 * A small static picture of a feature, built from the plugin's own styles.
	 *
	 * @param string $id Feature id.
	 */
	private function illustration( $id ) {
		echo '<div class="cf-mini cf-mini--' . esc_attr( $id ) . '" aria-hidden="true">';
		if ( 'auto' === $id ) {
			echo '<div class="cf-mini__bar"><span class="cf-mini__seg cf-mini__seg--done"></span><span class="cf-mini__seg cf-mini__seg--done"></span><span class="cf-mini__seg cf-mini__seg--now"></span><span class="cf-mini__seg"></span></div>';
			echo '<div class="cf-mini__row"><span class="cf-mini__dot cf-mini__dot--ok"></span><span class="cf-mini__line"></span></div>';
			echo '<div class="cf-mini__row"><span class="cf-mini__dot cf-mini__dot--ok"></span><span class="cf-mini__line cf-mini__line--short"></span></div>';
			echo '<div class="cf-mini__row"><span class="cf-mini__dot cf-mini__dot--now"></span><span class="cf-mini__line cf-mini__line--mid"></span></div>';
		} elseif ( 'theme' === $id ) {
			echo '<div class="cf-mini__themes"><span class="cf-mini__theme"><span class="cf-mini__head"></span><span class="cf-mini__line"></span></span><span class="cf-mini__theme cf-mini__theme--default"><span class="cf-mini__head"></span><span class="cf-mini__line"></span></span></div>';
		} else {
			echo '<div class="cf-mini__doc"><span class="cf-mini__brand"></span><span class="cf-mini__line"></span><span class="cf-mini__line cf-mini__line--mid"></span><span class="cf-mini__line cf-mini__line--short"></span></div>';
		}
		echo '</div>';
	}

	/**
	 * Link to the Culprit Finder Pro page for a placement (escaped HTML).
	 *
	 * @param string $placement Placement id.
	 * @param string $label     Link text.
	 * @return string
	 */
	private function link( $placement, $label ) {
		return '<a href="' . esc_url( self::url( $placement ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $label ) . '</a>';
	}

	/**
	 * Small "Hide Pro tips" link that returns to the current page (escaped HTML).
	 *
	 * @return string
	 */
	private function hide_link() {
		return ' <a class="cf-tip__hide" href="' . esc_url( Links::action( 'hide_pro_tips', array(), Links::current_url() ) ) . '">' . esc_html__( 'Hide Pro tips', 'culprit-finder' ) . '</a>';
	}
}
