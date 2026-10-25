<?php
/**
 * SubmitEmail use case.
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Tests\Unit\Application;

use LeadMagnet\Locker\Application\SubmitEmail;
use LeadMagnet\Locker\Domain\Schedule;
use LeadMagnet\Locker\Tests\Unit\Application\Fake\FakeSettings;
use LeadMagnet\Locker\Tests\Unit\Application\Fake\FakeStorage;
use LeadMagnet\Locker\Tests\Unit\Application\Fake\InMemoryRecords;
use PHPUnit\Framework\TestCase;

/**
 * SubmitEmail tests.
 */
final class SubmitEmailTest extends TestCase {

	private FakeSettings $settings;
	private FakeStorage $files;
	private InMemoryRecords $records;
	private SubmitEmail $submit;

	protected function setUp(): void {
		$this->settings       = new FakeSettings();
		$this->files          = new FakeStorage();
		$this->files->files   = array( 'guide.pdf' => 100 );
		$this->records        = new InMemoryRecords();
		$this->submit         = new SubmitEmail( $this->settings, $this->settings, $this->settings, $this->files, $this->records );
	}

	public function test_a_valid_token_stores_a_record_and_returns_its_key(): void {
		$outcome = $this->submit->execute( 'a@example.org', md5( 'guide.pdf' ) );

		$this->assertTrue( $outcome->is_success() );
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9]{32}$/', $outcome->string( 'download_key' ) );
		$record = $this->records->find_by_key( $outcome->string( 'download_key' ) );
		$this->assertNotNull( $record );
		$this->assertSame( 'a@example.org', $record->email );
		$this->assertSame( 'guide.pdf', $record->file_name );
	}

	public function test_a_closed_schedule_comes_first(): void {
		$this->settings->schedule = new Schedule( true, '2020-01-01', '2020-01-02', '00:00', '23:59', 'UTC' );

		$this->assertSame( SubmitEmail::UNAVAILABLE, $this->submit->execute( 'not-an-email', '' )->error );
		$this->assertSame( array(), $this->records->records );
	}

	public function test_the_email_is_checked_before_the_file(): void {
		$this->assertSame( SubmitEmail::INVALID_EMAIL, $this->submit->execute( 'not-an-email', 'unknown' )->error );
	}

	public function test_unknown_tokens_and_missing_files_are_not_found(): void {
		$this->assertSame( SubmitEmail::FILE_NOT_FOUND, $this->submit->execute( 'a@example.org', str_repeat( 'a', 32 ) )->error );
		$this->assertSame( SubmitEmail::FILE_NOT_FOUND, $this->submit->execute( 'a@example.org', '' )->error );
		$this->assertSame( SubmitEmail::FILE_NOT_FOUND, $this->submit->execute( 'a@example.org', '', '' )->error );
		$this->assertSame( SubmitEmail::FILE_NOT_FOUND, $this->submit->execute( 'a@example.org', '', 'other.pdf' )->error );
	}

	public function test_the_legacy_name_is_used_only_without_a_token(): void {
		$this->assertTrue( $this->submit->execute( 'a@example.org', '', 'guide.pdf' )->is_success() );
		$this->assertSame( SubmitEmail::FILE_NOT_FOUND, $this->submit->execute( 'a@example.org', 'bad-token', 'guide.pdf' )->error );
	}

	public function test_a_storage_failure_is_reported(): void {
		$this->records->fail = true;

		$this->assertSame( SubmitEmail::STORAGE_ERROR, $this->submit->execute( 'a@example.org', md5( 'guide.pdf' ) )->error );
	}
}
