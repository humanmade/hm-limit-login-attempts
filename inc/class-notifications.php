<?php

namespace HM\Limit_Login_Attempts;

use HM\Limit_Login_Attempts\Plugin;

class Notifications extends Plugin {

	public function load() {

	}

	/* Handle notification in event of lockout */
	public function notify( $user ) {
		$args = explode( ',', get_option( 'hm_limit_login_lockout_notify' ) );

		if ( empty( $args ) ) {
			return;
		}

		foreach ( $args as $mode ) {
			switch ( trim( $mode ) ) {
				case 'email':
					$this->notify_email( $user );
					break;
				case 'log':
					$this->notify_log( $user );
					break;
			}
		}
	}

	/* Email notification of lockout to admin (if configured) */
	private function notify_email( $user ) {
		$validation_object = Validation::get_instance();
		$ip          = $validation_object->get_address();
		$whitelisted = $validation_object->is_ip_whitelisted( $ip );

		$retries = get_option( 'hm_limit_login_retries' );
		if ( ! is_array( $retries ) ) {
			$retries = array();
		}

		/* check if we are at the right nr to do notification */
		if ( isset( $retries[ $ip ] )
		     && ( ( $retries[ $ip ] / get_option( 'hm_limit_login_allowed_retries' ) )
		          % get_option( 'hm_limit_login_notify_email_after' ) ) != 0
		) {
			return;
		}

		/* Format message. First current lockout duration */
		if ( ! isset( $retries[ $ip ] ) ) {
			/* longer lockout */
			$count    = get_option( 'hm_limit_login_allowed_retries' ) * get_option( 'hm_limit_login_allowed_lockouts' );
			$lockouts = get_option( 'hm_limit_login_allowed_lockouts' );
			$time     = round( get_option( 'hm_limit_login_long_duration' ) / 3600 );
			$when     = sprintf( _n( '%d hour', '%d hours', $time, 'limit-login-attempts' ), $time );
		} else {
			/* normal lockout */
			$count    = $retries[ $ip ];
			$lockouts = floor( $count / get_option( 'allowed_retries' ) );
			$time     = round( get_option( 'hm_limit_login_lockout_duration' ) / 60 );
			$when     = sprintf( _n( '%d minute', '%d minutes', $time, 'limit-login-attempts' ), $time );
		}

		$blogname = is_multisite() ? get_site_option( 'site_name' ) : get_option( 'blogname' );

		if ( $whitelisted ) {
			$subject = sprintf( __( "[%s] Failed login attempts from whitelisted IP"
					, 'limit-login-attempts' )
				, $blogname );
		} else {
			$subject = sprintf( __( "[%s] Too many failed login attempts"
					, 'limit-login-attempts' )
				, $blogname );
		}

		$message = sprintf( __( "%d failed login attempts (%d lockout(s)) from IP: %s", 'limit-login-attempts' ) . "\r\n\r\n" , $count, $lockouts, $ip );
		if ( $user != '' ) {
			$message .= sprintf( __( "Last user attempted: %s", 'limit-login-attempts' ) . "\r\n\r\n", $user );
		}
		if ( $whitelisted ) {
			$message .= __( "IP was NOT blocked because of external whitelist.", 'limit-login-attempts' );
		} else {
			$message .= sprintf( __( "IP was blocked for %s", 'limit-login-attempts' ), $when );
		}

		$admin_email = is_multisite() ? get_site_option( 'admin_email' ) : get_option( 'admin_email' );

		@wp_mail( $admin_email, $subject, $message );
	}


	/* Logging of lockout (if configured) */
	private function notify_log( $user ) {
		$log = $option = get_option( 'hm_limit_login_logged' );

		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$validation_object = Validation::get_instance();
		$ip = $validation_object->get_address();

		/* can be written much simpler, if you do not mind php warnings */
		if ( isset( $log[ $ip ] ) ) {
			if ( isset( $log[ $ip ][ $user ] ) ) {
				$log[ $ip ][ $user ] ++;
			} else {
				$log[ $ip ][ $user ] = 1;
			}
		} else {
			$log[ $ip ] = array( $user => 1 );
		}

		if ( $option === false ) {
			add_option( 'hm_limit_login_logged', $log, '', 'no' ); /* no autoload */
		} else {
			update_option( 'hm_limit_login_logged', $log );
		}
	}

}
