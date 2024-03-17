<?php

require_once __DIR__ . '/vendor/autoload.php';

use KishukushaReportSupporter\KishukushaReportSupporter;
use KishukushaReportSupporter\JsonDatabase;

if (!isset($_GET['userId']) || !isset($_GET['filename'])) {
    http_response_code(403);
    exit;
}

$userId = $_GET['userId'];
$filename = $_GET['filename'];

$database = new JsonDatabase(MAIN_TABLE_NAME);
$supporter = new KishukushaReportSupporter($userId, $database);

if (!$supporter->isAccessibleImage($filename)) {
    http_response_code(403);
    exit;
}

header('Content-type: image/jpg');
echo file_get_contents(IMAGE_FOLDER_PATH . $filename);
