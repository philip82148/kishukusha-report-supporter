<?php

require_once __DIR__ . '/vendor/autoload.php';

use KishukushaReportSupporter\JsonDatabase;
use KishukushaReportSupporter\LogDatabase;
use KishukushaReportSupporter\Forms\Shogyoji;

$logDatabase = new LogDatabase(LOG_TABLE_NAME);
// webhook.phpでincludeされず単体で稼働している場合
if (!isset($database)) {
    $database = new JsonDatabase(MAIN_TABLE_NAME);
    $logDatabase->log('delete-shogyoji-images.php:');
}
Shogyoji::deleteShogyojiImages($database, $logDatabase);
