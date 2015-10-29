<?php

/* Actual admin page */
function limit_login_option_page() {
	limit_login_cleanup();

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sorry, but you do not have permissions to change settings.' );
	}

	/* Make sure post was from this page */
	if ( count( $_POST ) > 0 ) {
		check_admin_referer( 'limit-login-attempts-options' );
	}

	/* Should we clear log? */
	if ( isset( $_POST['clear_log'] ) ) {
		delete_option( 'limit_login_logged' );
		echo '<div id="message" class="updated fade"><p>'
		     . __( 'Cleared IP log', 'limit-login-attempts' )
		     . '</p></div>';
	}

	/* Should we reset counter? */
	if ( isset( $_POST['reset_total'] ) ) {
		update_option( 'limit_login_lockouts_total', 0 );
		echo '<div id="message" class="updated fade"><p>'
		     . __( 'Reset lockout count', 'limit-login-attempts' )
		     . '</p></div>';
	}

	/* Should we restore current lockouts? */
	if ( isset( $_POST['reset_current'] ) ) {
		update_option( 'limit_login_lockouts', array() );
		echo '<div id="message" class="updated fade"><p>'
		     . __( 'Cleared current lockouts', 'limit-login-attempts' )
		     . '</p></div>';
	}

	/* Should we update options? */
	if ( isset( $_POST['update_options'] ) ) {
		global $limit_login_options;

		$limit_login_options['client_type']        = $_POST['client_type'];
		$limit_login_options['allowed_retries']    = $_POST['allowed_retries'];
		$limit_login_options['lockout_duration']   = $_POST['lockout_duration'] * 60;
		$limit_login_options['valid_duration']     = $_POST['valid_duration'] * 3600;
		$limit_login_options['allowed_lockouts']   = $_POST['allowed_lockouts'];
		$limit_login_options['long_duration']      = $_POST['long_duration'] * 3600;
		$limit_login_options['notify_email_after'] = $_POST['email_after'];
		$limit_login_options['cookies']            = ( isset( $_POST['cookies'] ) && $_POST['cookies'] == '1' );

		$v = array();
		if ( isset( $_POST['lockout_notify_log'] ) ) {
			$v[] = 'log';
		}
		if ( isset( $_POST['lockout_notify_email'] ) ) {
			$v[] = 'email';
		}
		$limit_login_options['lockout_notify'] = implode( ',', $v );

		limit_login_sanitize_variables();
		limit_login_update_options();
		echo '<div id="message" class="updated fade"><p>'
		     . __( 'Options changed', 'limit-login-attempts' )
		     . '</p></div>';
	}

	$lockouts_total = get_option( 'limit_login_lockouts_total', 0 );
	$lockouts       = get_option( 'limit_login_lockouts' );
	$lockouts_now   = is_array( $lockouts ) ? count( $lockouts ) : 0;

	$cookies_yes = limit_login_option( 'cookies' ) ? ' checked ' : '';
	$cookies_no  = limit_login_option( 'cookies' ) ? '' : ' checked ';

	$client_type        = limit_login_option( 'client_type' );
	$client_type_direct = $client_type == LIMIT_LOGIN_DIRECT_ADDR ? ' checked ' : '';
	$client_type_proxy  = $client_type == LIMIT_LOGIN_PROXY_ADDR ? ' checked ' : '';

	$client_type_guess = limit_login_guess_proxy();

	if ( $client_type_guess == LIMIT_LOGIN_DIRECT_ADDR ) {
		$client_type_message = sprintf( __( 'It appears the site is reached directly (from your IP: %s)', 'limit-login-attempts' ), limit_login_get_address( LIMIT_LOGIN_DIRECT_ADDR ) );
	} else {
		$client_type_message = sprintf( __( 'It appears the site is reached through a proxy server (proxy IP: %s, your IP: %s)', 'limit-login-attempts' ), limit_login_get_address( LIMIT_LOGIN_DIRECT_ADDR ), limit_login_get_address( LIMIT_LOGIN_PROXY_ADDR ) );
	}
	$client_type_message .= '<br />';

	$client_type_warning = '';
	if ( $client_type != $client_type_guess ) {
		$faq = 'http://wordpress.org/extend/plugins/limit-login-attempts/faq/';

		$client_type_warning = '<br /><br />' . sprintf( __( '<strong>Current setting appears to be invalid</strong>. Please make sure it is correct. Further information can be found <a href="%s" title="FAQ">here</a>', 'limit-login-attempts' ), $faq );
	}

	$v             = explode( ',', limit_login_option( 'lockout_notify' ) );
	$log_checked   = in_array( 'log', $v ) ? ' checked ' : '';
	$email_checked = in_array( 'email', $v ) ? ' checked ' : '';
	?>
	<div class="wrap">
		<h2><?php echo __( 'Limit Login Attempts Settings', 'limit-login-attempts' ); ?></h2>

		<h3><?php echo __( 'Statistics', 'limit-login-attempts' ); ?></h3>

		<form action="options-general.php?page=limit-login-attempts" method="post">
			<?php wp_nonce_field( 'limit-login-attempts-options' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row" valign="top"><?php echo __( 'Total lockouts', 'limit-login-attempts' ); ?></th>
					<td>
						<?php if ( $lockouts_total > 0 ) { ?>
							<input name="reset_total"
							       value="<?php echo __( 'Reset Counter', 'limit-login-attempts' ); ?>" type="submit"/>
							<?php echo sprintf( _n( '%d lockout since last reset', '%d lockouts since last reset', $lockouts_total, 'limit-login-attempts' ), $lockouts_total ); ?>
						<?php } else {
							echo __( 'No lockouts yet', 'limit-login-attempts' );
						} ?>
					</td>
				</tr>
				<?php if ( $lockouts_now > 0 ) { ?>
					<tr>
						<th scope="row" valign="top"><?php echo __( 'Active lockouts', 'limit-login-attempts' ); ?></th>
						<td>
							<input name="reset_current"
							       value="<?php echo __( 'Restore Lockouts', 'limit-login-attempts' ); ?>"
							       type="submit"/>
							<?php echo sprintf( __( '%d IP is currently blocked from trying to log in', 'limit-login-attempts' ), $lockouts_now ); ?>
						</td>
					</tr>
				<?php } ?>
			</table>
		</form>
		<h3><?php echo __( 'Options', 'limit-login-attempts' ); ?></h3>

		<form action="options-general.php?page=limit-login-attempts" method="post">
			<?php wp_nonce_field( 'limit-login-attempts-options' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row" valign="top"><?php echo __( 'Lockout', 'limit-login-attempts' ); ?></th>
					<td>
						<input type="text" size="3" maxlength="4"
						       value="<?php echo( limit_login_option( 'allowed_retries' ) ); ?>"
						       name="allowed_retries"/> <?php echo __( 'allowed retries', 'limit-login-attempts' ); ?>
						<br/>
						<input type="text" size="3" maxlength="4"
						       value="<?php echo( limit_login_option( 'lockout_duration' ) / 60 ); ?>"
						       name="lockout_duration"/> <?php echo __( 'minutes lockout', 'limit-login-attempts' ); ?>
						<br/>
						<input type="text" size="3" maxlength="4"
						       value="<?php echo( limit_login_option( 'allowed_lockouts' ) ); ?>"
						       name="allowed_lockouts"/> <?php echo __( 'lockouts increase lockout time to', 'limit-login-attempts' ); ?>
						<input type="text" size="3" maxlength="4"
						       value="<?php echo( limit_login_option( 'long_duration' ) / 3600 ); ?>"
						       name="long_duration"/> <?php echo __( 'hours', 'limit-login-attempts' ); ?> <br/>
						<input type="text" size="3" maxlength="4"
						       value="<?php echo( limit_login_option( 'valid_duration' ) / 3600 ); ?>"
						       name="valid_duration"/> <?php echo __( 'hours until retries are reset', 'limit-login-attempts' ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top"><?php echo __( 'Site connection', 'limit-login-attempts' ); ?></th>
					<td>
						<?php echo $client_type_message; ?>
						<label>
							<input type="radio" name="client_type"
								<?php echo $client_type_direct; ?> value="<?php echo LIMIT_LOGIN_DIRECT_ADDR; ?>"/>
							<?php echo __( 'Direct connection', 'limit-login-attempts' ); ?>
						</label>
						<label>
							<input type="radio" name="client_type"
								<?php echo $client_type_proxy; ?> value="<?php echo LIMIT_LOGIN_PROXY_ADDR; ?>"/>
							<?php echo __( 'From behind a reversy proxy', 'limit-login-attempts' ); ?>
						</label>
						<?php echo $client_type_warning; ?>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top"><?php echo __( 'Handle cookie login', 'limit-login-attempts' ); ?></th>
					<td>
						<label><input type="radio" name="cookies" <?php echo $cookies_yes; ?>
						              value="1"/> <?php echo __( 'Yes', 'limit-login-attempts' ); ?></label>
						<label><input type="radio" name="cookies" <?php echo $cookies_no; ?>
						              value="0"/> <?php echo __( 'No', 'limit-login-attempts' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top"><?php echo __( 'Notify on lockout', 'limit-login-attempts' ); ?></th>
					<td>
						<input type="checkbox" name="lockout_notify_log" <?php echo $log_checked; ?>
						       value="log"/> <?php echo __( 'Log IP', 'limit-login-attempts' ); ?><br/>
						<input type="checkbox" name="lockout_notify_email" <?php echo $email_checked; ?>
						       value="email"/> <?php echo __( 'Email to admin after', 'limit-login-attempts' ); ?>
						<input type="text" size="3" maxlength="4"
						       value="<?php echo( limit_login_option( 'notify_email_after' ) ); ?>"
						       name="email_after"/> <?php echo __( 'lockouts', 'limit-login-attempts' ); ?>
					</td>
				</tr>
			</table>
			<p class="submit">
				<input name="update_options" value="<?php echo __( 'Change Options', 'limit-login-attempts' ); ?>"
				       type="submit"/>
			</p>
		</form>
		<?php
		$log = get_option( 'limit_login_logged' );

		if ( is_array( $log ) && count( $log ) > 0 ) {
			?>
			<h3><?php echo __( 'Lockout log', 'limit-login-attempts' ); ?></h3>
			<form action="options-general.php?page=limit-login-attempts" method="post">
				<?php wp_nonce_field( 'limit-login-attempts-options' ); ?>
				<input type="hidden" value="true" name="clear_log"/>

				<p class="submit">
					<input name="submit" value="<?php echo __( 'Clear Log', 'limit-login-attempts' ); ?>"
					       type="submit"/>
				</p>
			</form>
			<style type="text/css" media="screen">
				.limit-login-log th {
					font-weight: bold;
				}

				.limit-login-log td, .limit-login-log th {
					padding: 1px 5px 1px 5px;
				}

				td.limit-login-ip {
					font-family: "Courier New", Courier, monospace;
					vertical-align: top;
				}

				td.limit-login-max {
					width: 100%;
				}
			</style>
			<div class="limit-login-log">
				<table class="form-table">
					<?php limit_login_show_log( $log ); ?>
				</table>
			</div>
			<?php
		} /* if showing $log */
		?>

	</div>
	<?php
}