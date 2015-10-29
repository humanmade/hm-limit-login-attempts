<?php



/*
 * Must be called in plugin_loaded (really early) to make sure we do not allow
 * auth cookies while locked out.
 */
function limit_login_handle_cookies() {
	if (is_limit_login_ok()) {
		return;
	}

	limit_login_clear_auth_cookie();
}


/*
 * Action: failed cookie login hash
 *
 * Make sure same invalid cookie doesn't get counted more than once.
 *
 * Requires WordPress version 3.0.0, previous versions use limit_login_failed_cookie()
 */
function limit_login_failed_cookie_hash($cookie_elements) {
	limit_login_clear_auth_cookie();

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

	extract($cookie_elements, EXTR_OVERWRITE);

	// Check if cookie is for a valid user
	$user = get_user_by('login', $username);
	if (!$user) {
		// "shouldn't happen" for this action
		limit_login_failed($username);
		return;
	}

	$previous_cookie = get_user_meta($user->ID, 'limit_login_previous_cookie', true);
	if ($previous_cookie && $previous_cookie == $cookie_elements) {
		// Identical cookies, ignore this attempt
		return;
	}

	// Store cookie
	if ($previous_cookie)
		update_user_meta($user->ID, 'limit_login_previous_cookie', $cookie_elements);
	else
		add_user_meta($user->ID, 'limit_login_previous_cookie', $cookie_elements, true);

	limit_login_failed($username);
}


/*
 * Action: successful cookie login
 *
 * Clear any stored user_meta.
 *
 * Requires WordPress version 3.0.0, not used in previous versions
 */
function limit_login_valid_cookie($cookie_elements, $user) {
	/*
	 * As all meta values get cached on user load this should not require
	 * any extra work for the common case of no stored value.
	 */

	if (get_user_meta($user->ID, 'limit_login_previous_cookie')) {
		delete_user_meta($user->ID, 'limit_login_previous_cookie');
	}
}


/* Action: failed cookie login (calls limit_login_failed()) */
function limit_login_failed_cookie($cookie_elements) {
	limit_login_clear_auth_cookie();

	/*
	 * Invalid username gets counted every time.
	 */

	limit_login_failed($cookie_elements['username']);
}


/* Make sure auth cookie really get cleared (for this session too) */
function limit_login_clear_auth_cookie() {
	wp_clear_auth_cookie();

	if (!empty($_COOKIE[AUTH_COOKIE])) {
		$_COOKIE[AUTH_COOKIE] = '';
	}
	if (!empty($_COOKIE[SECURE_AUTH_COOKIE])) {
		$_COOKIE[SECURE_AUTH_COOKIE] = '';
	}
	if (!empty($_COOKIE[LOGGED_IN_COOKIE])) {
		$_COOKIE[LOGGED_IN_COOKIE] = '';
	}
}
