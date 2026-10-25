<?php
/**
 * History page: search, pagination, CSV export, admin download, record delete.
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Tests\E2E;

use LeadMagnet\Locker\Tests\E2E\Support\Http;
use LeadMagnet\Locker\Tests\E2E\Support\TestCase;

/**
 * History tests.
 */
final class HistoryTest extends TestCase {

	private const URL = '/wp-admin/admin.php?page=lead-magnet-history';

	public function test_history_and_export_snapshots(): void {
		$admin = $this->admin();
		$html  = $admin->get( self::URL )->body;
		$wrap  = $this->elements( $html, 'lead-magnet-locker' );

		$this->assertCount( 1, $wrap );
		$this->assertMatchesSnapshot( 'admin-history', $wrap[0] );

		$csv = $admin->get( $this->export_url( $html ) )->body;
		$this->assertMatchesSnapshot( 'admin-export', $this->without_test_rows( $csv ) );
	}

	public function test_records_are_listed_newest_first(): void {
		$this->record( 'first' );
		$this->record( 'second' );

		$html = $this->admin()->get( self::URL )->body;

		$this->assertStringContainsString( 'second' . self::EMAIL, $html );
		$this->assertLessThan( strpos( $html, 'first' . self::EMAIL ), strpos( $html, 'second' . self::EMAIL ) );
		$this->assertStringContainsString( '⏳ Pending', $html );
	}

	public function test_search_needs_the_nonce_and_matches_literally(): void {
		$this->record( 'needle' );
		$this->record( 'other' );
		$admin = $this->admin();
		$nonce = (string) $this->input_value( $admin->get( self::URL )->body, '_wpnonce' );

		$found = $admin->get( self::URL . '&_wpnonce=' . $nonce . '&s=needle' )->body;
		$this->assertStringContainsString( 'needle' . self::EMAIL, $found );
		$this->assertStringNotContainsString( 'other' . self::EMAIL, $found );
		$this->assertStringContainsString( 'Export results (CSV)', $found );

		$ignored = $admin->get( self::URL . '&s=needle' )->body;
		$this->assertStringContainsString( 'other' . self::EMAIL, $ignored, 'Without a nonce the search is ignored.' );
		$this->assertStringContainsString( 'Export all (CSV)', $ignored );

		$this->assertStringContainsString( 'No records found.', $admin->get( self::URL . '&_wpnonce=' . $nonce . '&s=%25%25' )->body );
	}

	public function test_export_downloads_a_csv_and_guards_against_formulas(): void {
		$this->record( '=formula' );
		$this->record( 'plain' );
		$admin    = $this->admin();
		$response = $admin->get( $this->export_url( $admin->get( self::URL )->body ) );

		$this->assertSame( 200, $response->status );
		$this->assertSame( 'text/csv; charset=utf-8', $response->header( 'content-type' ) );
		$this->assertMatchesRegularExpression( '/^attachment; filename="lead-magnet-history-\d{4}-\d{2}-\d{2}-\d{6}\.csv"$/', (string) $response->header( 'content-disposition' ) );
		$this->assertStringStartsWith( "\xEF\xBB\xBFID,Email,File,Submitted,Downloaded,Status\n", $response->body );
		$this->assertStringContainsString( ",'=formula" . self::EMAIL . ',' . self::PREFIX . 'a.pdf,', $response->body );
		$this->assertMatchesRegularExpression( '/,plain@lml-e2e\.test,lml-e2e-a\.pdf,"\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}",,Pending\n/', $response->body );
	}

	public function test_export_of_search_results_only_contains_matches(): void {
		$this->record( 'alpha' );
		$this->record( 'beta' );
		$admin = $this->admin();
		$nonce = (string) $this->input_value( $admin->get( self::URL )->body, '_wpnonce' );
		$html  = $admin->get( self::URL . '&_wpnonce=' . $nonce . '&s=' . rawurlencode( 'alpha' . self::EMAIL ) )->body;

		$csv = $admin->get( $this->export_url( $html ) )->body;

		$this->assertStringContainsString( 'alpha' . self::EMAIL, $csv );
		$this->assertStringNotContainsString( 'beta' . self::EMAIL, $csv );
	}

	public function test_export_is_protected(): void {
		$this->assertSame( 403, $this->admin()->get( '/wp-admin/admin-post.php?action=leadmalo_export_history&_wpnonce=deadbeef00' )->status );

		$subscriber = $this->subscriber()->get( '/wp-admin/admin-post.php?action=leadmalo_export_history' );
		$this->assertStringContainsString( 'Insufficient permissions.', $subscriber->body );
		$this->assertStringNotContainsString( 'Email,File', $subscriber->body );
	}

