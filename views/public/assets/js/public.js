/**
 * Public-facing JavaScript for Lead Magnet Locker.
 *
 * Handles the email submission form, AJAX request, and download link display.
 *
 * @file
 * @package LeadMagnetLocker
 */

jQuery( document ).ready(
	function ($) {
		$( '.lead-magnet-email-form' ).on(
			'submit',
			function (e) {
				e.preventDefault();

				const form         = $( this );
				const container    = form.closest( '.lead-magnet-form' );
				const messagesDiv  = container.find( '.lead-magnet-messages' );
				const submitButton = form.find( 'button[type="submit"]' );

				// Show loading state.
				submitButton.text( 'Processing...' ).prop( 'disabled', true );
				messagesDiv.empty();

				$.ajax(
					{
						url: lead_magnet_ajax.ajax_url,
						type: 'POST',
						data: {
							action: 'leadmalo_submit_email',
							email: form.find( 'input[name="email"]' ).val(),
							file: form.find( 'input[name="file"]' ).val(),
							nonce: lead_magnet_ajax.nonce
						},
						success: function (response) {
							if (response.success) {
								messagesDiv.html(
									'<div class="lead-magnet-message lead-magnet-success">' +
									response.data.message +
									'<br><a href="' + response.data.download_url + '" target="_blank">Click here to download</a>' +
									'</div>'
								);
								form.hide();
							} else {
								messagesDiv.html(
									'<div class="lead-magnet-message lead-magnet-error">' +
									response.data.message + '</div>'
								);
							}
						},
						error: function () {
							messagesDiv.html( '<div class="lead-magnet-message lead-magnet-error">An error occurred. Please try again.</div>' );
						},
						complete: function () {
							submitButton.text( buttonText ).prop( 'disabled', false );
						}
					}
				);
			}
		);
	}
);