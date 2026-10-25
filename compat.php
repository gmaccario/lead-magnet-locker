<?php
/**
 * Class names of earlier versions, kept as aliases.
 *
 * Code written against 1.1/1.2 (e.g. class_exists() checks) keeps finding them.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

use LeadMagnet\Locker\Plugin;
use LeadMagnet\Locker\Presentation\DownloadEndpoint;
use LeadMagnet\Locker\Presentation\FileActions;
use LeadMagnet\Locker\Presentation\HistoryExport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

foreach (
	array(
		'LeadMagnet\Locker\Application\LeadMagnetLocker' => Plugin::class,
		'LeadMagnet\Locker\Infrastructure\LeadMagnetLockerPluginBootstrap' => Plugin::class,
		'LeadMagnet\Locker\Infrastructure\LeadMagnetLockerDownloader' => DownloadEndpoint::class,
		'LeadMagnet\Locker\Infrastructure\LeadMagnetLockerFileUploader' => FileActions::class,
		'LeadMagnet\Locker\Infrastructure\LeadMagnetLockerHistoryExporter' => HistoryExport::class,
	) as $lml_old_name => $lml_new_name
) {
	class_alias( $lml_new_name, $lml_old_name );
}
unset( $lml_old_name, $lml_new_name );
