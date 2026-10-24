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
jQuery( document ).ready(
	function ($) {
		// Timezone search: filters the timezone dropdown as the user types.
		const select = $( 'select[name="timezone"]' );
		if ( ! select.length) {
			return;
		}

		// Keep the full list; options are rebuilt on each search (Safari ignores hidden options).
		const allOptions = select.find( 'option' ).map(
			function () {
				return { value: this.value, text: $( this ).text() };
			}
		).get();

		const search = $( '<input type="search" class="lml-timezone-search" autocomplete="off">' )
			.attr( 'placeholder', 'Search timezones, e.g. Rome or New York' )
			.attr( 'aria-label', 'Search timezones' );
		const empty  = $( '<p class="lml-timezone-empty" aria-live="polite"></p>' ).hide();

		select.before( search ).after( empty );

		function render(query) {
			const current = select.val();
			const needle  = query.trim().toLowerCase().replace( /\s+/g, '_' );
			let matches   = allOptions.filter(
				function (option) {
					return '' === needle || option.text.toLowerCase().includes( needle );
				}
			);

			// Cities starting with the query come first ("tok" → Asia/Tokyo before Antarctica/Vostok).
			if ('' !== needle) {
				const startsWith = function (option) {
					const city = option.text.toLowerCase().split( '/' ).pop();
					return city.startsWith( needle ) || option.text.toLowerCase().startsWith( needle ) ? 0 : 1;
				};
				matches.sort(
					function (a, b) {
						return startsWith( a ) - startsWith( b );
					}
				);
			}

			empty.toggle( 0 === matches.length ).text( 0 === matches.length ? 'No matching timezones.' : '' );

			// Never leave the select empty: keep the saved choice when nothing matches.
			if ( ! matches.length) {
				matches = allOptions.filter(
					function (option) {
						return option.value === current;
					}
				);
			}

			select.empty();
			matches.forEach(
				function (option) {
					select.append( $( '<option>' ).val( option.value ).text( option.text ) );
				}
			);

			const stillThere = matches.some(
				function (option) {
					return option.value === current;
				}
			);
			select.val( stillThere ? current : matches[0].value );

			// Show the results as an open list while searching.
			if ( '' !== needle && matches.length > 1) {
				select.attr( 'size', Math.min( matches.length, 8 ) );
			} else {
				select.removeAttr( 'size' );
			}
		}

		search.on(
			'input',
			function () {
				render( this.value );
			}
		);

		// Enter picks the highlighted timezone instead of submitting the form.
		search.on(
			'keydown',
			function (event) {
				if ('Enter' === event.key) {
					event.preventDefault();
					search.val( '' );
					render( '' );
				} else if ('ArrowDown' === event.key && select.attr( 'size' )) {
					event.preventDefault();
					select.trigger( 'focus' );
				}
			}
		);

		// Clicking a result (or Enter on it) closes the list and keeps the choice.
		function pick() {
			if (select.attr( 'size' )) {
				search.val( '' );
				render( '' );
				search.trigger( 'focus' );
			}
		}

		select.on( 'click', pick );
		select.on(
			'keydown',
			function (event) {
				if ('Enter' === event.key) {
					event.preventDefault();
					pick();
				}
			}
		);
	}
);
