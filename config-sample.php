<?php

// ↓サーバー移動の際はこの項目の変更が必要
// webhook.phpが配置されている親フォルダのURL(https://...で始まること)
// (WEBHOOK_PARENT_URL/webhook.phpとしてブラウザでアクセスすると
// 「ここが webhook.php です」と表示されるURL)
define('WEBHOOK_PARENT_URL', 'https://.../kishukusha-report-supporter/');
// MySQLデータベースの設定
define('DB_HOST', '...');
define('DB_NAME', '...');
define('DB_USER', '...');
define('DB_PASSWORD', '...');
// ↑ここまで変更すべきもの

// ↓自分でLINE BOTを新たに用意する場合はこの設定も必要
// LINEボットの設定
define('CHANNEL_ACCESS_TOKEN', '...');
define('CHANNEL_SECRET', '...');
// ↑ここまで変更すべきもの


/* 以下は基本的に変更しなくてよい --------------------------------------------------------- */

// MySQLデータベースの設定(変更しなくてもよいもの)
define('DB_CHARSET', 'utf8');
define('MAIN_TABLE_NAME', 'bot_objects');
define('LOG_TABLE_NAME', 'bot_logs');

// Googleアカウントボットの設定
define('CREDENTIALS_PATH', __DIR__ . '/credentials.json');

// 画像をアップロードした際に保存する一時フォルダ
define('IMAGE_FOLDER_PATH', __DIR__ . '/image/');
define('IMAGE_FOLDER_URL', WEBHOOK_PARENT_URL . 'image/');

// Googleドライブへアップロードできるかを試すテスト画像
define('TEST_IMAGE_FILENAME', 'user-manual.jpg');

// サーバー移動後、最初にボットにメッセージを送った人が管理者となる
// 管理者権限を移す場合は、以下に'password'を設定しておき、ボットの入力に打ち込む
define('DEFAULT_CONFIG', [
    // 'password' => 'パスワードをここに設定する',
    'adminId' => 'dummy',
    'variableSheets' => 'dummy',
    'resultSheets' => 'dummy',
    'shogyojiImageFolder' => 'dummy',
    'odoribaImageFolder' => 'dummy',
    '309ImageFolder' => 'dummy',
    'bikesImageFolder' => 'dummy',
    'tamokutekiImageFolder' => 'dummy',
    'maxGaiburaihoushasuu' => 0,
    'endOfTerm' => '2023/05/31(水)'
]);

// 佐々木の情報
define('SSK_EMAIL', '...');
