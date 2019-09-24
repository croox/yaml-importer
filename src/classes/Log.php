<?php

namespace yaim;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

use croox\wde\utils;

class Log {

	protected $msgs = array();

	protected static $option_key = 'yaim_log';

	protected $start;

	public function __construct(){
		$this->start = date( 'Y-m-d H:i:s' );
		update_option( self::$option_key . '_start', $this->start );

		$this->add_entries( array( $this->start, '' ) );

	}

	public function add_entries( $msgs = array(), $key = null ){
		foreach( $msgs as $msg ) {
			$this->add_entry( $msg );
		}
	}

	public function add_entry( $msg = '', $key = null ){
		if ( null === $key ) {
			$this->msgs[] = $msg;
		} else {
			if ( array_key_exists( $key, $this->msgs ) ) {
				$this->msgs[$key] .= $msg;

			} else {
				$this->msgs[$key] = $msg;
			}
		}
	}

	public function get(){
		return $this->msgs;
	}

	public static function get_from_db(){
		$msgs = get_option( self::$option_key );
		$last = get_option( self::$option_key . '_start' );
		return array(
			'msgs'				=> $msgs,
			'last_start'		=> get_option( self::$option_key . '_start' ),
			'last_log_saved'	=> count( $msgs ) > 0 &&  $msgs[0] === $last,
		);
	}

	public function save(){
		$this->add_entries( array(
			date( 'Y-m-d H:i:s' ) . ' Save log to db $option_key=' . self::$option_key,
			'',
		) );
		update_option( self::$option_key, $this->msgs );
	}

}