	public function test_admin_download_serves_the_file_without_marking_it_downloaded(): void {
		$key   = $this->record( 'admin-download' );
		$admin = $this->admin();
		$html  = $admin->get( self::URL )->body;
		$id    = (string) self::fixture( 'record', $key )['record']['id'];

		$this->assertMatchesRegularExpression( '/admin-post\.php\?action=leadmalo_admin_download(?:&amp;|&#038;)record_id=' . $id . '(?:&amp;|&#038;)_wpnonce=[0-9a-f]{10}/', $html );
		preg_match( '/admin-post\.php\?action=leadmalo_admin_download(?:&amp;|&#038;)record_id=' . $id . '(?:&amp;|&#038;)_wpnonce=([0-9a-f]{10})/', $html, $match );

		$response = $admin->get( '/wp-admin/admin-post.php?action=leadmalo_admin_download&record_id=' . $id . '&_wpnonce=' . $match[1] );
		$this->assertSame( 200, $response->status );
		$this->assertSame( self::$fixture['files'][ self::PREFIX . 'a.pdf' ], $response->body );
		$this->assertNull( self::fixture( 'record', $key )['record']['downloaded_at'] );

		$this->assertSame( 403, $admin->get( '/wp-admin/admin-post.php?action=leadmalo_admin_download&record_id=' . $id . '&_wpnonce=deadbeef00' )->status );
		$this->assertStringContainsString( 'Insufficient permissions.', $this->subscriber()->get( '/wp-admin/admin-post.php?action=leadmalo_admin_download&record_id=' . $id )->body );
	}

	public function test_records_can_be_deleted_over_ajax(): void {
		$key   = $this->record( 'delete-me' );
		$id    = (string) self::fixture( 'record', $key )['record']['id'];
		$admin = $this->admin();
		$nonce = (string) $this->localized( $admin->get( self::URL )->body, 'lead_magnet_admin_ajax' )['nonce'];

		$bad = $this->delete_record( $admin, $id, 'deadbeef00' );
		$this->assertSame( array( 'success' => false, 'data' => array( 'message' => 'Security check failed' ) ), $bad );

		$invalid = $this->delete_record( $admin, '0', $nonce );
		$this->assertSame( array( 'success' => false, 'data' => array( 'message' => 'Invalid record ID' ) ), $invalid );

		$ok = $this->delete_record( $admin, $id, $nonce );
		$this->assertSame( array( 'success' => true, 'data' => array( 'message' => 'Record deleted successfully' ) ), $ok );
		$this->assertNull( self::fixture( 'record', $key )['record'] );
	}

	/**
	 * Submit the public form to create a record.
	 *
	 * @param string $local Local part of the email.
	 * @return string Download key.
	 */
	private function record( string $local ): string {
		$json = $this->submit( $this->visitor(), array( 'email' => $local . self::EMAIL, 'lmf' => self::$fixture['tokens']['a'] ) )->json();
		$this->assertTrue( $json['success'], 'Could not create a record for ' . $local );
		preg_match( '/lead_magnet_download=([A-Za-z0-9]{32})/', $json['data']['download_url'], $match );

		return $match[1];
	}

	/**
	 * Export URL from the history page.
	 *
	 * @param string $html History page HTML.
	 * @return string
	 */
	private function export_url( string $html ): string {
		$this->assertMatchesRegularExpression( '/href="([^"]*action=leadmalo_export_history[^"]*)"/', $html );
		preg_match( '/href="([^"]*action=leadmalo_export_history[^"]*)"/', $html, $match );

		return html_entity_decode( $match[1] );
	}

	/**
	 * Delete a record through admin-ajax.php.
	 *
	 * @param Http   $http  Client.
	 * @param string $id    Record ID.
	 * @param string $nonce Nonce.
	 * @return mixed
	 */
	private function delete_record( Http $http, string $id, string $nonce ): mixed {
		return $http->post( '/wp-admin/admin-ajax.php', array( 'action' => 'leadmalo_delete_download_record', 'record_id' => $id, 'nonce' => $nonce ) )->json();
	}

	/**
	 * CSV without the rows created by the tests.
	 *
	 * @param string $csv CSV.
	 * @return string
	 */
	private function without_test_rows( string $csv ): string {
		return implode( "\n", array_filter( explode( "\n", $csv ), static fn( string $line ): bool => ! str_contains( $line, self::EMAIL ) ) );
	}
}
