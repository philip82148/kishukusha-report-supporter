# 寄宿舎届出サポートの仕組み

## 登場人物

**LINE BOT アカウント**:ユーザーから送られてくる LINE の受信元/ユーザーに返ってくる LINE の送信元。  
**Google サービスアカウント**:Google スプレッドシートや Google ドライブを操作する Google アカウント。  
**MySQL データベース**:やり取りを行うためにユーザーの前回の回答等を保存しているデータベース。  
**PHP プログラム**:佐々木が作った、回答を**MySQL データベース**に保存したり、**LINE BOT アカウント**や**Google サービスアカウント**を操作したりするプログラムファイル。  
**サーバー**:**PHP プログラム**を保存し、実行するもの。

## 処理の流れ

寄宿舎届出サポートにメッセージを送る。  
↓  
**LINE BOT アカウント**が送られてきたメッセージの内容を**サーバー**に送る。  
↓  
**サーバー**が**PHP プログラム**を実行する。  
必要に応じて**Google サービスアカウント**でスプレッドシート等を操作した後、返信内容を**LINE BOT アカウント**に送る。  
ユーザーの回答は**MySQL データベース**に保存される。  
↓  
寄宿舎届出サポートから返信が返ってくる。

# サーバー移動の際にすること

以下は比較的高度な内容となる。  
やり方はネットで調べればわかる(「FTP やり方」「サーバー名 FTP やり方」等で検索)と思うので、調べながらやってほしい。

## 必要な技術

FTP という方法で**サーバー**からファイルをダウンロード/アップロードすることが必要になる。

## 必要なもの

### **サーバー**(SSL(https)を設定し、PHP 8.0 以上が載っているもの)

SSL と PHP8.0 はデフォルトでは有効になっていないかもしれない。  
SSL は設定していない場合**LINE BOT アカウント**が**サーバー**に受信内容を送れない。  
PHP 8.0 以上に対応していない場合は**PHP プログラム**が実行できない。

### **MySQL データベース**の認証情報

WordPress が載った **サーバー**なら**MySQL データベース**も付帯している。  
それにアクセスするための認証情報は WordPress の設定ファイルである wp-config.php(「wp-config.php 場所」で検索し、FTP でダウンロードする) に載っているものを使うか、もしくはサーバー管理画面から新しく作成することが可能である。

### Messaging API を設定した**LINE BOT**

参考:[Line Messaging API で簡単 Line Bot 作成（超初心者向け）](https://qiita.com/YSFT_KOBE/items/8dc62ac40c5112df2ed3)

### (必須ではない)**Google サービスアカウント**

これは前のものをそのまま流用すればよいので、わざわざ用意する必要はない。  
だが、何らかの理由で**Google サービスアカウント**を新しく用意したい場合は[Sheets API を使って PHP で Google スプレッドシートにデータを保存する](https://bashalog.c-brains.jp/19/04/12-101500.php)を参考にアカウントを作成、json ファイルを取得する。  
ただし、この記事では Spreadsheet API のみ有効にしているが、寄宿舎届出サポートでは Drive API も合わせて有効にする必要がある。

## 手順

### 1.**PHP プログラム**を**サーバー**上に配置する

FTP で kishukusha-report-supporter/フォルダを元サーバーからダウンロードし、新サーバーの public_html/配下のどこかにアップロードする。  
※アップロードに数時間ほどかかるが、コマンドラインを使う場合(このページ最下部参照)は数分で終わらせることができる。

### 2.**PHP プログラム**が他の登場人物を操作できるようにする

kishukusha-report-supporter/フォルダの中に config.php があるので、それを FTP でダウンロードし、

- WEBHOOK_PARENT_URL(kishukusha-report-supporter/フォルダの URL(webhook.php が配置されている親フォルダの URL))
- DB\_...(**MySQL データベース**の認証情報)
- CHANNEL_ACCESS_TOKEN, CHANNEL_SECRET(**LINE BOT アカウント**から取得したチャネルアクセストークンとチャネルシークレット)
- BOT_EMAIL(**Google サービスアカウント**のメールアドレス)

を書き換えて、FTP で同じ場所にアップロードして上書きする。

### 3.**LINE BOT アカウント**に**PHP プログラム**と**サーバー**の場所を教える

**LINE BOT アカウント**の設定画面から Webhook URL に kishukusha-report-supporter/フォルダ内の webhook.php の URL※を設定する。  
※ブラウザでアクセスすると「ここが webhook.php です」と表示される URL。  
設定する際は「https://...」で始まるようにする(して、**サーバー**も SSL の設定が必要)。

### 4.**PHP プログラム**が**Google サービスアカウント**を使えるようにする

※前のものを流用する場合はこのステップは飛ばしてよい。ただし、ステップ 1.でコマンドラインを使用した場合は前のサーバーの credentials.json を手動でアップロードする必要がある。  
認証情報を含んだ json ファイルを credentials.json という名前にして kishukusha-report-supporter/配下に FTP でアップロードする(して、前のものがある場合は上書きする)。

### 5.諸行事届の画像の削除を一日 1 回行う設定

ボットにアクセスされる(メッセージが送られる)度に、委員会行事の開催日(の開始日)か、アップロードされた日のどちらか遅い方から 1 週間経った諸行事届の画像が削除されるようになっているが、アクセスされなかった場合でも削除されるようにする。  
**サーバー**に cron というサービスがあるので、それに kishukusha-report-supporter/配下の delete-shogyoji-images.php という**PHP プログラムファイル**を設定し、一日 1 回稼働させるようする。

## 手順 1.でコマンドラインを使う場合

サーバーに SSH 接続できる(そしてサーバーに git と composer(コマンド名)がインストールされている)場合はサーバー上で下記を実行する。  
※SSH 接続はパソコンからリモートのサーバーのコマンドラインにアクセスする方法である。やり方はネットで調べること。

```shell
cd "public_html/のパス"
git clone https://github.com/philip82148/kishukusha-report-supporter
cd kishukusha-report-supporter
composer install
```

これにより public_html/直下に kishukusha-report-supporter/というフォルダができ、composer というコマンドにより kishukusha-report-supporter/直下に vendor/というフォルダができる。
