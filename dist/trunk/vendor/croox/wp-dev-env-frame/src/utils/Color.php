<?php

namespace croox\wde\utils;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Color utility class
 *
 * Contains static utility methods for color operations.
 *
 * @package  wde
 */
class Color {

	/**
	 * Convert HSL to HEX colors
	 *
	 * @see https://github.com/WordPress/twentynineteen/blob/b604f127c2cae10bd48bbbec0fbbbff2cd31f957/inc/template-functions.php#L345
	 * @since ???
	 * @param int  		$h
	 * @param int  		$s
	 * @param bool  	$to_hex
	 * @return string   example: rgb(255, 255, 255)
	 */
	public static function hsl_to_hex( $h, $s, $l, $to_hex = true ) {

		$h /= 360;
		$s /= 100;
		$l /= 100;

		$r = $l;
		$g = $l;
		$b = $l;
		$v = ( $l <= 0.5 ) ? ( $l * ( 1.0 + $s ) ) : ( $l + $s - $l * $s );
		if ( $v > 0 ) {
			$m;
			$sv;
			$sextant;
			$fract;
			$vsf;
			$mid1;
			$mid2;

			$m = $l + $l - $v;
			$sv = ( $v - $m ) / $v;
			$h *= 6.0;
			$sextant = floor( $h );
			$fract = $h - $sextant;
			$vsf = $v * $sv * $fract;
			$mid1 = $m + $vsf;
			$mid2 = $v - $vsf;

			switch ( $sextant ) {
				case 0:
					$r = $v;
					$g = $mid1;
					$b = $m;
					break;
				case 1:
					$r = $mid2;
					$g = $v;
					$b = $m;
					break;
				case 2:
					$r = $m;
					$g = $v;
					$b = $mid1;
					break;
				case 3:
					$r = $m;
					$g = $mid2;
					$b = $v;
					break;
				case 4:
					$r = $mid1;
					$g = $m;
					$b = $v;
					break;
				case 5:
					$r = $v;
					$g = $m;
					$b = $mid2;
					break;
			}
		}
		$r = round( $r * 255, 0 );
		$g = round( $g * 255, 0 );
		$b = round( $b * 255, 0 );

		if ( $to_hex ) {

			$r = ( $r < 15 ) ? '0' . dechex( $r ) : dechex( $r );
			$g = ( $g < 15 ) ? '0' . dechex( $g ) : dechex( $g );
			$b = ( $b < 15 ) ? '0' . dechex( $b ) : dechex( $b );

			return "#$r$g$b";

		}

		return "rgb($r, $g, $b)";
	}

}
