<?php

require_once __DIR__ . '/config.php';

define(
    'USER_MANUAL',
    "【寄宿舎届出サポート ユーザー用マニュアル】
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
9.ボットをブロックするとボットに登録された全ての情報が消える。"
);

define('USER_MANUAL_PHOTO_URL', IMAGE_FOLDER_URL . 'user-manual.jpg');

define(
    'ADMIN_MANUAL',
    "【寄宿舎届出サポート 管理者への注意事項】
ボットをブロックするとボットに登録されたその人の情報が全て消えます。
管理者がボットをブロックした場合、承認前の届出があれば、その情報も全て消えます(スプレッドシートを見て手動で承認してください)。
これらはLINEアカウントを削除した場合も同様です。

また、管理者がボットをブロック、またはLINEアカウントを削除した場合は、そのあと初めて承認が必要な届出を行ったユーザーに管理者権限が移ります。
自分が管理者であるかどうかは「回答を始める」を押したときに「管理者設定」があるかどうかで調べられます。"
);

define(
    'SERVER_MANUAL',
    "【寄宿舎届出サポート HPサーバーを移行する場合の手順】
寄宿舎届出サポートは寄宿舎のHPのサーバーを使用しています。
サーバーを移行する場合は次のページの説明を見てください。

https://github.com/philip82148/kishukusha-report-supporter

ここで、分かりやすいように具体例を上げると、次のようになっています。
現在のwebhook.phpのURL: " . WEBHOOK_PARENT_URL . "webhook.php
現在のkishukusha-report-supporter/のサーバー内のファイルパス: " . __DIR__ . "/
現在のwebhook.phpのサーバー内のファイルパス: " . __DIR__ . "/webhook.php

webhook.phpのURLのドメイン({$_SERVER['SERVER_NAME']})が寄宿舎のHPと違う場合は寄宿舎のHPのサーバーにはもう一つドメインがある可能性があります。
サーバー管理画面を参照してください。

分からない場合は佐々木のメアド(" . SSK_EMAIL . ")に連絡してください。"
);
