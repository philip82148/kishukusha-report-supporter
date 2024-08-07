<?php

namespace KishukushaReportSupporter\Forms;

use KishukushaReportSupporter\KishukushaReportSupporter;
use KishukushaReportSupporter\UnsubmittableForm;

class AdminSettings extends UnsubmittableForm
{
    public static function form(KishukushaReportSupporter $supporter, array $message): void
    {
        if ($message['type'] !== 'text') {
            $supporter->askAgainBecauseWrongReply();
            return;
        }
        $message = $message['text'];

        if (count($supporter->storage['phases']) === 0) {
            // 質問
            $supporter->pushText('項目を選んでください。', true);

            // 選択肢
            $supporter->pushOptions([
                '管理者用マニュアル表示',
                '行事登録・編集',
                '管理者変更',
                '財務変更',
                '舎生大会・諸行事届用画像保存先変更',
                '届出保存先スプレッドシート変更',
                'その他届出用画像保存先変更',
                '最大外部来訪者数変更',
                '任期終了日変更',
            ], true);
            $supporter->pushOptions([キャンセル]);

            $supporter->storage['phases'][] = 'askingSetting';
            return;
        }

        $lastPhase = $supporter->storage['phases'][count($supporter->storage['phases']) - 1];
        if ($lastPhase === 'askingSetting') {
            if ($message !== 前の項目を修正する) {
                if (self::storeOrAskAgain($supporter, '設定項目', $message))
                    return;
            }

            switch ($supporter->storage['unsavedAnswers']['設定項目']) {
                case '管理者用マニュアル表示':
                    $supporter->pushText(ADMIN_MANUAL);
                    $supporter->pushText(SERVER_MANUAL);
                    $supporter->pushOptions([OK]);
                    return;
                case '行事登録・編集':
                    // 質問
                    $supporter->pushText('下記の行事スプレッドシートに行事を入力して、「登録」と入力してください。
マニュアル: ' . ADMIN_MANUAL_PDF_URL . "

行事スプレッドシート:
https://docs.google.com/spreadsheets/d/{$supporter->config['eventSheetId']}

※スプレッドシートへのアクセス権がない場合は別のスプレッドシートを登録してください。

現在読み込まれている行事(開始日順):
" . self::getEventListString($supporter), true);

                    // 選択肢
                    $supporter->pushOptions(['登録', '別のスプレッドシートを使って登録する', 前の項目を修正する, キャンセル]);

                    $supporter->storage['phases'][] = 'confirmingReloadEvents';
                    return;
                case '管理者変更':
                    // 質問
                    $password = $supporter->config['password'] ?? 'なし';
                    $supporter->pushText('新しい管理者が入力するための8文字以上の合言葉を入力してください。
マニュアル: ' . ADMIN_MANUAL_PDF_URL . "

※前後の改行やスペースは無視されます。
※現在の設定値を削除するには、この画面をキャンセルして管理者自身が合言葉を入力してください。

現在の設定値:{$password}", true);

                    // 選択肢
                    $supporter->pushOptions([前の項目を修正する, キャンセル]);

                    $supporter->storage['phases'][] = 'askingPassword';
                    return;
                case '財務変更':
                    // 質問
                    $password = $supporter->config['zaimuPassword'] ?? 'なし';
                    $supporter->pushText('新しい財務が入力するための8文字以上の合言葉を入力してください。
マニュアル: ' . ADMIN_MANUAL_PDF_URL . "

※前後の改行やスペースは無視されます。
※現在の設定値を削除するには、この画面をキャンセルして財務自身が合言葉を入力してください。

現在の設定値:{$password}", true);

                    // 選択肢
                    $supporter->pushOptions([前の項目を修正する, キャンセル]);

                    $supporter->storage['phases'][] = 'askingZaimuPassword';
                    return;
                case '最大外部来訪者数変更':
                    // 質問
                    $supporter->pushText("許容する外部来訪者の最大数を数値で入力してください。
0人にすると人数の制限がなくなります。

現在の最大外部来訪者数:{$supporter->config['maxGaiburaihoushasuu']}人", true);

                    // 選択肢
                    $supporter->pushOptions([前の項目を修正する, キャンセル]);

                    $supporter->storage['phases'][] = 'askingMaxGaiburaihoushasuu';
                    return;
                case '任期終了日変更':
                    $supporter->updateEndOfTerm();

                    // 質問
                    $year = date('Y');
                    $supporter->pushText("任期終了日を4桁(年無し)または8桁(年有り)で入力してください。
例:1130、{$year}1130
※任期終了日は任期が終了する度に次の5/31または11/30に自動で更新されます。

現在の設定値:{$supporter->config['endOfTerm']}", true);

                    // 選択肢
                    $supporter->pushOptions([前の項目を修正する, キャンセル]);

                    $supporter->storage['phases'][] = 'askingEndOfTerm';
                    return;
            }

            // Google変更類
            // 質問
            switch ($supporter->storage['unsavedAnswers']['設定項目']) {
                case '届出保存先スプレッドシート変更':
                    $supporter->pushText("新しい届出保存先スプレッドシートをボットのGoogleアカウントに共有し、そのURLを入力してください。
届出保存先スプレッドシートはあらかじめ何かを書きこんでおく必要はありません。

ボットのGoogleアカウント:
" . BOT_EMAIL . "

現在の届出保存先スプレッドシート:
https://docs.google.com/spreadsheets/d/{$supporter->config['outputSheetId']}", true);
                    break;

                case '舎生大会・諸行事届用画像保存先変更':
                    $supporter->pushText('五役のみが閲覧可能な舎生大会・諸行事届の証拠画像を保存するためGoogle DriveのフォルダをボットのGoogleアカウントに共有し、そのURLを入力してください。
マニュアル: ' . ADMIN_MANUAL_PDF_URL . "

※プライバシーに関わる画像がアップロードされる可能性があるため、五役とボットのみに共有したフォルダにしてください。

ボットのGoogleアカウント:
" . BOT_EMAIL . "

現在の舎生大会・諸行事届用画像保存先:
https://drive.google.com/drive/folders/{$supporter->config['shogyojiImageFolderId']}", true);
                    break;

                case 'その他届出用画像保存先変更':
                    $supporter->pushText("舎生大会・諸行事届以外の届出の画像を保存するためのGoogle DriveのフォルダをボットのGoogleアカウントに共有し、そのURLを入力してください。
※このフォルダ内に各届出ごとにフォルダが作成され、それぞれに各届出の画像が保存されます。

ボットのGoogleアカウント:
" . BOT_EMAIL . "

現在のその他届出用画像保存先:
https://drive.google.com/drive/folders/{$supporter->config['generalImageFolderId']}", true);
                    break;
            }

            // 選択肢
            $supporter->pushOptions([前の項目を修正する, キャンセル]);

            $supporter->storage['phases'][] = 'askingGoogleUrl';
        } else if ($lastPhase === 'confirmingReloadEvents') {
            switch ($message) {
                case '登録':
                    // 再読み込み
                    $supporter->fetchEvents(true);

                    // 返信
                    $supporter->pushText("行事データの登録を行いました。
※全ての行事が読み込まれているか、日付の年があっているか確認してください。

読み込まれた行事(開始日順):
" . self::getEventListString($supporter));
                    $supporter->resetForm();
                    return;
                case '別のスプレッドシートを使って登録する':
                    $supporter->pushText("新しい行事スプレッドシートをボットのGoogleアカウントに共有し、そのURLを入力してください。
行事スプレッドシートには「行事」という名前のシートが作成されている必要があります。

ボットのGoogleアカウント:
" . BOT_EMAIL . "

現在の行事スプレッドシート:
https://docs.google.com/spreadsheets/d/{$supporter->config['eventSheetId']}", true);
                    $supporter->pushOptions([前の項目を修正する, キャンセル]);

                    $supporter->storage['phases'][] = 'askingGoogleUrl';
                    return;
                default:
                    $supporter->askAgainBecauseWrongReply();
                    return;
            }
        } else if ($lastPhase === 'askingMaxGaiburaihoushasuu') {
            if (self::storeOrAskAgain($supporter, '最大外部来訪者数', $message))
                return;

            // 返信
            $supporter->pushText("最大外部来訪者数を変更しました。");
            $supporter->resetForm();
        } else if ($lastPhase === 'askingEndOfTerm') {
            if (self::storeOrAskAgain($supporter, '任期終了日', $message))
                return;

            // 返信
            $supporter->pushText('任期終了日を変更しました。');
            $supporter->resetForm();
        } else if ($lastPhase === 'askingPassword') {
            if (self::storeOrAskAgain($supporter, '合言葉', $message))
                return;

            // 返信
            $supporter->pushText("合言葉を設定しました。\n新たな管理者は合言葉をメッセージしてください。");
            $supporter->resetForm();
        } else if ($lastPhase === 'askingZaimuPassword') {
            if (self::storeOrAskAgain($supporter, '財務の合言葉', $message))
                return;

            // 返信
            $supporter->pushText("合言葉を設定しました。\n新たな財務は合言葉をメッセージしてください。");
            $supporter->resetForm();
        } else {
            if (self::storeOrAskAgain($supporter, 'Google URL', $message))
                return;

            $supporter->resetForm();
        }
    }

    protected static function storeOrAskAgain(KishukushaReportSupporter $supporter, string $type, string|array $message): string
    {
        switch ($type) {
            case '設定項目':
                switch ($message) {
                    case '管理者用マニュアル表示':
                    case '行事登録・編集':
                    case '管理者変更':
                    case '財務変更':
                    case '舎生大会・諸行事届用画像保存先変更':
                    case '届出保存先スプレッドシート変更':
                    case 'その他届出用画像保存先変更':
                    case '最大外部来訪者数変更':
                    case '任期終了日変更':
                        $supporter->storage['unsavedAnswers']['設定項目'] = $message;
                        return '';
                }
                // 有効でなかった、もう一度質問文送信
                $supporter->askAgainBecauseWrongReply();
                return 'wrong-reply';
            case '最大外部来訪者数':
                $message = toHalfWidth($message);
                $count = preg_replace('/\D+/', '', $message);
                if ($count === '') {
                    $supporter->askAgainBecauseWrongReply("入力が不正です。\n数値で答えてください。");
                    return 'wrong-reply';
                }

                $count = (int)$count;
                $supporter->pushText("外部来訪者数:{$count}人");
                $supporter->config['maxGaiburaihoushasuu'] = $count;
                $supporter->storeConfig();
                return '';
            case '任期終了日':
                $date = stringToDate($message);
                if ($date === false) {
                    $year = date('Y');
                    $supporter->askAgainBecauseWrongReply("入力の形式が違うか、無効な日付です。\n「1130」または「{$year}1130」のように4桁または8桁で入力してください。");
                    return 'wrong-reply';
                }

                $dateString = dateToDateStringWithDay($date);
                $supporter->pushText("任期終了日:{$dateString}");

                $today = getDateAt0AM();
                if ($date < $today) {
                    $supporter->askAgainBecauseWrongReply("その日付はすでに過ぎています。\nもう一度入力してください。");
                    return 'wrong-reply';
                }

                $supporter->config['endOfTerm'] = $dateString;
                $supporter->storeConfig();
                return '';
            case '合言葉':
            case '財務の合言葉':
                if (mb_strlen($message) < 8) {
                    $supporter->askAgainBecauseWrongReply('合言葉は8文字以上としてください。');
                    return 'wrong-reply';
                }
                if ($type === '合言葉') {
                    $supporter->config['password'] = $message;
                } else {
                    $supporter->config['zaimuPassword'] = $message;
                }
                $supporter->storeConfig();
                return '';
            case 'Google URL':
                $id = self::extractId($message);
                switch ($supporter->storage['unsavedAnswers']['設定項目']) {
                    case '行事登録・編集':
                        if ($supporter->checkValidGoogleItem('eventSheetId', $id)) {
                            $supporter->config['eventSheetId'] = $id;
                            $supporter->storeConfig();
                            $supporter->fetchEvents(true);

                            // 返信
                            $supporter->pushText("設定を保存、行事データを更新しました。

読み込まれた行事(開始日順):
" . self::getEventListString($supporter));
                            return '';
                        }
                        $supporter->askAgainBecauseWrongReply("入力されたURLのスプレッドシートにアクセスできませんでした。
ボットのGoogleアカウントにスプレッドシートが共有されていないか、「行事」シートがない可能性があります。
もう一度入力してください。");
                        return 'wrong-reply';
                    case '届出保存先スプレッドシート変更':
                        if ($supporter->checkValidGoogleItem('outputSheetId', $id)) {
                            $supporter->config['outputSheetId'] = $id;
                            $supporter->storeConfig();

                            // 返信
                            $supporter->pushText('書き込み可能なスプレッドシートであることを確認、設定を保存しました。');
                            return '';
                        }
                        $supporter->askAgainBecauseWrongReply("入力されたURLのスプレッドシートへの書き込みに失敗しました。
ボットのGoogleアカウントにスプレッドシートが共有されていないか、編集権限が与えられていない可能性があります。
もう一度入力してください。");
                        return 'wrong-reply';
                    case '舎生大会・諸行事届用画像保存先変更':
                        if ($supporter->checkValidGoogleItem('shogyojiImageFolderId', $id)) {
                            $supporter->config['shogyojiImageFolderId'] = $id;
                            $supporter->storeConfig();

                            // 返信
                            $supporter->pushText('テストファイルのアップロード後削除に成功、設定を保存しました。');
                            return '';
                        }
                        $supporter->askAgainBecauseWrongReply("入力されたURLのフォルダへのテストファイルのアップロード、またはその削除に失敗しました。
ボットのGoogleアカウントにフォルダが共有されていないか、編集権限が与えられていない可能性があります。
なお、共有ドライブ上のフォルダを使用する場合は、ボットにコンテンツ管理者ではなく、管理者の権限を与えてください。
もう一度入力してください。");
                        return 'wrong-reply';
                    case 'その他届出用画像保存先変更':
                        if ($supporter->checkValidGoogleItem('generalImageFolderId', $id)) {
                            $supporter->config['generalImageFolderId'] = $id;
                            $supporter->storeConfig();

                            // 返信
                            $supporter->pushText('テストファイルのアップロードに成功、設定を保存しました。');
                            return '';
                        }
                        $supporter->askAgainBecauseWrongReply("入力されたURLのフォルダへのテストファイルのアップロードに失敗しました。
ボットのGoogleアカウントにフォルダが共有されていないか、編集権限が与えられていない可能性があります。
もう一度入力してください。");
                        return 'wrong-reply';
                }
        }
    }

    private static function extractId(string $url): string
    {
        // ?,#以降を取り去る
        $pureUrl = preg_split('/[\?#]/', $url, 2)[0];

        // '/'で分割して最も長い部分をidとする
        $splits = explode('/', $pureUrl);

        $longestSplit = '';
        $longestLength = 0;
        foreach ($splits as $split) {
            $length = mb_strlen($split);
            if ($length > $longestLength) {
                $longestSplit = $split;
                $longestLength = $length;
            }
        }

        return $longestSplit;
    }

    private static function getEventListString(KishukushaReportSupporter $supporter): string
    {
        $events = $supporter->fetchEvents();
        if (count($events) === 0) {
            return 'なし';
        }

        $eventListString = '';
        foreach ($events as $event) {
            if ($eventListString !== '')
                $eventListString .= "\n";
            if ($event['開始日'] === $event['終了日']) {
                $eventListString .= "「{$event['行事名']}」({$event['開始日']})";
            } else {
                $eventListString .= "「{$event['行事名']}」({$event['開始日']} - {$event['終了日']})";
            }
        }

        return $eventListString;
    }
}
