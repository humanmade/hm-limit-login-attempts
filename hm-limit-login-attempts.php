<?php

namespace HM\Limit_Login_Attempts;

add_action( 'plugins_loaded', function() {

	require_once( trailingslashit( __DIR__ ) . 'inc/class-plugin.php' );

	ptions::get_instance();

} );





/*
  Plugin Name: Limit Login Attempts
  Plugin URI: http://devel.kostdoktorn.se/limit-login-attempts
  Description: Limit rate of login attempts, including by way of cookies, for each IP.
  Author: Johan Eenfeldt
  Author URI: http://devel.kostdoktorn.se
  Text Domain: limit-login-attempts
  Version: 1.7.1

  Copyright 2008 - 2012 Johan Eenfeldt

  Thanks to Michael Skerwiderski for reverse proxy handling suggestions.

  Licenced under the GNU GPL:

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/*
 * Constants
 */

/* Different ways to get remote address: direct & behind proxy */
define('LIMIT_LOGIN_DIRECT_ADDR', 'REMOTE_ADDR');
define('LIMIT_LOGIN_PROXY_ADDR', 'HTTP_X_FORWARDED_FOR');

/* Notify value checked against these in limit_login_sanitize_variables() */
define('LIMIT_LOGIN_LOCKOUT_NOTIFY_ALLOWED', 'log,email');

/*
 * Variables
 *
 * Assignments are for default value -- change on admin page.
 */

$limit_login_options =
	array(
		/* Are we behind a proxy? */
		'client_type' => LIMIT_LOGIN_DIRECT_ADDR

		/* Lock out after this many tries */
	, 'allowed_retries' => 4

		/* Lock out for this many seconds */
	, 'lockout_duration' => 1200 // 20 minutes

		/* Long lock out after this many lockouts */
	, 'allowed_lockouts' => 4

		/* Long lock out for this many seconds */
	, 'long_duration' => 86400 // 24 hours

		/* Reset failed attempts after this many seconds */
	, 'valid_duration' => 43200 // 12 hours

		/* Also limit malformed/forged cookies? */
	, 'cookies' => true

		/* Notify on lockout. Values: '', 'log', 'email', 'log,email' */
	, 'lockout_notify' => 'log'

		/* If notify by email, do so after this number of lockouts */
	, 'notify_email_after' => 4
	);

$limit_login_my_error_shown = false; /* have we shown our stuff? */
$limit_login_just_lockedout = false; /* started this pageload??? */
$limit_login_nonempty_credentials = false; /* user and pwd nonempty */


/*
 * Startup
 */

add_action('plugins_loaded', 'limit_login_setup', 99999);


/*
 * Functions start here
 */

/* Get options and setup filters & actions */
function limit_login_setup() {
	load_plugin_textdomain('limit-login-attempts', false
		, dirname(plugin_basename(__FILE__)));

	limit_login_setup_options();

	/* Filters and actions */
	add_action('wp_login_failed', 'limit_login_failed');
	if (limit_login_option('cookies')) {
		limit_login_handle_cookies();
		add_action('auth_cookie_bad_username', 'limit_login_failed_cookie');

		global $wp_version;

		if (version_compare($wp_version, '3.0', '>=')) {
			add_action('auth_cookie_bad_hash', 'limit_login_failed_cookie_hash');
			add_action('auth_cookie_valid', 'limit_login_valid_cookie', 10, 2);
		} else {
			add_action('auth_cookie_bad_hash', 'limit_login_failed_cookie');
		}
	}
	add_filter('wp_authenticate_user', 'limit_login_wp_authenticate_user', 99999, 2);
	add_filter('shake_error_codes', 'limit_login_failure_shake');
	add_action('login_head', 'limit_login_add_error_message');
	add_action('login_errors', 'limit_login_fixup_error_messages');
	add_action('admin_menu', 'limit_login_admin_menu');

	/*
	 * This action should really be changed to the 'authenticate' filter as
	 * it will probably be deprecated. That is however only available in
	 * later versions of WP.
	 */
	add_action('wp_authenticate', 'limit_login_track_credentials', 10, 2);
}

