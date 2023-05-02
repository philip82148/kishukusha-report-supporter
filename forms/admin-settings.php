<?php

require_once __DIR__ . '/../includes.php';

class AdminSettings extends FormTemplateBasic
{
    public function form(array $message): void
    {
        if ($message['type'] !== 'text') {
            $this->supporter->askAgainBecauseWrongReply();
            return;
        }
        $message = $message['text'];

        if (count($this->supporter->storage['phases']) === 0) {
            // 質問
            $this->supporter->pushMessage('項目を選んでください。', true);

            // 選択肢
            $this->supporter->pushOptions([
                '管理者用マニュアル表示',
                '行事データ再読み込み',
                '最大外部来訪者数変更',
                '任期終了日変更',
                '管理者変更',
                '行事スプレッドシートID変更',
                '出力先スプレッドシートID変更',
                '多目的室使用届用画像フォルダID変更',
                '舎生大会・諸行事届用画像フォルダID変更',
                '踊り場私物配備届用画像フォルダID変更',
                '309私物配備届用画像フォルダID変更',
                '自転車・バイク配備届用画像フォルダID変更'
            ], true);
            $this->supporter->pushOptions(['キャンセル']);

            $this->supporter->storage['phases'][] = 'askingSetting';
            return;
        }

        $lastPhase = $this->supporter->storage['phases'][count($this->supporter->storage['phases']) - 1];
        if ($lastPhase === 'askingSetting') {
            if ($message !== '前の項目を修正する') {
                if ($this->storeOrAskAgain('設定項目', $message))
                    return;
            }

            switch ($this->supporter->storage['unsavedAnswers']['設定項目']) {
                case '管理者用マニュアル表示':
                    $this->supporter->pushMessage(ADMIN_MANUAL);
                    $this->supporter->pushMessage(SERVER_MANUAL);
                    $this->supporter->resetForm();
                    return;
                case '行事データ再読み込み':
                    // 質問
                    $this->supporter->pushMessage("行事データの再読み込みを行いますか？
※開始日(B列)が日付の形式でない行、終了日(C列)が設定されている行で、終了日が開始日より前の行はスキップされます。
再読み込み後に全ての行事が読み込まれているか確認してください。

読み込み先のスプレッドシート:
https://docs.google.com/spreadsheets/d/{$this->supporter->config['variableSheets']}

現在読み込まれている行事(開始日順):
" . $this->getEventListString(), true);

                    // 選択肢
                    $this->supporter->pushOptions(['はい', '前の項目を修正する', 'キャンセル']);

                    $this->supporter->storage['phases'][] = 'confirmingReloadEvents';
                    return;
                case '最大外部来訪者数変更':
                    // 質問
                    $this->supporter->pushMessage("許容する外部来訪者の最大数を数値で入力してください。
0人にすると人数の制限がなくなります。
現在の最大外部来訪者数:{$this->supporter->config['maxGaiburaihoushasuu']}人", true);

                    // 選択肢
                    $this->supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

                    $this->supporter->storage['phases'][] = 'askingMaxGaiburaihoushasuu';
                    return;
                case '任期終了日変更':
                    // 質問
                    $year = date('Y');
                    $this->supporter->pushMessage("任期終了日を4桁(年無し)または8桁(年有り)で入力してください。
例:1130、{$year}1130
※任期終了日は任期が終了する度に次の5/31または11/30に自動で更新されます。
現在の設定値:{$this->supporter->config['endOfTerm']}", true);

                    // 選択肢
                    $this->supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

                    $this->supporter->storage['phases'][] = 'askingEndOfTerm';
                    return;
                case '管理者変更':
                    // 質問
                    $password = $this->supporter->config['password'] ?? 'なし';
                    $this->supporter->pushMessage("新しい管理者が入力するための8文字以上の合言葉を入力してください。
※前後の改行やスペースは無視されます。
※現在の設定値を削除するには、この画面をキャンセルして管理者自身が合言葉を入力してください。
現在の設定値:{$password}", true);

                    // 選択肢
                    $this->supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

                    $this->supporter->storage['phases'][] = 'askingPassword';
                    return;
            }

            // ID変更類
            // 質問
            switch ($this->supporter->storage['unsavedAnswers']['設定項目']) {
                case '行事スプレッドシートID変更':
                    $this->supporter->pushMessage("行事の読み込み先のスプレッドシートのURLまたはIDを入力してください。
現在の行事スプレッドシート:
https://docs.google.com/spreadsheets/d/{$this->supporter->config['variableSheets']}", true);
                    break;

                case '出力先スプレッドシートID変更':
                    $this->supporter->pushMessage("提出された届出の内容を記録するスプレッドシートのURLまたはIDを入力してください。
現在の出力先スプレッドシート:
https://docs.google.com/spreadsheets/d/{$this->supporter->config['resultSheets']}", true);
                    break;

                case '舎生大会・諸行事届用画像フォルダID変更':
                    $this->supporter->pushMessage("舎生大会・諸行事届の証拠画像を保存するための、五役とボットのみに共有した共有Google Drive内のフォルダのURLまたはIDを入力してください。

※プライバシーに関わる画像がアップロードされる可能性があるため、五役とボットのみに共有したフォルダにしてください。
また、ボットに画像の完全な削除権限を与えるために、ボットにコンテンツ管理者ではなく管理者の権限を与えてください。
そのためには、個人所有のフォルダをボットに共有するのではなく、ボットとの間に作成した共有ドライブ内のフォルダを使用する必要があります。

現在の舎生大会・諸行事届用画像フォルダ:
https://drive.google.com/drive/u/0/folders/{$this->supporter->config['shogyojiImageFolder']}", true);
                    break;

                case '多目的室使用届用画像フォルダID変更':
                    $this->supporter->pushMessage("多目的室使用届の画像を保存するためのGoogle DriveのフォルダのURLまたはIDを入力してください。
現在の多目的室使用届用画像フォルダ:
https://drive.google.com/drive/u/0/folders/{$this->supporter->config['tamokutekiImageFolder']}", true);
                    break;

                case '踊り場私物配備届用画像フォルダID変更':
                    $this->supporter->pushMessage("踊り場私物配備届の画像を保存するためのGoogle DriveのフォルダのURLまたはIDを入力してください。
現在の踊り場私物配備届用画像フォルダ:
https://drive.google.com/drive/u/0/folders/{$this->supporter->config['odoribaImageFolder']}", true);
                    break;

                case '309私物配備届用画像フォルダID変更':
                    $this->supporter->pushMessage("309私物配備届の画像を保存するためのGoogle DriveのフォルダのURLまたはIDを入力してください。
現在の309私物配備届用画像フォルダ:
https://drive.google.com/drive/u/0/folders/{$this->supporter->config['309ImageFolder']}", true);
                    break;

                case '自転車・バイク配備届用画像フォルダID変更':
                    $this->supporter->pushMessage("自転車・バイク配備届の画像を保存するためのGoogle DriveのフォルダのURLまたはIDを入力してください。
現在の自転車・バイク配備届用画像フォルダ:
https://drive.google.com/drive/u/0/folders/{$this->supporter->config['bikesImageFolder']}", true);
                    break;
            }

            // 選択肢
            $this->supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $this->supporter->storage['phases'][] = 'askingID';
        } else if ($lastPhase === 'confirmingReloadEvents') {
            switch ($message) {
                case 'はい':
                    // 再読み込み
                    $this->supporter->fetchEvents(true);

                    // 返信
                    $replyMessage = "行事データの再読み込みを行いました。
※全ての行事が読み込まれているか、日付の年があっているか確認してください。

読み込まれた行事(開始日順):
" . $this->getEventListString();
                    $this->supporter->pushMessage($replyMessage);
                    $this->supporter->resetForm();
                    return;
                default:
                    $this->supporter->askAgainBecauseWrongReply();
                    return;
            }
        } else if ($lastPhase === 'askingMaxGaiburaihoushasuu') {
            if ($this->storeOrAskAgain('最大外部来訪者数', $message))
                return;

            // 返信
            $this->supporter->pushMessage("最大外部来訪者数を変更しました。");
            $this->supporter->resetForm();
        } else if ($lastPhase === 'askingEndOfTerm') {
            if ($this->storeOrAskAgain('任期終了日', $message))
                return;

            // 返信
            $this->supporter->pushMessage('任期終了日変更を変更しました。');
            $this->supporter->resetForm();
        } else if ($lastPhase === 'askingPassword') {
            if ($this->storeOrAskAgain('合言葉', $message))
                return;

            // 返信
            $this->supporter->pushMessage("合言葉を設定しました。\n新たな管理者は合言葉をメッセージしてください。");
            $this->supporter->resetForm();
        } else {
            if ($this->storeOrAskAgain('Google ID', $message))
                return;

            $this->supporter->resetForm();
        }
    }

    protected function storeOrAskAgain(string $type, string|array $message): string
    {
        switch ($type) {
            case '設定項目':
                switch ($message) {
                    case '管理者用マニュアル表示':
                    case '行事データ再読み込み':
                    case '最大外部来訪者数変更':
                    case '任期終了日変更':
                    case '管理者変更':
                    case '行事スプレッドシートID変更':
                    case '出力先スプレッドシートID変更':
                    case '多目的室使用届用画像フォルダID変更':
                    case '舎生大会・諸行事届用画像フォルダID変更':
                    case '踊り場私物配備届用画像フォルダID変更':
                    case '309私物配備届用画像フォルダID変更':
                    case '自転車・バイク配備届用画像フォルダID変更':
                        $this->supporter->storage['unsavedAnswers']['設定項目'] = $message;
                        return '';
                }
                // 有効でなかった、もう一度質問文送信
                $this->supporter->askAgainBecauseWrongReply();
                return 'wrong-reply';
            case '最大外部来訪者数':
                $message = toHalfWidth($message);
                $count = preg_replace('/\D+/', '', $message);
                if ($count === '') {
                    $this->supporter->askAgainBecauseWrongReply("入力が不正です。\n数値で答えてください。");
                    return 'wrong-reply';
                }

                $count = (int)$count;
                $this->supporter->pushMessage("外部来訪者数:{$count}人");
                $this->supporter->config['maxGaiburaihoushasuu'] = $count;
                $this->supporter->storeConfig();
                return '';
            case '任期終了日':
                $date = stringToDate($message);
                if ($date === false) {
                    $year = date('Y');
                    $this->supporter->askAgainBecauseWrongReply("入力の形式が違うか、無効な日付です。\n「1130」または「{$year}1130」のように4桁または8桁で入力してください。");
                    return 'wrong-reply';
                }

                $dateString = dateToDateStringWithDay($date);
                $this->supporter->pushMessage("任期終了日:{$dateString}");

                $today = getDateAt0AM();
                if ($date < $today) {
                    $this->supporter->askAgainBecauseWrongReply("その日付はすでに過ぎています。\nもう一度入力してください。");
                    return 'wrong-reply';
                }

                $this->supporter->config['endOfTerm'] = $dateString;
                $this->supporter->storeConfig();
                return '';
            case '合言葉':
                if (mb_strlen($message) < 8) {
                    $this->supporter->askAgainBecauseWrongReply('合言葉は8文字以上としてください。');
                    return 'wrong-reply';
                }
                $this->supporter->config['password'] = $message;
                $this->supporter->storeConfig();
                return '';
            case 'Google ID':
                $id = $this->extractId($message);
                switch ($this->supporter->storage['unsavedAnswers']['設定項目']) {
                    case '行事スプレッドシートID変更':
                        if ($this->supporter->checkValidGoogleItem('variableSheets', $id)) {
                            $this->supporter->config['variableSheets'] = $id;
                            $this->supporter->storeConfig();
                            $this->supporter->fetchEvents(true);

                            // 返信
                            $replyMessage = "設定を保存、行事データを更新しました。

読み込まれた行事:
" . $this->getEventListString();
                            $this->supporter->pushMessage($replyMessage);
                            return '';
                        }
                        $this->supporter->askAgainBecauseWrongReply("入力されたIDのスプレッドシートにアクセスできませんでした。
ボットにスプレッドシートが共有されていないか、「行事」シートがない可能性があります。
もう一度入力してください。");
                        return 'wrong-reply';
                    case '出力先スプレッドシートID変更':
                        if ($this->supporter->checkValidGoogleItem('resultSheets', $id)) {
                            $this->supporter->config['resultSheets'] = $id;
                            $this->supporter->storeConfig();

                            // 返信
                            $this->supporter->pushMessage('書き込み可能なスプレッドシートであることを確認、設定を保存しました。');
                            return '';
                        }
                        $this->supporter->askAgainBecauseWrongReply("入力されたIDのスプレッドシートへの書き込みに失敗しました。
ボットにスプレッドシートが共有されていないか、編集権限が与えられていない可能性があります。
もう一度入力してください。");
                        return 'wrong-reply';
                    case '舎生大会・諸行事届用画像フォルダID変更':
                        if ($this->supporter->checkValidGoogleItem('shogyojiImageFolder', $id)) {
                            $this->supporter->config['shogyojiImageFolder'] = $id;
                            $this->supporter->storeConfig();

                            // 返信
                            $this->supporter->pushMessage('テストファイルのアップロード後削除に成功、設定を保存しました。');
                            return '';
                        }
                        $this->supporter->askAgainBecauseWrongReply("入力されたIDのフォルダへのテストファイルのアップロード、またはその削除に失敗しました。
ボットにフォルダが共有されていないか、管理者権限が与えられていない可能性があります。
ボットとの間に作成した共有ドライブ内のフォルダを使用し、ボットにコンテンツ管理者ではなく、管理者の権限を与えてください。
もう一度入力してください。");
                        return 'wrong-reply';
                    case '多目的室使用届用画像フォルダID変更':
                        if ($this->supporter->checkValidGoogleItem('tamokutekiImageFolder', $id)) {
                            $this->supporter->config['tamokutekiImageFolder'] = $id;
                            $this->supporter->storeConfig();

                            // 返信
                            $this->supporter->pushMessage('テストファイルのアップロードに成功、設定を保存しました。');
                            return '';
                        }
                        $this->supporter->askAgainBecauseWrongReply("入力されたIDのフォルダへのテストファイルのアップロードに失敗しました。
ボットにフォルダが共有されていないか、権限が与えられていない可能性があります。
もう一度入力してください。");
                        return 'wrong-reply';
                    case '踊り場私物配備届用画像フォルダID変更':
                        if ($this->supporter->checkValidGoogleItem('odoribaImageFolder', $id)) {
                            $this->supporter->config['odoribaImageFolder'] = $id;
                            $this->supporter->storeConfig();

                            // 返信
                            $this->supporter->pushMessage('テストファイルのアップロードに成功、設定を保存しました。');
                            return '';
                        }
                        $this->supporter->askAgainBecauseWrongReply("入力されたIDのフォルダへのテストファイルのアップロードに失敗しました。
ボットにフォルダが共有されていないか、権限が与えられていない可能性があります。
もう一度入力してください。");
                        return 'wrong-reply';
                    case '309私物配備届用画像フォルダID変更':
                        if ($this->supporter->checkValidGoogleItem('309ImageFolder', $id)) {
                            $this->supporter->config['309ImageFolder'] = $id;
                            $this->supporter->storeConfig();

                            // 返信
                            $this->supporter->pushMessage('テストファイルのアップロードに成功、設定を保存しました。');
                            return '';
                        }
                        $this->supporter->askAgainBecauseWrongReply("入力されたIDのフォルダへのテストファイルのアップロードに失敗しました。
ボットにフォルダが共有されていないか、権限が与えられていない可能性があります。
もう一度入力してください。");
                        return 'wrong-reply';
                    case '自転車・バイク配備届用画像フォルダID変更':
                        if ($this->supporter->checkValidGoogleItem('bikesImageFolder', $id)) {
                            $this->supporter->config['bikesImageFolder'] = $id;
                            $this->supporter->storeConfig();

                            // 返信
                            $this->supporter->pushMessage('テストファイルのアップロードに成功、設定を保存しました。');
                            return '';
                        }
                        $this->supporter->askAgainBecauseWrongReply("入力されたIDのフォルダへのテストファイルのアップロードに失敗しました。
ボットにフォルダが共有されていないか、権限が与えられていない可能性があります。
もう一度入力してください。");
                        return 'wrong-reply';
                }
        }
    }

    private function extractId(string $url): string
    {
        // '/'で分割して最も長い部分をidとする
        $splits = explode('/', $url);

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

    private function getEventListString(): string
    {
        $events = $this->supporter->fetchEvents();
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
