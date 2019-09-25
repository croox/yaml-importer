<?php

namespace yaim;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

use croox\wde\utils;

class Import_Posts extends Import_Base {


	protected $action = 'import_posts_process';


	protected static $type = 'post';

	protected static  $autop_keys = array(
		'post_content',
		'post_excerpt',
	);



	protected static function setup_import_data( $object_raw_data ) {

		// clean object_raw_data from not allowed fields
		foreach( array(
			'ID',
			'guid',
		) as $not_allowed ) {
			if ( array_key_exists( $not_allowed, $object_raw_data ) ) {
				unset( $object_raw_data[$not_allowed] );
			}
		}

		return array(
			'is_wpml_import'	=> static::is_wpml_import( $object_raw_data ),
			'inserted'			=> array(),
			'atts' 				=> static::classify_atts_by_lang( $object_raw_data, array(
				'post_type',
				// ??? may be more
			) ),
		);

	}

	protected static function classify_atts_by_validy( $object_atts, $use_deferred = false ) {

		$valid_insert_args = array(
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
		$deferred_insert_args = array(
			'post_category',
			'tags_input',
			'tax_input',
			'meta_input',
		);

		$classified_atts = array(
			'insert_args'			=> array(),
			'deferred_insert_args'	=> array(),
			'custom_args'			=> array(),
		);

		foreach( $object_atts as $key => $val ) {
			if ( $use_deferred && in_array( $key, $deferred_insert_args ) ) {
				$classified_atts['deferred_insert_args'][$key] = $val;
			} elseif ( in_array( $key, $valid_insert_args ) ) {
				$classified_atts['insert_args'][$key] = $val;
			} else {
				$classified_atts['custom_args'][$key] = $val;
			}
		}

		return $classified_atts;
	}

	protected static function fix_insert_args( $atts ) {

		foreach( array(
			'insert_args',
			'deferred_insert_args',
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

	protected function insert_object_wmpl( $object ) {

		if ( class_exists( 'SitePress' ) ) {
			global $sitepress;
		}

		$object_trid = null;
		$original_id = null;
		foreach( $object['atts'] as $lang => $atts_by_lang ) {
			if ( 'all' === $lang )
				continue;

			$object_id = wp_insert_post( $atts_by_lang['insert_args'] );

			if ( is_wp_error( $object_id ) ) {
				static::log( implode( ' ', array(
					'ERROR' . "\t",
					'inserting',
					static::$type,
					$object_id->get_error_message()
				) ) );
				continue;
			}

			// original_id of first inserted object
			$original_id = null === $original_id ? $object_id : $original_id;

			$object['inserted'][$lang] = $object_id;

			static::log( implode( ' ', array(
				'Inserted' . "\t",
				static::$type,
				'$id=' . $object_id,
			) ) );

			// object_trid of first inserted object
			$object_trid = null === $object_trid
				? $sitepress->get_element_trid( $object_id )
				: $object_trid;

			// do_action( "yaim_" . static::$type . "_inserted_for_wpml_translation",
			// 	$object_id,
			// 	$object,
			// 	$lang,
			// 	$object_trid
			// );

			$translation_id = $sitepress->set_element_language_details(
				$object_id,
				'post_' . get_post_type( $object_id ),
				$object_trid,
				$lang
			);

			static::log( implode( ' ', array(
				'Updated' . "\t",
				static::$type,
				'$id=' . $object_id,
				'$lang=' . $lang,
				'$translation_id=' . $translation_id,
				$original_id === $object_id ? '' : 'as translation for $id=' . $original_id,
			) ) );

			// do_action( "yaim_" . static::$type . "_wpml_language_set",
			// 	$object_id,
			// 	$object,
			// 	$lang,
			// 	$object_trid,
			// 	$translation_id
			// );

		}


		$object = $this->update_object_deferred( $object );

		$object = $this->update_object_p2p( $object );

		// if ( isset( $object_id ) )
		// 	do_action( "yaim_" . static::$type . "_inserted", $object, $object_id );		// ??? needs $i

		return $object;
	}

	protected function insert_object( $object ) {
		$object_id = wp_insert_post( array_merge(
			utils\Arr::get( $object, 'atts.all.insert_args', array() ),
			utils\Arr::get( $object, 'atts.all.deferred_insert_args', array() )
		) );

		if ( is_wp_error( $object_id ) ) {
			static::log( implode( ' ', array(
				'ERROR' . "\t",
				'inserting',
				static::$type,
				$object_id->get_error_message()
			) ) );
			return;
		}

		$object['inserted']['all'] = $object_id;

		$this->update_object_p2p( $object );

		static::log( implode( ' ', array(
			'Inserted' . "\t",
			static::$type,
			'$id=' . $object_id,
		) ) );

		// do_action( "yaim_" . static::$type . "_inserted", $object, $object_id );
	}

	protected function update_object_deferred( $object ) {

		foreach( $object['atts'] as $lang => $atts_by_lang ) {
			if ( 'all' === $lang )
				continue;

			if ( ! array_key_exists( 'deferred_insert_args', $atts_by_lang )
				|| empty( $atts_by_lang['deferred_insert_args'] ) )
				continue;

			$object_id = utils\Arr::get( $object, 'inserted.' . $lang, false );

			if ( ! $object_id )
				continue;

			$args = array_merge(
				array(
					'ID' => $object_id,
					'post_type' => get_post_type( $object_id ),
				),
				$atts_by_lang['deferred_insert_args']
			);

			if ( ! array_key_exists( 'post_title', $args ) )
				$args['post_title'] = utils\Arr::get( $atts_by_lang, 'insert_args.post_title' );

			$updated = wp_update_post( $args, true );

			if ( is_wp_error( $updated ) ) {
				static::log( implode( ' ', array(
					'WARNING' . "\t",
					'updating',
					'$id=' . $object_id,
					static::$type,
					$updated->get_error_message(),
				) ) );
				continue;
			}

			static::log( implode( ' ', array(
				'Updated' . "\t",
				static::$type,
				'$id=' . $object_id,
				'$args=[' . implode( ', ', array_keys( $atts_by_lang['deferred_insert_args'] ) ) . ']',
				'and wpml should have done the sync',
			) ) );


		}

		return $object;
	}

	protected function update_object_p2p( $object ) {
		if ( class_exists( 'P2P_Connection_Type_Factory' ) ) {
			foreach( $object['atts'] as $lang => $atts_by_lang ) {
				if ( 'all' === $lang )
					continue;

				$p2p = utils\Arr::get( $atts_by_lang,'custom_args.p2p' );

				if ( ! $p2p )
					continue;

				$object_id = utils\Arr::get( $object, 'inserted.' . $lang, false );

				if ( ! $object_id )
					continue;

				foreach( $p2p as $ctype_name => $conns ) {

					$ctype = \P2P_Connection_Type_Factory::get_instance( $ctype_name );

					$object_side = false;
					$object_post_type = get_post_type( $object_id );
					$both_sides_are_posttype = true;
					$conn_post_type = false;
					foreach( $ctype->side as $direction => $side ) {
						if ( ! $side instanceof \P2P_Side_Post ) {
							$both_sides_are_posttype = false;
							continue;
						}
						if ( in_array( $object_post_type, utils\Arr::get( $side->query_vars, 'post_type' ) ) ) {
							$object_side = ! $object_side
								? $direction
								: $object_side;
						} else {
							$conn_post_type = ! $conn_post_type
								? utils\Arr::get( $side->query_vars, 'post_type.0' )
								: $conn_post_type;
						}
					}

					if ( ! $both_sides_are_posttype ) {
						static::log( implode( ' ', array(
							'WARNING' . "\t",
							'updating',
							static::$type,
							'Currently p2p connections are only supported, if both connection sides represent a posttype',
						) ) );
						continue;
					}

					if ( ! $object_side ) {
						static::log( implode( ' ', array(
							'WARNING' . "\t",
							'updating',
							static::$type,
							'No side of $connection_type=' . $ctype_name . ' represents $posttype=' . $object_post_type,
						) ) );
						continue;
					}

					$connected_post_ids = array();
					foreach( $conns as $conn_k => $conn_v ) {

						$conn_post_slug = is_array( $conn_v ) || ( is_string( $conn_v ) && empty( $conn_v ) )
							? $conn_k
							: $conn_v;

						$get_post_args = array(
							'name'           => $conn_post_slug,
							'posts_per_page' => 1,
							'post_type'      => $conn_post_type,
							'fields'      	 => 'ids',
						);
						$get_post_args = class_exists( 'SitePress' ) ? array_merge( $get_post_args, array(
							'lang' => apply_filters( 'wpml_current_language', null ),
							'suppress_filters'  => false,
						) ) : $get_post_args;

						$conn_post_id = utils\Arr::get( get_posts( $get_post_args ), '0', false );

						if ( ! $conn_post_id ) {
							static::log( implode( ' ', array(
								'ERROR' . "\t",
								'updating',
								static::$type,
								'$id=' . $object_id,
								'can not create p2p connection, can not find post with $slug=' . $conn_post_slug,
							) ) );
							continue;
						}

						// create connection
						$p2p_id = $ctype->connect(
							'from' === $object_side ? $object_id : $conn_post_id,
							'to' === $object_side ? $object_id : $conn_post_id,
							is_array( $conn_v ) ? $conn_v : array()
						);

						if ( is_wp_error( $p2p_id ) ) {
							static::log( implode( ' ', array(
								'ERROR' . "\t",
								'creating p2p connection',
								static::$type,
								'$id=' . $object_id,
								$object_id->get_error_message()
							) ) );
							continue;
						}
						$connected_post_ids[] = $conn_post_id;
					}

					static::log( implode( ' ', array(
						'Updated' . "\t",
						static::$type,
						'$id=' . $object_id,
						'p2p connected with post $ids=[' . implode( ', ', $connected_post_ids ) . ']',
						'$ctype_name=' . $ctype_name,
					) ) );
				}
			}
		}
		return $object;
	}

}