<div class="wrap">
    <h1>Download History</h1>

    <div class="tablenav top">
        <form method="get" action="">
            <input type="hidden" name="page" value="lead-magnet-history">
            <p class="search-box">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search emails or files...">
                <input type="submit" class="button" value="Search">
            </p>
        </form>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
        <tr>
            <th>ID</th>
            <th>Email</th>
            <th>File</th>
            <th>Submitted</th>
            <th>Downloaded</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($records)): ?>
            <tr>
                <td colspan="7">No records found.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($records as $record): ?>
                <tr id="record-<?php echo $record->id; ?>">
                    <td><?php echo $record->id; ?></td>
                    <td><?php echo esc_html($record->email); ?></td>
                    <td><?php echo esc_html($record->file_name); ?></td>
                    <td><?php echo mysql2date('Y-m-d H:i:s', $record->created_at); ?></td>
                    <td>
                        <?php if ($record->downloaded_at): ?>
                            <?php echo mysql2date('Y-m-d H:i:s', $record->downloaded_at); ?>
                        <?php else: ?>
                            <em>Not downloaded</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($record->downloaded_at): ?>
                            <span style="color: green;">✓ Downloaded</span>
                        <?php else: ?>
                            <span style="color: orange;">⏳ Pending</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="button-secondary delete-record" data-id="<?php echo $record->id; ?>">Delete</button>
                        <a href="<?php echo home_url('/?lead_magnet_download=' . $record->download_key); ?>" target="_blank" class="button-secondary">Download Link</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo $total_items; ?> items</span>
                <?php
                $base_url = admin_url('admin.php?page=lead-magnet-history');
                if (!empty($search)) {
                    $base_url .= '&s=' . urlencode($search);
                }

                if ($page > 1): ?>
                    <a class="button" href="<?php echo $base_url . '&paged=' . ($page - 1); ?>">&laquo; Previous</a>
                <?php endif; ?>

                <span class="paging-input">
                            Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                        </span>

                <?php if ($page < $total_pages): ?>
                    <a class="button" href="<?php echo $base_url . '&paged=' . ($page + 1); ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <script>
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
    </script>
</div>
