<?php
/**
 * Settings page and the visibility schedule.
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Tests\E2E;

use LeadMagnet\Locker\Tests\E2E\Support\Http;
use LeadMagnet\Locker\Tests\E2E\Support\Response;
use LeadMagnet\Locker\Tests\E2E\Support\TestCase;

/**
 * Settings tests.
 */
final class SettingsTest extends TestCase {

	private const URL = '/wp-admin/admin.php?page=lead-magnet-settings';

	public function test_settings_snapshot(): void {
		$wrap = $this->elements( $this->admin()->get( self::URL )->body, 'lml-settings' );

		$this->assertCount( 1, $wrap );
		$this->assertStringContainsString( 'Forms are currently visible', $wrap[0] );
		$this->assertStringContainsString( '<option value="UTC" selected>UTC</option>', $wrap[0] );
		$this->assertMatchesSnapshot( 'admin-settings', $wrap[0] );
	}

	public function test_a_window_in_the_past_hides_the_forms(): void {
		$response = $this->save( $this->admin(), array( 'enable_date_trigger' => '1', 'start_date' => '2020-01-01', 'end_date' => '2020-01-02', 'start_time' => '00:00', 'end_time' => '23:59', 'timezone' => 'UTC' ) );

		$this->assertStringContainsString( 'Settings saved successfully!', $response->body );
		$this->assertStringContainsString( 'Forms are currently hidden', $response->body );

		$boxes = $this->elements( $this->visitor()->get( $this->page( 'single' ) )->body, 'lead-magnet-unavailable' );
		$this->assertCount( 1, $boxes );
		$this->assertStringContainsString( 'This download is currently unavailable.', $boxes[0] );
		$this->assertMatchesSnapshot( 'front-unavailable', $boxes[0] );

		$json = $this->submit( $this->visitor(), array( 'email' => 'hidden' . self::EMAIL, 'lmf' => self::$fixture['tokens']['a'] ) )->json();
		$this->assertFalse( $json['success'] );
		$this->assertSame( 'This download is currently unavailable.', $json['data']['message'] );
	}

	public function test_a_window_around_now_shows_the_forms(): void {
		$response = $this->save(
			$this->admin(),
			array(
				'enable_date_trigger' => '1',
				'start_date'          => gmdate( 'Y-m-d', time() - 2 * 86400 ),
				'end_date'            => gmdate( 'Y-m-d', time() + 2 * 86400 ),
				'start_time'          => '00:00',
				'end_time'            => '23:59',
				'timezone'            => 'Europe/Rome',
			)
		);

		$this->assertStringContainsString( 'Forms are currently visible', $response->body );
		$this->assertCount( 1, $this->elements( $this->visitor()->get( $this->page( 'single' ) )->body, 'lead-magnet-form' ) );

		$settings = self::fixture( 'settings' );
		$this->assertSame( '1', $settings['enable_date_trigger']['setting_value'] );
		$this->assertSame( 'Europe/Rome', $settings['timezone']['setting_value'] );
	}

	public function test_dates_without_an_end_keep_the_forms_visible(): void {
		$this->save( $this->admin(), array( 'enable_date_trigger' => '1', 'start_date' => '2020-01-01', 'end_date' => '', 'start_time' => '00:00', 'end_time' => '23:59', 'timezone' => 'UTC' ) );

		$this->assertCount( 1, $this->elements( $this->visitor()->get( $this->page( 'single' ) )->body, 'lead-magnet-form' ) );
	}

	public function test_an_unknown_timezone_falls_back_to_utc(): void {
		$this->save( $this->admin(), array( 'start_date' => '', 'end_date' => '', 'start_time' => '00:00', 'end_time' => '23:59', 'timezone' => 'Mars/Olympus_Mons' ) );

		$settings = self::fixture( 'settings' );
		$this->assertSame( 'UTC', $settings['timezone']['setting_value'] );
		$this->assertSame( '0', $settings['enable_date_trigger']['setting_value'], 'An unticked checkbox disables the trigger.' );
	}

	public function test_saving_without_the_schedule_fields_stores_empty_values(): void {
		$response = $this->save( $this->admin(), array() );

		$this->assertStringContainsString( 'Settings saved successfully!', $response->body );
		$settings = self::fixture( 'settings' );
		foreach ( array( 'start_date', 'end_date', 'start_time', 'end_time' ) as $key ) {
			$this->assertSame( '', $settings[ $key ]['setting_value'], $key );
		}
		$this->assertSame( 'UTC', $settings['timezone']['setting_value'] );
	}

	public function test_a_bad_nonce_does_not_save(): void {
		$admin    = $this->admin();
		$response = $admin->post( self::URL, array( 'submit' => 'Save Changes', 'settings_nonce' => 'deadbeef00', 'timezone' => 'Europe/Rome' ) );

		$this->assertStringContainsString( 'Security check failed.', $response->body );
		$this->assertSame( 'UTC', self::fixture( 'settings' )['timezone']['setting_value'] );
	}

	/**
	 * Save the settings form.
	 *
	 * @param Http                  $http   Client.
	 * @param array<string, string> $fields Fields.
	 * @return Response
	 */
	private function save( Http $http, array $fields ): Response {
		$nonce = (string) $this->input_value( $http->get( self::URL )->body, 'settings_nonce' );

		return $http->post( self::URL, array( 'submit' => 'Save Changes', 'settings_nonce' => $nonce ) + $fields );
	}
}
