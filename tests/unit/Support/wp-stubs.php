<?php
/**
 * Minimal WordPress function stubs for loading the MU loader without WordPress.
 * Every call is recorded in $GLOBALS['cf_stub_calls'].
 *
 * @package CulpritFinder
 */

$GLOBALS['cf_stub_calls']   = array();
$GLOBALS['cf_stub_options'] = array();
$GLOBALS['cf_stub_filters'] = array();

function cf_stub_record( $name, $args ) {
	$GLOBALS['cf_stub_calls'][] = array( $name, $args );
}
function get_option( $name, $default = false ) {
	cf_stub_record( __FUNCTION__, func_get_args() );
	return array_key_exists( $name, $GLOBALS['cf_stub_options'] ) ? $GLOBALS['cf_stub_options'][ $name ] : $default;
}
function delete_option( $name ) {
	cf_stub_record( __FUNCTION__, func_get_args() );
	unset( $GLOBALS['cf_stub_options'][ $name ] );
	return true;
}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	cf_stub_record( __FUNCTION__, func_get_args() );
	$GLOBALS['cf_stub_filters'][ $hook ][] = $callback;
	return true;
}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	cf_stub_record( __FUNCTION__, func_get_args() );
	return true;
}
function wp_doing_cron() {
	cf_stub_record( __FUNCTION__, array() );
	return false;
}
function wp_installing() {
	cf_stub_record( __FUNCTION__, array() );
	return false;
}
function is_multisite() {
	cf_stub_record( __FUNCTION__, array() );
	return ! empty( $GLOBALS['cf_stub_multisite'] );
}
function is_ssl() {
	return false;
}
