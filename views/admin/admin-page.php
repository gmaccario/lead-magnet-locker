<div class="wrap">
    <h1>Lead Magnet Locker</h1>
    <div class="card">
        <h2>How to Use</h2>
        <p>Use the shortcode <code>[lead_magnet file="your-file.pdf" title="Your Title"]</code> to display a lead capture form.</p>

        <h3>Shortcode Parameters:</h3>
        <ul>
            <li><strong>file</strong> (required): Name of the file in wp-content/uploads/lead-magnet-files/</li>
            <li><strong>title</strong>: Form title (default: "Get Your Free Download")</li>
            <li><strong>description</strong>: Form description</li>
            <li><strong>button_text</strong>: Submit button text</li>
        </ul>

        <h3>File Upload</h3>
        <p>Upload your files to: <code><?php echo wp_upload_dir()['basedir']; ?>/lead-magnet-files/</code></p>

        <?php echo $upload_message; ?>
    </div>
    <div class="card">
        <h2>Statistics</h2>
        <p><strong>Total Submissions:</strong> <?php echo $total_downloads; ?></p>
        <p><strong>Unique Emails:</strong> <?php echo $unique_emails; ?></p>
        <p><strong>Completed Downloads:</strong> <?php echo $completed_downloads; ?></p>
        <p><strong>Conversion Rate:</strong> <?php echo $total_downloads > 0 ? round(($completed_downloads / $total_downloads) * 100, 2) . '%' : '0%'; ?></p>
    </div>
</div>
