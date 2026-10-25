<?php
/**
 * Use case: a visitor submits their email to get a file.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Application;

use LeadMagnet\Locker\Application\Port\Clock;
use LeadMagnet\Locker\Application\Port\DownloadRecords;
use LeadMagnet\Locker\Application\Port\EmailValidator;
use LeadMagnet\Locker\Application\Port\FileStorage;
use LeadMagnet\Locker\Application\Port\SettingsStore;
use LeadMagnet\Locker\Domain\DownloadKey;

/**
 * Checks the schedule, the email and the file, then stores the submission.
 */
final class SubmitEmail {

	public const UNAVAILABLE    = 'unavailable';
	public const INVALID_EMAIL  = 'invalid_email';
	public const FILE_NOT_FOUND = 'file_not_found';
	public const STORAGE_ERROR  = 'storage_error';

	/**
	 * Constructor.
	 *
	 * @param SettingsStore   $settings Settings.
	 * @param Clock           $clock    Clock.
	 * @param EmailValidator  $emails   Email validation.
	 * @param FileStorage     $files    File storage.
	 * @param DownloadRecords $records  Download records.
	 */
	public function __construct(
		private SettingsStore $settings,
		private Clock $clock,
		private EmailValidator $emails,
		private FileStorage $files,
		private DownloadRecords $records
	) {}

	/**
	 * Run the use case.
	 *
	 * @param string      $email       Email address (sanitised).
	 * @param string      $token       Form token ("" when absent).
	 * @param string|null $legacy_name File name from the pre-1.3.0 field, when it is accepted.
	 * @return Outcome Success with data "download_key".
	 */
	public function execute( string $email, string $token, ?string $legacy_name = null ): Outcome {
		if ( ! $this->settings->schedule()->is_open( $this->clock->now() ) ) {
			return Outcome::failure( self::UNAVAILABLE );
		}

		if ( ! $this->emails->is_valid( $email ) ) {
			return Outcome::failure( self::INVALID_EMAIL );
		}

		$path = $this->requested_path( $token, $legacy_name );
		if ( null === $path ) {
			return Outcome::failure( self::FILE_NOT_FOUND );
		}

		$download_key = DownloadKey::generate();
		if ( ! $this->records->create( $email, basename( $path ), $download_key ) ) {
			return Outcome::failure( self::STORAGE_ERROR );
		}

		return Outcome::success( array( 'download_key' => $download_key ) );
	}

	/**
	 * Path of the requested file: from the token, or the legacy name when there is no token.
	 *
	 * @param string      $token       Form token.
	 * @param string|null $legacy_name Legacy file name.
	 * @return string|null
	 */
	private function requested_path( string $token, ?string $legacy_name ): ?string {
		if ( '' !== $token ) {
			$name = $this->files->name_from_token( $token );

			return null !== $name ? $this->files->path( $name ) : null;
		}

		return null !== $legacy_name && '' !== $legacy_name ? $this->files->path( $legacy_name ) : null;
	}
}
