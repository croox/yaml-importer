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
			foreach( $file_data['posts'] as $post_i => $post_raw_data ) {

				// clean post_raw_data from not allowed fields
				foreach( array(
					'ID',
					'guid',
				) as $not_allowed ) {
					if ( array_key_exists( $not_allowed, $post_raw_data ) ) {
						unset( $post_raw_data[$not_allowed] );
					}
				}

				// check if is_wpml_import for this post
				$is_wpml_import = false;
				foreach( $post_raw_data as $key => $attr ) {
					if ( $is_wpml_import )
						break;
					$is_wpml_import = is_array( $attr )
						&& substr( array_keys( $attr )[0], 0, strlen( 'wpml_' )) === 'wpml_';
				}

				$this->posts[$post_i] = array(
					'is_wpml_import'	=> $is_wpml_import,
					'inserted'			=> array(),
					'atts' 				=> $this->classify_post_atts_by_lang( $post_raw_data ),
					// 'post_raw_data'			=> $post_raw_data,
				);

			}

			// classify_post_atts_by_validy for wp_insert_post function
			// and fix_insert_post_args
			foreach( $this->posts as $post_i => $post ) {
				if ( $post['is_wpml_import'] ) {
					foreach( $post['atts'] as $lang => $atts ) {
						if ( 'all' === $lang )
							continue;

						// merge atts for all into current atts_by_lang
						$atts = array_merge(
							$atts,
							utils\Arr::get( $this->posts, $post_i . '.atts.all', array() )
						);

						$atts = $this->classify_post_atts_by_validy( $atts, $post['is_wpml_import'] );

						$atts = $this->fix_insert_post_args( $atts );

						$this->posts[$post_i]['atts'][$lang] = apply_filters( 'yaim_post_atts', $atts, $lang, $post );
					}
				} else {
					$atts = $this->classify_post_atts_by_validy( utils\Arr::get( $post, 'atts.all', array() ), $post['is_wpml_import'] );

					$atts = $this->fix_insert_post_args( $atts );

					$this->posts[$post_i]['atts']['all'] = apply_filters( 'yaim_post_atts', $atts, 'all', $post );
				}
			}

		}
	}

	protected function fix_insert_post_args( $atts ) {

		foreach( array(
			'insert_post_args',
			'deferred_insert_post_args',
		) as $to_fix ) {

			// for hierarchical taxonomies, tax_input should be array of taxonomy term ids
			// for nonhierarchical taxonomies, tax_input should be array of taxonomy term slugs
			if ( array_key_exists( $to_fix, $atts ) &&
				array_key_exists( 'tax_input', $atts[$to_fix] )
			) {
				foreach( $atts[$to_fix]['tax_input'] as $tax_slug => $terms ) {
					$tax = get_taxonomy( $tax_slug );

					if ( $tax->hierarchical ) {
						foreach( $terms as $term_i => $term_slug_or_id ) {
							if ( is_numeric( $term_slug_or_id ) && $term_slug_or_id == (int) $term_slug_or_id )
								continue;
							$term = get_term_by( 'slug', $term_slug_or_id, $tax->name );

							if ( $term )
								$atts[$to_fix]['tax_input'][$tax_slug][$term_i] = $term->term_id;
						}
					} else {
						foreach( $terms as $term_slug_or_id ) {
							if ( is_string( $term_slug_or_id ) )
								continue;
							$term = get_term_by( 'id', $term_slug_or_id, $tax->name );
							if ( $term )
								$atts[$to_fix]['tax_input'][$tax_slug][$term_i] = $term->slug;
						}
					}
				}
			}

		}

		return $atts;
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
		if ( $post['is_wpml_import'] ) {

			foreach( $post['atts'] as $lang => $atts_by_lang ) {
				if ( 'all' === $lang )
					continue;

				$post_id = wp_insert_post( $atts_by_lang['insert_post_args'] );

				if ( is_wp_error( $post_id ) )
					continue;

				$post['inserted'][$lang] = $post_id;
				$this->posts[$i]['inserted'][$lang] = $post_id;

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

			// ??? do the deferred
			foreach( $post['atts'] as $lang => $atts_by_lang ) {
				if ( 'all' === $lang )
					continue;

				if ( ! array_key_exists( 'deferred_insert_post_args', $atts_by_lang )
					|| empty( $atts_by_lang['deferred_insert_post_args'] ) )
					continue;

				$post_id = utils\Arr::get( $post, 'inserted.' . $lang, false );

				if ( ! $post_id )
					continue;

				$args = array_merge(
					array(
						'ID' => $post_id,
						'post_type' => utils\Arr::get( $atts_by_lang, 'insert_post_args.post_type' ),
						// 'post_excerpt' => $post_id,
					),
					$atts_by_lang['deferred_insert_post_args']
				);

				if ( ! array_key_exists( 'post_title', $args ) )
					$args['post_title'] = utils\Arr::get( $atts_by_lang, 'insert_post_args.post_title' );

				$post_id = wp_update_post( $args, true );
			}


			do_action( 'yaim_post_inserted', $this->posts[$i], $post_trid );

		} else {
			$post_id = wp_insert_post( utils\Arr::get( $post, 'atts.all.insert_post_args', array() ) );
			$this->posts[$i]['inserted']['all'] = $post_id;
			if ( ! is_wp_error( $post_id ) ) {
				do_action( 'yaim_post_inserted', $this->posts[$i], null );
			}

		}

		if ( class_exists( 'SitePress' ) ) {
			do_action( 'wpml_switch_language', $current_lang );
		}
	}

	protected function classify_post_atts_by_validy( $post_atts, $use_deferred ) {

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

		// ??? should check wpml actually,
		// may be more synchronized and should be deferred: eg ping_status, post_password ...
		$deferred_insert_post_args = array(
			'post_category',
			'tags_input',
			'tax_input',
			'meta_input',
		);

		$classified_post_atts = array(
			'insert_post_args'			=> array(),
			'deferred_insert_post_args'	=> array(),
			'custom_args'				=> array(),
		);

		foreach( $post_atts as $key => $val ) {
			if ( $use_deferred && in_array( $key, $deferred_insert_post_args ) ) {
				$classified_post_atts['deferred_insert_post_args'][$key] = $val;
			} elseif ( in_array( $key, $valid_insert_post_args ) ) {
				$classified_post_atts['insert_post_args'][$key] = $val;
			} else {
				$classified_post_atts['custom_args'][$key] = $val;
			}
		}

		return $classified_post_atts;
	}

	protected function classify_post_atts_by_lang( $post_raw_data ) {
		$atts = array();
		foreach( $post_raw_data as $key => $attr ) {
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