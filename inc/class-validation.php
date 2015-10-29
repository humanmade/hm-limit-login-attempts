<?php

namespace HM\Limit_Login_Attempts;

use HM\Limit_Login_Attempts\Plugin;

class Validation extends Plugin {

	public function load() {
		add_action( 'wp_login_failed', 'limit_login_failed' );
		add_filter( 'wp_authenticate_user', 'limit_login_wp_authenticate_user', 99999, 2 );
	}

	/* Get correct remote address */
	function limit_login_get_address( $type_name = '' ) {
		$type = $type_name;
		if ( empty( $type ) ) {
			$type = limit_login_option( 'client_type' );
		}

		if ( isset( $_SERVER[ $type ] ) ) {
			return $_SERVER[ $type ];
		}

		/*
		 * Not found. Did we get proxy type from option?
		 * If so, try to fall back to direct address.
		 */
		if ( empty( $type_name ) && $type == LIMIT_LOGIN_PROXY_ADDR
		     && isset( $_SERVER[ LIMIT_LOGIN_DIRECT_ADDR ] )
		) {

			/*
			 * NOTE: Even though we fall back to direct address -- meaning you
			 * can get a mostly working plugin when set to PROXY mode while in
			 * fact directly connected to Internet it is not safe!
			 *
			 * Client can itself send HTTP_X_FORWARDED_FOR header fooling us
			 * regarding which IP should be banned.
			 */

			return $_SERVER[ LIMIT_LOGIN_DIRECT_ADDR ];
		}

		return '';
	}

	/* Clean up old lockouts and retries, and save supplied arrays */
	function limit_login_cleanup( $retries = null, $lockouts = null, $valid = null ) {
		$now      = time();
		$lockouts = ! is_null( $lockouts ) ? $lockouts : get_option( 'limit_login_lockouts' );

		/* remove old lockouts */
		if ( is_array( $lockouts ) ) {
			foreach ( $lockouts as $ip => $lockout ) {
				if ( $lockout < $now ) {
					unset( $lockouts[ $ip ] );
				}
			}
			update_option( 'limit_login_lockouts', $lockouts );
		}

		/* remove retries that are no longer valid */
		$valid   = ! is_null( $valid ) ? $valid : get_option( 'limit_login_retries_valid' );
		$retries = ! is_null( $retries ) ? $retries : get_option( 'limit_login_retries' );
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

		update_option( 'limit_login_retries', $retries );
		update_option( 'limit_login_retries_valid', $valid );
	}


	/* Is this WP Multisite? */
	function is_limit_login_multisite() {
		return function_exists( 'get_site_option' ) && function_exists( 'is_multisite' ) && is_multisite();
	}


	/* Check if it is ok to login */
	function is_limit_login_ok() {
		$ip = limit_login_get_address();

		/* Check external whitelist filter */
		if ( is_limit_login_ip_whitelisted( $ip ) ) {
			return true;
		}

		/* lockout active? */
		$lockouts = get_option( 'limit_login_lockouts' );

		return ( ! is_array( $lockouts ) || ! isset( $lockouts[ $ip ] ) || time() >= $lockouts[ $ip ] );
	}


	/*
	 * Check if IP is whitelisted.
	 *
	 * This function allow external ip whitelisting using a filter. Note that it can
	 * be called multiple times during the login process.
	 *
	 * Note that retries and statistics are still counted and notifications
	 * done as usual for whitelisted ips , but no lockout is done.
	 *
	 * Example:
	 * function my_ip_whitelist($allow, $ip) {
	 * 	return ($ip == 'my-ip') ? true : $allow;
	 * }
	 * add_filter('limit_login_whitelist_ip', 'my_ip_whitelist', 10, 2);
	 */
	function is_limit_login_ip_whitelisted( $ip = null ) {
		if ( is_null( $ip ) ) {
			$ip = limit_login_get_address();
		}
		$whitelisted = apply_filters( 'limit_login_whitelist_ip', false, $ip );

		return ( $whitelisted === true );
	}


	/* Filter: allow login attempt? (called from wp_authenticate()) */
	function limit_login_wp_authenticate_user( $user, $password ) {
		if ( is_wp_error( $user ) || is_limit_login_ok() ) {
			return $user;
		}

		global $limit_login_my_error_shown;
		$limit_login_my_error_shown = true;

		$error = new WP_Error();
		// This error should be the same as in "shake it" filter below
		$error->add( 'too_many_retries', limit_login_error_msg() );

		return $error;
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
	function limit_login_failed( $username ) {
		$ip = limit_login_get_address();

		/* if currently locked-out, do not add to retries */
		$lockouts = get_option( 'limit_login_lockouts' );
		if ( ! is_array( $lockouts ) ) {
			$lockouts = array();
		}
		if ( isset( $lockouts[ $ip ] ) && time() < $lockouts[ $ip ] ) {
			return;
		}

		/* Get the arrays with retries and retries-valid information */
		$retries = get_option( 'limit_login_retries' );
		$valid   = get_option( 'limit_login_retries_valid' );
		if ( ! is_array( $retries ) ) {
			$retries = array();
			add_option( 'limit_login_retries', $retries, '', 'no' );
		}
		if ( ! is_array( $valid ) ) {
			$valid = array();
			add_option( 'limit_login_retries_valid', $valid, '', 'no' );
		}

		/* Check validity and add one to retries */
		if ( isset( $retries[ $ip ] ) && isset( $valid[ $ip ] ) && time() < $valid[ $ip ] ) {
			$retries[ $ip ] ++;
		} else {
			$retries[ $ip ] = 1;
		}
		$valid[ $ip ] = time() + limit_login_option( 'valid_duration' );

		/* lockout? */
		if ( $retries[ $ip ] % limit_login_option( 'allowed_retries' ) != 0 ) {
			/*
			 * Not lockout (yet!)
			 * Do housecleaning (which also saves retry/valid values).
			 */
			limit_login_cleanup( $retries, null, $valid );

			return;
		}

		/* lockout! */

		$whitelisted = is_limit_login_ip_whitelisted( $ip );

		$retries_long = limit_login_option( 'allowed_retries' )
		                * limit_login_option( 'allowed_lockouts' );

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
				$lockouts[ $ip ] = time() + limit_login_option( 'long_duration' );
				unset( $retries[ $ip ] );
				unset( $valid[ $ip ] );
			} else {
				/* normal lockout */
				$lockouts[ $ip ] = time() + limit_login_option( 'lockout_duration' );
			}
		}

		/* do housecleaning and save values */
		limit_login_cleanup( $retries, $lockouts, $valid );

		/* do any notification */
		limit_login_notify( $username );

		/* increase statistics */
		$total = get_option( 'limit_login_lockouts_total' );
		if ( $total === false || ! is_numeric( $total ) ) {
			add_option( 'limit_login_lockouts_total', 1, '', 'no' );
		} else {
			update_option( 'limit_login_lockouts_total', $total + 1 );
		}
	}
}