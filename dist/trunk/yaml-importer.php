<?php
/*
	Plugin Name: YAML Importer
	Plugin URI: https://github.com/croox/yaml-importer
	Description: Import Posts from YAML
	Version: 0.1.0
	Author: croox
	Author URI: https://github.com/croox
	License: GNU General Public License v2 or later
	License URI: http://www.gnu.org/licenses/gpl-2.0.html
	Text Domain: yaim
	Domain Path: /languages
	Tags: import yaml bulk
	GitHub Plugin URI: https://github.com/croox/yaml-importer
	Release Asset: true
*/
?><?php
/**
 * Yaml Importer Plugin init
 *
 * @package WordPress
 * @subpackage yaml-importer
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

include_once( dirname( __FILE__ ) . '/vendor/autoload.php' );

function yaim_init() {

	$init_args = array(
		'version'		=> '0.1.0',
		'slug'			=> 'yaml-importer',
		'name'			=> 'YAML Importer',
		'prefix'		=> 'yaim',
		'textdomain'	=> 'yaim',
		'project_kind'	=> 'plugin',
		'FILE_CONST'	=> __FILE__,
		'db_version'	=> 0,
		'wde'			=> array(
			'generator-wp-dev-env'	=> '0.10.4',
			'wp-dev-env-grunt'		=> '0.8.5',
			'wp-dev-env-frame'		=> '0.7.2',
		),
		'deps'			=> array(
			'php_version'	=> '7.0.0',		// required php version
			'wp_version'	=> '5.0.0',			// required wp version
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