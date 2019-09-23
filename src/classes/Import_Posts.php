<?php

namespace yaim;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

use croox\wde\utils;

class Import_Posts extends Import_Base {

	protected $type = 'post';

	protected $autop_keys = array(
		'post_content',
		'post_excerpt',
	);

	protected function setup_import_data( $objects ) {

		// classify_atts_by_lang and check is_wpml_import
		foreach( $objects as $object_i => $object_raw_data ) {

			// clean object_raw_data from not allowed fields
			foreach( array(
				'ID',
				'guid',
			) as $not_allowed ) {
				if ( array_key_exists( $not_allowed, $object_raw_data ) ) {
					unset( $object_raw_data[$not_allowed] );
				}
			}

			$this->objects[$object_i] = array(
				'is_wpml_import'	=> $this->is_wpml_import( $object_raw_data ),
				'inserted'			=> array(),
				'atts' 				=> $this->classify_atts_by_lang( $object_raw_data, array(
					'post_type',
					// ??? may be more
				) ),
			);

		}

	}

	protected function classify_atts_by_validy( $object_atts, $use_deferred = false ) {

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

	protected function fix_insert_args( $atts ) {

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

	protected function insert_object_wmpl( $i, $object ) {

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
				$this->log[] = 'ERROR: ' . $object_id->get_error_message();
				continue;
			}

			// original_id of first inserted object
			$original_id = null === $original_id ? $object_id : $original_id;

			$object['inserted'][$lang] = $object_id;
			$this->objects[$i]['inserted'][$lang] = $object_id;

			$this->log[$object_id . '_in'] = "Inserted {$this->type} \$id={$object_id}";

			// object_trid of first inserted object
			$object_trid = null === $object_trid
				? $sitepress->get_element_trid( $object_id )
				: $object_trid;

			do_action( "yaim_{$this->type}_inserted_for_wpml_translation",
				$object_id,
				$this->objects[$i],
				$lang,
				$object_trid
			);

			$translation_id = $sitepress->set_element_language_details(
				$object_id,
				'post_' . get_post_type( $object_id ),
				$object_trid,
				$lang
			);

			$this->log[$object_id . '_in'] .= " \$lang={$lang} \$translation_id={$translation_id}";
			$this->log[$object_id . '_in'] .= $original_id === $object_id ? '' : " as translation for \$id={$original_id}";

			do_action( "yaim_{$this->type}_wpml_language_set",
				$object_id,
				$this->objects[$i],
				$lang,
				$object_trid,
				$translation_id
			);

		}

		// do the deferred stuff
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
				$this->log[] = "ERROR updating {$object_id}: " . $updated->get_error_message();
				continue;
			}

			$this->log[$object_id . '_up'] = "Updated {$this->type} \$id={$object_id} \$args=[" . implode( ', ', array_keys( $atts_by_lang['deferred_insert_args'] ) ) . "] and wpml should have done the sync";
		}

		if ( isset( $object_id ) )
			$this->log[$object_id . '_after'] = '';

		do_action( "yaim_{$this->type}_inserted", $this->objects[$i], $object_id );

	}

	protected function insert_object( $i, $object ) {
		$object_id = wp_insert_post( array_merge(
			utils\Arr::get( $object, 'atts.all.insert_args', array() ),
			utils\Arr::get( $object, 'atts.all.deferred_insert_args', array() )
		) );

		if ( is_wp_error( $object_id ) ) {
			$this->log[] = 'ERROR: ' . $object_id->get_error_message();
			return;
		}

		$this->objects[$i]['inserted']['all'] = $object_id;

		$this->log[$object_id . '_in'] = "Inserted {$this->type} \$id={$object_id}";

		do_action( "yaim_{$this->type}_inserted", $this->objects[$i], null );
	}

}