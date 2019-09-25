<?php

namespace yaim;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

use croox\wde\utils;

class Import_Terms extends Import_Base {

	protected $action = 'import_term';

	protected static $type = 'term';

	protected static $autop_keys = array(
		'description',
	);

	protected static function setup_import_data( $item_raw_data ) {
		return array(
			'is_wpml_import'	=> static::is_wpml_import( $item_raw_data ),
			'inserted'			=> array(),
			'atts' 				=> static::classify_atts_by_lang( $item_raw_data, array(
				'taxonomy',
			) ),
		);
	}

	protected static function classify_atts_by_validy( $item_atts, $use_deferred = false ) {

		$valid_insert_args = array(
			'name',
			'taxonomy',
			'description',
			'parent',
			'slug',
		);

		$classified_atts = array(
			'insert_args'			=> array(),
			'custom_args'				=> array(),
		);

		foreach( $item_atts as $key => $val ) {
			if ( in_array( $key, $valid_insert_args ) ) {
				$classified_atts['insert_args'][$key] = $val;
			} else {
				$classified_atts['custom_args'][$key] = $val;
			}
		}

		return $classified_atts;
	}

	protected static function fix_insert_args( $atts ) {

		foreach( array(
			'parent',
		) as $to_fix ) {

			if ( array_key_exists( $to_fix, $atts ) &&
				array_key_exists( 'parent', $atts[$to_fix] )
			) {
				foreach( $atts[$to_fix]['parent'] as $parent_slug_or_id ) {
					// // allow parent slugs
					// ???

				}
			}

		}

		return $atts;
	}

	protected function insert_item_wmpl( $item ) {

		if ( class_exists( 'SitePress' ) ) {
			global $sitepress;
		}

		$item_trid = null;
		$original_id = null;
		foreach( $item['atts'] as $lang => $atts_by_lang ) {
			if ( 'all' === $lang )
				continue;

			$args = $atts_by_lang['insert_args'];
			$name = array_splice( $args, array_search( 'name', array_keys( $args ) ), 1  )['name'];
			$taxonomy = array_splice( $args, array_search( 'taxonomy', array_keys( $args ) ), 1 )['taxonomy'];

			$term_tax_ids = wp_insert_term( $name, $taxonomy, $args );

			if ( is_wp_error( $term_tax_ids ) ) {
				static::log( implode( ' ', array(
					'ERROR' . "\t",
					'inserting',
					static::$type,
					'Message: ' . $term_tax_ids->get_error_message()
				) ) );
				continue;
			}

			$item_id = $term_tax_ids['term_id'];

			// original_id of first inserted item
			$original_id = null === $original_id ? $item_id : $original_id;

			$item['inserted'][$lang] = $item_id;

			static::log( implode( ' ', array(
				'Inserted' . "\t",
				static::$type,
				'$id=' . $item_id,
			) ) );

			// item_trid of first inserted item
			$item_trid = null === $item_trid
				? apply_filters( 'wpml_element_trid', NULL, $item_id, 'tax_' . $taxonomy )
				: $item_trid;

			// do_action( "yaim_{$this->type}_inserted_for_wpml_translation",
			// 	$item_id,
			// 	$this->items[$i],
			// 	$lang,
			// 	$item_trid
			// );

			$translation_id = $sitepress->set_element_language_details(
				$item_id,
				'tax_' . $taxonomy,
				$item_trid,
				$lang
			);

			static::log( implode( ' ', array(
				'Updated' . "\t",
				static::$type,
				'$id=' . $item_id,
				'$lang=' . $lang,
				'$translation_id=' . $translation_id,
				$original_id === $item_id ? '' : 'as translation for $id=' . $original_id,
			) ) );

			// do_action( "yaim_{$this->type}_wpml_language_set",
			// 	$item_id,
			// 	$this->items[$i],
			// 	$lang,
			// 	$item_trid,
			// 	$translation_id
			// );
		}

		// handle meta_input
		foreach( $item['atts'] as $lang => $atts_by_lang ) {
			if ( 'all' === $lang )
				continue;

			$meta_input = utils\Arr::get( $atts_by_lang,'custom_args.meta_input' );

			if ( ! $meta_input )
				continue;

			$item_id = utils\Arr::get( $item, 'inserted.' . $lang, false );

			if ( ! $item_id )
				continue;

			foreach( $meta_input as $meta_key => $meta_val ) {
				$updated = update_term_meta( $item_id, $meta_key, $meta_val );
				if ( is_numeric( $updated ) && $updated == ( int ) $updated ) {
					static::log( implode( ' ', array(
						'Updated' . "\t",
						static::$type,
						'$id=' . $item_id,
						'added $meta_key=' . $meta_key . ' $meta_id ' . $updated,
					) ) );
				} elseif ( $updated ) {
					static::log( implode( ' ', array(
						'Updated' . "\t",
						static::$type,
						'$id=' . $item_id,
						'updated $meta_key=' . $meta_key,
					) ) );
				} else {
					static::log( implode( ' ', array(
						'ERROR' . "\t",
						'updating',
						static::$type,
						'$id=' . $item_id,
						'could not add $meta_key=' . $meta_key,
					) ) );
					continue;
				}
			}
		}

		// if ( isset( $item_id ) )
		// 	do_action( "yaim_{$this->type}_inserted", $this->items[$i], $item_id );

	}

	protected function insert_item( $item ) {
		$args = utils\Arr::get( $item, 'atts.all.insert_args', array() );
		$name = array_splice( $args, array_search( 'name', array_keys( $args ) ), 1  )['name'];
		$taxonomy = array_splice( $args, array_search( 'taxonomy', array_keys( $args ) ), 1 )['taxonomy'];

		$term_tax_ids = wp_insert_term( $name, $taxonomy, $args );

		if ( is_wp_error( $term_tax_ids ) ) {
			static::log( implode( ' ', array(
				'ERROR' . "\t",
				'inserting',
				static::$type,
				'Message: ' . $term_tax_ids->get_error_message()
			) ) );
			return;
		}

		$item_id = $term_tax_ids['term_id'];
		$item['inserted']['all'] = $item_id;

		static::log( implode( ' ', array(
			'Inserted' . "\t",
			static::$type,
			'$id=' . $item_id,
		) ) );

		// do_action( "yaim_{$this->type}_inserted", $this->items[$i], null );
	}

}