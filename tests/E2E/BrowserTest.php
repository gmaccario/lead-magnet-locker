<?php
/**
 * The public form in a real (headless) browser, to cover public.js.
 *
 * Skipped when Chrome is not available. Set LML_E2E_CHROME to its binary.
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Tests\E2E;

use LeadMagnet\Locker\Tests\E2E\Support\TestCase;

/**
 * Browser tests.
 */
final class BrowserTest extends TestCase {

	public function test_submitting_the_form_shows_the_download_link_or_the_error(): void {
		$chrome = getenv( 'LML_E2E_CHROME' ) ? (string) getenv( 'LML_E2E_CHROME' ) : '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
		if ( ! is_executable( $chrome ) ) {
			$this->markTestSkipped( 'Chrome not found; set LML_E2E_CHROME.' );
		}

		$page    = self::fixture( 'js-page' );
		$profile = sys_get_temp_dir() . '/lml-e2e-chrome-' . getmypid();
		exec(
			escapeshellarg( $chrome ) . ' --headless=new --disable-gpu --no-first-run --user-data-dir=' . escapeshellarg( $profile )
				. ' --virtual-time-budget=20000 --dump-dom ' . escapeshellarg( $page['url'] ) . ' 2>/dev/null',
			$output
		);
		exec( 'rm -rf ' . escapeshellarg( $profile ) );
		$dom = implode( "\n", $output );

		$boxes = $this->elements( $dom, 'lead-magnet-form' );
		$this->assertCount( 2, $boxes );

		// First form: success message as text, link with the download URL, form hidden, button restored.
		$success = $this->elements( $boxes[0], 'lead-magnet-success' );
		$this->assertCount( 1, $success, 'No success message. DOM: ' . $boxes[0] );
		$this->assertMatchesRegularExpression( '#^<div class="lead-magnet-message lead-magnet-success">Success! Your download is ready\.<br><a target="_blank" href="[^"]*lead_magnet_download=[A-Za-z0-9]{32}&amp;lmd-nonce=[0-9a-f]{10}">Click here to download</a></div>$#', $success[0] );
		$this->assertStringContainsString( 'style="display: none;"', $boxes[0] );
		$this->assertCount( 1, self::fixture( 'records', 'js' . self::EMAIL ) );

		// Second form: the nonce check answers with plain text; the script shows the generic error.
		$error = $this->elements( $boxes[1], 'lead-magnet-error' );
		$this->assertCount( 1, $error, 'No error message. DOM: ' . $boxes[1] );
		$this->assertStringContainsString( 'An error occurred. Please try again.', $error[0] );
		$this->assertStringContainsString( 'Get Download Link</button>', $boxes[1] );
		$this->assertSame( array(), self::fixture( 'records', 'js2' . self::EMAIL ) );
	}
}
