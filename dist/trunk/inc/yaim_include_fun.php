<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

function yaim_include_fun() {

	$paths = array(
		'/inc/fun/yaim_init_cmb2_cmb2.php',
	);

	if ( count( $paths ) > 0 ) {
		foreach( $paths as $path ) {
			include_once( yaim\Yaim::get_instance()->dir_path . $path );
		}
	}

}

?>