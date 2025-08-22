<div class="lead-magnet-form" id="lead-magnet-<?php echo esc_attr(uniqid()); ?>">
    <style>
        .lead-magnet-form {
            max-width: 400px;
            padding: 20px;
            border: 2px solid #0073aa;
            border-radius: 8px;
            margin: 20px 0;
            background: #f9f9f9;
        }
        .lead-magnet-form h3 {
            margin-top: 0;
            color: #0073aa;
        }
        .lead-magnet-form input[type="email"] {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .lead-magnet-form button {
            background: #0073aa;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
        }
        .lead-magnet-form button:hover {
            background: #005a87;
        }
        .lead-magnet-message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .lead-magnet-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .lead-magnet-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>

    <h3><?php echo esc_html($atts['title']); ?></h3>
    <p><?php echo esc_html($atts['description']); ?></p>

    <div class="lead-magnet-messages"></div>

    <form class="lead-magnet-email-form">
        <input type="email"
               name="email"
               placeholder="Enter your email address"
               required>
        <input type="hidden" name="file" value="<?php echo esc_attr($atts['file']); ?>">
        <button type="submit"><?php echo esc_html($atts['button_text']); ?></button>
    </form>

    <script>
        jQuery(document).ready(function($) {
            $('.lead-magnet-email-form').on('submit', function(e) {
                e.preventDefault();

                var form = $(this);
                var container = form.closest('.lead-magnet-form');
                var messagesDiv = container.find('.lead-magnet-messages');
                var submitButton = form.find('button[type="submit"]');

                // Show loading state
                submitButton.text('Processing...').prop('disabled', true);
                messagesDiv.empty();

                $.ajax({
                    url: lead_magnet_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'submit_email',
                        email: form.find('input[name="email"]').val(),
                        file: form.find('input[name="file"]').val(),
                        nonce: lead_magnet_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            messagesDiv.html('<div class="lead-magnet-message lead-magnet-success">' +
                                response.data.message +
                                '<br><a href="' + response.data.download_url + '" target="_blank">Click here to download</a>' +
                                '</div>');
                            form.hide();
                        } else {
                            messagesDiv.html('<div class="lead-magnet-message lead-magnet-error">' +
                                response.data.message + '</div>');
                        }
                    },
                    error: function() {
                        messagesDiv.html('<div class="lead-magnet-message lead-magnet-error">An error occurred. Please try again.</div>');
                    },
                    complete: function() {
                        submitButton.text('<?php echo esc_js($atts['button_text']); ?>').prop('disabled', false);
                    }
                });
            });
        });
    </script>
</div>
