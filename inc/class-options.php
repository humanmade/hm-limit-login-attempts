<?php




/* Get current option value */
function limit_login_option($option_name) {
	global $limit_login_options;

	if (isset($limit_login_options[$option_name])) {
		return $limit_login_options[$option_name];
	} else {
		return null;
	}
}


/* Only change var if option exists */
function limit_login_get_option($option, $var_name) {
	$a = get_option($option);

	if ($a !== false) {
		global $limit_login_options;

		$limit_login_options[$var_name] = $a;
	}
}




/*
 * Admin stuff
 */

/* Make a guess if we are behind a proxy or not */
function limit_login_guess_proxy() {
	return isset($_SERVER[LIMIT_LOGIN_PROXY_ADDR])
		? LIMIT_LOGIN_PROXY_ADDR : LIMIT_LOGIN_DIRECT_ADDR;
}

/* Setup global variables from options */
function limit_login_setup_options() {
	limit_login_get_option('limit_login_client_type', 'client_type');
	limit_login_get_option('limit_login_allowed_retries', 'allowed_retries');
	limit_login_get_option('limit_login_lockout_duration', 'lockout_duration');
	limit_login_get_option('limit_login_valid_duration', 'valid_duration');
	limit_login_get_option('limit_login_cookies', 'cookies');
	limit_login_get_option('limit_login_lockout_notify', 'lockout_notify');
	limit_login_get_option('limit_login_allowed_lockouts', 'allowed_lockouts');
	limit_login_get_option('limit_login_long_duration', 'long_duration');
	limit_login_get_option('limit_login_notify_email_after', 'notify_email_after');

	limit_login_sanitize_variables();
}


/* Update options in db from global variables */
function limit_login_update_options() {
	update_option('limit_login_client_type', limit_login_option('client_type'));
	update_option('limit_login_allowed_retries', limit_login_option('allowed_retries'));
	update_option('limit_login_lockout_duration', limit_login_option('lockout_duration'));
	update_option('limit_login_allowed_lockouts', limit_login_option('allowed_lockouts'));
	update_option('limit_login_long_duration', limit_login_option('long_duration'));
	update_option('limit_login_valid_duration', limit_login_option('valid_duration'));
	update_option('limit_login_lockout_notify', limit_login_option('lockout_notify'));
	update_option('limit_login_notify_email_after', limit_login_option('notify_email_after'));
	update_option('limit_login_cookies', limit_login_option('cookies') ? '1' : '0');
}


/* Make sure the variables make sense -- simple integer */
function limit_login_sanitize_simple_int($var_name) {
	global $limit_login_options;

	$limit_login_options[$var_name] = max(1, intval(limit_login_option($var_name)));
}


/* Make sure the variables make sense */
function limit_login_sanitize_variables() {
	global $limit_login_options;

	limit_login_sanitize_simple_int('allowed_retries');
	limit_login_sanitize_simple_int('lockout_duration');
	limit_login_sanitize_simple_int('valid_duration');
	limit_login_sanitize_simple_int('allowed_lockouts');
	limit_login_sanitize_simple_int('long_duration');

	$limit_login_options['cookies'] = !!limit_login_option('cookies');

	$notify_email_after = max(1, intval(limit_login_option('notify_email_after')));
	$limit_login_options['notify_email_after'] = min(limit_login_option('allowed_lockouts'), $notify_email_after);

	$args = explode(',', limit_login_option('lockout_notify'));
	$args_allowed = explode(',', LIMIT_LOGIN_LOCKOUT_NOTIFY_ALLOWED);
	$new_args = array();
	foreach ($args as $a) {
		if (in_array($a, $args_allowed)) {
			$new_args[] = $a;
		}
	}
	$limit_login_options['lockout_notify'] = implode(',', $new_args);

	if ( limit_login_option('client_type') != LIMIT_LOGIN_DIRECT_ADDR
	     && limit_login_option('client_type') != LIMIT_LOGIN_PROXY_ADDR ) {
		$limit_login_options['client_type'] = LIMIT_LOGIN_DIRECT_ADDR;
	}
}


/* Add admin options page */
function limit_login_admin_menu() {
	global $wp_version;

	// Modern WP?
	if (version_compare($wp_version, '3.0', '>=')) {
		add_options_page('Limit Login Attempts', 'Limit Login Attempts', 'manage_options', 'limit-login-attempts', 'limit_login_option_page');
		return;
	}

	// Older WPMU?
	if (function_exists("get_current_site")) {
		add_submenu_page('wpmu-admin.php', 'Limit Login Attempts', 'Limit Login Attempts', 9, 'limit-login-attempts', 'limit_login_option_page');
		return;
	}

	// Older WP
	add_options_page('Limit Login Attempts', 'Limit Login Attempts', 9, 'limit-login-attempts', 'limit_login_option_page');
}


/* Show log on admin page */
function limit_login_show_log($log) {
	if (!is_array($log) || count($log) == 0) {
		return;
	}

	echo('<tr><th scope="col">' . _x("IP", "Internet address", 'limit-login-attempts') . '</th><th scope="col">' . __('Tried to log in as', 'limit-login-attempts') . '</th></tr>');
	foreach ($log as $ip => $arr) {
		echo('<tr><td class="limit-login-ip">' . $ip . '</td><td class="limit-login-max">');
		$first = true;
		foreach($arr as $user => $count) {
			$count_desc = sprintf(_n('%d lockout', '%d lockouts', $count, 'limit-login-attempts'), $count);
			if (!$first) {
				echo(', ' . $user . ' (' .  $count_desc . ')');
			} else {
				echo($user . ' (' .  $count_desc . ')');
			}
			$first = false;
		}
		echo('</td></tr>');
	}
}
