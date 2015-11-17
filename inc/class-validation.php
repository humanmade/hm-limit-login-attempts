<?php

namespace HM\Limit_Login_Attempts;

use HM\Limit_Login_Attempts\Plugin;

class Validation extends Plugin {

	public function load() {
		add_filter( 'wp_authenticate_user', array( $this, 'wp_authenticate_user' ), 99999, 2 );
	}

	/* Get correct remote address */
	public function get_address( $type_name = '' ) {
		$type = $type_name;

		if ( empty( $type ) ) {
			$type = get_option( 'hm_limit_login_client_type' );
		}

		if ( isset( $_SERVER[ $type ] ) ) {
			return $_SERVER[ $type ];
		}

		/*
		 * Not found. Did we get proxy type from option?
		 * If so, try to fall back to direct address.
		 */
		if ( empty( $type_name ) && $type == HM_LIMIT_LOGIN_PROXY_ADDR
		     && isset( $_SERVER[ HM_LIMIT_LOGIN_DIRECT_ADDR ] )
		) {
			/*
			 * NOTE: Even though we fall back to direct address -- meaning you
			 * can get a mostly working plugin when set to PROXY mode while in
			 * fact directly connected to Internet it is not safe!
			 *
			 * Client can itself send HTTP_X_FORWARDED_FOR header fooling us
			 * regarding which IP should be banned.
			 */

			return $_SERVER[ HM_LIMIT_LOGIN_DIRECT_ADDR ];
		}

		return '';
	}

	public function get_lockout_method( ) {
		$saved_lockout_method = explode( ',', get_option( 'hm_limit_login_lockout_method' ) );
		$lockout_method = array();
		$lockout_method['ip'] = in_array( 'ip', $saved_lockout_method ) ? true : false ;
		$lockout_method['username'] = in_array( 'username', $saved_lockout_method ) ? true : false;

		return $lockout_method;

	}

	/* Check if it is ok to login */
	public function is_ok_to_login( $user ) {

		$lockout_method = $this->get_lockout_method();

		$ip_result = false;
		$username_result = true;

		if( $lockout_method['ip'] ){
			$ip_result = $this->validate_ip_login();
		}

		if( $lockout_method['username'] ) {
			$username_result = $this->validate_username_login( $user );
		}

		return ( $ip_result || $username_result );
	}

	private function validate_ip_login() {

		$ip = $this->get_address();

		/* Check external whitelist filter */
		if ( $this->is_ip_whitelisted( $ip ) ) {
			return true;
		}

		/* lockout active? */
		$lockouts = get_option( 'hm_limit_login_lockouts' );

		return ( ! is_array( $lockouts ) || ! isset( $lockouts[ $ip ] ) || time() >= $lockouts[ $ip ] );

	}

	private function validate_username_login($user) {

		if( empty( $user ) ){
			return false;
		}

		$username = $user->user_login;

		$lockouts = get_option( 'hm_limit_login_lockouts' );

		return false;
		return ( ! is_array( $lockouts ) || ! isset( $lockouts[ $username ] ) || time() >= $lockouts[ $username ] );

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
	 * add_filter('hm_limit_login_whitelist_ip', 'my_ip_whitelist', 10, 2);
	 */
	public function is_ip_whitelisted( $ip = null ) {
		if ( is_null( $ip ) ) {
			$ip = $this->get_address();
		}
		$whitelisted = apply_filters( 'hm_limit_login_whitelist_ip', false, $ip );

		return ( $whitelisted === true );
	}


	/* Filter: allow login attempt? (called from wp_authenticate()) */
	public function wp_authenticate_user( $user, $password ) {

		if ( is_wp_error( $user ) || $this->is_ok_to_login( $user ) ) {
			return $user;
		}

		global $limit_login_my_error_shown;
		$limit_login_my_error_shown = true;

		$error = new \WP_Error();
		// This error should be the same as in "shake it" filter below
		$errors_object = Errors::get_instance();
		$error->add( 'too_many_retries', $errors_object->error_msg() );

		return $error;
	}

}
