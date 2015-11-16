<?php

namespace HM\Limit_Login_Attempts;

use HM\Limit_Login_Attempts\Plugin;

class Cookies extends Plugin {

	public function load() {

		if ( get_option( 'hn_limiy_login_cookies' ) ) {

			$this->handle_cookies();

			add_action( 'auth_cookie_bad_username', array( $this, 'failed_cookie' ) );
			add_action( 'auth_cookie_bad_hash', array( $this, 'failed_cookie_hash' ) );
			add_action( 'auth_cookie_valid', array( $this, 'valid_cookie' ), 10, 2 );
			add_action( 'wp_login_failed', array( $this, 'failed' ) );

		}

	}

	/**
	 * Must be called in plugin_loaded (really early) to make sure we do not allow
	 * auth cookies while locked out.
	 */
	private function handle_cookies() {
		if ( Validation::is_ok_to_login() ) {
			return;
		}

		$this->clear_auth_cookie();
	}

	/* Action: failed cookie login (calls limit_login_failed()) */
	private function failed_cookie( $cookie_elements ) {
		$this->clear_auth_cookie();

		/*
		 * Invalid username gets counted every time.
		 */

		$this->failed( $cookie_elements['username'] );
	}

	/*
	 * Action: failed cookie login hash
	 *
	 * Make sure same invalid cookie doesn't get counted more than once.
	 *
	 * Requires WordPress version 3.0.0, previous versions use limit_login_failed_cookie()
	 */
	private function failed_cookie_hash( $cookie_elements ) {
		$this->clear_auth_cookie();

		/*
		 * Under some conditions an invalid auth cookie will be used multiple
		 * times, which results in multiple failed attempts from that one
		 * cookie.
		 *
		 * Unfortunately I've not been able to replicate this consistently and
		 * thus have not been able to make sure what the exact cause is.
		 *
		 * Probably it is because a reload of for example the admin dashboard
		 * might result in multiple requests from the browser before the invalid
		 * cookie can be cleard.
		 *
		 * Handle this by only counting the first attempt when the exact same
		 * cookie is attempted for a user.
		 */

		extract( $cookie_elements, EXTR_OVERWRITE );

		// Check if cookie is for a valid user
		$user = get_user_by( 'login', $username );
		if ( ! $user ) {
			// "shouldn't happen" for this action
			$this->failed( $username );

			return;
		}

		$previous_cookie = get_user_meta( $user->ID, 'hm_limit_login_previous_cookie', true );
		if ( $previous_cookie && $previous_cookie == $cookie_elements ) {
			// Identical cookies, ignore this attempt
			return;
		}

		// Store cookie
		if ( $previous_cookie ) {
			update_user_meta( $user->ID, 'hm_limit_login_previous_cookie', $cookie_elements );
		} else {
			add_user_meta( $user->ID, 'hm_limit_login_previous_cookie', $cookie_elements, true );
		}

		$this->failed( $username );
	}


	/*
	 * Action: successful cookie login
	 *
	 * Clear any stored user_meta.
	 *
	 * Requires WordPress version 3.0.0, not used in previous versions
	 */
	private function valid_cookie( $cookie_elements, $user ) {
		/*
		 * As all meta values get cached on user load this should not require
		 * any extra work for the common case of no stored value.
		 */

		if ( get_user_meta( $user->ID, 'hm_limit_login_previous_cookie' ) ) {
			delete_user_meta( $user->ID, 'hm_limit_login_previous_cookie' );
		}
	}

