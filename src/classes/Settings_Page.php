<?php

namespace yaim;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

use croox\wde\utils;

class Settings_Page {

	protected $main_class;

	protected $uploads_dir_path;

	protected $cmb_id = 'yaim_options';

	protected $import_posts;
	protected $import_terms;

	protected $suported_types = array(
		'posts',
		// 'terms',
	);


	protected $log;

	protected static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			// $required_args = array(
			// 	'main_class',
			// );
			// foreach( $required_args as $required_arg ) {
			// 	if ( ! array_key_exists( $required_arg, $args ) || empty( $args[$required_arg] ) ) {
			// 		error_log( __FILE__ );
			// 		return new \WP_Error( 'missing_arg',  sprintf( __( 'Argument "%s" missing', 'yaim' ), $required_arg ) );
			// 	}
			// }
			self::$instance = new self();
		}
		return self::$instance;
	}


	public function __construct(){
		// $this->main_class = $args['main_class'];
		// $this->uploads_dir_path = WP_CONTENT_DIR . '/' . call_user_func( array( $this->main_class, 'get_instance' ) )->slug;
		add_action( 'cmb2_admin_init', array( $this, 'options_page_metabox' ) );
		add_action( 'cmb2_options-page_process_fields_' . $this->cmb_id, array( $this, 'process_fields' ), 10, 2 );
		add_action( 'init', array( $this, 'init_importers' ) );
	}

	public function init_importers() {
		foreach( $this->suported_types as $type ) {
			$importer = 'import_' . $type;
			$importer_class = __NAMESPACE__ . '\Import_' . ucfirst( $type );
			$this->$importer = new $importer_class();
		}

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
			'save_button'     => esc_html__( 'Start Import', 'yaim' ), // The text for the options-page save button. Defaults to 'Save'.
			// 'disable_settings_errors' => true, // On settings pages (not options-general.php sub-pages), allows disabling.
			'message_cb'      => array( $this, 'message_cb' ),
			// 'tab_group'       => '', // Tab-group identifier, enables options page tab navigation.
			// 'tab_title'       => null, // Falls back to 'title' (above).
			// 'autoload'        => false, // Defaults to true, the options-page option will be autloaded.
		) );

		// select the file
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

	function render_empty_field( $field_args, $field ) {}

	public function options_cb( $field ) {
		$path = WP_CONTENT_DIR . '/yaml-importer/';
		$files = glob( $path .'*.yaml');
		$labels = array_map( function( $file ) use ( $path ) {
			return str_replace( $path, '', $file );
		}, $files );
		return array_combine( $files, $labels );
	}


	// Do some processing just before the fileds are saved
	public function process_fields( $cmb, $cmb_id ) {

		$admin_message_log = new Admin_Message_Log();

		$admin_message_log->add_entry( 'Start Import' );

		$file = utils\Arr::get( $cmb->data_to_save, 'file' );

		if ( empty( $file ) )
			return;

		$file_data = \Spyc::YAMLLoad( $file );

		// ??? validate $file_data

		foreach( $file_data as $type => $items ) {
			if( ! in_array( $type, $this->suported_types ) ) {
				$admin_message_log->add_entry( "ERROR: import for {$type} not supported" );
				continue;
			}

			$importer = 'import_' . $type;

			foreach( $items as $item_raw_data ) {
				$this->$importer->push_to_queue( $item_raw_data );
			}
			$this->$importer->save()->dispatch();

			$admin_message_log->add_entry( 'All ' . count( $items ) . ' ' . $type . ' queued for import' );
		}

		$log_path = WP_CONTENT_DIR . '/yaml-importer/import.log';
		$admin_message_log->add_entry( 'Start import in background, check the import.log ' . $log_path );

		$admin_message_log->save();
	}

	/**
	 * Callback to define the optionss-saved message.
	 *
	 * @param CMB2  $cmb The CMB2 object.
	 * @param array $args {
	 *     An array of message arguments
	 *
	 *     @type bool   $is_options_page Whether current page is this options page.
	 *     @type bool   $should_notify   Whether options were saved and we should be notified.
	 *     @type bool   $is_updated      Whether options were updated with save (or stayed the same).
	 *     @type string $setting         For add_settings_error(), Slug title of the setting to which
	 *                                   this error applies.
	 *     @type string $code            For add_settings_error(), Slug-name to identify the error.
	 *                                   Used as part of 'id' attribute in HTML output.
	 *     @type string $message         For add_settings_error(), The formatted message text to display
	 *                                   to the user (will be shown inside styled `<div>` and `<p>` tags).
	 *                                   Will be 'Settings updated.' if $is_updated is true, else 'Nothing to update.'
	 *     @type string $type            For add_settings_error(), Message type, controls HTML class.
	 *                                   Accepts 'error', 'updated', '', 'notice-warning', etc.
	 *                                   Will be 'updated' if $is_updated is true, else 'notice-warning'.
	 * }
	 */
	public function message_cb( $cmb, $args ) {
		if ( empty( $args['should_notify'] ) )
			return;

		$admin_message_log = Admin_Message_Log::get_from_db();

		$log_path = WP_CONTENT_DIR . '/yaml-importer/import.log';

		if ( $admin_message_log['last_log_saved'] ) {
			$args['message'] = implode( '</br>', $admin_message_log['msgs'] );
			$args['type'] = strpos( $args['message'], 'ERROR' ) !== false
				? 'notice-warning'
				: 'updated';
		} else {
			$args['message'] = 'Something went wrong';
			$args['type'] = 'error';
		}

		add_settings_error( $args['setting'], $args['code'], $args['message'], $args['type'] );
	}
}