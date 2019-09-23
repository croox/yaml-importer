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

	protected $autop_keys = array();

	protected $log = array();

	public function __construct( $objects ){

		$this->active_langs = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
		$this->active_langs = null === $this->active_langs ? $this->active_langs : array_map( function( $lang ) {
			return $lang['language_code'];
		}, $this->active_langs );

		$this->setup_import_data( $objects );

		$this->prepare_import_data();

		$this->insert_objects();

		return $this;
	}

	abstract protected function setup_import_data( $file_data );

	protected function prepare_import_data() {
		// classify_atts_by_validy for wp_insert_post wp_insert_term function
		// and fix_insert_args
		foreach( $this->objects as $object_i => $object ) {
			if ( $object['is_wpml_import'] ) {
				foreach( $object['atts'] as $lang => $atts ) {
					if ( 'all' === $lang )
						continue;

					// merge atts recursive for all into current atts_by_lang
					$atts = array_merge_recursive(
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
			if ( is_wp_error( $object['atts'] ) ) {
				$this->log[] = 'ERROR: ' . $object['atts']->get_error_message();
				continue;
			}
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

				$is_wpml_attr = $this->is_wpml_attr( $key, array_keys( $attr ) );
				if ( is_wp_error( $is_wpml_attr ) ) {
					$this->log[] = 'ERROR: ' . $is_wpml_attr->get_error_message();
					continue;
				}

				if ( $is_wpml_attr ) {
					foreach( $attr as $lang => $val ) {
						$lang = str_replace( 'wpml_', '', $lang );
						if ( in_array( $lang, $this->active_langs ) ) {
							if ( in_array( $key, $not_translatable ) ) {
								return new \WP_Error( 'attr_not_translatable', sprintf( __( '"%s" is not translatable.', 'yaim' ), $key ) );
							} else {
								$atts[$lang][$key] = $this->maybe_autop( $key, $val );
							}
						} else {
							$this->log[] = "ERROR: \$lang={$lang} not acive";
						}
					}
				} else {
					foreach( $attr as $n_key => $n_attr ) {

						if ( is_array( $n_attr ) ) {

							$is_wpml_attr = $this->is_wpml_attr( $n_key, array_keys( $n_attr ) );
							if ( is_wp_error( $is_wpml_attr ) ) {
								$this->log[] = 'ERROR: ' . $is_wpml_attr->get_error_message();
								continue;
							}

							if ( $is_wpml_attr ) {
								foreach( $n_attr as $lang => $val ) {
									$lang = str_replace( 'wpml_', '', $lang );
									if ( in_array( $lang, $this->active_langs ) ) {
										$atts[$lang][$key][$n_key] = $this->maybe_autop( $n_key, $val );
									} else {
										$this->log[] = "ERROR: \$lang={$lang} not acive";
									}
								}
							} else { // is normal field
								$atts['all'][$key][$n_key] = $this->maybe_autop( $n_key, $n_attr );
							}

						} else { // is normal field
							$atts['all'][$key][$n_key] = $this->maybe_autop( $n_key, $n_attr );
						}
					}
				}

			} else { // is normal field
				$atts['all'][$key] = $this->maybe_autop( $key, $attr );
			}
		}
		return $atts;
	}

	protected function maybe_autop( $key, $val ) {
		$autop_keys = apply_filters( "yaim_{$this->type}_autop_keys", $this->autop_keys );
		return in_array( $key, $autop_keys )
			? wpautop( $val )
			: $val;
	}

	protected function is_wpml_attr( $key, $keys ) {
		$keys_starts_wpml_ = array_unique( array_map( function( $k ) {
			return substr( $k, 0, strlen( 'wpml_' ) ) === 'wpml_';
		}, $keys ) );

		if ( count( $keys_starts_wpml_ ) > 1 )
			return new \WP_Error( 'attr_mixed_language', sprintf( __( '"%s" has translated and non-translated input.', 'yaim' ), $key ) );

		return $keys_starts_wpml_[0];
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