	/*
	 * Action when login attempt failed
	 *
	 * Increase nr of retries (if necessary). Reset valid value. Setup
	 * lockout if nr of retries are above threshold. And more!
	 *
	 * A note on external whitelist: retries and statistics are still counted and
	 * notifications done as usual, but no lockout is done.
	 */
	function failed( $username ) {

		$validation_object = Validation::get_instance();

		$ip = $validation_object->get_address();

		/* if currently locked-out, do not add to retries */
		$lockouts = get_option( 'hm_limit_login_lockouts' );
		if ( ! is_array( $lockouts ) ) {
			$lockouts = array();
		}
		if ( isset( $lockouts[ $ip ] ) && time() < $lockouts[ $ip ] ) {
			return;
		}

		/* Get the arrays with retries and retries-valid information */
		$retries = get_option( 'hm_limit_login_retries' );
		$valid   = get_option( 'hm_limit_login_retries_valid' );
		if ( ! is_array( $retries ) ) {
			$retries = array();
			add_option( 'hm_limit_login_retries', $retries, '', 'no' );
		}
		if ( ! is_array( $valid ) ) {
			$valid = array();
			add_option( 'hm_limit_login_retries_valid', $valid, '', 'no' );
		}

		/* Check validity and add one to retries */
		if ( isset( $retries[ $ip ] ) && isset( $valid[ $ip ] ) && time() < $valid[ $ip ] ) {
			$retries[ $ip ] ++;
		} else {
			$retries[ $ip ] = 1;
		}

		$valid[ $ip ] = time() + absint( get_option( 'hm_limit_login_valid_duration' ) );

		/* lockout? */
		if ( $retries[ $ip ] %  absint( get_option( 'hm_limit_login_allowed_retries' ) )  != 0 ) {
			/*
			 * Not lockout (yet!)
			 * Do housecleaning (which also saves retry/valid values).
			 */
			$this->cleanup( $retries, null, $valid );

			return;
		}

		/* lockout! */

		$whitelisted = $validation_object->is_ip_whitelisted( $ip );

		$retries_long = get_option( 'hm_limit_login_allowed_retries' )
		                * get_option( 'hm_limit_login_allowed_lockouts' );

		/*
		 * Note that retries and statistics are still counted and notifications
		 * done as usual for whitelisted ips , but no lockout is done.
		 */
		if ( $whitelisted ) {
			if ( $retries[ $ip ] >= $retries_long ) {
				unset( $retries[ $ip ] );
				unset( $valid[ $ip ] );
			}
		} else {
			global $limit_login_just_lockedout;
			$limit_login_just_lockedout = true;

			/* setup lockout, reset retries as needed */
			if ( $retries[ $ip ] >= $retries_long ) {
				/* long lockout */
				$lockouts[ $ip ] = time() + get_option( 'hm_limit_login_long_duration' );
				unset( $retries[ $ip ] );
				unset( $valid[ $ip ] );
			} else {
				/* normal lockout */
				$lockouts[ $ip ] = time() + get_option( 'hm_limit_login_lockout_duration' );
			}
		}

		/* do housecleaning and save values */
		$this->cleanup( $retries, $lockouts, $valid );

		/* do any notification */
		$notifcation_object = Notifications::get_instance();
		$notifcation_object->notify( $username );

		/* increase statistics */
		$total = get_option( 'hm_limit_login_lockouts_total' );
		if ( $total === false || ! is_numeric( $total ) ) {
			add_option( 'hm_limit_login_lockouts_total', 1, '', 'no' );
		} else {
			update_option( 'hm_limit_login_lockouts_total', $total + 1 );
		}
	}


	/* Make sure auth cookie really get cleared (for this session too) */
	private function clear_auth_cookie() {
		wp_clear_auth_cookie();

		if ( ! empty( $_COOKIE[ AUTH_COOKIE ] ) ) {
			$_COOKIE[ AUTH_COOKIE ] = '';
		}
		if ( ! empty( $_COOKIE[ SECURE_AUTH_COOKIE ] ) ) {
			$_COOKIE[ SECURE_AUTH_COOKIE ] = '';
		}
		if ( ! empty( $_COOKIE[ LOGGED_IN_COOKIE ] ) ) {
			$_COOKIE[ LOGGED_IN_COOKIE ] = '';
		}
	}


	/* Clean up old lockouts and retries, and save supplied arrays */
	public function cleanup( $retries = null, $lockouts = null, $valid = null ) {
		$now      = time();
		$lockouts = ! is_null( $lockouts ) ? $lockouts : get_option( 'hm_limit_login_lockouts' );

		/* remove old lockouts */
		if ( is_array( $lockouts ) ) {
			foreach ( $lockouts as $ip => $lockout ) {
				if ( $lockout < $now ) {
					unset( $lockouts[ $ip ] );
				}
			}
			update_option( 'hm_limit_login_lockouts', $lockouts );
		}

		/* remove retries that are no longer valid */
		$valid   = ! is_null( $valid ) ? $valid : get_option( 'hm_limit_login_retries_valid' );
		$retries = ! is_null( $retries ) ? $retries : get_option( 'hm_limit_login_retries' );
		if ( ! is_array( $valid ) || ! is_array( $retries ) ) {
			return;
		}

		foreach ( $valid as $ip => $lockout ) {
			if ( $lockout < $now ) {
				unset( $valid[ $ip ] );
				unset( $retries[ $ip ] );
			}
		}

		/* go through retries directly, if for some reason they've gone out of sync */
		foreach ( $retries as $ip => $retry ) {
			if ( ! isset( $valid[ $ip ] ) ) {
				unset( $retries[ $ip ] );
			}
		}

		update_option( 'hm_limit_login_retries', $retries );
		update_option( 'hm_limit_login_retries_valid', $valid );
	}


}