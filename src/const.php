<?php

// MySQLデータベースの設定(変更しなくてもよいもの)
define('DB_CHARSET', 'utf8');
define('MAIN_TABLE_NAME', 'bot_objects');
define('LOG_TABLE_NAME', 'bot_logs');

// Googleアカウントボットの設定
define('CREDENTIALS_PATH', dirname(__DIR__) . '/credentials.json');

// 画像用フォルダ
define('IMAGE_FOLDER_PATH', dirname(__DIR__) . '/images/');
define('IMAGE_FOLDER_URL', WEBHOOK_PARENT_URL . '/images/');

// Googleドライブへアップロードできるかを試すテスト画像
define('TEST_IMAGE_FILENAME', 'user-manual.jpg');

// 佐々木の情報
define('SSK_EMAIL', 'ssk70679@keio.jp');
