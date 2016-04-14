<?php

namespace HM\Limit_Login_Attempts;

class Notifications extends Plugin {

	public function load() {

	}

	/* Handle notification in event of lockout */
	public function notify() {
		$args = explode( ',', get_option( 'hm_limit_login_lockout_notify' ) );

		if ( empty( $args ) ) {
			return;
		}

		foreach ( $args as $mode ) {
			switch ( trim( $mode ) ) {
				case 'email':
					$this->notify_email();
					break;
				case 'log':
					$this->notify_log();
					break;
			}
		}
	}

	/* Email notification of lockout to admin (if configured) */
	private function notify_email() {
		$validation_object = Validation::get_instance();
		$ip                = $validation_object->get_address();
		$username          = $validation_object->get_username();
		$whitelisted       = $validation_object->is_ip_whitelisted( $ip );
		$lockout_methods   = $validation_object->get_lockout_methods();
		$blogname          = is_multisite() ? get_site_option( 'site_name' ) : get_option( 'blogname' );
		$subject           = sprintf( __( "[%s] Too many failed login attempts", 'limit-login-attempts' ), $blogname );
		$message           = '';

		$retries = get_option( 'hm_limit_login_retries' );
		if ( ! is_array( $retries ) ) {
			$retries = array();
		}

		foreach ( $lockout_methods as $method => $active ) {

			if ( ! $active ) {
				continue;
			}

			// Get the item currently being checked
			$lockout_item = $$method;

			/* check if we are at the right nr to do notification */
			if ( isset( $retries[ $lockout_item ] )
				&& ( ( $retries[ $lockout_item ] / get_option( 'hm_limit_login_allowed_retries' ) )
					% get_option( 'hm_limit_login_notify_email_after' ) ) != 0
			) {
				return;
			}

			/* Format message. First current lockout duration */
			if ( ! isset( $retries[ $lockout_item ] ) ) {
				/* longer lockout */
				$count    = get_option( 'hm_limit_login_allowed_retries' ) * get_option( 'hm_limit_login_allowed_lockouts' );
				$lockouts = get_option( 'hm_limit_login_allowed_lockouts' );
				$time     = round( get_option( 'hm_limit_login_long_duration' ) / 3600 );
				$when     = sprintf( _n( '%d hour', '%d hours', $time, 'limit-login-attempts' ), $time );
			} else {
				/* normal lockout */
				$count    = $retries[ $lockout_item ];
				$lockouts = floor( $count / get_option( 'allowed_retries' ) );
				$time     = round( get_option( 'hm_limit_login_lockout_duration' ) / 60 );
				$when     = sprintf( _n( '%d minute', '%d minutes', $time, 'limit-login-attempts' ), $time );
			}

			switch( $method ) {
				case 'username':
					$message .= sprintf( __( '%d failed login attempts (%d lockout(s)) from User: %s', 'limit-login-attempts' ) . "\r\n\r\n",
						$count,
						$lockouts,
						$username );
					break;
				case 'ip':
					$message .= sprintf( __( '%d failed login attempts (%d lockout(s)) from IP: %s', 'limit-login-attempts' ) . "\r\n\r\n",
						$count,
						$lockouts,
						$ip );

					if ( $whitelisted ) {
						$subject = sprintf( __( '[%s] Failed login attempts from whitelisted IP', 'limit-login-attempts' ),
							$blogname );
						$message .= __( 'IP was NOT blocked because of external whitelist.', 'limit-login-attempts' ) . "\r\n\r\n";
					} else {
						$message .= sprintf( __( 'IP was blocked for %s', 'limit-login-attempts' ) . "\r\n\r\n",
							$when );
					}
					break;
			}

		}

		if ( ! empty( $username ) ) {
			$message .= sprintf( __( 'Last user attempted: %s', 'limit-login-attempts' ) . "\r\n\r\n", $username );
		}

		$admin_email = get_site_option( 'admin_email' );

		@wp_mail( $admin_email, $subject, $message );
	}

	/* Logging of lockout (if configured) */
	private function notify_log() {
		$log = $option = get_option( 'hm_limit_login_logged' );

		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$validation_object = Validation::get_instance();
		$ip                = $validation_object->get_address();
		$username          = $validation_object->get_username();

		if ( isset( $log[ $ip ] ) ) {
			if ( isset( $log[ $ip ][ $username ] ) ) {
				$log[ $ip ][ $username ]++;
			} else {
				$log[ $ip ][ $username ] = 1;
			}
		} else {
			$log[ $ip ] = array( $username => 1 );
		}

		if ( $option === false ) {
			add_option( 'hm_limit_login_logged', $log, '', 'no' ); /* no autoload */
		} else {
			update_option( 'hm_limit_login_logged', $log );
		}
	}

}

