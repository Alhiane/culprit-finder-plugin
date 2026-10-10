<?php
/**
 * Plugin Name: Culprit Finder Pro (test stub)
 * Description: Culprit Finder test fixture at Pro's basename. Adds a `pro` tab the way Culprit Finder Pro does (culprit_finder_admin_tabs + culprit_finder_render_tab_pro) and nothing else. It does not define CULPRIT_FINDER_PRO_VERSION, so detection by active state is what gets tested. Copied into wp-content/plugins only by the pro-discovery E2E scenario. Never install on a real site.
 * Version: 0.0.1
 * License: GPLv2 or later
 *
 * @package CulpritFinderFixtures
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'culprit_finder_admin_tabs',
	function ( $tabs ) {
		if ( is_array( $tabs ) ) {
			$tabs['pro'] = 'Pro';
		}
		return $tabs;
	}
);

add_action(
	'culprit_finder_render_tab_pro',
	function () {
		echo '<p>CFF-PRO-STUB-TAB</p>';
	}
);
