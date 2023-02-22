# 必要なもの
 - SSL(https)を設定し、PHP 8.0以上が載ったサーバー
 - MySQLデータベースの認証情報(WordPressが載ったサーバーならwp-config.phpに載っている。もしくはサーバー管理画面から作成可能)
 - Messaging APIを設定してチャネルアクセストークンとチャネルシークレットが取得でき、Webhook URLが設定できるLINE BOT([Line Messaging APIで簡単Line Bot作成（超初心者向け）](https://qiita.com/YSFT_KOBE/items/8dc62ac40c5112df2ed3))
 - (元サーバーのGoogle Service Accountを引き継がずに新たに用意する場合)  
[Sheets API を使ってPHPでGoogle スプレッドシートにデータを保存する](https://bashalog.c-brains.jp/19/04/12-101500.php)を参考に取得したjsonファイル。  
ただし、この記事のAPIに加えてDrive APIも有効にする必要がある。

# 使用方法
## 1.インストール
### 元サーバーからコピーする場合(コマンドラインが使えない場合)
kishukusha-form-supporter/フォルダを元サーバーからダウンロード、新サーバーのpublic_html/配下のどこかに配置する。

### git cloneする場合(元サーバーのファイルが壊れている場合等)
```
git clone https://github.com/philip82148/kishukusha-form-supporter
cd kishukusha-form-supporter
composer require google/apiclient:^2.0
```
gitとcomposer(とphp)を適宜インストールし、上記を実行する。  
サーバー上で実行するか、ローカルで3まで行って、kishukusha-form-supporter/ごとサーバーにアップロードする。  
この操作によりvendor/というフォルダができる。

## 2.Google APIの設定
認証情報を含んだjsonファイルをcredentials.jsonという名前にしてkishukusha-form-supporter/配下に配置する。  
元サーバーから引き継ぐ場合は元サーバーのcredentials.jsonをダウンロードして配置する。

## 3.config.phpの設定
config-sample.phpをconfig.phpとリネームし、各値を設定する。  
※ここまでの操作をローカルで行った場合はここでサーバーにアップロードする。

## 4.cronの設定
サーバーにcronというサービスがあるので、それを使ってdelete-shogyoji-images.phpを一日1回稼働させるようする。  
これにより諸行事届の画像の削除が定期的に行われるようになる。
