<?php

// ↓サーバー移動の際はこの項目の変更が必要(サーバー移動の時読んでください.txt参照)
// index.phpが配置されているURL
define('WEBHOOK_PARENT_URL', 'https://.../kishukusha-form-supporter/');
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
define('TEST_IMAGE_FILENAME', 'test.jpg');

// 佐々木の情報
define('SSK_ID', '...');
define('SSK_EMAIL', '...');

// サーバー移動直後の管理者は、'password'を設定しておき、ボットの画面に打ち込めば管理者となれる
define('DEFAULT_CONFIG', [
    // 'password' => 'パスワードをここに設定する',
    'adminId' => SSK_ID,
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

// デバッグ時はtrue
define('DEBUGGING_ADMIN_SETTINGS', false);
