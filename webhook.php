<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/kishukusha-form-supporter.php';

// 署名確認
$requestBody = file_get_contents('php://input');
if (!checkSignature($requestBody)) {
    echo 'ここがwebhook.phpです';
    exit;
}

// コネクション切断→既読?
set_time_limit(20); // 永遠に稼働しないようにする
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ignore_user_abort(true); // レスポンス後処理続行可
    header('Connection: close');
    header('Content-Length: 0');
}

// event取得
$events = json_decode($requestBody, true)['events'] ?? [];

// ほとんどの場合eventは1つしかないみたい
$database = new JsonDatabase(MAIN_TABLE_NAME);
foreach ($events as $event) {
    if (!isset($event['type'])) continue;

    $start = hrtime(true);

    // 設定取得、なければデフォルトで作成
    $config = $database->restore('config');
    if (!isset($config)) {
        $config = DEFAULT_CONFIG;
        $database->store('config', $config);
    }

    $userId = $event['source']['userId'];

    // 管理者用意
    $adminSupporter = new KishukushaFormSupporter($config['adminId'], $config, $database);

    // イベント処理
    try {
        if ($userId === $config['adminId']) {
            // 管理者である
            $adminSupporter->handleEvent($event);
        } else {
            // 一般ユーザーである
            $userSupporter = new KishukushaFormSupporter($userId, $config, $database, $adminSupporter);
            $userSupporter->handleEvent($event);
        }
    } catch (Throwable $e) {
        $errorMsg = "{$e}";
    }

    $end = hrtime(true);

    // ログの記録
    $processing_time_ms = (($end - $start) / 1000000);
    if ($userId === $config['adminId']) {
        $eventInfo = $adminSupporter->getEventInfo();
    } else {
        $eventInfo = $userSupporter->getEventInfo();
    }
    $logDb = new LogDatabase(LOG_TABLE_NAME);
    if (isset($errorMsg)) $logDb->log("An error occurred:\n" . $errorMsg);
    $logDb->log("Handled the event in {$processing_time_ms} ms. {$eventInfo}");

    // エラーメールの送信
    if (isset($errorMsg)) {
        $to = SSK_EMAIL;
        $subject = '【寄宿舎届出サポート】エラーが発生しました。送信/返信が行われていない可能性があります。';
        $message = "<Error Message>
{$errorMsg}

<Event Info>
{$eventInfo}

<Processing Time>
{$processing_time_ms}ms";
        $headers = 'From: supporter@kishukusha-form-supporter.iam.gserviceaccount.com';
        if (!mb_send_mail($to, $subject, $message, $headers)) {
            $logDb->log("Failed in sending an error mail.");
        };
    }
}
