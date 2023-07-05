<?php

$start = hrtime(true);

require_once __DIR__ . '/vendor/autoload.php';

use KishukushaReportSupporter\KishukushaReportSupporter;
use KishukushaReportSupporter\JsonDatabase;
use KishukushaReportSupporter\LogDatabase;

// 署名確認
$requestBody = file_get_contents('php://input');
if (!checkSignature($requestBody)) {
    echo 'ここがwebhook.phpです';
    exit;
}

// コネクション切断→既読?
// 切断するとerror_logが効かなくなるのでデバッグ時はしない
if (!DEBUGGING) {
    set_time_limit(20); // 永遠に稼働しないようにする
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        ignore_user_abort(true); // レスポンス後処理続行可
        header('Connection: close');
        header('Content-Length: 0');
    }
}

// event取得
$events = json_decode($requestBody, true)['events'] ?? [];

// ほとんどの場合eventは1つしかないみたい
$database = new JsonDatabase(MAIN_TABLE_NAME);
foreach ($events as $event) {
    if (!isset($event['source']['userId'])) continue;

    $userId = $event['source']['userId'];
    $supporter = new KishukushaReportSupporter($userId, $database);

    // イベント処理
    $errorMessage = '';
    try {
        $supporter->handleEvent($event);
    } catch (Throwable $e) {
        $errorMessage = "{$e}";
    }

    $end = hrtime(true);

    // ログの記録
    if (ENABLE_LOGGING) {
        $processingTimeMs = (($end - $start) / 1000000);
        $eventInfo = $supporter->getEventInfo();
        $logDatabase = new LogDatabase(LOG_TABLE_NAME);
        if ($errorMessage) $logDatabase->log("An error occurred:\n" . $errorMessage);
        $logDatabase->log("Handled the event in {$processingTimeMs} ms. {$eventInfo}");
    }

    // エラーメールの送信
    if ($errorMessage) {
        $to = SSK_EMAIL;
        $subject = '【寄宿舎届出サポート】エラーが発生しました。送信/返信が行われていない可能性があります。';
        $message = "<Error Message>
{$errorMessage}

<Event Info>
{$eventInfo}

<Processing Time>
{$processingTimeMs}ms";
        $headers = 'From: ' . BOT_EMAIL;
        if (!mb_send_mail($to, $subject, $message, $headers)) {
            if (ENABLE_LOGGING)
                $logDatabase->log("Failed in sending an error mail.");
        };
    }
}

include __DIR__ . '/delete-shogyoji-images.php';
