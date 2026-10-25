<?php
/**
 * HTTP response returned by the E2E client.
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Tests\E2E\Support;

/**
 * Response value object.
 */
final class Response {

	/**
	 * Constructor.
	 *
	 * @param int                          $status  Status code.
	 * @param array<string, list<string>>  $headers Headers, lower-cased names.
	 * @param string                       $body    Body.
	 */
	public function __construct(
		public readonly int $status,
		public readonly array $headers,
		public readonly string $body
	) {}

	/**
	 * Last value of a header, or null.
	 *
	 * @param string $name Header name.
	 * @return string|null
	 */
	public function header( string $name ): ?string {
		$values = $this->headers[ strtolower( $name ) ] ?? array();

		return $values ? (string) end( $values ) : null;
	}

	/**
	 * Body decoded as JSON.
	 *
	 * @return mixed
	 */
	public function json(): mixed {
		return json_decode( $this->body, true, 512, JSON_THROW_ON_ERROR );
	}
}
