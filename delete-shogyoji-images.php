<?php

require_once __DIR__ . '/vendor/autoload.php';

use KishukushaReportSupporter\JsonDatabase;
use KishukushaReportSupporter\LogDatabase;
use KishukushaReportSupporter\Forms\Shogyoji;

// $databaseが存在しない、つまりwebhook.phpでincludeされず単体で稼働している場合は
// 'delete-shogyoji-image.php:'とログに残す
if (!isset($database)) {
    $database = new JsonDatabase(MAIN_TABLE_NAME);
    $logDatabase = new LogDatabase(LOG_TABLE_NAME);
    $logDatabase->log('delete-shogyoji-images.php:');
}
Shogyoji::deleteShogyojiImages($database, $logDatabase);
