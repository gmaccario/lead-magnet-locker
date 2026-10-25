<?php
/**
 * Email submissions from the public form.
 *
 * @package LeadMagnetLocker
 * @subpackage Application
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Application;

use LeadMagnet\Locker\Infrastructure\DownloadRepository;
use LeadMagnet\Locker\Infrastructure\LeadMagnetLockerDownloader;
use LeadMagnet\Locker\Infrastructure\LeadMagnetLockerStorage;
use LeadMagnet\Locker\Infrastructure\SettingsRepository;

/**
 * Validates a submission, stores the record and returns the download link.
 */
final class SubmissionHandler {

	/**
	 * AJAX action.
	 */
	public const ACTION = 'leadmalo_submit_email';

	/**
	 * Nonce action of the public form.
	 */
	public const NONCE = 'leadmalo_public_nonce';

	/**
	 * Constructor.
	 *
	 * @param LeadMagnetLockerStorage    $storage    Protected file storage.
	 * @param DownloadRepository         $downloads  Download records.
	 * @param SettingsRepository         $settings   Settings.
	 * @param LeadMagnetLockerDownloader $downloader Builds download links.
	 */
	public function __construct(
		private LeadMagnetLockerStorage $storage,
		private DownloadRepository $downloads,
		private SettingsRepository $settings,
		private LeadMagnetLockerDownloader $downloader
	) {}

	/**
	 * AJAX callback (logged-in and logged-out visitors).
	 *
	 * @return void
	 */
	public function handle(): void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			wp_die( esc_html__( 'Security check failed', 'lead-magnet-locker' ) );
		}

		if ( ! $this->settings->schedule()->is_open( current_time( 'mysql' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'This download is currently unavailable.', 'lead-magnet-locker' ) ) );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'lead-magnet-locker' ) ) );
		}

		$file_path = $this->requested_file();
		if ( null === $file_path ) {
			wp_send_json_error( array( 'message' => __( 'File not found.', 'lead-magnet-locker' ) ) );
		}

		$download_key = wp_generate_password( 32, false );
		if ( ! $this->downloads->create( $email, basename( $file_path ), $download_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Database error. Please try again.', 'lead-magnet-locker' ) ) );
		}

		wp_send_json_success(
			array(
				'message'      => __( 'Success! Your download is ready.', 'lead-magnet-locker' ),
				'download_url' => $this->downloader->get_download_url( $download_key ),
			)
		);
	}

	/**
	 * Path of the requested file, from the form token (or the legacy field).
	 *
	 * @return string|null
	 */
	private function requested_file(): ?string {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in handle().
		$token = isset( $_POST['lmf'] ) ? sanitize_text_field( wp_unslash( $_POST['lmf'] ) ) : '';
		if ( '' !== $token ) {
			$name = $this->storage->name_from_token( $token );

			return null !== $name ? $this->storage->path( $name ) : null;
		}

		/**
		 * Whether to accept the pre-1.3.0 "file" field, sent by pages cached before the update.
		 *
		 * By default it is accepted for 30 days after the upgrade moved files to the
		 * private folder (always, when the migration is disabled). Return false to
		 * refuse it now, true to keep accepting it.
		 *
		 * @param bool $accept Whether to accept the legacy field.
		 */
		if ( ! isset( $_POST['file'] ) || ! apply_filters( 'lead_magnet_accept_legacy_file_field', $this->storage->accepts_legacy_field() ) ) {
			return null;
		}

		$name = sanitize_file_name( wp_unslash( $_POST['file'] ) );
		// phpcs:enable

		return '' !== $name ? $this->storage->path( $name ) : null;
	}
}
