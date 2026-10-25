<?php
/**
 * Protected file storage for Lead Magnet Locker.
 *
 * Files live in a randomly named subfolder of uploads/lead-magnet-files/,
 * so their URL cannot be guessed even on servers that ignore .htaccess
 * (Nginx, Caddy, IIS). Shortcodes and download records only store the file
 * name, so moving files into the private folder is transparent to them.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Infrastructure;

/**
 * Storage service class.
 *
 * Resolves file paths, keeps the folders hardened, migrates files from the
 * legacy folder, and maps public form tokens to file names.
 */
class LeadMagnetLockerStorage {

	/**
	 * Legacy (pre-1.3.0) folder name under uploads, also the parent of the private folder.
	 */
	public const BASE_DIR = 'lead-magnet-files';

	/**
	 * Option holding the private folder name.
	 */
	public const OPTION_DIR = 'leadmalo_storage_dir';

	/**
	 * Option set once the legacy folder holds no more files.
	 */
	public const OPTION_MIGRATED = 'leadmalo_storage_migrated';

	/**
	 * Option holding the timestamp of the next migration retry after a failure.
	 */
	public const OPTION_RETRY_AT = 'leadmalo_storage_retry_at';

	/**
	 * Option used as a short-lived migration lock.
	 */
	public const OPTION_LOCK = 'leadmalo_storage_lock';

	/**
	 * Option holding the timestamp until which the pre-1.3.0 "file" form field is accepted.
	 */
	public const OPTION_LEGACY_UNTIL = 'leadmalo_legacy_field_until';

	/**
	 * How long after the upgrade pages cached with the old form keep working.
	 */
	private const LEGACY_FIELD_WINDOW = 30 * 86400;

	/**
	 * Files that protect a folder and are never treated as lead magnets.
	 */
	private const PROTECTION_FILES = array( '.htaccess', 'index.php', 'index.html' );

	/**
	 * .htaccess written by earlier versions (Apache 2.2 syntax only).
	 */
	private const LEGACY_HTACCESS = "Order deny,allow\nDeny from all";

	/**
	 * Deny-all rules for Apache 2.4, with the 2.2 syntax as fallback.
	 */
	private const HTACCESS = "# Lead Magnet Locker: deny direct access.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n";

	/**
	 * Whether files are moved into the private folder.
	 *
	 * Sites that must stay downgradable to 1.2.x can define
	 * LML_DISABLE_STORAGE_MIGRATION as true in wp-config.php.
	 *
	 * @return bool
	 */
	public function is_private_storage_enabled(): bool {
		return ! ( defined( 'LML_DISABLE_STORAGE_MIGRATION' ) && LML_DISABLE_STORAGE_MIGRATION );
	}

	/**
	 * Absolute path of the legacy folder (uploads/lead-magnet-files).
	 *
	 * @return string
	 */
	public function base_dir(): string {
		return trailingslashit( wp_upload_dir()['basedir'] ) . self::BASE_DIR;
	}

	/**
	 * Absolute path of the private folder, creating its random name on first use.
	 *
	 * @return string
	 */
	public function private_dir(): string {
		$name = (string) get_option( self::OPTION_DIR, '' );
		if ( ! preg_match( '/^lmf-[a-f0-9]{32}$/', $name ) ) {
			$name = 'lmf-' . bin2hex( random_bytes( 16 ) );
			update_option( self::OPTION_DIR, $name, true );
		}

		return $this->base_dir() . '/' . $name;
	}

	/**
	 * Folder where new uploads are stored.
	 *
	 * @return string
	 */
	public function upload_dir(): string {
		return $this->is_private_storage_enabled() ? $this->private_dir() : $this->base_dir();
	}

	/**
	 * Folder where new uploads are stored, relative to the uploads base directory.
	 *
	 * @return string Leading slash, no trailing slash (as wp_upload_dir() 'subdir').
	 */
	public function upload_subdir(): string {
		return '/' . ltrim( substr( $this->upload_dir(), strlen( wp_upload_dir()['basedir'] ) ), '/' );
	}

