<?php
/**
 * Plugin Name: CFF Request Probe
 * Description: Culprit Finder test fixture. Reports which cff-fixtures marker plugins were loaded for this request, over admin-ajax, REST, a front-end form POST, a redirect and an iframe page. Never install on a real site.
 * Version: 1.0.0
 * License: GPLv2 or later
 *
 * @package CulpritFinderFixtures
 */

defined( 'ABSPATH' ) || exit;

/**
 * Marker plugins actually loaded in this request (from the included files, not the option).
 *
 * @param string $via How the request arrived.
 * @return string e.g. "CFF-LOADED[via=ajax;cff-noise-01,cff-solo]"
 */
function cff_request_probe_report( $via ) {
	$loaded = array();
	foreach ( get_included_files() as $file ) {
		if ( preg_match( '#/cff-fixtures/(cff-(?:noise-\d+|solo|pair-a|pair-b))\.php$#', str_replace( '\\', '/', $file ), $m ) ) {
			$loaded[] = $m[1];
		}
	}
	sort( $loaded );
	return 'CFF-LOADED[via=' . preg_replace( '/[^a-z-]/', '', $via ) . ';' . implode( ',', $loaded ) . ']';
}

/**
 * The admin-ajax action, for logged-in users and visitors.
 */
function cff_request_probe_ajax() {
	echo esc_html( cff_request_probe_report( 'ajax' ) );
	wp_die();
}
add_action( 'wp_ajax_cff_probe', 'cff_request_probe_ajax' );
add_action( 'wp_ajax_nopriv_cff_probe', 'cff_request_probe_ajax' );

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'cff/v1',
			'/probe',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'permission_callback' => '__return_true',
				'callback'            => function () {
					return array( 'probe' => cff_request_probe_report( 'rest' ) );
				},
			)
		);
	}
);

add_action(
	'template_redirect',
	function () {
		// phpcs:disable WordPress.Security.NonceVerification -- test fixture, read-only.
		if ( isset( $_GET['cff_probe_redirect'] ) ) {
			header( 'X-CFF-Probe: ' . cff_request_probe_report( 'redirect-hop' ) );
			wp_safe_redirect( home_url( '/?cff_probe=redirect' ) );
			exit;
		}
		if ( isset( $_GET['cff_probe_frame'] ) ) {
			echo '<!doctype html><title>frame host</title><iframe id="cff-frame" src="' . esc_url( home_url( '/?cff_probe=iframe' ) ) . '"></iframe>';
			exit;
		}
		$via = '';
		if ( isset( $_POST['cff_probe_form'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$via = 'form-post';
		} elseif ( isset( $_GET['cff_probe'] ) ) {
			$via = sanitize_key( wp_unslash( $_GET['cff_probe'] ) );
		}
		// phpcs:enable
		if ( '' !== $via ) {
			echo '<!doctype html><title>probe</title><p id="cff-probe">' . esc_html( cff_request_probe_report( $via ) ) . '</p>';
			exit;
		}
	}
);
