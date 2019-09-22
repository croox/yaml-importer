<?php

namespace yaim;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

use croox\wde\utils;

class Importer {

	protected $file;

	protected $post = array();

	protected $active_langs = array();

	protected $is_wpml_import;

	public function __construct( $args ){
		$required_args = array(
			'file',
		);

		foreach( $required_args as $required_arg ) {
			if ( ! array_key_exists( $required_arg, $args ) || empty( $args[$required_arg] ) ) {
				error_log( __FILE__ );
				return new \WP_Error( 'missing_arg',  sprintf( __( 'Argument "%s" missing', 'yaim' ), $required_arg ) );
			}
		}

		$this->active_langs = array_map( function( $lang ) {
			return $lang['language_code'];
		}, apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) ) );

		$this->file = $args['file'];

		$this->start();
	}

	protected function start() {

		if ( empty( $this->file ) )
			return;

		$data = \Spyc::YAMLLoad( $this->file );

		// clean data from not allowed fields
		foreach( array(
			'ID',
			'guid',
		) as $i => $not_allowed ) {
			if ( array_key_exists( $not_allowed, $data ) )
				unset( $data[$not_allowed] );
		}

		// is_wpml_import
		$this->is_wpml_import = false;
		foreach( $data as $key => $attr ) {
			if ( $this->is_wpml_import )
				break;
			$this->is_wpml_import = is_array( $attr )
				&& substr( array_keys( $attr )[0], 0, strlen( 'wpml_' )) === 'wpml_';
		}

		if ( $this->is_wpml_import && ! class_exists( 'SitePress' ) )
			return;

		if ( is_wp_error( $this->setup_post_atts( $data ) ) )
			return;

		$this->insert_post( $data );

	}

	protected function insert_post() {

		if ( class_exists( 'SitePress' ) ) {
			global $sitepress;
			$current_lang = apply_filters( 'wpml_current_language', null );
			$default_lang = apply_filters( 'wpml_default_language', null );
			do_action( 'wpml_switch_language', $default_lang );
		}

		$post_trid = null;
		$instered = array();
		if ( $this->is_wpml_import ) {
			foreach( $this->post as $lang => $post_atts ) {
				if ( 'all' === $lang )
					continue;

				// merge post_atts for all into current post_atts
				$post_atts = array_merge(
					$post_atts,
					utils\Arr::get( $this->post, 'all', array() )
				);

				$post_atts = apply_filters( 'yaim_post_atts_wpml',
					$this->classify_post_atts( $post_atts ),
					$lang,
					$post_trid
				);

				$this->post[$lang] = $post_atts;

				$post_id = wp_insert_post( $post_atts['insert_post_args'] );

				if ( is_wp_error( $post_id ) )
					continue;

				$instered[$lang] = $post_id;

				// post_trid of first inserted post
				$post_trid = null === $post_trid
					? $sitepress->get_element_trid( $post_id )
					: $post_trid;

				do_action( 'yaim_post_wpml_inserted_translation',
					$post_id,
					$post_atts,
					$lang,
					$post_trid
				);

				$translation_id = $sitepress->set_element_language_details(
					$post_id,
					'post_' . get_post_type( $post_id ),
					$post_trid,
					$lang
				);

				do_action( 'yaim_post_wpml_language_set',
					$post_id,
					$post_atts,
					$lang,
					$post_trid,
					$translation_id
				);

			}

			do_action( 'yaim_post_wpml_all_inserted_translated',
				$this->post,
				$post_trid,
				$instered
			);

		} else {
			$post_atts = apply_filters( 'yaim_post_atts',
				$this->classify_post_atts( utils\Arr::get( $this->post, 'all' ) )
			);
			$this->post['all'] = $post_atts;

			$post_id = wp_insert_post( $post_atts['insert_post_args'] );

			if ( ! is_wp_error( $post_id ) ) {
				do_action( 'yaim_post_inserted',
					$this->post,
					$post_id
				);
			}

		}

		if ( class_exists( 'SitePress' ) ) {
			do_action( 'wpml_switch_language', $current_lang );
		}
	}

	protected function classify_post_atts( $post_atts ) {

		$valid_insert_post_args = array(
			'post_author',
			'post_date',
			'post_date_gmt',
			'post_content',
			'post_content_filtered',
			'post_title',
			'post_excerpt',
			'post_status',
			'post_type',
			'comment_status',
			'ping_status',
			'post_password',
			'post_name',
			'to_ping',
			'pinged',
			'post_modified',
			'post_modified_gmt',
			'post_parent',
			'menu_order',
			'post_mime_type',
			'post_category',
			'tags_input',
			'tax_input',
			'meta_input',
		);

		$insert_post_args = array();
		$custom_args = array();
		foreach( $post_atts as $key => $val ) {
			if ( in_array( $key, $valid_insert_post_args ) ) {
				$insert_post_args[$key] = $val;
			} else {
				$custom_args[$key] = $val;
			}
		}

		return array(
			'insert_post_args'	=> $insert_post_args,
			'custom_args'		=> $custom_args,
		);

	}

	protected function setup_post_atts( $data ) {
		$post = $this->post;
		foreach( $data as $key => $attr ) {
			if ( is_array( $attr ) ) {
				$keys_starts_wpml_ = array_unique( array_map( function( $k ) {
					return substr( $k, 0, strlen( 'wpml_' ) ) === 'wpml_';
				}, array_keys( $attr ) ) );

				if ( count( $keys_starts_wpml_ ) > 1 ) // something wrong, mixed language fields
					continue;

				if ( $keys_starts_wpml_[0] ) {	// all keys start with wpml_
					foreach( $attr as $lang => $val ) {
						$lang = str_replace( 'wpml_', '', $lang );
						if ( in_array( $lang, $this->active_langs ) ) {
							if ( in_array( $key, array(
								'post_type',
								// ??? may be more
							) ) ) {
								return new \WP_Error( 'attr_not_translatable', sprintf( __( '"%s" is not translatable.', 'yaim' ), $key ) );
							} else {
								$post[$lang][$key] = $val;
							}

						} else {
							// something wrong, lang not active
						}
					}
				} else { // is normal field
					$post['all'][$key] = $attr;
				}

			} else { // is normal field
				$post['all'][$key] = $attr;
			}
		}
		$this->post = $post;
	}

}