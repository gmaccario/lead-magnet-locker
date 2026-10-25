<?php
/**
 * Result of a use case.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Application;

/**
 * Success with data, or an error code (a constant of the use case) with data.
 *
 * Use cases return codes, not messages: the presentation layer words and translates them.
 */
final class Outcome {

	/**
	 * Constructor.
	 *
	 * @param string|null          $error Error code, or null on success.
	 * @param array<string, mixed> $data  Values for the caller.
	 */
	private function __construct(
		public readonly ?string $error,
		public readonly array $data
	) {}

	/**
	 * Success.
	 *
	 * @param array<string, mixed> $data Values for the caller.
	 * @return self
	 */
	public static function success( array $data = array() ): self {
		return new self( null, $data );
	}

	/**
	 * Failure.
	 *
	 * @param string               $error Error code.
	 * @param array<string, mixed> $data  Values for the caller.
	 * @return self
	 */
	public static function failure( string $error, array $data = array() ): self {
		return new self( $error, $data );
	}

	/**
	 * Whether the use case succeeded.
	 *
	 * @return bool
	 */
	public function is_success(): bool {
		return null === $this->error;
	}

	/**
	 * A data value as a string.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public function string( string $key ): string {
		return is_scalar( $this->data[ $key ] ?? null ) ? (string) $this->data[ $key ] : '';
	}

	/**
	 * A data value as an integer.
	 *
	 * @param string $key Key.
	 * @return int
	 */
	public function int( string $key ): int {
		return is_numeric( $this->data[ $key ] ?? null ) ? (int) $this->data[ $key ] : 0;
	}
}
