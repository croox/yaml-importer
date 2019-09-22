<?php

namespace yaim;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

use croox\wde\utils;

class Settings_Page {

	protected $main_class;

	protected $cmb_id;

	protected $importer;

	protected static $instance = null;

	public static function get_instance( $args = array() ) {
		if ( null === self::$instance ) {
			$required_args = array(
				'main_class',
			);
			foreach( $required_args as $required_arg ) {
				if ( ! array_key_exists( $required_arg, $args ) || empty( $args[$required_arg] ) ) {
					error_log( __FILE__ );
					return new \WP_Error( 'missing_arg',  sprintf( __( 'Argument "%s" missing', 'yaim' ), $required_arg ) );
				}
			}
			self::$instance = new self( $args );
		}
		return self::$instance;
	}


	public function __construct( $args ){
		$this->main_class = $args['main_class'];
		$this->cmb_id = 'yaim_options';
		add_action( 'cmb2_admin_init', array( $this, 'options_page_metabox' ) );
		add_action( 'cmb2_options-page_process_fields_' . $this->cmb_id, array( $this, 'process_fields' ), 10, 2 );

	}

	public function options_page_metabox() {

		/**
		 * Registers options page menu item and form.
		 */
		$cmb = new_cmb2_box( array(
			'id'           => $this->cmb_id,
			'title'        => esc_html__( 'YAML Importer', 'yaim' ),
			'object_types' => array( 'options-page' ),

			/*
			 * The following parameters are specific to the options-page box
			 * Several of these parameters are passed along to add_menu_page()/add_submenu_page().
			 */

			'option_key'      => $this->cmb_id, // The option key and admin menu page slug.
			// 'icon_url'        => 'dashicons-download', // Menu icon. Only applicable if 'parent_slug' is left empty.
			// 'menu_title'      => esc_html__( 'Options', 'yaim' ), // Falls back to 'title' (above).
			'parent_slug'     => 'tools.php', // Make options page a submenu item of the themes menu.
			// 'capability'      => 'manage_options', // Cap required to view options-page.
			// 'position'        => 1, // Menu position. Only applicable if 'parent_slug' is left empty.
			// 'admin_menu_hook' => 'network_admin_menu', // 'network_admin_menu' to add network-level options page.
			// 'display_cb'      => false, // Override the options-page form output (CMB2_Hookup::options_page_output()).
			// 'save_button'     => esc_html__( 'Save Theme Options', 'yaim' ), // The text for the options-page save button. Defaults to 'Save'.
			// 'disable_settings_errors' => true, // On settings pages (not options-general.php sub-pages), allows disabling.
			// 'message_cb'      => 'yaim_options_page_message_callback',
			// 'tab_group'       => '', // Tab-group identifier, enables options page tab navigation.
			// 'tab_title'       => null, // Falls back to 'title' (above).
			// 'autoload'        => false, // Defaults to true, the options-page option will be autloaded.
		) );

		$cmb->add_field( array(
			'name'             	=> 'Test Select',
			'desc'             	=> 'Select a YAML file from your wp-content/yaml_importer directory.',
			'id'               	=> 'file',
			'type'             	=> 'select',
			'show_option_none'	=> false,
			'save_field' 		=> false,
			'options_cb'       	=> array( $this, 'options_cb' ),
		) );
	}


	public function options_cb( $field ) {
		$uploads_dir_path = WP_CONTENT_DIR . '/' . call_user_func( array( $this->main_class, 'get_instance' ) )->slug;
		$files = glob( $uploads_dir_path . '/*');

		$labels = array_map( function( $file ) use ( $uploads_dir_path ) {
			return str_replace( $uploads_dir_path . '/', '', $file );
		}, $files );

		return array_combine( $files, $labels );
	}


	// Do some processing just before the fileds are saved
	public function process_fields( $object_id, $cmb_id ) {

		$file = utils\Arr::get( $object_id->data_to_save, 'file' );

		if ( empty( $file ) )
			return;

		$this->importer = new Importer( array(
			'file' => $file,
		) );
	}


}