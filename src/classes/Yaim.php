<?php

namespace yaim;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

use croox\wde;

class Yaim extends wde\Plugin {

	public function hooks(){
        parent::hooks();

        Settings_Page::get_instance();
	}

}