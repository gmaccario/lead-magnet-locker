/**
 * Admin JavaScript for Lead Magnet Locker.
 *
 * Handles record deletion, shortcode copy-to-clipboard,
 * and file upload validation in the admin dashboard.
 *
 * @file
 * @package LeadMagnetLocker
 */

jQuery( document ).ready(
	function ($) {
		$( '.delete-record' ).on(
			'click',
			function () {
				if ( ! confirm( lead_magnet_admin_ajax.confirm_delete )) {
					return false;
				}

				var recordId = $( this ).data( 'id' );
				var row      = $( '#record-' + recordId );

				$.ajax(
					{
						url: lead_magnet_admin_ajax.ajax_url,
						type: 'POST',
						data: {
							action: 'leadmalo_delete_download_record',
							record_id: recordId,
							nonce: lead_magnet_admin_ajax.nonce
						},
						success: function (response) {
							if (response.success) {
								row.fadeOut(
									function () {
										$( this ).remove();
									}
								);
							} else {
								alert( 'Error deleting record: ' + response.data.message );
							}
						},
						error: function () {
							alert( 'An error occurred while deleting the record.' );
						}
					}
				);
			}
		);
	}
);

jQuery( document ).ready(
	function ($) {
		// Copy shortcode functionality.
		$( '.copy-shortcode' ).on(
			'click',
			function () {
				const shortcode = $( this ).data( 'shortcode' );

				// Create a temporary textarea to copy text.
				const temp = $( '<textarea>' );
				$( 'body' ).append( temp );
				temp.val( shortcode ).select();
				document.execCommand( 'copy' );
				temp.remove();

				// Show feedback.
				const button       = $( this );
				const originalText = button.text();
				button.text( 'Copied!' ).prop( 'disabled', true );

				setTimeout(
					function () {
						button.text( originalText ).prop( 'disabled', false );
					},
					2000
				);
			}
		);

		// File upload preview.
		$( '#lead_magnet_file' ).on(
			'change',
			function () {
				const file = this.files[0];
				if (file) {
					const fileSize = file.size;
					let maxSize    = wp_max_upload_size;

					if (fileSize > maxSize) {
						alert( 'File size exceeds maximum allowed size of ' + (maxSize / (1024 * 1024)).toFixed( 2 ) + 'MB' );
						$( this ).val( '' );
						return;
					}

					// Show file info.
					const info = 'Selected: ' + file.name + ' (' + (fileSize / (1024 * 1024)).toFixed( 2 ) + 'MB)';
					if ( ! $( '.file-info' ).length) {
						$( this ).after( '<p class="file-info description">' + info + '</p>' );
					} else {
						$( '.file-info' ).text( info );
					}
				}
			}
		);
	}
);