<?php

require_once __DIR__ . '/vendor/autoload.php';

use KishukushaReportSupporter\JsonDatabase;
use KishukushaReportSupporter\LogDatabase;
use KishukushaReportSupporter\Forms\Shogyoji;

if (ENABLE_LOGGING)
    $logDatabase = new LogDatabase(LOG_TABLE_NAME);

// webhook.phpでincludeされず単体で稼働している場合
if (!isset($database)) {
    $database = new JsonDatabase(MAIN_TABLE_NAME);
    if (ENABLE_LOGGING)
        $logDatabase->log('delete-shogyoji-images.php:');
}

if (ENABLE_LOGGING) {
    Shogyoji::deleteShogyojiImages($database, $logDatabase);
} else {
    Shogyoji::deleteShogyojiImages($database);
}
