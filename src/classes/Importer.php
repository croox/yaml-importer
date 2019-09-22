<?php

namespace yaim;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

use croox\wde\utils;


class Importer {

	protected $posts = array();

	protected $active_langs = array();

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

		$file_data = \Spyc::YAMLLoad( $args['file'] );

		$this->setup_import_data( $file_data );
		$this->insert_objects();
	}

	protected function setup_import_data( $file_data ) {

		// parse posts and setup post atts
		if ( array_key_exists( 'posts', $file_data ) ) {

			// classify_post_atts_by_lang and check is_wpml_import
			foreach( $file_data['posts'] as $i => $raw_data ) {

				// clean raw_data from not allowed fields
				foreach( array(
					'ID',
					'guid',
				) as $i => $not_allowed ) {
					if ( array_key_exists( $not_allowed, $raw_data ) ) {
						unset( $raw_data[$not_allowed] );
					}
				}

				// check if is_wpml_import for this post
				$is_wpml_import = false;
				foreach( $raw_data as $key => $attr ) {
					if ( $is_wpml_import )
						break;
					$is_wpml_import = is_array( $attr )
						&& substr( array_keys( $attr )[0], 0, strlen( 'wpml_' )) === 'wpml_';
				}

				$this->posts[$i] = array(
					'is_wpml_import'	=> $is_wpml_import,
					'instered'			=> array(),
					'atts' 				=> $this->classify_post_atts_by_lang( $raw_data ),
					// 'raw_data'			=> $raw_data,
				);
			}

			// classify_post_atts_by_validy for wp_insert_post function
			foreach( $this->posts as $i => $post ) {
				if ( $post['is_wpml_import'] ) {
					foreach( $post['atts'] as $lang => $atts ) {
						if ( 'all' === $lang )
							continue;

						// merge atts for all into current atts_by_lang
						$atts = array_merge(
							$atts,
							utils\Arr::get( $this->posts, $i . '.atts.all', array() )
						);

						$atts = $this->classify_post_atts_by_validy( $atts );

						$this->posts[$i]['atts'][$lang] = apply_filters( 'yaim_post_atts', $atts, $lang, $post );
					}
				} else {
					$atts = $this->classify_post_atts_by_validy( utils\Arr::get( $post, 'atts.all', array() ) );

					$this->posts[$i]['atts']['all'] = apply_filters( 'yaim_post_atts', $atts, 'all', $post );
				}
			}

		}
	}

	protected function insert_objects() {
		// insert posts
		foreach( $this->posts as $i => $post ) {
			if ( is_wp_error( $post['atts'] ) )
				continue;
			$this->insert_post( $i, $post );
		}
	}

	protected function insert_post( $i, $post ) {

		if ( class_exists( 'SitePress' ) ) {
			global $sitepress;
			$current_lang = apply_filters( 'wpml_current_language', null );
			$default_lang = apply_filters( 'wpml_default_language', null );
			do_action( 'wpml_switch_language', $default_lang );
		}

		$post_trid = null;
		// $instered = array();
		if ( $post['is_wpml_import'] ) {
			foreach( $post['atts'] as $lang => $atts_by_lang ) {
				if ( 'all' === $lang )
					continue;

				$post_id = wp_insert_post( $atts_by_lang['insert_post_args'] );

				if ( is_wp_error( $post_id ) )
					continue;

				$this->posts[$i]['instered'][$lang] = $post_id;

				// post_trid of first inserted post
				$post_trid = null === $post_trid
					? $sitepress->get_element_trid( $post_id )
					: $post_trid;

				do_action( 'yaim_post_inserted_for_wpml_translation',
					$post_id,
					$this->posts[$i],
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
					$this->posts[$i],
					$lang,
					$post_trid,
					$translation_id
				);

			}

			do_action( 'yaim_post_inserted', $this->posts[$i], $post_trid );

		} else {
			$post_id = wp_insert_post( utils\Arr::get( $post, 'atts.all.insert_post_args', array() ) );
			$this->posts[$i]['instered']['all'] = $post_id;
			if ( ! is_wp_error( $post_id ) ) {
				do_action( 'yaim_post_inserted', $this->posts[$i], null );
			}

		}

		if ( class_exists( 'SitePress' ) ) {
			do_action( 'wpml_switch_language', $current_lang );
		}
	}

	protected function classify_post_atts_by_validy( $post_atts ) {

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

	protected function classify_post_atts_by_lang( $raw_data ) {
		$atts = array();
		foreach( $raw_data as $key => $attr ) {
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
								$atts[$lang][$key] = $val;
							}

						} else {
							// something wrong, lang not active
						}
					}
				} else { // is normal field
					$atts['all'][$key] = $attr;
				}

			} else { // is normal field
				$atts['all'][$key] = $attr;
			}
		}
		return $atts;
	}

}