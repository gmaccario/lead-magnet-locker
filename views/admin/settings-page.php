<?php
/**
 * Admin settings page template for Lead Magnet Locker.
 *
 * Displays the form fields for configuring date/time restrictions,
 * timezone selection, and shows the current form visibility status.
 *
 * @file
 * @package LeadMagnetLocker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="lead-magnet-locker lml-settings wrap">
	<h1>Lead Magnet Settings</h1>

	<div class="card lml-status <?php echo $is_visible ? 'lml-status--visible' : 'lml-status--hidden'; ?>">
		<span class="lml-status__icon dashicons <?php echo $is_visible ? 'dashicons-visibility' : 'dashicons-hidden'; ?>" aria-hidden="true"></span>
		<div class="lml-status__body">
			<h2 class="lml-status__title">
				<?php echo $is_visible ? 'Forms are currently visible' : 'Forms are currently hidden'; ?>
			</h2>
			<p class="lml-status__meta">
				<strong>Current Server Time:</strong> <?php echo esc_html( current_time( 'Y-m-d H:i:s' ) ); ?>
				<?php
				echo ( get_option( 'timezone_string', 'UTC' ) )
						? '(' . esc_html( get_option( 'timezone_string', 'UTC' ) ) . ')'
						: '';
				?>
			</p>
		</div>
	</div>

	<form method="post" action="">
		<?php wp_nonce_field( 'lead_magnet_settings', 'settings_nonce' ); ?>

		<div class="lml-panel">
			<div class="lml-panel__header">
				<h2>Schedule</h2>
				<p>Choose when your lead magnet forms are shown to visitors.</p>
				<p><strong>This schedule is global:</strong> it applies to every lead magnet form on your site, whatever file it offers. Outside the schedule, all forms show "This download is currently unavailable."</p>
			</div>

			<table class="form-table">
				<tr class="lml-toggle-row">
					<th scope="row">Date & Time Trigger</th>
					<td>
						<label class="lml-toggle">
							<input type="checkbox" name="enable_date_trigger" value="1" <?php checked( $enable_date_trigger, '1' ); ?>>
							<span class="lml-toggle__label">Enable date/time restrictions for forms</span>
						</label>
						<p class="description">When enabled, forms are visible continuously from the start date and time until the end date and time.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Start Date</th>
					<td>
						<input type="date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>" class="regular-text">
						<p class="description">Forms will be visible starting from this date.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">End Date</th>
					<td>
						<input type="date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>" class="regular-text">
						<p class="description">Forms will be hidden after this date (at the end time).</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Start Time</th>
					<td>
						<input type="time" name="start_time" value="<?php echo esc_attr( $start_time ); ?>">
						<p class="description">Time on the start date when forms become visible.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">End Time</th>
					<td>
						<input type="time" name="end_time" value="<?php echo esc_attr( $end_time ); ?>">
						<p class="description">Time on the end date after which forms are hidden.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Timezone</th>
					<td>
						<select name="timezone">
							<?php
							$timezones = timezone_identifiers_list();
							foreach ( $timezones as $tz ) {
								echo '<option value="' . esc_attr( $tz ) . '"' . selected( $timezone, $tz, false ) . '>' . esc_html( $tz ) . '</option>';
							}
							?>
						</select>
						<p class="description">Timezone for date/time calculations.</p>
					</td>
				</tr>
			</table>
		</div>

		<?php submit_button(); ?>
	</form>
</div>
