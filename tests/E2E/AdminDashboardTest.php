<?php
/**
 * Admin dashboard: statistics, upload, file list, delete, storage migration.
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Tests\E2E;

use LeadMagnet\Locker\Tests\E2E\Support\Http;
use LeadMagnet\Locker\Tests\E2E\Support\Response;
use LeadMagnet\Locker\Tests\E2E\Support\TestCase;

/**
 * Dashboard tests.
 */
final class AdminDashboardTest extends TestCase {

	private const URL = '/wp-admin/admin.php?page=lead-magnet-locker';

	public function test_dashboard_snapshot(): void {
		$html = $this->admin()->get( self::URL )->body;
		$wrap = $this->elements( $html, 'lead-magnet-locker' );

		$this->assertCount( 1, $wrap );
		$this->assertMatchesSnapshot( 'admin-dashboard', $wrap[0] );
		$this->assertMatchesRegularExpression( '#lead-magnet-locker/views/admin/assets/css/admin\.css\?ver=#', $html );
		$this->assertMatchesRegularExpression( '#lead-magnet-locker/views/admin/assets/js/admin\.js\?ver=#', $html );
		$this->assertArrayHasKey( 'nonce', $this->localized( $html, 'lead_magnet_admin_ajax' ) );
	}

	public function test_dashboard_lists_files_with_their_shortcode(): void {
		$html = $this->admin()->get( self::URL )->body;

		foreach ( array_keys( self::$fixture['files'] ) as $name ) {
			$this->assertStringContainsString( '<code>[lead_magnet file="' . $name . '"]</code>', $html );
		}
		$this->assertStringNotContainsString( '.htaccess</strong>', $html );
		$this->assertStringNotContainsString( 'index.php</strong>', $html );
	}

