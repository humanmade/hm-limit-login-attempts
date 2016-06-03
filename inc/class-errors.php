<?php

namespace HM\Limit_Login_Attempts;

class Errors extends Plugin {

	/**
	 * Whether to show the user's error
	 *
	 * @var
	 */
	private $error_shown;

	public function load() {

		add_filter( 'shake_error_codes', array( $this, 'failure_shake' ) );
		add_action( 'login_head', array( $this, 'add_error_message' ) );
		add_action( 'login_errors', array( $this, 'fixup_error_messages' ) );

	}

	/* Construct informative error message */

	public function error_msg() {

		$validation_object = Validation::get_instance();
		$ip                = $validation_object->get_address();
		$username          = $validation_object->get_username();
		$lockouts          = $validation_object->get_lockouts();

		$msg = __( '<strong>ERROR</strong>: Too many failed login attempts.', 'limit-login-attempts' ) . ' ';

		if (
			( ! is_array( $lockouts ) || ! isset( $lockouts[ $username ] ) || time() >= $lockouts[ $username ] )
			&&
			( ! is_array( $lockouts ) || ! isset( $lockouts[ $ip ] ) || time() >= $lockouts[ $ip ] )
		) {
			/* Huh? No timeout active? */
			$msg .= __( 'Please try again later.', 'limit-login-attempts' );

			return $msg;
		}

		if ( isset( $lockouts[ $username ] ) ) {
			$lockout_item = $username;
		} else {
			$lockout_item = $ip;
		}

		$when = ceil( ( $lockouts[ $lockout_item ] - time() ) / 60 );
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

		$validation_object = Validation::get_instance();
		$ip                = $validation_object->get_address();
		$username          = $validation_object->get_username();
		$retries           = get_option( 'hm_limit_login_retries' );
		$valid             = get_option( 'hm_limit_login_retries_valid' );
		$allowed_retries   = get_option( 'hm_limit_login_allowed_retries' );

		/* Should we show retries remaining? */

		if ( ! is_array( $retries ) || ! is_array( $valid ) ) {
			/* no retries at all */
			return '';
		}
		if (
			( ! isset( $retries[ $username ] ) || ! isset( $valid[ $username ] ) || time() > $valid[ $username ] )
			&&
			( ! isset( $retries[ $ip ] ) || ! isset( $valid[ $ip ] ) || time() > $valid[ $ip ] )
		) {
			/* no: no valid retries */
			return '';
		}

		if ( isset( $retries[ $username ] ) ) {
			$lockout_item = $username;
		} else {
			$lockout_item = $ip;
		}

		if ( 0 === ( $retries[ $lockout_item ] % $allowed_retries ) ) {
			/* no: already been locked out for these retries */
			return '';
		}

		$remaining = max( ( $allowed_retries - ( $retries[ $lockout_item ] % $allowed_retries ) ), 0 );

		return sprintf( _n( "<strong>%d</strong> attempt remaining.", "<strong>%d</strong> attempts remaining.", $remaining, 'limit-login-attempts' ), $remaining );
	}

	/**
	 * Sets the class variable $this->error_shown to true
	 */
	public function show_error() {
		$this->error_shown = true;
	}

	/* Return current (error) message to show, if any */
	private function get_message() {

		$validation_object = Validation::get_instance();

		/* Check external whitelist */
		if ( $validation_object->is_ip_whitelisted() ) {
			return '';
		}

		/* Is lockout in effect? */
		if ( ! $validation_object->is_ok_to_login() ) {
			return $this->error_msg();
		}

		return $this->retries_remaining_msg();
	}


	/* Should we show errors and messages on this page? */
	private function should_show_msg() {
		if ( isset( $_GET['key'] ) ) {
			/* reset password */
			return false;
		}

		$action     = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
		$logged_out = isset( $_REQUEST['loggedout'] );

		return ! $logged_out && ! in_array( $action, array(
			'lostpassword',
			'retrievepassword',
			'resetpass',
			'rp',
			'register',
		) );
	}


	/* Fix up the error message before showing it */
	public function fixup_error_messages( $content ) {

		if ( ! $this->should_show_msg() ) {
			return $content;
		}

		/*
		 * During lockout we do not want to show any other error messages (like
		 * unknown user or empty password).
		 */
		$validation_object = Validation::get_instance();
		if ( ! $validation_object->is_ok_to_login() && ! $validation_object->just_locked_out() ) {
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
		$my_warn_count = $this->error_shown ? 1 : 0;

		if ( ! empty( $validation_object->get_username() ) && $validation_object->has_password() && $count > $my_warn_count ) {
			/* Replace error message, including ours if necessary */
			$content = __( '<strong>ERROR</strong>: Incorrect username or password.', 'limit-login-attempts' ) . "<br />\n";
			if ( $this->error_shown ) {
				$content .= "<br />\n" . $this->get_message() . "<br />\n";
			}

			return $content;
		} elseif ( $count <= 1 ) {
			return $content;
		}

		$new = '';
		for ( $i = $count; $i > 0; $i-- ) {
			$new .= array_shift( $msgs ) . "<br />\n";
			if ( $i > 0 ) {
				$new .= "<br />\n";
			}
		}

		return $new;
	}


	/**
	 * Add a message to login page when necessary
	 */
	public function add_error_message() {
		global $error;

		if ( ! $this->should_show_msg() || $this->error_shown ) {
			return;
		}

		$msg = $this->get_message();

		if ( $msg != '' ) {
			$this->error_shown = true;
			$error .= $msg;
		}

		return;
	}

}
