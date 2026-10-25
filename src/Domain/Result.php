<?php
/**
 * Outcome of a file operation.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Domain;

/**
 * Success or failure with a plain-text message (escaped by whoever displays it).
 */
final class Result {

	/**
	 * Constructor.
	 *
	 * @param bool   $success Whether the operation succeeded.
	 * @param string $message Plain-text message.
	 */
	private function __construct(
		public readonly bool $success,
		public readonly string $message
	) {}

	/**
	 * A successful result.
	 *
	 * @param string $message Message.
	 * @return self
	 */
	public static function success( string $message ): self {
		return new self( true, $message );
	}

	/**
	 * A failed result.
	 *
	 * @param string $message Message.
	 * @return self
	 */
	public static function failure( string $message ): self {
		return new self( false, $message );
	}

	/**
	 * Array shape returned by the AJAX endpoints since 1.0.
	 *
	 * @return array{success: bool, message: string}
	 */
	public function to_array(): array {
		return array(
			'success' => $this->success,
			'message' => $this->message,
		);
	}
}
