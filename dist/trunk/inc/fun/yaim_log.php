<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

function yaim_log( $msg = '' ) {
	$path = WP_CONTENT_DIR . '/yaml-importer/import.log';
	error_log(
		sprintf( '[%s] %s %s', date('d-M-Y H:i:s T'), $msg, PHP_EOL ),
		3,
		$path
	);
}