<?php
/**
 * Locations of the plugin's own files.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Infrastructure;

/**
 * Paths, URLs and asset versions, derived from the main plugin file.
 */
final class PluginPaths {

	/**
	 * Constructor.
	 *
	 * @param string $main_file Absolute path of lead-magnet-locker.php.
	 * @param string $version   Plugin version.
	 */
	public function __construct(
		public readonly string $main_file,
		public readonly string $version
	) {}

	/**
	 * Absolute path of a plugin file.
	 *
	 * @param string $relative_path Path relative to the plugin folder.
	 * @return string
	 */
	public function path( string $relative_path ): string {
		return plugin_dir_path( $this->main_file ) . ltrim( $relative_path, '/' );
	}

	/**
	 * URL of a plugin file.
	 *
	 * @param string $relative_path Path relative to the plugin folder.
	 * @return string
	 */
	public function url( string $relative_path ): string {
		return plugin_dir_url( $this->main_file ) . ltrim( $relative_path, '/' );
	}

	/**
	 * Cache-busting version for an asset: plugin version plus file modification time.
	 *
	 * @param string $relative_path Path relative to the plugin folder.
	 * @return string
	 */
	public function asset_version( string $relative_path ): string {
		$file = $this->path( $relative_path );

		return file_exists( $file ) ? $this->version . '.' . filemtime( $file ) : $this->version;
	}

	/**
	 * Include a view with the given variables in scope.
	 *
	 * Uses include (not include_once): a shortcode view may render several times per page.
	 *
	 * @param string               $view View path relative to views/.
	 * @param array<string, mixed> $vars Variables for the view.
	 * @return bool Whether the view was found.
	 */
	public function render( string $view, array $vars = array() ): bool {
		$file = $this->path( 'views/' . $view );
		if ( ! is_readable( $file ) ) {
			return false;
		}

		( static function ( string $lml_view_file, array $lml_view_vars ): void {
			extract( $lml_view_vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- view variables only.
			include $lml_view_file;
		} )( $file, $vars );

		return true;
	}
}
