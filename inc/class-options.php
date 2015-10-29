<?php

namespace HM\Limit_Login_Attempts;

use HM\Limit_Login_Attempts\Plugin;

class Options extends Plugin {

	/* Setup global variables from options */
	public function load() {

		add_action( 'admin_menu', 'limit_login_admin_menu' );


		$this->get_option( 'limit_login_client_type', 'client_type' );
		$this->get_option( 'limit_login_allowed_retries', 'allowed_retries' );
		$this->get_option( 'limit_login_lockout_duration', 'lockout_duration' );
		$this->get_option( 'limit_login_valid_duration', 'valid_duration' );
		$this->get_option( 'limit_login_cookies', 'cookies' );
		$this->get_option( 'limit_login_lockout_notify', 'lockout_notify' );
		$this->get_option( 'limit_login_allowed_lockouts', 'allowed_lockouts' );
		$this->get_option( 'limit_login_long_duration', 'long_duration' );
		$this->get_option( 'limit_login_notify_email_after', 'notify_email_after' );

		limit_login_sanitize_variables();

	}

	/*
	 * Variables
	 *
	 * Assignments are for default value -- change on admin page.
	 *
	 */
	private function variables( $option ) {


		$limit_login_options =
			array(
				'client_type'     => LIMIT_LOGIN_DIRECT_ADDR, /* Are we behind a proxy? */
				'allowed_retries' => 4,     /* Lock out after this many tries */
				'lockout_duration' => 1200, /* Lock out for this many seconds - default to 20 minutes */
				'allowed_lockouts' => 4,    /* Long lock out after this many lockouts */
				'long_duration' => 86400,   /* Long lock out for this many seconds - defaults to 24 hours */
				'valid_duration' => 43200,  /* Reset failed attempts after this many seconds - defaults to 12 hours */
				'cookies' => true,			/* Also limit malformed/forged cookies? */
				'lockout_notify' => 'log',	/* Notify on lockout. Values: '', 'log', 'email', 'log,email' */
				'notify_email_after' => 4	/* If notify by email, do so after this number of lockouts */
			);


		$limit_login_my_error_shown       = false; /* have we shown our stuff? */
		$limit_login_just_lockedout       = false; /* started this pageload??? */
		$limit_login_nonempty_credentials = false; /* user and pwd nonempty */

		return call_ref_array();
	}

	/* Only change var if option exists */
	public function get_option( $option, $var_name ) {
		$a = get_option( $option );

		if ( $a !== false ) {
			global $limit_login_options;

			$limit_login_options[ $var_name ] = $a;
		}
	}


	/* Get current option value */
	public function get_current_option( $option_name ) {
		global $limit_login_options;

		if ( isset( $limit_login_options[ $option_name ] ) ) {
			return $limit_login_options[ $option_name ];
		} else {
			return null;
		}
	}






	/*
	 * Admin stuff
	 */

	/* Make a guess if we are behind a proxy or not */
	public function limit_login_guess_proxy() {
		return isset( $_SERVER[ LIMIT_LOGIN_PROXY_ADDR ] )
			? LIMIT_LOGIN_PROXY_ADDR : LIMIT_LOGIN_DIRECT_ADDR;
	}


	/* Update options in db from global variables */
	public function limit_login_update_options() {
		update_option( 'limit_login_client_type', limit_login_option( 'client_type' ) );
		update_option( 'limit_login_allowed_retries', limit_login_option( 'allowed_retries' ) );
		update_option( 'limit_login_lockout_duration', limit_login_option( 'lockout_duration' ) );
		update_option( 'limit_login_allowed_lockouts', limit_login_option( 'allowed_lockouts' ) );
		update_option( 'limit_login_long_duration', limit_login_option( 'long_duration' ) );
		update_option( 'limit_login_valid_duration', limit_login_option( 'valid_duration' ) );
		update_option( 'limit_login_lockout_notify', limit_login_option( 'lockout_notify' ) );
		update_option( 'limit_login_notify_email_after', limit_login_option( 'notify_email_after' ) );
		update_option( 'limit_login_cookies', limit_login_option( 'cookies' ) ? '1' : '0' );
	}


