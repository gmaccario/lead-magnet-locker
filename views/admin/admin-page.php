<?php
/**
 * Enhanced Admin Page Template
 *
 * @package LeadMagnetLocker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="lead-magnet-locker wrap">
	<h1>Lead Magnet Locker</h1>

	<?php if ( ! empty( $upload_message ) ) : ?>
		<div class="notice notice-info">
			<p><?php echo esc_html( $upload_message ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Statistics -->
	<div class="card">
		<h2>Statistics</h2>
		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
			<div style="text-align: center; padding: 20px; background: #f0f6fc; border-radius: 5px;">
				<h3 style="margin: 0; color: #0073aa;"><?php echo esc_html( $total_downloads ); ?></h3>
				<p style="margin: 5px 0 0 0;">Total Submissions</p>
			</div>
			<div style="text-align: center; padding: 20px; background: #f0f6fc; border-radius: 5px;">
				<h3 style="margin: 0; color: #0073aa;"><?php echo esc_html( $unique_emails ); ?></h3>
				<p style="margin: 5px 0 0 0;">Unique Emails</p>
			</div>
			<div style="text-align: center; padding: 20px; background: #f0f6fc; border-radius: 5px;">
				<h3 style="margin: 0; color: #0073aa;"><?php echo esc_html( $completed_downloads ); ?></h3>
				<p style="margin: 5px 0 0 0;">Completed Downloads</p>
			</div>
			<div style="text-align: center; padding: 20px; background: #f0f6fc; border-radius: 5px;">
				<h3 style="margin: 0; color: #0073aa;">
				<?php
				echo $total_downloads > 0
							? esc_html( round( ( $completed_downloads / $total_downloads ) * 100, 2 ) ) . '%'
							: '0%';
				?>
							</h3>
				<p style="margin: 5px 0 0 0;">Conversion Rate</p>
			</div>
		</div>
	</div>

	<div class="lml-row">
		<!-- File Upload Section -->
		<div class="card">
			<h2>Upload New File</h2>
			<form method="post" enctype="multipart/form-data" id="file-upload-form">
				<?php wp_nonce_field( 'lead_magnet_upload', 'upload_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">Select File</th>
						<td>
							<input type="file" name="lead_magnet_file" id="lead_magnet_file" accept=".pdf,.zip,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.txt,.mp3,.mp4">
							<p class="description">
								Allowed file types: PDF, ZIP, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, JPEG, PNG, GIF, TXT, MP3, MP4<br>
								Maximum file size: <?php echo esc_html( $this->file_uploader->format_file_size( wp_max_upload_size() ) ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" name="upload_file" class="button-primary" value="Upload File">
				</p>
			</form>
		</div>

		<!-- Usage Instructions -->
		<div class="card">
			<h2>How to Use</h2>
			<ol>
				<li><strong>Upload Files:</strong> Use the "Upload New File" form to upload your lead magnet files.</li>
				<li><strong>Copy Shortcode:</strong> Click the "Copy" button next to any file to get its shortcode.</li>
				<li><strong>Add to Content:</strong> Paste the shortcode into any page or post where you want the lead capture form.</li>
				<li><strong>Customize:</strong> Add parameters like <code>title="Your Title"</code> to personalize the form.</li>
			</ol>

			<h3>Shortcode Parameters:</h3>
			<ul>
				<li><strong>file</strong> (required): Name of the uploaded file</li>
				<li><strong>title</strong>: Form title (default: "Get Your Free Download")</li>
				<li><strong>description</strong>: Form description</li>
				<li><strong>button_text</strong>: Submit button text</li>
			</ul>

			<h3>Example:</h3>
			<code>[lead_magnet file="marketing-guide.pdf" title="Free Marketing Guide" description="Get our proven strategies" button_text="Download Now"]</code>
		</div>
	</div>

	<!-- File Management Section -->
	<div class="card">
		<h2>Manage Files</h2>

		<?php if ( empty( $files ) ) : ?>
			<p><em>No files uploaded yet. Upload your first file above.</em></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
				<tr>
					<th>Filename</th>
					<th>Size</th>
					<th>Modified</th>
					<th>Shortcode</th>
					<th>Actions</th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( $files as $file ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $file['name'] ); ?></strong></td>
						<td><?php echo esc_html( $file['size'] ); ?></td>
						<td><?php echo esc_html( $file['modified'] ); ?></td>
						<td>
							<code>[lead_magnet file="<?php echo esc_attr( $file['name'] ); ?>"]</code>
						</td>
						<td>
							<button class="button-secondary copy-shortcode" data-shortcode='[lead_magnet file="<?php echo esc_attr( $file['name'] ); ?>"]'>
								Copy
							</button>
							<form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this file?');">
								<?php wp_nonce_field( 'lead_magnet_delete', 'delete_nonce' ); ?>
								<input type="hidden" name="file_to_delete" value="<?php echo esc_attr( $file['name'] ); ?>">
								<input type="submit" name="delete_file" class="button-secondary" value="Delete">
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
