<?php

namespace HM\Limit_Login_Attempts;

use HM\Limit_Login_Attempts\Plugin;

class Errors extends Plugin {

	public function load() {

		add_filter( 'shake_error_codes', array( $this, 'failure_shake' ) );
		add_action( 'login_head', array( $this, 'add_error_message' ) );
		add_action( 'login_errors', array( $this, 'fixup_error_messages' ) );

	}

	/* Construct informative error message */

	public function error_msg() {
		$ip       = Validation::get_address();
		$lockouts = get_option( 'hm_limit_login_lockouts' );

		$msg = __( '<strong>ERROR</strong>: Too many failed login attempts.', 'limit-login-attempts' ) . ' ';

		if ( ! is_array( $lockouts ) || ! isset( $lockouts[ $ip ] ) || time() >= $lockouts[ $ip ] ) {
			/* Huh? No timeout active? */
			$msg .= __( 'Please try again later.', 'limit-login-attempts' );

			return $msg;
		}

		$when = ceil( ( $lockouts[ $ip ] - time() ) / 60 );
		if ( $when > 60 ) {
			$when = ceil( $when / 60 );
			$msg .= sprintf( _n( 'Please try again in %d hour.', 'Please try again in %d hours.', $when, 'limit-login-attempts' ), $when );
		} else {
			$msg .= sprintf( _n( 'Please try again in %d minute.', 'Please try again in %d minutes.', $when, 'limit-login-attempts' ), $when );
		}

		return $msg;
	}

	/* Filter: add this failure to login page "Shake it!" */
	public function failure_shake( $error_codes ) {
		$error_codes[] = 'too_many_retries';

		return $error_codes;
	}

	/* Construct retries remaining message */
	private function retries_remaining_msg() {
		$ip      = Validation::get_address();
		$retries = get_option( 'limit_login_retries' );
		$valid   = get_option( 'limit_login_retries_valid' );
		$allowed_retries = $this->option( 'allowed_retries' );

		/* Should we show retries remaining? */

		if ( ! is_array( $retries ) || ! is_array( $valid ) ) {
			/* no retries at all */
			return '';
		}
		if ( ! isset( $retries[ $ip ] ) || ! isset( $valid[ $ip ] ) || time() > $valid[ $ip ] ) {
			/* no: no valid retries */
			return '';
		}
		if ( ( $retries[ $ip ] % $allowed_retries ) == 0 ) {
			/* no: already been locked out for these retries */
			return '';
		}

		$remaining = max( ( $allowed_retries - ( $retries[ $ip ] % $allowed_retries ) ), 0 );

		return sprintf( _n( "<strong>%d</strong> attempt remaining.", "<strong>%d</strong> attempts remaining.", $remaining, 'limit-login-attempts' ), $remaining );
	}


	/* Return current (error) message to show, if any */
	private function get_message() {
		/* Check external whitelist */
		if ( is_ip_whitelisted() ) {
			return '';
		}

		/* Is lockout in effect? */
		if ( ! Validation::is_ok_to_login() ) {
			return $this->_error_msg();
		}

		return $this->retries_remaining_msg();
	}


	/* Should we show errors and messages on this page? */
	private function should_limit_login_show_msg() {
		if ( isset( $_GET['key'] ) ) {
			/* reset password */
			return false;
		}

		$action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';

		return ( $action != 'lostpassword' && $action != 'retrievepassword'
		         && $action != 'resetpass' && $action != 'rp'
		         && $action != 'register' );
	}


	/* Fix up the error message before showing it */
	public function fixup_error_messages( $content ) {
		global $hm_limit_login_just_lockedout, $hm_limit_login_nonempty_credentials, $hm_limit_login_my_error_shown;

		if ( ! $this->should_limit_login_show_msg() ) {
			return $content;
		}

		/*
		 * During lockout we do not want to show any other error messages (like
		 * unknown user or empty password).
		 */
		if ( ! Validation::is_ok_to_login() && ! $hm_limit_login_just_lockedout ) {
			return $this->error_msg();
		}

		/*
		 * We want to filter the messages 'Invalid username' and
		 * 'Invalid password' as that is an information leak regarding user
		 * account names (prior to WP 2.9?).
		 *
		 * Also, if more than one error message, put an extra <br /> tag between
		 * them.
		 */
		$msgs = explode( "<br />\n", $content );

		if ( strlen( end( $msgs ) ) == 0 ) {
			/* remove last entry empty string */
			array_pop( $msgs );
		}

		$count         = count( $msgs );
		$my_warn_count = $hm_limit_login_my_error_shown ? 1 : 0;

		if ( $hm_limit_login_nonempty_credentials && $count > $my_warn_count ) {
			/* Replace error message, including ours if necessary */
			$content = __( '<strong>ERROR</strong>: Incorrect username or password.', 'limit-login-attempts' ) . "<br />\n";
			if ( $hm_limit_login_my_error_shown ) {
				$content .= "<br />\n" . $this->get_message() . "<br />\n";
			}

			return $content;
		} elseif ( $count <= 1 ) {
			return $content;
		}

		$new = '';
		while ( $count -- > 0 ) {
			$new .= array_shift( $msgs ) . "<br />\n";
			if ( $count > 0 ) {
				$new .= "<br />\n";
			}
		}

		return $new;
	}


	/**
	 * Add a message to login page when necessary
	 */
	private function add_error_message() {
		global $error, $hm_limit_login_my_error_shown;

		if ( ! $this->should_show_msg() || $hm_limit_login_my_error_shown ) {
			return;
		}

		$msg = $this->get_message();

		if ( $msg != '' ) {
			$hm_limit_login_my_error_shown = true;
			$error .= $msg;
		}

		return;
	}

	/**
	 * Keep track of if user or password are empty,
	 * to filter errors correctly
	 */
	private function track_credentials( $user, $password ) {
		global $hm_limit_login_nonempty_credentials;

		$hm_limit_login_nonempty_credentials = ( ! empty( $user ) && ! empty( $password ) );
	}
}
