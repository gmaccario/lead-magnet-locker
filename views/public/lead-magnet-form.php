<?php
/**
 * Public-facing form template for Lead Magnet Locker.
 *
 * This template outputs the email capture form with title, description, and AJAX-powered submission button.
 *
 * @file
 * @package LeadMagnetLocker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="lead-magnet-form" id="lead-magnet-<?php echo esc_attr( uniqid() ); ?>">
	<h3><?php echo esc_html( $atts['title'] ); ?></h3>
	<p><?php echo esc_html( $atts['description'] ); ?></p>

	<div class="lead-magnet-messages"></div>

	<form class="lead-magnet-email-form">
		<input type="email"
				name="email"
				placeholder="Enter your email address"
				required>
		<input type="hidden" name="file" value="<?php echo esc_attr( $atts['file'] ); ?>">
		<button type="submit"><?php echo esc_html( $atts['button_text'] ); ?></button>
	</form>
</div>
