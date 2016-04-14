<?php
/* Admin Options Page Template */
?>

<div class="wrap">
	<h2><?php esc_html_e( 'Limit Login Attempts Settings', 'limit-login-attempts' ); ?></h2>

	<h3><?php esc_html_e( 'Statistics', 'limit-login-attempts' ); ?></h3>

	<form action="options-general.php?page=hm-limit-login-attempts" method="post">
		<?php wp_nonce_field( 'hm-limit-login-attempts-options' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row" valign="top"><?php esc_html_e( 'Total lockouts', 'limit-login-attempts' ); ?></th>
				<td>
					<?php if ( $lockouts_total > 0 ) : ?>
						<input name="reset_total" value="<?php esc_html_e( 'Reset Counter', 'limit-login-attempts' ); ?>" type="submit" />
						<?php echo esc_html( sprintf( _n( '%d lockout since last reset', '%d lockouts since last reset', $lockouts_total, 'limit-login-attempts' ), $lockouts_total ) ); ?>
					<?php else : ?>
						<?php esc_html_e( 'No lockouts yet', 'limit-login-attempts' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( $lockouts_now > 0 ) : ?>
				<tr>
					<th scope="row" valign="top"><?php esc_html_e( 'Active lockouts', 'limit-login-attempts' ); ?></th>
					<td>
						<input name="reset_current"
						       value="<?php echo esc_attr( esc_html__( 'Restore Lockouts', 'limit-login-attempts' ) ); ?>"
						       type="submit" />
						<?php echo esc_html( sprintf( _n( '%d IP address is currently blocked from trying to log in', '%d IP addresses are currently blocked from trying to log in', 'limit-login-attempts', $lockouts_now ), $lockouts_now ) ); ?>
					</td>
				</tr>
			<?php endif; ?>
		</table>
	</form>
	<h3><?php esc_html_e( 'Options', 'limit-login-attempts' ); ?></h3>

	<form action="options-general.php?page=hm-limit-login-attempts" method="post">
		<?php wp_nonce_field( 'hm-limit-login-attempts-options' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row" valign="top"><?php esc_html_e( 'Lockout', 'limit-login-attempts' ); ?></th>
				<td>
					<input type="text" size="3" maxlength="4"
					       value="<?php echo esc_attr( $allowed_retries ); ?>"
					       name="allowed_retries" /> <?php esc_html_e( 'allowed retries', 'limit-login-attempts' ); ?>
					<br />
					<input type="text" size="3" maxlength="4"
					       value="<?php echo esc_attr( $lockout_duration ); ?>"
					       name="lockout_duration" /> <?php esc_html_e( 'minutes lockout', 'limit-login-attempts' ); ?>
					<br />
					<input type="text" size="3" maxlength="4"
					       value="<?php echo esc_attr( $allowed_lockouts ); ?>"
					       name="allowed_lockouts" /> <?php esc_html_e( 'lockouts increase lockout time to', 'limit-login-attempts' ); ?>
					<input type="text" size="3" maxlength="4"
					       value="<?php echo esc_attr( $long_duration ); ?>"
					       name="long_duration" /> <?php esc_html_e( ' hours', 'limit-login-attempts' ); ?> <br />
					<input type="text" size="3" maxlength="4"
					       value="<?php echo esc_attr( $valid_duration ); ?>"
					       name="valid_duration" /> <?php esc_html_e( 'hours until retries are reset', 'limit-login-attempts' ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row" valign="top"><?php esc_html_e( 'Site connection', 'limit-login-attempts' ); ?></th>
				<td>
					<?php echo wp_kses( $client_type_message, 'data' ); ?>
					<p>
						<label>
							<input type="radio" name="client_type"
								<?php echo sanitize_text_field( $client_type_direct ); ?> value="<?php echo esc_attr( HM_LIMIT_LOGIN_DIRECT_ADDR ); ?>" />
							<?php esc_html_e( 'Direct connection', 'limit-login-attempts' ); ?>
						</label>
						<label>
							<input type="radio" name="client_type"
								<?php echo sanitize_text_field( $client_type_proxy ); ?> value="<?php echo esc_attr( HM_LIMIT_LOGIN_PROXY_ADDR ); ?>" />
							<?php esc_html_e( 'From behind a reverse proxy', 'limit-login-attempts' ); ?>
						</label>
					</p>
					<?php echo wp_kses( $client_type_warning, 'data' ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row" valign="top"><?php esc_html_e( 'Handle cookie login', 'limit-login-attempts' ); ?></th>
				<td>
					<label><input type="radio" name="cookies" <?php echo sanitize_text_field( $cookies_yes ); ?>
					              value="1" /> <?php esc_html_e( 'Yes', 'limit-login-attempts' ); ?></label>
					<label><input type="radio" name="cookies" <?php echo sanitize_text_field( $cookies_no ); ?>
					              value="0" /> <?php esc_html_e( 'No', 'limit-login-attempts' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row" valign="top"><?php esc_html_e( 'Notify on lockout', 'limit-login-attempts' ); ?></th>
				<td>
					<input type="checkbox" name="lockout_notify_log" <?php echo $log_checked; ?>
					       value="log" /> <?php esc_html_e( 'Log IP', 'limit-login-attempts' ); ?><br />
					<input type="checkbox" name="lockout_notify_email" <?php echo $email_checked; ?>
					       value="email" /> <?php esc_html_e( 'Email to admin after', 'limit-login-attempts' ); ?>
					<input type="text" size="3" maxlength="4"
					       value="<?php echo esc_attr( $notify_email_after ); ?>"
					       name="email_after" /> <?php esc_html_e( 'lockouts', 'limit-login-attempts' ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row" valign="top"><?php esc_html_e( 'Lockout Method', 'limit-login-attempts' ); ?></th>
				<td>
					<p><?php esc_html_e( 'Please select a lockout method. If non are selected, the default method: IP, will be selected.', 'limit-login-attempts' ); ?></p>
					<label for="lockout_method_ip">
						<input type="checkbox" id="lockout_method_ip" name="lockout_method_ip" <?php echo sanitize_text_field( $lockout_method_ip ); ?>
						       value="ip" /> <?php esc_html_e( 'Lock users out based on IP', 'limit-login-attempts' ); ?>
					</label>
					<br />
					<label for="lockout_method_username">
						<input type="checkbox" id="lockout_method_username" name="lockout_method_username" <?php echo sanitize_text_field( $lockout_method_username ); ?>
						       value="username" /> <?php esc_html_e( 'Lock users out based on username', 'limit-login-attempts' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php submit_button( esc_html__( 'Change Options', 'limit-login-attempts' ), 'primary', 'update_options' ); ?>
	</form>
	<?php
	$log = get_option( 'hm_limit_login_logged' );

	if ( is_array( $log ) && count( $log ) > 0 ) : ?>
		<h3><?php esc_html_e( 'Lockout log', 'limit-login-attempts' ); ?></h3>
		<form action="options-general.php?page=hm-limit-login-attempts" method="post">
			<?php wp_nonce_field( 'hm-limit-login-attempts-options' ); ?>
			<input type="hidden" value="true" name="clear_log" />

			<?php submit_button( esc_html__( 'Clear Log', 'limit-login-attempts' ), 'secondary' ); ?>
		</form>
		<style type="text/css" media="screen">
			.limit-login-log th {
				font-weight: bold;
			}

			.limit-login-log td, .limit-login-log th {
				padding: 1px 5px 1px 5px;
			}

			td.limit-login-ip {
				font-family:    "Courier New", Courier, monospace;
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
	<?php endif; /* if showing $log */ ?>

</div>
