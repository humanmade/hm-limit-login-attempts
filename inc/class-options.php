<?php

namespace HM\Limit_Login_Attempts;

use HM\Limit_Login_Attempts\Plugin;

/**
 * Class Options
 * @package HM\Limit_Login_Attempts
 */
class Options extends Plugin {

	public function load() {

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

	}

	/**
	 * Add admin options page
	 */
	public function admin_menu() {

		add_options_page( 'HM Limit Login Attempts', 'HM Limit Login', 'manage_options', 'hm-limit-login-attempts', array( $this, 'option_page' ) );

	}

	/**
	 * Show log on admin page
	 */
	private function show_log( $log ) {

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

	/* Actual admin page */
	public function option_page() {
		$cookies_object = Cookies::get_instance();
		$cookies_object->cleanup();

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sorry, but you do not have permissions to change settings.' );
		}

		/* Make sure post was from this page */
		if ( count( $_POST ) > 0 ) {
			check_admin_referer( 'hm-limit-login-attempts-options' );
		}

		/* Should we clear log? */
		if ( isset( $_POST['clear_log'] ) ) {
			delete_option( 'hm_limit_login_logged' );
			echo '<div id="message" class="updated fade"><p>'
			     . __( 'Cleared IP log', 'limit-login-attempts' )
			     . '</p></div>';
		}

		/* Should we reset counter? */
		if ( isset( $_POST['reset_total'] ) ) {
			update_option( 'hm_limit_login_lockouts_total', 0 );
			echo '<div id="message" class="updated fade"><p>'
			     . __( 'Reset lockout count', 'limit-login-attempts' )
			     . '</p></div>';
		}

		/* Should we restore current lockouts? */
		if ( isset( $_POST['reset_current'] ) ) {
			update_option( 'hm_limit_login_lockouts', array() );
			echo '<div id="message" class="updated fade"><p>'
			     . __( 'Cleared current lockouts', 'limit-login-attempts' )
			     . '</p></div>';
		}

		/* Should we update options? */
		if ( isset( $_POST['update_options'] ) ) {

			$new_options = array();

			$new_options['client_type']        = $_POST['client_type'];
			$new_options['allowed_retries']    = absint( $_POST['allowed_retries'] );
			$new_options['lockout_duration']   = absint( $_POST['lockout_duration'] * 60 ); // into seconds
			$new_options['valid_duration']     = absint( $_POST['valid_duration'] * 3600 ); // into seconds
			$new_options['allowed_lockouts']   = absint( $_POST['allowed_lockouts'] );
			$new_options['long_duration']      = absint( $_POST['long_duration'] * 3600 );  // into seconds
			$new_options['notify_email_after'] = absint( $_POST['email_after'] );
			$new_options['cookies']            = absint( isset( $_POST['cookies'] ) && $_POST['cookies'] == '1' );

			$v = array();
			if ( isset( $_POST['lockout_notify_log'] ) ) {
				$v[] = 'log';
			}
			if ( isset( $_POST['lockout_notify_email'] ) ) {
				$v[] = 'email';
			}
			$new_options['lockout_notify'] = implode( ',', $v );



			foreach( $new_options as $option_key => $option_value ){

				$meta_key = 'hm_limit_login_' . $option_key;
				$meta_value = $option_value;

				update_option( $meta_key, $meta_value );

			}

			echo '<div id="message" class="updated fade"><p>'
			     . __( 'Options changed', 'limit-login-attempts' )
			     . '</p></div>';
		}

		/* Get current options to populate the form with */
		$client_type        = get_option( 'hm_limit_login_client_type' );
		$allowed_retries    = absint( get_option( 'hm_limit_login_allowed_retries' ) );
		$lockout_duration   = absint( get_option( 'hm_limit_login_lockout_duration' ) ) / 60 ; // in minutes
		$valid_duration     = absint( get_option( 'hm_limit_login_valid_duration' ) ) / 3600; // in hours
		$allowed_lockouts   = absint( get_option( 'hm_limit_login_allowed_lockouts') );
		$long_duration      = absint( get_option( 'hm_limit_login_long_duration' ) ) / 3600; // in hours
		$notify_email_after = absint( get_option( 'hm_limit_login_email_after' ) );
		$cookies            = absint( isset( $_POST['cookies'] ) && $_POST['cookies'] == '1' );
		$lockouts_total     = absint( get_option( 'hm_limit_login_lockouts_total', 0 ) );
		$lockouts           = get_option( 'hm_limit_login_lockouts' );
		$lockouts_now       = is_array( $lockouts ) ? count( $lockouts ) : 0;
		$cookies_yes        = get_option( 'hm_limit_login_cookies' ) ? ' checked ' : '';
		$cookies_no         = get_option( 'hm_limit_login_cookies' ) ? '' : ' checked ';
		$client_type_direct = ( $client_type == LIMIT_LOGIN_DIRECT_ADDR ? ' checked ' : '' );
		$client_type_proxy  = ( $client_type == LIMIT_LOGIN_PROXY_ADDR ? ' checked ' : '' );
		$client_type_guess  = $this->guess_proxy();
		$client_type_message = '';
		$client_type_warning = '';

		$validation_object = Validation::get_instance();

		if ( $client_type_guess == LIMIT_LOGIN_DIRECT_ADDR ) {

			$client_type_message = sprintf( __( 'It appears the site is reached directly (from your IP: %s)', 'limit-login-attempts' ), $validation_object->get_address( LIMIT_LOGIN_DIRECT_ADDR ) );

		} else {
			$client_type_message = sprintf( __( 'It appears the site is reached through a proxy server (proxy IP: %s, your IP: %s)', 'limit-login-attempts' ), $validation_object->get_address( LIMIT_LOGIN_DIRECT_ADDR ), $validation_object->get_address( LIMIT_LOGIN_PROXY_ADDR ) );
		}
		$client_type_message .= '<br />';

		if ( $client_type != $client_type_guess ) {

			$faq = 'http://wordpress.org/extend/plugins/limit-login-attempts/faq/';
			$client_type_warning = '<br /><br />' . sprintf( __( '<strong>Current setting appears to be invalid</strong>. Please make sure it is correct. Further information can be found <a href="%s" title="FAQ">here</a>', 'limit-login-attempts' ), $faq );

		}

		$v             = explode( ',', get_option( 'hm_limit_login_lockout_notify' ) );
		$log_checked   = in_array( 'log', $v ) ? ' checked ' : '';
		$email_checked = in_array( 'email', $v ) ? ' checked ' : '';

		include( HM_LIMIT_LOGIN_DIR . 'inc/options-page.php' );

	}

	/**
	 * Make a guess if we are behind a proxy or not
	 */
	private function guess_proxy() {
		return isset( $_SERVER[ LIMIT_LOGIN_PROXY_ADDR ] )
			? LIMIT_LOGIN_PROXY_ADDR : LIMIT_LOGIN_DIRECT_ADDR;
	}

}