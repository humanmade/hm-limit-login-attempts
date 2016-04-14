<?php

namespace HM\Limit_Login_Attempts;

class Setup extends Plugin {

	/* Get options and setup filters & actions */
	public function load() {

		require_once( HM_LIMIT_LOGIN_DIR . 'inc/class-options.php' );
		require_once( HM_LIMIT_LOGIN_DIR . 'inc/class-errors.php' );
		require_once( HM_LIMIT_LOGIN_DIR . 'inc/class-cookies.php' );
		require_once( HM_LIMIT_LOGIN_DIR . 'inc/class-validation.php' );
		require_once( HM_LIMIT_LOGIN_DIR . 'inc/class-notifications.php' );

		if ( HM_LIMIT_LOGIN_VERSION !== get_option( 'hm_limit_login_version' ) ) {
			$this->set_default_variables();
		}

		load_plugin_textdomain(
			'limit-login-attempts',
			false,
			dirname( plugin_basename( __FILE__ ) )
		);

		Options::get_instance();
		Errors::get_instance();
		Cookies::get_instance();
		Validation::get_instance();
		Notifications::get_instance();

	}

	/**
	 * Variables
	 *
	 * Assignments are for default value -- change on admin page.
	 *
	 */
	private function set_default_variables() {

		$default_options =
			array(
				'version'               => HM_LIMIT_LOGIN_VERSION,
				'client_type'           => HM_LIMIT_LOGIN_DIRECT_ADDR, /* Are we behind a proxy? */
				'allowed_retries'       => 4,       /* Lock out after this many tries */
				'lockout_duration'      => 1200,    /* Lock out for this many seconds - default to 20 minutes */
				'allowed_lockouts'      => 4,       /* Long lock out after this many lockouts */
				'long_duration'         => 86400,   /* Long lock out for this many seconds - defaults to 24 hours */
				'valid_duration'        => 43200,   /* Reset failed attempts after this many seconds - defaults to 12 hours */
				'cookies'               => 1,       /* Also limit malformed/forged cookies? */
				'lockout_notify'        => 'log',	/* Notify on lockout. Values: '', 'log', 'email', 'log,email' */
				'notify_email_after'    => 4,	    /* If notify by email, do so after this number of lockouts */
				'my_error_shown'        => false,   /* have we shown our stuff? */
				'just_lockouts'         => false,   /* started this pageload??? */
				'noempty_credentials'   => false,   /* user and pwd nonempty */
				'lockout_method'        => 'ip'     /* method of lock out. Values: '', 'ip', 'username', 'ip,username' */
			);

		foreach( $default_options as $option_key => $option_value ){

			$meta_key = 'hm_limit_login_' . $option_key;
			$meta_value = $option_value;

			update_option( $meta_key, $meta_value );

		}

	}

}
