<?php

namespace HM\Limit_Login_Attempts;

use HM\Limit_Login_Attempts\Plugin;


/*
 * Variables
 *
 * Assignments are for default value -- change on admin page.
 */


$my_error_shown = false;        /* have we shown our stuff? */
$just_lockedout = false;        /* started this pageload??? */
$nonempty_credentials = false;  /* user and pwd nonempty */


class Setup extends Plugin {

	/* Get options and setup filters & actions */
	public function load() {

		load_plugin_textdomain(
			'limit-login-attempts',
			false,
			dirname( plugin_basename( __FILE__ ) )
		);

		Options::get_instance();
		Errors::get_instance();
		Notifition::get_instance();
		Cookiecations::get_instance();
		Validas::get_instance();




		/*
		 * This action should really be changed to the 'authenticate' filter as
		 * it will probably be deprecated. That is however only available in
		 * later versions of WP.
		 */
		add_action( 'wp_authenticate', 'limit_login_track_credentials', 10, 2 );
	}

}