<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * May be init composer package cmb2/cmb2
 *
 */
function yaim_init_cmb2_cmb2() {
	$path = yaim\Yaim::get_instance()->dir_path . 'vendor/cmb2/cmb2/init.php';
	if ( file_exists( $path ) ) {
		require_once $path;
	}
}
add_action( 'after_setup_theme', 'yaim_init_cmb2_cmb2', 1 );
