<?php
/**
 * Help tab.
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * How it works, safety, ways out, and limits.
 */
final class HelpView {

	/**
	 * Render the tab.
	 */
	public function render() {
		echo '<div class="cf-help-page">';

		$this->section(
			__( 'How it works', 'culprit-finder' ),
			'<p>' . esc_html__( 'Culprit Finder switches plugins off for your browser only and asks after each change whether the problem is still there. It narrows the list down by halves, so 30 plugins take about 7 answers. It also catches two plugins that only fail together.', 'culprit-finder' ) . '</p>'
		);

		$this->section(
			__( 'What happens to my site?', 'culprit-finder' ),
			self::items(
				array(
					esc_html__( 'Visitors and other admins always see every plugin.', 'culprit-finder' ),
					esc_html__( 'Your real plugin settings never change. Nothing is deactivated.', 'culprit-finder' ),
					esc_html__( 'Plugin management is paused for you while troubleshooting runs.', 'culprit-finder' ),
					esc_html__( 'Results stay on this site. Nothing is sent anywhere.', 'culprit-finder' ),
				)
			)
		);

		$this->section(
			__( 'How to get out', 'culprit-finder' ),
			self::items(
				array(
					wp_kses_post( __( '<strong>Stop and exit</strong> on this page, in the dashboard widget, or in the toolbar.', 'culprit-finder' ) ),
					wp_kses_post( __( 'The <strong>emergency exit</strong> link you saved. It works even when you are logged out.', 'culprit-finder' ) ),
					esc_html__( 'Wait an hour without answering: troubleshooting ends by itself.', 'culprit-finder' ),
					wp_kses_post( __( 'Deactivate Culprit Finder, or delete <code>wp-content/mu-plugins/culprit-finder-loader.php</code> with FTP or your host’s file manager.', 'culprit-finder' ) ),
					esc_html__( 'Clear your browser cookies or use another browser: only your browser was affected.', 'culprit-finder' ),
				)
			)
		);

		$this->section(
			__( 'A step shows a critical error', 'culprit-finder' ),
			'<p>' . esc_html__( 'That usually means the plugins switched on in that step are the problem: answer Yes. Open your bookmarked control panel to answer when the page itself is broken. WordPress may email you about the error; that is expected while troubleshooting.', 'culprit-finder' ) . '</p>'
		);

		$this->section(
			__( 'Whole site down?', 'culprit-finder' ),
			'<p>' . wp_kses_post( __( 'Open the recovery link WordPress emailed you and log in, start here, then click <strong>Exit Recovery Mode</strong> in the toolbar before you answer. If the email never arrives, ask another administrator, use WP-CLI, or rename the plugin’s folder with FTP to get back in.', 'culprit-finder' ) ) . '</p>'
		);

		$this->section(
			__( 'What it can’t test', 'culprit-finder' ),
			'<p>' . esc_html__( 'Your theme, must-use plugins, drop-ins such as object-cache.php, and server settings. If the problem stays with every plugin off, the result says so.', 'culprit-finder' ) . '</p>'
		);

		echo '</div>';
	}

	/**
	 * One help section.
	 *
	 * @param string $title Heading.
	 * @param string $html  Trusted, already-escaped body markup.
	 */
	private function section( $title, $html ) {
		echo '<section><h2>' . esc_html( $title ) . '</h2>' . wp_kses_post( $html ) . '</section>';
	}

	/**
	 * A bullet list of already-escaped items.
	 *
	 * @param string[] $items Items.
	 * @return string
	 */
	private static function items( array $items ) {
		return '<ul><li>' . implode( '</li><li>', $items ) . '</li></ul>';
	}
}
