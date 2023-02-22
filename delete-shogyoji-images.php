<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

// Google関連
$googleClient = new Google_Client();
$googleClient->setScopes([
    Google_Service_Sheets::DRIVE, // ドライブ
]);
$googleClient->setAuthConfig(CREDENTIALS_PATH);
$drive = new Google_Service_Drive($googleClient);

// データベース関連
$database = new JsonDatabase(MAIN_TABLE_NAME);
$shogyojiImages = $database->restore('shogyojiImages') ?? [];

// 昨日の0:00より前のイベントの写真を削除
$yesterday = time() - 60 * 60 * 24;
$yesterday = strtotime(date('Y/m/d', $yesterday));
$deleted = 'Nothing';
$failed = '';
foreach ($shogyojiImages as $eventDate => $ids) {
    if (strtotime($eventDate) >= $yesterday) continue;

    foreach ($ids as $i => $id) {
        try {
            $drive->files->delete($id, ['supportsAllDrives' => true]);
            // $drive->files->trash($id, ['supportsAllDrives' => true]); // ゴミ箱に移動するだけ

            if ($i === 0) {
                $deleted = '';
            } else if ($i < count($ids) - 1) {
                $deleted .= ', ';
            } else {
                $deleted .= ' and ';
            }

            $deleted .= "https://drive.google.com/file/d/{$id}/view?usp=sharing";
        } catch (Throwable $e) {
            if ($failed !== '') $failed .= "\n";
            $failed .= "An error occurred. Please delete https://drive.google.com/file/d/{$id}/view?usp=sharing manually.\nError Message:\n{$e}";
        }
    }
    unset($shogyojiImages[$eventDate]);
}
$database->store('shogyojiImages', $shogyojiImages);

// ログの記録
$logDb = new LogDatabase(LOG_TABLE_NAME);
if ($failed !== '') $logDb->log('delete-shogyoji-images: ' . $failed);

$logDb->log('delete-shogyoji-images: Deleted ' . $deleted);