	/* Make sure the variables make sense -- simple integer */
	public function limit_login_sanitize_simple_int( $var_name ) {
		global $limit_login_options;

		$limit_login_options[ $var_name ] = max( 1, intval( limit_login_option( $var_name ) ) );
	}


	/* Make sure the variables make sense */
	public function limit_login_sanitize_variables() {
		global $limit_login_options;

		limit_login_sanitize_simple_int( 'allowed_retries' );
		limit_login_sanitize_simple_int( 'lockout_duration' );
		limit_login_sanitize_simple_int( 'valid_duration' );
		limit_login_sanitize_simple_int( 'allowed_lockouts' );
		limit_login_sanitize_simple_int( 'long_duration' );

		$limit_login_options['cookies'] = ! ! limit_login_option( 'cookies' );

		$notify_email_after                        = max( 1, intval( limit_login_option( 'notify_email_after' ) ) );
		$limit_login_options['notify_email_after'] = min( limit_login_option( 'allowed_lockouts' ), $notify_email_after );

		$args         = explode( ',', limit_login_option( 'lockout_notify' ) );
		$args_allowed = explode( ',', LIMIT_LOGIN_LOCKOUT_NOTIFY_ALLOWED );
		$new_args     = array();
		foreach ( $args as $a ) {
			if ( in_array( $a, $args_allowed ) ) {
				$new_args[] = $a;
			}
		}
		$limit_login_options['lockout_notify'] = implode( ',', $new_args );

		if ( limit_login_option( 'client_type' ) != LIMIT_LOGIN_DIRECT_ADDR
		     && limit_login_option( 'client_type' ) != LIMIT_LOGIN_PROXY_ADDR
		) {
			$limit_login_options['client_type'] = LIMIT_LOGIN_DIRECT_ADDR;
		}
	}


	/* Add admin options page */
	public function limit_login_admin_menu() {
		global $wp_version;

		// Modern WP?
		if ( version_compare( $wp_version, '3.0', '>=' ) ) {
			add_options_page( 'Limit Login Attempts', 'Limit Login Attempts', 'manage_options', 'limit-login-attempts', 'limit_login_option_page' );

			return;
		}

		// Older WPMU?
		if ( function_exists( "get_current_site" ) ) {
			add_submenu_page( 'wpmu-admin.php', 'Limit Login Attempts', 'Limit Login Attempts', 9, 'limit-login-attempts', 'limit_login_option_page' );

			return;
		}

		// Older WP
		add_options_page( 'Limit Login Attempts', 'Limit Login Attempts', 9, 'limit-login-attempts', 'limit_login_option_page' );
	}


	/* Show log on admin page */
	public function limit_login_show_log( $log ) {
		if ( ! is_array( $log ) || count( $log ) == 0 ) {
			return;
		}

		echo( '<tr><th scope="col">' . _x( "IP", "Internet address", 'limit-login-attempts' ) . '</th><th scope="col">' . __( 'Tried to log in as', 'limit-login-attempts' ) . '</th></tr>' );
		foreach ( $log as $ip => $arr ) {
			echo( '<tr><td class="limit-login-ip">' . $ip . '</td><td class="limit-login-max">' );
			$first = true;
			foreach ( $arr as $user => $count ) {
				$count_desc = sprintf( _n( '%d lockout', '%d lockouts', $count, 'limit-login-attempts' ), $count );
				if ( ! $first ) {
					echo( ', ' . $user . ' (' . $count_desc . ')' );
				} else {
					echo( $user . ' (' . $count_desc . ')' );
				}
				$first = false;
			}
			echo( '</td></tr>' );
		}
	}

}