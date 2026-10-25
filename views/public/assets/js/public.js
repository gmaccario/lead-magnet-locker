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
		const settings = window.lead_magnet_ajax || {};
		const i18n     = $.extend(
			{
				processing: 'Processing...',
				download: 'Click here to download',
				error: 'An error occurred. Please try again.'
			},
			settings.i18n
		);

		// Messages are inserted as text, never as HTML.
		function message(type, text) {
			return $( '<div>' ).addClass( 'lead-magnet-message lead-magnet-' + type ).text( text );
		}

		$( '.lead-magnet-email-form' ).on(
			'submit',
			function (e) {
				e.preventDefault();

				const form         = $( this );
				const container    = form.closest( '.lead-magnet-form' );
				const messagesDiv  = container.find( '.lead-magnet-messages' );
				const submitButton = form.find( 'button[type="submit"]' );
				const buttonText   = submitButton.text();

				// Show loading state.
				submitButton.text( i18n.processing ).prop( 'disabled', true );
				messagesDiv.empty();

				$.ajax(
					{
						url: settings.ajax_url,
						type: 'POST',
						data: {
							action: 'leadmalo_submit_email',
							email: form.find( 'input[name="email"]' ).val(),
							lmf: form.find( 'input[name="lmf"]' ).val() || '',
							file: form.find( 'input[name="file"]' ).val() || '', // Pages cached before 1.3.0.
							nonce: settings.nonce
						},
						success: function (response) {
							// A failed security check answers with plain text, not JSON.
							if ( ! response || ! response.data) {
								messagesDiv.append( message( 'error', i18n.error ) );
								return;
							}

							if (response.success) {
								messagesDiv.append(
									message( 'success', response.data.message ).append(
										'<br>',
										$( '<a target="_blank">' ).attr( 'href', response.data.download_url ).text( i18n.download )
									)
								);
								form.hide();
							} else {
								messagesDiv.append( message( 'error', response.data.message ) );
							}
						},
						error: function () {
							messagesDiv.append( message( 'error', i18n.error ) );
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
