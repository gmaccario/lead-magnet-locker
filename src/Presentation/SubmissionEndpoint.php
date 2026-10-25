<?php
/**
 * Email submissions from the public form (admin-ajax.php).
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Presentation;

use LeadMagnet\Locker\Application\Port\FileStorage;
use LeadMagnet\Locker\Application\SubmitEmail;

/**
 * Reads the AJAX request, runs SubmitEmail and answers with JSON.
 */
final class SubmissionEndpoint {

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
	 * @param SubmitEmail $submit Use case.
	 * @param FileStorage $files  File storage (legacy-field policy).
	 */
	public function __construct(
		private SubmitEmail $submit,
		private FileStorage $files
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

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$token = isset( $_POST['lmf'] ) ? sanitize_text_field( wp_unslash( $_POST['lmf'] ) ) : '';

		$outcome = $this->submit->execute( $email, $token, '' === $token ? $this->legacy_name() : null );

		if ( ! $outcome->is_success() ) {
			wp_send_json_error( array( 'message' => $this->message( (string) $outcome->error ) ) );
		}

		wp_send_json_success(
			array(
				'message'      => __( 'Success! Your download is ready.', 'lead-magnet-locker' ),
				'download_url' => DownloadEndpoint::public_url( $outcome->string( 'download_key' ) ),
			)
		);
	}

	/**
	 * File name from the pre-1.3.0 "file" field, when it is sent and accepted.
	 *
	 * @return string|null
	 */
	private function legacy_name(): ?string {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in handle().
		/**
		 * Whether to accept the pre-1.3.0 "file" field, sent by pages cached before the update.
		 *
		 * By default it is accepted for 30 days after the upgrade moved files to the
		 * private folder (always, when the migration is disabled). Return false to
		 * refuse it now, true to keep accepting it.
		 *
		 * @param bool $accept Whether to accept the legacy field.
		 */
		if ( ! isset( $_POST['file'] ) || ! apply_filters( 'lead_magnet_accept_legacy_file_field', $this->files->accepts_legacy_field() ) ) {
			return null;
		}

		return sanitize_file_name( wp_unslash( $_POST['file'] ) );
		// phpcs:enable
	}

	/**
	 * Visitor-facing message for an error code.
	 *
	 * @param string $error SubmitEmail error code.
	 * @return string
	 */
	private function message( string $error ): string {
		return match ( $error ) {
			SubmitEmail::UNAVAILABLE   => __( 'This download is currently unavailable.', 'lead-magnet-locker' ),
			SubmitEmail::INVALID_EMAIL => __( 'Please enter a valid email address.', 'lead-magnet-locker' ),
			SubmitEmail::STORAGE_ERROR => __( 'Database error. Please try again.', 'lead-magnet-locker' ),
			default                    => __( 'File not found.', 'lead-magnet-locker' ),
		};
	}
}
