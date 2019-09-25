<?php

namespace yaim;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

use croox\wde\utils;

abstract class Import_Base extends \WP_Background_Process {

	protected $items = array();

	protected static $type = '';	// eg. post, term

	protected static $autop_keys = array();

	protected $uploads_dir_path;

	// public function __construct(){
	// 	parent::__construct();
	// }

	protected static function log( $msg = '' ) {
		$path = WP_CONTENT_DIR . '/yaml-importer/import.log';
		error_log(
			sprintf( '[%s] %s %s', date('d-M-Y H:i:s T'), $msg, PHP_EOL ),
			3,
			$path
		);
	}

	protected function task( $item_raw_data ) {

		static::log( '' );
		static::log( implode( ' ', array(
			'Start' . "\t",
			static::$type,
			'import',
		) ) );

		$item = static::setup_import_data( $item_raw_data );

		$item = static::prepare_import_data( $item );

		static::log( implode( ' ', array(
			'Prepared' . "\t",
			static::$type,
			'data',
		) ) );

		if ( class_exists( 'SitePress' ) ) {
			$current_lang = apply_filters( 'wpml_current_language', null );
			do_action( 'wpml_switch_language', apply_filters( 'wpml_default_language', null ) );
		}

		if ( ! is_wp_error( $item['atts'] ) ) {

			if ( $item['is_wpml_import'] ) {
				$item = $this->insert_item_wmpl( $item );
			} else {
				$item = $this->insert_item( $item );
			}

		} else {
			static::log( implode( ' ', array(
				'ERROR',
				$item['atts']->get_error_message()
			) ) );
		}

		if ( class_exists( 'SitePress' ) ) {
			do_action( 'wpml_switch_language', $current_lang, null );
		}

		static::log( 'Done' );
		static::log( '' );

		return false;
	}

	abstract protected static function setup_import_data( $item );

	// classify_atts_by_validy for wp_insert_post wp_insert_term function
	// and fix_insert_args
	protected static function prepare_import_data( $item ) {
		if ( $item['is_wpml_import'] ) {
			foreach( $item['atts'] as $lang => $atts ) {
				if ( 'all' === $lang )
					continue;

				// merge atts recursive for all into current atts_by_lang
				$atts = array_merge_recursive(
					$atts,
					utils\Arr::get( $item, 'atts.all', array() )
				);

				$atts = static::classify_atts_by_validy( $atts, $item['is_wpml_import'] );

				$atts = static::fix_insert_args( $atts );

				$item['atts'][$lang] = apply_filters( "yaim_" . static::$type . "_atts", $atts, $lang, $item );
			}
		} else {
			$atts = static::classify_atts_by_validy( utils\Arr::get( $item, 'atts.all', array() ), $item['is_wpml_import'] );

			$atts = static::fix_insert_args( $atts );

			$item['atts']['all'] = apply_filters( "yaim_" . static::$type . "_atts", $atts, 'all', $item );
		}

		return $item;
	}

	abstract protected static function classify_atts_by_validy( $item_atts, $use_deferred = false );

	protected static function fix_insert_args( $atts ) {
		return $atts;
	}

	abstract protected function insert_item( $item );

	abstract protected function insert_item_wmpl( $item );

	protected static function is_wpml_import( $item_raw_data ) {
		$is_wpml_import = false;
		foreach( $item_raw_data as $key => $attr ) {
			if ( $is_wpml_import )
				break;
			$is_wpml_import = is_array( $attr )
				&& substr( array_keys( $attr )[0], 0, strlen( 'wpml_' )) === 'wpml_';
		}
		return $is_wpml_import;
	}

	protected static function classify_atts_by_lang( $item_raw_data, $not_translatable = array() ) {
		$atts = array(
			'all' => array()
		);

		// first key is default language
		$default_lang = apply_filters( 'wpml_default_language', null );
		if ( null !== $default_lang )
			$atts[$default_lang] = array();

		foreach( $item_raw_data as $key => $attr ) {
			if ( is_array( $attr ) ) {

				$is_wpml_attr = static::is_wpml_attr( $key, array_keys( $attr ) );
				if ( is_wp_error( $is_wpml_attr ) ) {
					static::log( 'ERROR: ' . $is_wpml_attr->get_error_message() );
					continue;
				}

				if ( $is_wpml_attr ) {
					foreach( $attr as $lang => $val ) {
						$lang = str_replace( 'wpml_', '', $lang );
						if ( in_array( $lang, static::get_active_langs() ) ) {
							if ( in_array( $key, $not_translatable ) ) {
								return new \WP_Error( 'attr_not_translatable', sprintf( __( '"%s" is not translatable.', 'yaim' ), $key ) );
							} else {
								$atts[$lang][$key] = static::maybe_autop( $key, $val );
							}
						} else {
							static::log( "ERROR: \$lang={$lang} not acive" );
						}
					}
				} else {
					foreach( $attr as $n_key => $n_attr ) {

						if ( is_array( $n_attr ) ) {

							$is_wpml_attr = static::is_wpml_attr( $n_key, array_keys( $n_attr ) );
							if ( is_wp_error( $is_wpml_attr ) ) {
								static::log( 'ERROR: ' . $is_wpml_attr->get_error_message() );
								continue;
							}

							if ( $is_wpml_attr ) {
								foreach( $n_attr as $lang => $val ) {
									$lang = str_replace( 'wpml_', '', $lang );
									if ( in_array( $lang, static::get_active_langs() ) ) {
										$atts[$lang][$key][$n_key] = static::maybe_autop( $n_key, $val );
									} else {
										static::log( "ERROR: \$lang={$lang} not acive" );
									}
								}
							} else { // is normal field
								$atts['all'][$key][$n_key] = static::maybe_autop( $n_key, $n_attr );
							}

						} else { // is normal field
							$atts['all'][$key][$n_key] = static::maybe_autop( $n_key, $n_attr );
						}
					}
				}

			} else { // is normal field
				$atts['all'][$key] = static::maybe_autop( $key, $attr );
			}
		}
		return $atts;
	}

	protected static function maybe_autop( $key, $val ) {
		$autop_keys = apply_filters( "yaim_" . static::$type . "_autop_keys", static::$autop_keys );
		return in_array( $key, $autop_keys )
			? wpautop( $val )
			: $val;
	}

	protected static function is_wpml_attr( $key, $keys ) {
		$keys_starts_wpml_ = array_unique( array_map( function( $k ) {
			return substr( $k, 0, strlen( 'wpml_' ) ) === 'wpml_';
		}, $keys ) );

		if ( count( $keys_starts_wpml_ ) > 1 )
			return new \WP_Error( 'attr_mixed_language', sprintf( __( '"%s" has translated and non-translated input.', 'yaim' ), $key ) );

		return $keys_starts_wpml_[0];
	}

	protected static function get_active_langs() {
		$active_langs = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
		return is_array( $active_langs )
			? array_map( function( $lang ) { return $lang['language_code']; }, $active_langs )
			: array();
	}

}