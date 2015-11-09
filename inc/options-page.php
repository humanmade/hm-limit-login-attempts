<?php

/* Admin Options Page Template */

?>

<div class="wrap">
	<h2><?php __e( 'Limit Login Attempts Settings', 'limit-login-attempts' ); ?></h2>

	<h3><?php __e( 'Statistics', 'limit-login-attempts' ); ?></h3>

	<form action="options-general.php?page=hm-limit-login-attempts" method="post">
		<?php wp_nonce_field( 'hm-limit-login-attempts-options' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row" valign="top"><?php echo __( 'Total lockouts', 'limit-login-attempts' ); ?></th>
				<td>
					<?php if ( $lockouts_total > 0 ) { ?>
						<input name="reset_total" value="<?php echo __( 'Reset Counter', 'limit-login-attempts' ); ?>" type="submit"/>
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

	<form action="options-general.php?page=hm-limit-login-attempts" method="post">
		<?php wp_nonce_field( 'hm-limit-login-attempts-options' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row" valign="top"><?php echo __( 'Lockout', 'limit-login-attempts' ); ?></th>
				<td>
					<input type="text" size="3" maxlength="4"
					       value="<?php echo $allowed_retries; ?>"
					       name="allowed_retries"/> <?php echo __( 'allowed retries', 'limit-login-attempts' ); ?>
					<br/>
					<input type="text" size="3" maxlength="4"
					       value="<?php echo( $lockout_duration / 60 ); ?>"
					       name="lockout_duration"/> <?php echo __( 'minutes lockout', 'limit-login-attempts' ); ?>
					<br/>
					<input type="text" size="3" maxlength="4"
					       value="<?php echo $allowed_lockouts; ?>"
					       name="allowed_lockouts"/> <?php echo __( 'lockouts increase lockout time to', 'limit-login-attempts' ); ?>
					<input type="text" size="3" maxlength="4"
					       value="<?php echo( $long_duration / 3600 ); ?>"
					       name="long_duration"/> <?php echo __( 'hours', 'limit-login-attempts' ); ?> <br/>
					<input type="text" size="3" maxlength="4"
					       value="<?php echo( $valid_duration / 3600 ); ?>"
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
					       value="<?php echo $notify_email_after; ?>"
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
		<form action="options-general.php?page=hm-limit-login-attempts" method="post">
			<?php wp_nonce_field( 'hm-limit-login-attempts-options' ); ?>
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
				<?php $this->show_log( $log ); ?>
			</table>
		</div>
		<?php
	} /* if showing $log */
	?>

</div>
