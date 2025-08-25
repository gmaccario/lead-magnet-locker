jQuery(document).ready(function($) {
    $('.delete-record').on('click', function() {
        if (!confirm(lead_magnet_admin_ajax.confirm_delete)) {
            return false;
        }

        var recordId = $(this).data('id');
        var row = $('#record-' + recordId);

        $.ajax({
            url: lead_magnet_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'delete_download_record',
                record_id: recordId,
                nonce: lead_magnet_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    row.fadeOut(function() {
                        $(this).remove();
                    });
                } else {
                    alert('Error deleting record: ' + response.data.message);
                }
            },
            error: function() {
                alert('An error occurred while deleting the record.');
            }
        });
    });
});
