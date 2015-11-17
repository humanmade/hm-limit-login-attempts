<?php
/* Admin Options Page Template */
?>

<div class="wrap">
	<h2><?php _e( 'Limit Login Attempts Settings', 'limit-login-attempts' ); ?></h2>

	<h3><?php _e( 'Statistics', 'limit-login-attempts' ); ?></h3>

	<form action="options-general.php?page=hm-limit-login-attempts" method="post">
		<?php wp_nonce_field( 'hm-limit-login-attempts-options' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row" valign="top"><?php _e( 'Total lockouts', 'limit-login-attempts' ); ?></th>
				<td>
					<?php if ( $lockouts_total > 0 ) { ?>
						<input name="reset_total" value="<?php _e( 'Reset Counter', 'limit-login-attempts' ); ?>" type="submit"/>
						<?php echo sprintf( _n( '%d lockout since last reset', '%d lockouts since last reset', $lockouts_total, 'limit-login-attempts' ), $lockouts_total ); ?>
					<?php } else {
						_e( 'No lockouts yet', 'limit-login-attempts' );
					} ?>
				</td>
			</tr>
			<?php if ( $lockouts_now > 0 ) { ?>
				<tr>
					<th scope="row" valign="top"><?php _e( 'Active lockouts', 'limit-login-attempts' ); ?></th>
					<td>
						<input name="reset_current"
						       value="<?php _e( 'Restore Lockouts', 'limit-login-attempts' ); ?>"
						       type="submit"/>
						<?php echo sprintf( __( '%d IP is currently blocked from trying to log in', 'limit-login-attempts' ), $lockouts_now ); ?>
					</td>
				</tr>
			<?php } ?>
		</table>
	</form>
	<h3><?php _e( 'Options', 'limit-login-attempts' ); ?></h3>

	<form action="options-general.php?page=hm-limit-login-attempts" method="post">
		<?php wp_nonce_field( 'hm-limit-login-attempts-options' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row" valign="top"><?php _e( 'Lockout', 'limit-login-attempts' ); ?></th>
				<td>
					<input type="text" size="3" maxlength="4"
					       value="<?php echo( $allowed_retries ); ?>"
					       name="allowed_retries"/> <?php _e( 'allowed retries', 'limit-login-attempts' ); ?>
					<br/>
					<input type="text" size="3" maxlength="4"
					       value="<?php echo( $lockout_duration ); ?>"
					       name="lockout_duration"/> <?php _e( 'minutes lockout', 'limit-login-attempts' ); ?>
					<br/>
					<input type="text" size="3" maxlength="4"
					       value="<?php echo $allowed_lockouts; ?>"
					       name="allowed_lockouts"/> <?php _e( 'lockouts increase lockout time to', 'limit-login-attempts' ); ?>
					<input type="text" size="3" maxlength="4"
					       value="<?php echo( $long_duration ); ?>"
					       name="long_duration"/> <?php _e( ' hours', 'limit-login-attempts' ); ?> <br/>
					<input type="text" size="3" maxlength="4"
					       value="<?php echo( $valid_duration ); ?>"
					       name="valid_duration"/> <?php _e( 'hours until retries are reset', 'limit-login-attempts' ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row" valign="top"><?php _e( 'Site connection', 'limit-login-attempts' ); ?></th>
				<td>
					<?php echo $client_type_message; ?>
					<label>
						<input type="radio" name="client_type"
							<?php echo $client_type_direct; ?> value="<?php echo HM_LIMIT_LOGIN_DIRECT_ADDR; ?>"/>
						<?php _e( 'Direct connection', 'limit-login-attempts' ); ?>
					</label>
					<label>
						<input type="radio" name="client_type"
							<?php echo $client_type_proxy; ?> value="<?php echo HM_LIMIT_LOGIN_PROXY_ADDR; ?>"/>
						<?php _e( 'From behind a reversy proxy', 'limit-login-attempts' ); ?>
					</label>
					<?php echo $client_type_warning; ?>
				</td>
			</tr>
			<tr>
				<th scope="row" valign="top"><?php _e( 'Handle cookie login', 'limit-login-attempts' ); ?></th>
				<td>
					<label><input type="radio" name="cookies" <?php echo $cookies_yes; ?>
					              value="1"/> <?php _e( 'Yes', 'limit-login-attempts' ); ?></label>
					<label><input type="radio" name="cookies" <?php echo $cookies_no; ?>
					              value="0"/> <?php _e( 'No', 'limit-login-attempts' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row" valign="top"><?php _e( 'Notify on lockout', 'limit-login-attempts' ); ?></th>
				<td>
					<input type="checkbox" name="lockout_notify_log" <?php echo $log_checked; ?>
					       value="log"/> <?php _e( 'Log IP', 'limit-login-attempts' ); ?><br/>
					<input type="checkbox" name="lockout_notify_email" <?php echo $email_checked; ?>
					       value="email"/> <?php _e( 'Email to admin after', 'limit-login-attempts' ); ?>
					<input type="text" size="3" maxlength="4"
					       value="<?php echo $notify_email_after; ?>"
					       name="email_after"/> <?php _e( 'lockouts', 'limit-login-attempts' ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row" valign="top"><?php _e( 'Lockout Method', 'limit-login-attempts' ); ?></th>
				<td>
					<p></p><?php _e( 'Please select a lockout method. If non are selected, the default method: IP, will be selected.' ); ?></p>
					<input type="checkbox" name="lockout_method_ip" <?php echo $lockout_method_ip; ?>
					       value="ip"/> <?php _e( 'Lock users out based on IP', 'limit-login-attempts' ); ?><br/>
					<input type="checkbox" name="lockout_method_username" <?php echo $lockout_method_username; ?>
					       value="username"/> <?php _e( 'Lock users out based on username', 'limit-login-attempts' ); ?>
				</td>
			</tr>
		</table>
		<p class="submit">
			<input name="update_options" value="<?php _e( 'Change Options', 'limit-login-attempts' ); ?>"
			       type="submit"/>
		</p>
	</form>
	<?php
	$log = get_option( 'hm_limit_login_logged' );

	if ( is_array( $log ) && count( $log ) > 0 ) {
		?>
		<h3><?php _e( 'Lockout log', 'limit-login-attempts' ); ?></h3>
		<form action="options-general.php?page=hm-limit-login-attempts" method="post">
			<?php wp_nonce_field( 'hm-limit-login-attempts-options' ); ?>
			<input type="hidden" value="true" name="clear_log"/>

			<p class="submit">
				<input name="submit" value="<?php _e( 'Clear Log', 'limit-login-attempts' ); ?>"
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
				<?php $this->show_log( $log ); ?>
			</table>
		</div>
		<?php
	} /* if showing $log */
	?>

</div>
