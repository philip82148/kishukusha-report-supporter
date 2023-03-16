# 必要なもの

- SSL(https)を設定し、PHP 8.0 以上が載ったサーバー
- MySQL データベースの認証情報(WordPress が載ったサーバーなら wp-config.php に載っている。もしくはサーバー管理画面から作成可能)
- Messaging API を設定してチャネルアクセストークンとチャネルシークレットが取得でき、Webhook URL が設定できる LINE BOT([Line Messaging API で簡単 Line Bot 作成（超初心者向け）](https://qiita.com/YSFT_KOBE/items/8dc62ac40c5112df2ed3))
- (元サーバーの Google Service Account を引き継がずに新たに用意する場合)  
  [Sheets API を使って PHP で Google スプレッドシートにデータを保存する](https://bashalog.c-brains.jp/19/04/12-101500.php)を参考に取得した json ファイル。  
  ただし、この記事の API に加えて Drive API も有効にする必要がある。

# 使用方法

## 1.インストール

### 元サーバーからコピーする場合(コマンドラインが使えない場合)

kishukusha-form-supporter/フォルダを元サーバーからダウンロード、新サーバーの public_html/配下のどこかに配置する。

### git clone する場合(元サーバーのファイルが壊れている場合等)

```
git clone https://github.com/philip82148/kishukusha-form-supporter
cd kishukusha-form-supporter
composer require google/apiclient:^2.0
```

git と composer(と php)を適宜インストールし、上記を実行する。  
サーバー上で実行するか、ローカルで 4.config.php の設定 まで行って、kishukusha-form-supporter/ごとサーバーにアップロードする。  
この操作により vendor/というフォルダができる。

## 2.LINE BOT アカウントの設定

LINE BOT の Webhook URL は webhook.php の URL にする。

## 3.Google API の設定

認証情報を含んだ json ファイルを credentials.json という名前にして kishukusha-form-supporter/配下に配置する。  
元サーバーから引き継ぐ場合は元サーバーの credentials.json をダウンロードして配置する。

## 4.config.php の設定

config-sample.php を config.php とリネームし、各値を設定する。  
※ここまでの操作をローカルで行った場合はここでサーバーにアップロードする。

## 5.cron の設定

サーバーに cron というサービスがあるので、それを使って delete-shogyoji-images.php を一日 1 回稼働させるようする。  
これにより諸行事届の画像の削除が定期的に行われるようになる。
