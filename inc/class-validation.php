<?php

namespace HM\Limit_Login_Attempts;

class Validation extends Plugin {

	/**
	 * Track username during login
	 *
	 * @var string|null
	 */
	private $username;

	/**
	 * Track password during login
	 *
	 * @var string|null
	 */
	private $password;

	/**
	 * Has the user only just been locked out
	 *
	 * @var bool
	 */
	private $just_locked_out = false;


	public function load() {
		add_filter( 'authenticate', array( $this, 'track_credentials' ), -10, 3 );
		add_filter( 'authenticate', array( $this, 'authenticate' ), 99999, 1 );
	}

	/**
	 * Return the username from the authentication step
	 *
	 * @return null|string
	 */
	public function get_username() {
		return strtolower( $this->username );
	}

	/**
	 * Check if the password is empty or not, do not pass the value
	 *
	 * @return bool
	 */
	public function has_password() {
		return ! empty( $this->password );
	}

	/**
	 * Get correct remote address
	 *
	 * @param string $type_name
	 * @return string
	 */
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

	/**
	 * Return an array of lockout methods to try
	 *
	 * @return array
	 */
	public function get_lockout_methods() {
		$saved_lockout_methods       = explode( ',', get_option( 'hm_limit_login_lockout_method' ) );
		$lockout_methods             = array();
		$lockout_methods['ip']       = in_array( 'ip', $saved_lockout_methods, true );
		$lockout_methods['username'] = in_array( 'username', $saved_lockout_methods, true );

		return $lockout_methods;
	}

	/**
	 * Returns the array of lockouts
	 *
	 * @return array
	 */
	public function get_lockouts() {
		$lockouts = (array) get_option( 'hm_limit_login_lockouts', array() );
		$lockouts = array_change_key_case( $lockouts, CASE_LOWER );
		return $lockouts;
	}

	/**
	 * Check if it is ok to login
	 *
	 * @return bool
	 */
	public function is_ok_to_login() {

		$lockout_methods = $this->get_lockout_methods();

		// If the method is active then we default the value to false so
		// the validation result has to override it. If it's inactive we
		// assume the result is ok.
		$username_result = ! $lockout_methods['username'];
		$ip_result       = ! $lockout_methods['ip'];

		if ( $lockout_methods['username'] ) {
			$username_result = $this->validate_username_login();
		}

		if ( $lockout_methods['ip'] ) {
			$ip_result = $this->validate_ip_login();
		}

		return ( $ip_result && $username_result );
	}

	private function validate_ip_login() {

		$ip = $this->get_address();

		/* Check external whitelist filter */
		if ( $this->is_ip_whitelisted( $ip ) ) {
			return true;
		}

		$lockouts = $this->get_lockouts();

		return ( ! is_array( $lockouts ) || ! isset( $lockouts[ $ip ] ) || time() >= $lockouts[ $ip ] );

	}

	/**
	 * Check username isn't in block list
	 *
	 * @return bool
	 */
	private function validate_username_login() {

		$username = $this->get_username();
		$lockouts = $this->get_lockouts();

		return ( ! is_array( $lockouts ) || ! isset( $lockouts[ $username ] ) || time() >= $lockouts[ $username ] );
	}

	/**
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
	 *    return ($ip == 'my-ip') ? true : $allow;
	 * }
	 * add_filter('hm_limit_login_whitelist_ip', 'my_ip_whitelist', 10, 2);
	 *
	 * @param null|string $ip
	 * @return bool
	 */
	public function is_ip_whitelisted( $ip = null ) {
		if ( is_null( $ip ) ) {
			$ip = $this->get_address();
		}
		$whitelisted = apply_filters( 'hm_limit_login_whitelist_ip', false, $ip );

		return ( $whitelisted === true );
	}

	/**
	 * Filter: allow login attempt? (called from wp_authenticate())
	 *
	 * @param \WP_User|\WP_Error $user
	 * @return \WP_User|\WP_Error
	 */
	public function authenticate( $user ) {
		
		if ( $this->is_ok_to_login() ) {
			if ( $user instanceof \WP_User ) {
				$this->cleanup_on_login();
			}
			return $user;
		}

		$error = new \WP_Error();
		// This error should be the same as in "shake it" filter below
		$errors_object = Errors::get_instance();
		$errors_object->show_error();
		$error->add( 'too_many_retries', $errors_object->error_msg() );

		return $error;
	}

	/**
	 * Tidy up retries on a successful login
	 */
	protected function cleanup_on_login() {

		$cookies = Cookies::get_instance();
		list( , $valid, )  = $cookies->get_retries_data();

		foreach( array( $this->get_address(), $this->get_username() ) as $lockout_item ) {
			if ( isset( $valid[ $lockout_item ] ) ) {
				$valid[ $lockout_item ] = -1;
			}
		}

		// Removes the lockout and retries after a successful login
		$cookies->cleanup( null, null, $valid );
	}

	/**
	 * Set the passed in credentials early.
	 *
	 * @param $user
	 * @param $username
	 * @param $password
	 */
	public function track_credentials( $user, $username, $password ) {

		$this->username = $username;
		$this->password = $password;

		return $user;
	}

	/**
	 * Lockout the user immediately.
	 */
	public function lockout() {
		$this->just_locked_out = true;
	}

	/**
	 * Get the lockout status.
	 */
	public function just_locked_out() {
		return $this->just_locked_out;
	}

}
