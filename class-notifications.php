<?php



/* Filter: add this failure to login page "Shake it!" */
function limit_login_failure_shake($error_codes) {
	$error_codes[] = 'too_many_retries';
	return $error_codes;
}


/* Email notification of lockout to admin (if configured) */
function limit_login_notify_email($user) {
	$ip = limit_login_get_address();
	$whitelisted = is_limit_login_ip_whitelisted($ip);

	$retries = get_option('limit_login_retries');
	if (!is_array($retries)) {
		$retries = array();
	}

	/* check if we are at the right nr to do notification */
	if ( isset($retries[$ip])
	     && ( ($retries[$ip] / limit_login_option('allowed_retries'))
	          % limit_login_option('notify_email_after') ) != 0 ) {
		return;
	}

	/* Format message. First current lockout duration */
	if (!isset($retries[$ip])) {
		/* longer lockout */
		$count = limit_login_option('allowed_retries')
		         * limit_login_option('allowed_lockouts');
		$lockouts = limit_login_option('allowed_lockouts');
		$time = round(limit_login_option('long_duration') / 3600);
		$when = sprintf(_n('%d hour', '%d hours', $time, 'limit-login-attempts'), $time);
	} else {
		/* normal lockout */
		$count = $retries[$ip];
		$lockouts = floor($count / limit_login_option('allowed_retries'));
		$time = round(limit_login_option('lockout_duration') / 60);
		$when = sprintf(_n('%d minute', '%d minutes', $time, 'limit-login-attempts'), $time);
	}

	$blogname = is_limit_login_multisite() ? get_site_option('site_name') : get_option('blogname');

	if ($whitelisted) {
		$subject = sprintf(__("[%s] Failed login attempts from whitelisted IP"
				, 'limit-login-attempts')
			, $blogname);
	} else {
		$subject = sprintf(__("[%s] Too many failed login attempts"
				, 'limit-login-attempts')
			, $blogname);
	}

	$message = sprintf(__("%d failed login attempts (%d lockout(s)) from IP: %s"
			, 'limit-login-attempts') . "\r\n\r\n"
		, $count, $lockouts, $ip);
	if ($user != '') {
		$message .= sprintf(__("Last user attempted: %s", 'limit-login-attempts')
		                    . "\r\n\r\n" , $user);
	}
	if ($whitelisted) {
		$message .= __("IP was NOT blocked because of external whitelist.", 'limit-login-attempts');
	} else {
		$message .= sprintf(__("IP was blocked for %s", 'limit-login-attempts'), $when);
	}

	$admin_email = is_limit_login_multisite() ? get_site_option('admin_email') : get_option('admin_email');

	@wp_mail($admin_email, $subject, $message);
}


/* Logging of lockout (if configured) */
function limit_login_notify_log($user) {
	$log = $option = get_option('limit_login_logged');
	if (!is_array($log)) {
		$log = array();
	}
	$ip = limit_login_get_address();

	/* can be written much simpler, if you do not mind php warnings */
	if (isset($log[$ip])) {
		if (isset($log[$ip][$user])) {
			$log[$ip][$user]++;
		} else {
			$log[$ip][$user] = 1;
		}
	} else {
		$log[$ip] = array($user => 1);
	}

	if ($option === false) {
		add_option('limit_login_logged', $log, '', 'no'); /* no autoload */
	} else {
		update_option('limit_login_logged', $log);
	}
}


/* Handle notification in event of lockout */
function limit_login_notify($user) {
	$args = explode(',', limit_login_option('lockout_notify'));

	if (empty($args)) {
		return;
	}

	foreach ($args as $mode) {
		switch (trim($mode)) {
			case 'email':
				limit_login_notify_email($user);
				break;
			case 'log':
				limit_login_notify_log($user);
				break;
		}
	}
}
