<?php
/**
 * PHPUnit bootstrap: no WordPress. Source files guard on ABSPATH, so define it.
 *
 * @package CulpritFinder
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
