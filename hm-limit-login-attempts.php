<?php
/**
 * Plugin Name: HM Limit Login Attempts
 * Plugin URI:   https://github.com/humanmade/hm-limit-login-attempts
 * Description:  Limit rate of login attempts, including by way of cookies, for each IP and user.
 * Based off the original Limit Login Attempts plugin version 1.7.1 by Johan Eenfeldt http://devel.kostdoktorn.se/limit-login-attempts
 * Author:       Human Made
 * Author URI:   http://hmn.md
 * Text Domain:  hm-limit-login-attempts
 * Version: 1.0
 *
 *
 * Licenced under the GNU GPL:
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

namespace HM\Limit_Login_Attempts;


/*
 * Constants
 */

/* Different ways to get remote address: direct & behind proxy */
defined( 'LIMIT_LOGIN_DIRECT_ADDR' ) or define( 'LIMIT_LOGIN_DIRECT_ADDR', 'LIMIT_LOGIN_DIRECT_ADDR' );
defined( 'LIMIT_LOGIN_PROXY_ADDR' ) or define( 'LIMIT_LOGIN_PROXY_ADDR', 'HTTP_X_FORWARDED_FOR' );

/* Notify value checked against these in limit_login_sanitize_variables() */
defined( 'LIMIT_LOGIN_LOCKOUT_NOTIFY_ALLOWED' ) or define( 'LIMIT_LOGIN_LOCKOUT_NOTIFY_ALLOWED', 'log,email' );

add_action( 'plugins_loaded', function() {

	require_once( trailingslashit( __DIR__ ) . 'inc/class-setup.php' );

	Setup::get_instance();

}, 99999 );
