<?php
/**
 * YAML Importer Plugin init
 *
 * @package yaml-importer
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

include_once( dirname( __FILE__ ) . '/vendor/autoload.php' );

function yaim_init() {

	$init_args = array(
		'version'		=> 'wde_replace_version',
		'slug'			=> 'wde_replace_name',
		'name'			=> 'wde_replace_displayName',
		'prefix'		=> 'wde_replace_funcPrefix',
		'textdomain'	=> 'wde_replace_textDomain',
		'project_kind'	=> 'wde_replace_projectType',
		'FILE_CONST'	=> __FILE__,
		'db_version'	=> 0,
		'wde'			=> array(
			'generator-wp-dev-env'	=> 'wde_replace_generator-wp-dev-env',
			'wp-dev-env-grunt'		=> 'wde_replace_wp-dev-env-grunt',
			'wp-dev-env-frame'		=> 'wde_replace_wp-dev-env-frame',
		),
		'deps'			=> array(
			'php_version'	=> 'wde_replace_phpRequiresAtLeast',		// required php version
			'wp_version'	=> 'wde_replace_wpRequiresAtLeast',			// required wp version
			'plugins'    	=> array(
				/*
				'woocommerce' => array(
					'name'              => 'WooCommerce',               // full name
					'link'              => 'https://woocommerce.com/',  // link
					'ver_at_least'      => '3.0.0',                     // min version of required plugin
					'ver_tested_up_to'  => '3.2.1',                     // tested with required plugin up to
					'class'             => 'WooCommerce',               // test by class
					//'function'        => 'WooCommerce',               // test by function
				),
				*/
			),
			'php_ext'     => array(
				/*
				'xml' => array(
					'name'              => 'Xml',                                           // full name
					'link'              => 'http://php.net/manual/en/xml.installation.php', // link
				),
				*/
			),
		),
	);

	// see ./classes/Yaim.php
	return yaim\Yaim::get_instance( $init_args );
}
yaim_init();

?>