	/**
	 * Create the folders (if missing) and keep their protection files in place.
	 *
	 * @return void
	 */
	public function ensure_directories(): void {
		$dirs = array( $this->base_dir() );
		if ( $this->is_private_storage_enabled() ) {
			$dirs[] = $this->private_dir();
		}

		foreach ( $dirs as $dir ) {
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}
			$this->protect_directory( $dir );
		}
	}

	/**
	 * Resolve a lead magnet file name to its absolute path.
	 *
	 * Looks in the private folder first and falls back to the legacy folder,
	 * so files not yet migrated keep working. The resolved path must stay
	 * inside the storage folder (no "../").
	 *
	 * @param string $name File name as used in shortcodes and records.
	 * @return string|null Absolute path, or null when not found.
	 */
	public function path( string $name ): ?string {
		$name = trim( $name );
		if ( '' === $name || in_array( strtolower( $name ), self::PROTECTION_FILES, true ) ) {
			return null;
		}

		$dirs = $this->is_private_storage_enabled()
			? array( $this->private_dir(), $this->base_dir(), $this->private_dir() ) // Re-check private: a file may move mid-lookup.
			: array( $this->base_dir() );

		foreach ( $dirs as $dir ) {
			$real_dir  = realpath( $dir );
			$real_file = realpath( $dir . '/' . $name );
			if ( false !== $real_dir && false !== $real_file && dirname( $real_file ) === $real_dir && is_file( $real_file ) ) {
				return $real_file;
			}
		}

		return null;
	}

	/**
	 * List lead magnet file names from both folders.
	 *
	 * @return string[] File names, sorted.
	 */
	public function list_files(): array {
		$names = array_merge(
			$this->files_in( $this->base_dir() ),
			$this->is_private_storage_enabled() ? $this->files_in( $this->private_dir() ) : array()
		);
		$names = array_values( array_unique( $names ) );
		sort( $names );

		return $names;
	}

	/**
	 * Opaque token for a file name, used by the public form instead of the name.
	 *
	 * @param string $name File name.
	 * @return string 32-character token.
	 */
	public function token( string $name ): string {
		return substr( hash_hmac( 'sha256', 'lead-magnet-file|' . $name, wp_salt( 'auth' ) ), 0, 32 );
	}

	/**
	 * Find the file name matching a public form token.
	 *
	 * @param string $token Token from the form.
	 * @return string|null File name, or null when no file matches.
	 */
	public function name_from_token( string $token ): ?string {
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
			return null;
		}

		foreach ( $this->list_files() as $name ) {
			if ( hash_equals( $this->token( $name ), $token ) ) {
				return $name;
			}
		}

		return null;
	}

	/**
	 * Whether the public form may still name the file directly (the pre-1.3.0 "file" field).
	 *
	 * Only pages cached before the upgrade send it, so it is accepted for 30 days
	 * after files were first moved to the private folder. Fresh installs never
	 * accept it. Sites that opted out of the migration keep accepting it.
	 *
	 * @return bool
	 */
	public function accepts_legacy_field(): bool {
		if ( ! $this->is_private_storage_enabled() ) {
			return true;
		}

		return time() < (int) get_option( self::OPTION_LEGACY_UNTIL, 0 );
	}

	/**
	 * Run the migration on 'init' when it is still pending and not waiting for a retry.
	 *
	 * @return void
	 */
	public function maybe_migrate(): void {
		if ( ! $this->is_private_storage_enabled() || get_option( self::OPTION_MIGRATED ) ) {
			return;
		}
		if ( time() < (int) get_option( self::OPTION_RETRY_AT, 0 ) ) {
			return;
		}

		$this->migrate();
	}

	/**
	 * Move files from the legacy folder into the private folder.
	 *
	 * Uses rename(), which is atomic on the same filesystem, so a file is never
	 * half copied or duplicated. Files that cannot be moved stay where they are
	 * (path() still finds them) and the move is retried a day later.
	 *
	 * @return string[] Names of the files that could not be moved.
	 */
	public function migrate(): array {
		if ( ! $this->is_private_storage_enabled() ) {
			return array();
		}

		// Atomic lock: add_option() fails when another request holds it.
		if ( ! add_option( self::OPTION_LOCK, time(), '', false ) ) {
			if ( time() - (int) get_option( self::OPTION_LOCK, 0 ) < 5 * MINUTE_IN_SECONDS ) {
				return array();
			}
			update_option( self::OPTION_LOCK, time(), false ); // Stale lock from a crashed request.
		}

		try {
			$this->ensure_directories();

			$failed = array();
			$moved  = 0;
			foreach ( $this->files_in( $this->base_dir() ) as $name ) {
				$from = $this->base_dir() . '/' . $name;
				$to   = $this->private_dir() . '/' . $name;

				// Never overwrite: a same-named file already in the private folder wins.
				// A failed move is handled below (file stays put, retried later), so its warning is silenced.
				// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename, WordPress.PHP.NoSilencedErrors.Discouraged
				if ( file_exists( $to ) || ! @rename( $from, $to ) ) {
					$failed[] = $name;
				} else {
					++$moved;
				}
			}

			// First migration after the upgrade moved files: cached pages may still send the old field.
			if ( $moved > 0 && ! get_option( self::OPTION_MIGRATED ) ) {
				add_option( self::OPTION_LEGACY_UNTIL, time() + self::LEGACY_FIELD_WINDOW, '', false );
			}

			if ( empty( $failed ) ) {
				update_option( self::OPTION_MIGRATED, '1', true );
				delete_option( self::OPTION_RETRY_AT );
			} else {
				update_option( self::OPTION_RETRY_AT, time() + DAY_IN_SECONDS, false );
			}

			return $failed;
		} finally {
			delete_option( self::OPTION_LOCK );
		}
	}

	/**
	 * Names of files still in the legacy (guessable) folder.
	 *
	 * @return string[]
	 */
	public function unprotected_files(): array {
		return $this->is_private_storage_enabled() ? $this->files_in( $this->base_dir() ) : array();
	}

	/**
	 * Regular files in a folder, without protection files.
	 *
	 * @param string $dir Absolute folder path.
	 * @return string[]
	 */
	private function files_in( string $dir ): array {
		if ( ! is_dir( $dir ) ) {
			return array();
		}

		$names = array();
		foreach ( (array) scandir( $dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry || in_array( strtolower( (string) $entry ), self::PROTECTION_FILES, true ) ) {
				continue;
			}
			if ( is_file( $dir . '/' . $entry ) ) {
				$names[] = (string) $entry;
			}
		}

		return $names;
	}

	/**
	 * Write the protection files into a folder.
	 *
	 * An existing .htaccess is only replaced when it still holds the content
	 * written by earlier versions, so manual edits are respected.
	 *
	 * @param string $dir Absolute folder path.
	 * @return void
	 */
	private function protect_directory( string $dir ): void {
		$htaccess = $dir . '/.htaccess';
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( ! file_exists( $htaccess ) || self::LEGACY_HTACCESS === trim( (string) file_get_contents( $htaccess ) ) ) {
			file_put_contents( $htaccess, self::HTACCESS );
		}

		// Empty index files stop directory listings from revealing the private folder name.
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		}
		if ( ! file_exists( $dir . '/index.html' ) ) {
			file_put_contents( $dir . '/index.html', '' );
		}
		// phpcs:enable
	}
}
