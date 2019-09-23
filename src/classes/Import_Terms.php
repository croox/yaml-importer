<?php

namespace yaim;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

use croox\wde\utils;

class Import_Terms extends Import_Base {

	protected $type = 'term';

	protected function setup_import_data( $objects ) {

		// classify_atts_by_lang and check is_wpml_import
		foreach( $objects as $object_i => $object_raw_data ) {

			// check if is_wpml_import for this object
			$is_wpml_import = $this->is_wpml_import( $object_raw_data );

			$this->objects[$object_i] = array(
				'is_wpml_import'	=> $is_wpml_import,
				'inserted'			=> array(),
				'atts' 				=> $this->classify_atts_by_lang( $object_raw_data, array() ),
			);

		}

		// classify_atts_by_validy for wp_insert_term function
		// and fix_insert_args
		foreach( $this->objects as $object_i => $object ) {
			if ( $object['is_wpml_import'] ) {
				foreach( $object['atts'] as $lang => $atts ) {
					if ( 'all' === $lang )
						continue;

					// merge atts for all into current atts_by_lang
					$atts = array_merge(
						$atts,
						utils\Arr::get( $this->objects, $object_i . '.atts.all', array() )
					);

					$atts = $this->classify_atts_by_validy( $atts, $object['is_wpml_import'] );

					$atts = $this->fix_insert_args( $atts );

					$this->objects[$object_i]['atts'][$lang] = apply_filters( "yaim_{$this->type}_atts", $atts, $lang, $object );
				}
			} else {
				$atts = $this->classify_atts_by_validy( utils\Arr::get( $object, 'atts.all', array() ), $object['is_wpml_import'] );

				$atts = $this->fix_insert_args( $atts );

				$this->objects[$object_i]['atts']['all'] = apply_filters( "yaim_{$this->type}_atts", $atts, 'all', $object );
			}
		}
	}

	protected function classify_atts_by_validy( $object_atts, $use_deferred = false ) {

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

		foreach( $object_atts as $key => $val ) {
			if ( in_array( $key, $valid_insert_args ) ) {
				$classified_atts['insert_args'][$key] = $val;
			} else {
				$classified_atts['custom_args'][$key] = $val;
			}
		}

		return $classified_atts;
	}

	protected function fix_insert_args( $atts ) {

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

	protected function insert_object_wmpl( $i, $object ) {

		if ( class_exists( 'SitePress' ) ) {
			global $sitepress;
		}

		$object_trid = null;

		foreach( $object['atts'] as $lang => $atts_by_lang ) {
			if ( 'all' === $lang )
				continue;

			$args = $atts_by_lang['insert_args'];
			$name = array_splice( $args, array_search( 'name', array_keys( $args ) ), 1  )['name'];
			$taxonomy = array_splice( $args, array_search( 'taxonomy', array_keys( $args ) ), 1 )['taxonomy'];

			$term_tax_ids = wp_insert_term( $name, $taxonomy, $args );

			if ( is_wp_error( $term_tax_ids ) )
				continue;

			$object_id = $term_tax_ids['term_id'];
			$object['inserted'][$lang] = $object_id;
			$this->objects[$i]['inserted'][$lang] = $object_id;

			// object_trid of first inserted object
			$object_trid = null === $object_trid
				? apply_filters( 'wpml_element_trid', NULL, $object_id, 'tax_' . $taxonomy )
				: $object_trid;

			do_action( "yaim_{$this->type}_inserted_for_wpml_translation",
				$object_id,
				$this->objects[$i],
				$lang,
				$object_trid
			);

			$translation_id = $sitepress->set_element_language_details(
				$object_id,
				'tax_' . $taxonomy,
				$object_trid,
				$lang
			);

			do_action( "yaim_{$this->type}_wpml_language_set",
				$object_id,
				$this->objects[$i],
				$lang,
				$object_trid,
				$translation_id
			);
		}

		do_action( "yaim_{$this->type}_inserted", $this->objects[$i], $object_id );

	}

	protected function insert_object( $i, $object ) {
		$args = utils\Arr::get( $object, 'atts.all.insert_args', array() );
		$name = array_splice( $args, array_search( 'name', array_keys( $args ) ), 1  )['name'];
		$taxonomy = array_splice( $args, array_search( 'taxonomy', array_keys( $args ) ), 1 )['taxonomy'];

		$term_tax_ids = wp_insert_term( $name, $taxonomy, $args );

		if ( is_wp_error( $term_tax_ids ) )
			return;

		$object_id = $term_tax_ids['term_id'];
		$this->objects[$i]['inserted']['all'] = $object_id;

		do_action( "yaim_{$this->type}_inserted", $this->objects[$i], null );
	}

}