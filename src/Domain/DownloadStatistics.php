<?php
/**
 * Dashboard figures.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Domain;

/**
 * Submission and download counts.
 */
final class DownloadStatistics {

	/**
	 * Constructor.
	 *
	 * @param int $total         Submissions.
	 * @param int $unique_emails Distinct email addresses.
	 * @param int $completed     Submissions whose file was downloaded.
	 */
	public function __construct(
		public readonly int $total,
		public readonly int $unique_emails,
		public readonly int $completed
	) {}
}
