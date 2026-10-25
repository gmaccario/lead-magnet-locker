<?php
/**
 * The [lead_magnet] shortcode and its front-end assets.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Presentation;

use LeadMagnet\Locker\Application\Port\Clock;
use LeadMagnet\Locker\Application\Port\FileStorage;
use LeadMagnet\Locker\Application\Port\SettingsStore;

/**
 * Renders the email capture form.
 */
final class ShortcodeController {

	/**
	 * Shortcode tag.
	 */
	public const TAG = 'lead_magnet';

	/**
	 * Constructor.
	 *
	 * @param FileStorage   $files    File storage.
	 * @param SettingsStore $settings Settings.
	 * @param Clock         $clock    Clock.
	 * @param PluginPaths   $paths    Plugin files.
	 */
	public function __construct(
		private FileStorage $files,
		private SettingsStore $settings,
		private Clock $clock,
		private PluginPaths $paths
	) {}

	/**
	 * Shortcode callback.
	 *
	 * Usage: [lead_magnet file="your-file.pdf" title="Download Free Guide"]
	 *
	 * @param array<string, string>|string $atts Shortcode attributes ("" when there are none).
	 * @return string
	 */
	public function render( array|string $atts ): string {
		if ( ! $this->settings->schedule()->is_open( $this->clock->now() ) ) {
			return '<div class="lead-magnet-unavailable" style="padding: 20px; border: 2px solid #ccc; border-radius: 8px; text-align: center; color: #666;">'
				. esc_html__( 'This download is currently unavailable.', 'lead-magnet-locker' )
				. '</div>';
		}

		$atts = shortcode_atts(
			array(
				'file'        => '',
				'title'       => __( 'Get Your Free Download', 'lead-magnet-locker' ),
				'description' => __( 'Enter your email address to receive your free download.', 'lead-magnet-locker' ),
				'button_text' => __( 'Get Download Link', 'lead-magnet-locker' ),
			),
			is_array( $atts ) ? $atts : array(),
			self::TAG
		);

		if ( empty( $atts['file'] ) ) {
			return $this->error( __( 'Please specify a file in the shortcode.', 'lead-magnet-locker' ) );
		}

		// Resolve the file inside the protected storage (blocks "../").
		$file_path = $this->files->path( (string) $atts['file'] );
		if ( null === $file_path ) {
			return $this->error( __( 'File not found. Please upload the file first from the WordPress admin dashboard.', 'lead-magnet-locker' ) );
		}

		ob_start();
		$rendered = $this->paths->render(
			'public/lead-magnet-form.php',
			array(
				'atts'       => $atts,
				// The form carries an opaque token, never the file name.
				'file_token' => $this->files->token( basename( $file_path ) ),
			)
		);
		$html     = (string) ob_get_clean();

		return $rendered ? $html : $this->error( __( 'Missing form view file.', 'lead-magnet-locker' ) );
	}

	/**
	 * Front-end script and style (runs on wp_enqueue_scripts).
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		wp_enqueue_style(
			'leadmalo-public',
			$this->paths->url( 'views/public/assets/css/public.css' ),
			array(),
			$this->paths->asset_version( 'views/public/assets/css/public.css' )
		);

		wp_enqueue_script(
			'leadmalo-public',
			$this->paths->url( 'views/public/assets/js/public.js' ),
			array( 'jquery' ),
			$this->paths->asset_version( 'views/public/assets/js/public.js' ),
			true
		);

		wp_localize_script(
			'leadmalo-public',
			'lead_magnet_ajax',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( SubmissionEndpoint::NONCE ),
				'i18n'     => array(
					'processing' => __( 'Processing...', 'lead-magnet-locker' ),
					'download'   => __( 'Click here to download', 'lead-magnet-locker' ),
					'error'      => __( 'An error occurred. Please try again.', 'lead-magnet-locker' ),
				),
			)
		);
	}

	/**
	 * Error message for administrators; visitors get nothing.
	 *
	 * @param string $message Plain-text message.
	 * @return string
	 */
	private function error( string $message ): string {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		return '<p class="lead-magnet-error-admin"><strong>Lead Magnet Locker:</strong> ' . esc_html( $message ) . ' <em>(' . esc_html__( 'Only administrators see this message.', 'lead-magnet-locker' ) . ')</em></p>';
	}
}
