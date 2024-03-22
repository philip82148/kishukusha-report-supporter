<?php

// ↓サーバー移動の際はこの項目の変更が必要
// webhook.phpが配置されている親フォルダのURL(https://...で始まり、最後は/をつけない)
// (WEBHOOK_PARENT_URL/webhook.phpとしてブラウザでアクセスすると「ここが webhook.php です」と表示されるURL。
// ただし、このファイルがアップロードされていないと表示されない)
define('WEBHOOK_PARENT_URL', 'https://.../kishukusha-report-supporter');
// MySQLデータベースの設定
define('DB_HOST', '...');
define('DB_NAME', '...');
define('DB_USER', '...');
define('DB_PASSWORD', '...');

// ↓自分でLINE BOTを新たに用意する場合はこの設定も必要
// LINEボットの設定
define('CHANNEL_ACCESS_TOKEN', '...');
define('CHANNEL_SECRET', '...');

// ↓自分でGoogleサービスアカウントを新たに用意する場合はこの設定も必要
// Googleサービスアカウントの設定
define('BOT_EMAIL', '...');

// サーバー移動後、最初にボットにメッセージを送った人が管理者となる
// 管理者権限を強制的に移す場合は、以下に'password'を設定しておき、ボットの入力に打ち込む
define('DEFAULT_CONFIG', [
    // 'password' => 'パスワードをここに設定する(コメントアウト(//を消すこと)すること)',
    'adminId' => 'dummy',
    'zaimuId' => 'dummy',
    'eventSheetId' => 'dummy',
    'outputSheetId' => 'dummy',
    'shogyojiImageFolderId' => 'dummy',
    'generalImageFolderId' => 'dummy',
    'maxGaiburaihoushasuu' => 0,
    'endOfTerm' => '2023/05/31(水)'
]);


/* 以下は基本的に変更しなくてよい --------------------------------------------------------- */

// デバッグ時はtrue
define('DEBUGGING', false);
define('ENABLE_LOGGING', false);
