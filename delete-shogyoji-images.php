<?php

require_once __DIR__ . '/vendor/autoload.php';

use KishukushaReportSupporter\JsonDatabase;
use KishukushaReportSupporter\LogDatabase;
use KishukushaReportSupporter\Forms\Shogyoji;

$database = new JsonDatabase(MAIN_TABLE_NAME);
$logDatabase = new LogDatabase(LOG_TABLE_NAME);
Shogyoji::deleteShogyojiImage($database, $logDatabase);