	/**
	 * @dataProvider admin_pages
	 */
	public function test_subscribers_cannot_open_admin_pages( string $page ): void {
		$response = $this->subscriber()->get( '/wp-admin/admin.php?page=' . $page );

		$this->assertSame( 403, $response->status );
		$this->assertStringNotContainsString( 'lead-magnet-locker wrap', $response->body );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function admin_pages(): array {
		return array(
			'dashboard' => array( 'lead-magnet-locker' ),
			'settings'  => array( 'lead-magnet-settings' ),
			'history'   => array( 'lead-magnet-history' ),
		);
	}

	public function test_upload_stores_the_file_in_private_storage(): void {
		$admin = $this->admin();

		$first = $this->upload( $admin, self::PREFIX . 'up.txt', 'uploaded' );
		$this->assertStringContainsString( 'File uploaded successfully: ' . self::PREFIX . 'up.txt', $this->notice( $first ) );
		$this->assertSame( 'private', self::fixture( 'where', self::PREFIX . 'up.txt' )['where'] );
		$this->assertStringContainsString( '[lead_magnet file="' . self::PREFIX . 'up.txt"]', $first->body );

		$second = $this->upload( $admin, self::PREFIX . 'up.txt', 'again' );
		$this->assertStringContainsString( 'File uploaded successfully: ' . self::PREFIX . 'up-1.txt', $this->notice( $second ) );
		$this->assertSame( 'private', self::fixture( 'where', self::PREFIX . 'up-1.txt' )['where'] );
	}

	public function test_upload_refuses_disallowed_types(): void {
		$response = $this->upload( $this->admin(), self::PREFIX . 'shell.php', '<?php echo 1;' );

		$this->assertStringContainsString( 'File type not allowed. Allowed types: pdf, zip, doc, docx, xls, xlsx, ppt, pptx, jpg, jpeg, png, gif, txt, mp3, mp4', $this->notice( $response ) );
		$this->assertSame( 'none', self::fixture( 'where', self::PREFIX . 'shell.php' )['where'] );
	}

	public function test_upload_with_a_bad_nonce_is_rejected(): void {
		$response = $this->upload( $this->admin(), self::PREFIX . 'nonce.txt', 'x', 'deadbeef00' );

		// The admin header is already sent when the check runs, so the status stays 200.
		$this->assertStringContainsString( 'The link you followed has expired.', $response->body );
		$this->assertSame( 'none', self::fixture( 'where', self::PREFIX . 'nonce.txt' )['where'] );
	}

	public function test_delete_removes_an_unused_file(): void {
		$response = $this->delete( $this->admin(), self::PREFIX . 'free.txt' );

		$this->assertStringContainsString( 'File deleted successfully: ' . self::PREFIX . 'free.txt', $this->notice( $response ) );
		$this->assertSame( 'none', self::fixture( 'where', self::PREFIX . 'free.txt' )['where'] );
	}

	public function test_delete_keeps_a_file_that_has_downloads(): void {
		$response = $this->delete( $this->admin(), self::PREFIX . 'used.txt' );

		$this->assertStringContainsString( 'Cannot delete file. It has been used in 1 downloads.', $this->notice( $response ) );
		$this->assertSame( 'private', self::fixture( 'where', self::PREFIX . 'used.txt' )['where'] );
	}

	public function test_delete_reports_a_missing_file(): void {
		$this->assertStringContainsString( 'File not found.', $this->notice( $this->delete( $this->admin(), self::PREFIX . 'nope.txt' ) ) );
	}

	public function test_ajax_delete_endpoint(): void {
		$admin = $this->admin();
		$nonce = (string) $this->input_value( $admin->get( self::URL )->body, 'delete_nonce' );

		$json = $admin->post( '/wp-admin/admin-ajax.php', array( 'action' => 'lead_magnet_delete_file', 'delete_nonce' => $nonce, 'filename' => self::PREFIX . 'b.txt' ) )->json();

		$this->assertTrue( $json['success'] );
		$this->assertSame( 'File deleted successfully: ' . self::PREFIX . 'b.txt', $json['data']['message'] );
		$this->assertSame( 'none', self::fixture( 'where', self::PREFIX . 'b.txt' )['where'] );
	}

	public function test_files_added_to_the_legacy_folder_are_moved_to_private_storage(): void {
		self::fixture( 'option', 'leadmalo_legacy_field_until', '__delete__' );
		self::fixture( 'put-file', 'legacy', self::PREFIX . 'ftp.txt', 'added by FTP' );

		$html = $this->admin()->get( self::URL )->body;

		$this->assertSame( 'private', self::fixture( 'where', self::PREFIX . 'ftp.txt' )['where'] );
		$this->assertStringContainsString( '[lead_magnet file="' . self::PREFIX . 'ftp.txt"]', $html );
		$this->assertNull( self::fixture( 'option', 'leadmalo_legacy_field_until' )['value'], 'A later move must not reopen the legacy window.' );
	}

	public function test_the_first_migration_after_the_upgrade_opens_the_legacy_window(): void {
		// State of a site upgrading from 1.2.x: files in the legacy folder, never migrated.
		// The "migrated" flag goes last: every fixture call boots WordPress, which would migrate.
		self::fixture( 'put-file', 'legacy', self::PREFIX . 'upgrade.txt', 'from 1.2.x' );
		self::fixture( 'option', 'leadmalo_legacy_field_until', '__delete__' );
		self::fixture( 'option', 'leadmalo_storage_migrated', '__delete__' );

		$this->visitor()->get( $this->page( 'single' ) ); // Any request runs the migration on 'init'.

		$this->assertSame( 'private', self::fixture( 'where', self::PREFIX . 'upgrade.txt' )['where'] );
		$this->assertSame( '1', self::fixture( 'option', 'leadmalo_storage_migrated' )['value'] );
		$until = (int) self::fixture( 'option', 'leadmalo_legacy_field_until' )['value'];
		$this->assertGreaterThan( time() + 29 * 86400, $until );
		$this->assertLessThanOrEqual( time() + 30 * 86400, $until );
	}

	public function test_a_file_that_cannot_be_moved_stays_put_and_raises_a_notice(): void {
		self::fixture( 'put-file', 'private', self::PREFIX . 'clash.txt', 'private copy' );
		self::fixture( 'put-file', 'legacy', self::PREFIX . 'clash.txt', 'legacy copy' );

		$html = $this->admin()->get( self::URL )->body;

		$this->assertStringContainsString( 'could not be moved to protected storage', $html );
		$this->assertStringContainsString( '<code>' . self::PREFIX . 'clash.txt</code>', $html );
		$this->assertSame( 'private', self::fixture( 'where', self::PREFIX . 'clash.txt' )['where'] );
	}

	/**
	 * Upload a file through the dashboard form.
	 *
	 * @param Http        $http    Client.
	 * @param string      $name    File name.
	 * @param string      $content Content.
	 * @param string|null $nonce   Nonce (default: read from the page).
	 * @return Response
	 */
	private function upload( Http $http, string $name, string $content, ?string $nonce = null ): Response {
		$tmp = (string) tempnam( sys_get_temp_dir(), 'lml-e2e-up' );
		file_put_contents( $tmp, $content );

		try {
			return $http->post(
				self::URL,
				array(
					'upload_nonce'     => $nonce ?? (string) $this->input_value( $http->get( self::URL )->body, 'upload_nonce' ),
					'upload_file'      => 'Upload File',
					'lead_magnet_file' => new \CURLFile( $tmp, 'application/octet-stream', $name ),
				)
			);
		} finally {
			unlink( $tmp );
		}
	}

	/**
	 * Delete a file through the dashboard form.
	 *
	 * @param Http   $http Client.
	 * @param string $name File name.
	 * @return Response
	 */
	private function delete( Http $http, string $name ): Response {
		return $http->post(
			self::URL,
			array(
				'delete_nonce'   => (string) $this->input_value( $http->get( self::URL )->body, 'delete_nonce' ),
				'file_to_delete' => $name,
				'delete_file'    => 'Delete',
			)
		);
	}

	/**
	 * Text of the plugin's notice on the dashboard.
	 *
	 * @param Response $response Response.
	 * @return string
	 */
	private function notice( Response $response ): string {
		$wrap    = $this->elements( $response->body, 'lead-magnet-locker' );
		$notices = $wrap ? $this->elements( $wrap[0], 'notice-info' ) : array();

		return $notices ? html_entity_decode( trim( strip_tags( $notices[0] ) ), ENT_QUOTES ) : '';
	}
}
