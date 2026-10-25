<?php
/**
 * Allowed file types.
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Tests\Unit\Domain;

use LeadMagnet\Locker\Domain\FileTypes;
use PHPUnit\Framework\TestCase;

/**
 * FileTypes tests.
 */
final class FileTypesTest extends TestCase {

	public function test_extensions_keep_the_documented_order(): void {
		$this->assertSame(
			array( 'pdf', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'mp3', 'mp4' ),
			FileTypes::extensions()
		);
	}

	public function test_allowed_types_ignore_case(): void {
		$this->assertTrue( FileTypes::is_allowed( 'guide.PDF' ) );
		$this->assertTrue( FileTypes::is_allowed( 'archive.tar.zip' ) );
		$this->assertFalse( FileTypes::is_allowed( 'shell.php' ) );
		$this->assertFalse( FileTypes::is_allowed( 'shell.pdf.php' ) );
		$this->assertFalse( FileTypes::is_allowed( 'no-extension' ) );
	}

	public function test_mime_types(): void {
		$this->assertSame( 'application/pdf', FileTypes::mime_type( '/path/to/Guide.Pdf' ) );
		$this->assertSame( 'text/plain', FileTypes::mime_type( 'notes.txt' ) );
		$this->assertSame( 'application/octet-stream', FileTypes::mime_type( 'data.csv' ) );
	}
}
