<?php
/**
 * Shortcode output on the front end.
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Tests\E2E;

use LeadMagnet\Locker\Tests\E2E\Support\TestCase;

/**
 * Front-end tests.
 */
final class FrontendTest extends TestCase {

	public function test_single_box_renders_the_form_with_a_token_and_no_file_name(): void {
		$html  = $this->visitor()->get( $this->page( 'single' ) )->body;
		$boxes = $this->elements( $html, 'lead-magnet-form' );

		$this->assertCount( 1, $boxes );
		$this->assertStringContainsString( '<h3>Get Your Free Download</h3>', $boxes[0] );
		$this->assertStringContainsString( 'Enter your email address to receive your free download.', $boxes[0] );
		$this->assertStringContainsString( 'Get Download Link</button>', $boxes[0] );
		$this->assertSame( self::$fixture['tokens']['a'], $this->input_value( $boxes[0], 'lmf' ) );
		$this->assertStringNotContainsString( self::PREFIX . 'a.pdf', $html, 'The file name must not appear in the page.' );
		$this->assertStringNotContainsString( 'name="file"', $boxes[0] );
		$this->assertMatchesSnapshot( 'front-single-box', $boxes[0] );
	}

	public function test_every_box_on_a_page_is_rendered(): void {
		$boxes = $this->elements( $this->visitor()->get( $this->page( 'double' ) )->body, 'lead-magnet-form' );

		$this->assertCount( 2, $boxes );
		$this->assertSame( self::$fixture['tokens']['a'], $this->input_value( $boxes[0], 'lmf' ) );
		$this->assertSame( self::$fixture['tokens']['b'], $this->input_value( $boxes[1], 'lmf' ) );
		$this->assertStringContainsString( '<h3>Second box</h3>', $boxes[1] );
		preg_match_all( '/id="(lead-magnet-[0-9a-f]+)"/', $boxes[0] . $boxes[1], $ids );
		$this->assertCount( 2, array_unique( $ids[1] ), 'Each box needs its own id.' );
	}

	public function test_custom_attributes_are_escaped(): void {
		$boxes = $this->elements( $this->visitor()->get( $this->page( 'custom' ) )->body, 'lead-magnet-form' );

		$this->assertCount( 1, $boxes );
		$this->assertStringNotContainsString( '<b>', $boxes[0] );
		$this->assertStringContainsString( 'Send it</button>', $boxes[0] );
		$this->assertMatchesSnapshot( 'front-custom-box', $boxes[0] );
	}

	/**
	 * @dataProvider broken_pages
	 */
	public function test_visitors_see_nothing_for_a_broken_shortcode( string $page ): void {
		$html = $this->visitor()->get( $this->page( $page ) )->body;

		$this->assertSame( array(), $this->elements( $html, 'lead-magnet-form' ) );
		$this->assertSame( array(), $this->elements( $html, 'lead-magnet-error-admin' ) );
		$this->assertStringNotContainsString( 'File not found', $html );
		$this->assertStringNotContainsString( 'Please specify a file', $html );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function broken_pages(): array {
		return array(
			'missing file'   => array( 'missing' ),
			'no file'        => array( 'empty' ),
			'path traversal' => array( 'escape' ),
		);
	}

	public function test_administrators_see_why_a_shortcode_is_broken(): void {
		$admin = $this->admin();

		$missing = $this->elements( $admin->get( $this->page( 'missing' ) )->body, 'lead-magnet-error-admin' );
		$this->assertCount( 1, $missing );
		$this->assertStringContainsString( 'File not found. Please upload the file first from the WordPress admin dashboard.', $missing[0] );
		$this->assertStringContainsString( '(Only administrators see this message.)', $missing[0] );
		$this->assertMatchesSnapshot( 'front-admin-error', $missing[0] );

		$empty = $this->elements( $admin->get( $this->page( 'empty' ) )->body, 'lead-magnet-error-admin' );
		$this->assertCount( 1, $empty );
		$this->assertStringContainsString( 'Please specify a file in the shortcode.', $empty[0] );

		$this->assertCount( 1, $this->elements( $admin->get( $this->page( 'escape' ) )->body, 'lead-magnet-error-admin' ) );
	}

	public function test_subscribers_see_nothing_for_a_broken_shortcode(): void {
		$html = $this->subscriber()->get( $this->page( 'missing' ) )->body;

		$this->assertSame( array(), $this->elements( $html, 'lead-magnet-error-admin' ) );
	}

	public function test_public_assets_and_ajax_settings_are_printed(): void {
		$html = $this->visitor()->get( $this->page( 'single' ) )->body;

		$this->assertMatchesRegularExpression( '#lead-magnet-locker/views/public/assets/js/public\.js\?ver=#', $html );
		$this->assertMatchesRegularExpression( '#lead-magnet-locker/views/public/assets/css/public\.css\?ver=#', $html );

		$ajax = $this->localized( $html, 'lead_magnet_ajax' );
		$this->assertSame( self::base_url() . '/wp-admin/admin-ajax.php', $ajax['ajax_url'] );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{10}$/', $ajax['nonce'] );
	}

	public function test_theme_helper_reports_whether_a_file_exists(): void {
		$this->assertTrue( self::fixture( 'file-exists', self::PREFIX . 'a.pdf' )['exists'] );
		$this->assertFalse( self::fixture( 'file-exists', self::PREFIX . 'nope.pdf' )['exists'] );
		$this->assertFalse( self::fixture( 'file-exists', '../../../wp-config.php' )['exists'] );
		$this->assertFalse( self::fixture( 'file-exists', '.htaccess' )['exists'] );
	}
}
