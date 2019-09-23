<?php

namespace yaim;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

use croox\wde\utils;

abstract class Import_Base {

	protected $objects = array();

	protected $type = '';	// eg. post, term

	protected $active_langs = array();

	protected $log = array();

	public function __construct( $objects ){

		$this->active_langs = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
		$this->active_langs = null === $this->active_langs ? $this->active_langs : array_map( function( $lang ) {
			return $lang['language_code'];
		}, $this->active_langs );

		$this->setup_import_data( $objects );

		$this->insert_objects();

		return $this;
	}

	abstract protected function setup_import_data( $file_data );

	abstract protected function classify_atts_by_validy( $object_atts, $use_deferred = false );

	protected function fix_insert_args( $atts ) {
		return $atts;
	}

	protected function insert_objects() {
		if ( class_exists( 'SitePress' ) ) {
			$current_lang = apply_filters( 'wpml_current_language', null );
			do_action( 'wpml_switch_language', apply_filters( 'wpml_default_language', null ) );
		}

		foreach( $this->objects as $i => $object ) {
			if ( is_wp_error( $object['atts'] ) )
				continue;
			if ( $object['is_wpml_import'] ) {
				$this->insert_object_wmpl( $i, $object );
			} else {
				$this->insert_object( $i, $object );
			}
		}

		if ( class_exists( 'SitePress' ) ) {
			do_action( 'wpml_switch_language', $current_lang, null );
		}
	}

	abstract protected function insert_object( $i, $object );

	abstract protected function insert_object_wmpl( $i, $object );

	protected function is_wpml_import( $object_raw_data ) {
		$is_wpml_import = false;
		foreach( $object_raw_data as $key => $attr ) {
			if ( $is_wpml_import )
				break;
			$is_wpml_import = is_array( $attr )
				&& substr( array_keys( $attr )[0], 0, strlen( 'wpml_' )) === 'wpml_';
		}
		return $is_wpml_import;
	}

	protected function classify_atts_by_lang( $object_raw_data, $not_translatable = array() ) {
		$atts = array(
			'all' => array()
		);

		// first key is default language
		$default_lang = apply_filters( 'wpml_default_language', null );
		if ( null !== $default_lang )
			$atts[$default_lang] = array();

		foreach( $object_raw_data as $key => $attr ) {
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
							if ( in_array( $key, $not_translatable ) ) {
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

	/**
	 * Public getter method for retrieving protected/private variables
	 * @param  string  		$field Field to retrieve
	 * @return mixed        Field value or exception is thrown
	 */
	public function __get( $field ) {
		if ( in_array( $field, array(
			'objects',
			'type',
			'log',
		), true ) ) {
			return $this->{$field};
		}
		throw new Exception( 'Invalid property: ' . $field );
	}

}