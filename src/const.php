<?php

// MySQLデータベースの設定(変更しなくてもよいもの)
define('DB_CHARSET', 'utf8');
define('MAIN_TABLE_NAME', 'bot_objects');
define('LOG_TABLE_NAME', 'bot_logs');

// Googleアカウントボットの設定
define('CREDENTIALS_PATH', dirname(__DIR__) . '/credentials.json');

// 画像用フォルダ
define('IMAGE_FOLDER_PATH', dirname(__DIR__) . '/images/');

// Googleドライブへアップロードできるかを試すテスト画像
define('TEST_IMAGE_FILENAME', 'user-manual.jpg');

// 佐々木の情報
define('SSK_EMAIL', 'ssk70679@keio.jp');

define(
    'USER_MANUAL',
    '【寄宿舎届出サポート ユーザー用マニュアル】
<申請について>
1.申請を行うと必ず写真のような返信が返ってくる。この返信が帰ってこないうちは申請は完了していないので注意すること。

<返信が来ない場合>
2.友達追加したりメッセージを送っても返信が来ない場合は、「あ」などと打って返信を待つ。
3.ボットは送信すると必ず返信を返すようになっているので、応答がない時は適当に何か入力して反応を見る。

<クイックリプライについて>
4.パソコンではクイックリプライが使えない。
5.しかし、「前の項目を修正する」「キャンセル」はどのような質問項目でも返信として使えるので利用すること。それぞれ、「一つ前の質問にもう一度答える」「フォームへの入力をすべてクリアして『回答を始める』の質問まで戻る」という意味。
6.クイックリプライの選択肢は、「前の項目を修正する」「キャンセル」と「はい」「いいえ」以外は全て質問文中に表示される。

<TIPS>
7.ボットの日付や時間の入力はかなり柔軟で、「4桁または8桁で入力してください」となっているが、実際は2桁や3桁、「/」や「:」などが入っていても認識する。
8.「OK」と入力すると「今聞かれている質問文をもう一度送信する」。
9.ボットをブロックするとボットに登録された全ての情報が消える。'
);

define('USER_MANUAL_PHOTO_FILENAME', 'user-manual.jpg');

define('ADMIN_MANUAL_PDF_URL', 'https://github.com/philip82148/kishukusha-report-supporter/blob/main/admin-manual.pdf');

define(
    'ADMIN_MANUAL',
    '【管理者用 寄宿舎届出サポート マニュアル】
' . ADMIN_MANUAL_PDF_URL
);

define(
    'SERVER_MANUAL',
    "【寄宿舎届出サポート HPサーバーを移行する場合の手順】
寄宿舎届出サポートは寄宿舎のHPのサーバーを使用しています。
サーバーを移行する場合は次のページの説明を見てください。

https://github.com/philip82148/kishukusha-report-supporter

ここで、分かりやすいように具体例を上げると、次のようになっています。
現在のwebhook.phpのURL: " . WEBHOOK_PARENT_URL . "/webhook.php
現在のkishukusha-report-supporter/のサーバー内のフォルダパス: " . dirname(__DIR__) . "/
現在のwebhook.phpのサーバー内のファイルパス: " . dirname(__DIR__) . "/webhook.php

webhook.phpのURLのドメイン({$_SERVER['SERVER_NAME']})が寄宿舎のHPと違う場合は寄宿舎のHPのサーバーにはもう一つドメインがある可能性があります。
サーバー管理画面を参照してください。

分からない場合は佐々木のメアド(" . SSK_EMAIL . ")に連絡してください。"
);

define('前の項目を修正する', '前の項目を修正する');
define('キャンセル', 'キャンセル');
define('OK', 'OK');
define('はい', 'はい');
define('いいえ', 'いいえ');

define('承認する', '承認する');
define('直接伝えた', '直接伝えた');
define('一番最後に見る', '一番最後に見る');
