<?php
/**
 * UploadFile, DeleteFile, ListFiles and ResolveDownload use cases.
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Tests\Unit\Application;

use LeadMagnet\Locker\Application\DeleteFile;
use LeadMagnet\Locker\Application\ListFiles;
use LeadMagnet\Locker\Application\Outcome;
use LeadMagnet\Locker\Application\ResolveDownload;
use LeadMagnet\Locker\Application\UploadedFile;
use LeadMagnet\Locker\Application\UploadFile;
use LeadMagnet\Locker\Tests\Unit\Application\Fake\FakeStorage;
use LeadMagnet\Locker\Tests\Unit\Application\Fake\InMemoryRecords;
use PHPUnit\Framework\TestCase;

/**
 * File use case tests.
 */
final class FileUseCasesTest extends TestCase {

	private FakeStorage $files;
	private InMemoryRecords $records;

	protected function setUp(): void {
		$this->files        = new FakeStorage();
		$this->files->files = array( 'guide.pdf' => 100, 'used.txt' => 5 );
		$this->records      = new InMemoryRecords();
		$this->records->create( 'a@example.org', 'used.txt', 'KEYUSED' );
	}

	private function upload( string $name = 'new.pdf', int $size = 10, int $error = UPLOAD_ERR_OK, bool $is_uploaded = true ): UploadedFile {
		return new UploadedFile( $name, '/tmp/php123', $size, $error, $is_uploaded, 'application/pdf' );
	}

	public function test_a_valid_upload_is_stored(): void {
		$outcome = ( new UploadFile( $this->files ) )->execute( $this->upload(), 100 );

		$this->assertTrue( $outcome->is_success() );
		$this->assertSame( 'new.pdf', $outcome->string( 'name' ) );
		$this->assertCount( 1, $this->files->stored );
	}

	public function test_upload_checks_run_in_order_and_store_nothing(): void {
		$upload = new UploadFile( $this->files );

		$this->assertSame( array( UploadFile::UPLOAD_ERROR, UPLOAD_ERR_PARTIAL ), $this->code( $upload->execute( $this->upload( 'x.php', 999, UPLOAD_ERR_PARTIAL ), 100 ), 'code' ) );
		$this->assertSame( UploadFile::INVALID_PAYLOAD, $upload->execute( $this->upload( '' ), 100 )->error );
		$this->assertSame( UploadFile::INVALID_PAYLOAD, $upload->execute( $this->upload( 'new.pdf', 10, UPLOAD_ERR_OK, false ), 100 )->error );
		$this->assertSame( UploadFile::TYPE_NOT_ALLOWED, $upload->execute( $this->upload( 'shell.php', 999 ), 100 )->error );
		$this->assertSame( array( UploadFile::TOO_LARGE, 100 ), $this->code( $upload->execute( $this->upload( 'big.pdf', 101 ), 100 ), 'max_size' ) );
		$this->assertSame( array(), $this->files->stored );
	}

	public function test_a_refused_upload_passes_the_storage_message_on(): void {
		$this->files->store_result = Outcome::failure( 'rejected', array( 'message' => 'Sorry, not allowed.' ) );

		$this->assertSame( array( UploadFile::REJECTED, 'Sorry, not allowed.' ), $this->code( ( new UploadFile( $this->files ) )->execute( $this->upload(), 100 ), 'message' ) );
	}

	public function test_delete_rules(): void {
		$delete = new DeleteFile( $this->files, $this->records );

		$this->assertSame( DeleteFile::INVALID_NAME, $delete->execute( '' )->error );
		$this->assertSame( DeleteFile::NOT_FOUND, $delete->execute( 'nope.pdf' )->error );
		$this->assertSame( array( DeleteFile::IN_USE, 1 ), $this->code( $delete->execute( 'used.txt' ), 'count' ) );
		$this->assertArrayHasKey( 'used.txt', $this->files->files );

		$this->files->delete_fails = true;
		$this->assertSame( DeleteFile::FAILED, $delete->execute( 'guide.pdf' )->error );

		$this->files->delete_fails = false;
		$this->assertTrue( $delete->execute( 'guide.pdf' )->is_success() );
		$this->assertArrayNotHasKey( 'guide.pdf', $this->files->files );
	}

	public function test_listing_protects_unprotected_files_first(): void {
		$this->files->unprotected = array( 'ftp.txt' );

		$names = array_map( static fn( $file ) => $file->name, ( new ListFiles( $this->files ) )->execute() );

		$this->assertSame( array( 'guide.pdf', 'used.txt', 'ftp.txt' ), $names );
		$this->assertSame( array(), $this->files->unprotected );
	}

	public function test_visitor_downloads_are_marked_admin_downloads_are_not(): void {
		$resolve = new ResolveDownload( $this->records, $this->files );

		$admin = $resolve->for_admin( 1 );
		$this->assertSame( '/storage/used.txt', $admin->string( 'path' ) );
		$this->assertNull( $this->records->find( 1 )?->downloaded_at );

		$visitor = $resolve->for_visitor( 'KEYUSED' );
		$this->assertSame( '/storage/used.txt', $visitor->string( 'path' ) );
		$this->assertNotNull( $this->records->find( 1 )?->downloaded_at );
	}

	public function test_downloads_of_unknown_records_or_missing_files_fail(): void {
		$resolve = new ResolveDownload( $this->records, $this->files );
		$this->records->create( 'b@example.org', 'gone.pdf', 'KEYGONE' );

		$this->assertSame( ResolveDownload::RECORD_NOT_FOUND, $resolve->for_visitor( 'NOPE' )->error );
		$this->assertSame( ResolveDownload::RECORD_NOT_FOUND, $resolve->for_admin( 99 )->error );
		$this->assertSame( ResolveDownload::FILE_NOT_FOUND, $resolve->for_visitor( 'KEYGONE' )->error );
		$this->assertNull( $this->records->find( 2 )?->downloaded_at, 'A failed download is not marked.' );
	}

	/**
	 * Error code and one data value of an outcome.
	 *
	 * @param Outcome $outcome Outcome.
	 * @param string  $key     Data key.
	 * @return array{string|null, int|string}
	 */
	private function code( Outcome $outcome, string $key ): array {
		return array( $outcome->error, is_int( $outcome->data[ $key ] ?? null ) ? $outcome->int( $key ) : $outcome->string( $key ) );
	}
}
