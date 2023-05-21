<?php

require_once __DIR__ . '/../includes.php';

class Tamokuteki extends FormTemplate
{
    public const HEADER = ['氏名', '多目的室の種類', '使用開始日', '使用開始時刻', '使用終了時刻', '目的・備考', '使用後の状態'];

    public function form(array $message): void
    {
        // 一番最初
        if (count($this->supporter->storage['phases']) === 0) {
            $this->supporter->storage['unsavedAnswers']['氏名'] = $this->supporter->storage['userName'];

            // 質問
            $this->supporter->pushMessage("使用した(する)多目的室を選んでください。", true);

            // 選択肢
            $this->supporter->pushOptions(['309号室', '308号室', '301号室', '209号室', '208号室', '201号室'], true);
            $this->supporter->pushUnsavedAnswerOption('多目的室の種類');
            $this->supporter->pushOptions(['キャンセル']);

            $this->supporter->storage['phases'][] = 'askingWhichRoom';
            return;
        }

        $lastPhase = $this->supporter->storage['phases'][count($this->supporter->storage['phases']) - 1];
        if ($lastPhase === 'askingWhichRoom') {
            if ($message['type'] !== 'text') {
                $this->supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            if ($message !== '前の項目を修正する') {
                if ($this->storeOrAskAgain('多目的室の種類', $message))
                    return;
            }

            // 質問文
            $year = date('Y');
            $this->supporter->pushMessage("使用開始日を4桁(年無し)または8桁(年有り)で入力してください。\n例:0506、{$year}0506", true);

            // 選択肢
            $this->supporter->pushUnsavedAnswerOption('使用開始日');
            $this->supporter->pushOptions([dateToDateStringWithDay(), '前の項目を修正する', 'キャンセル']);

            $this->supporter->storage['phases'][] = 'askingDay';
        } else if ($lastPhase === 'askingDay') {
            if ($message['type'] !== 'text') {
                $this->supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            if ($message !== '前の項目を修正する') {
                if ($this->storeOrAskAgain('使用開始日', $message))
                    return;
            }

            // 質問文
            $this->supporter->pushMessage("使用開始時刻を4桁で入力してください。\n例:1000", true);

            // 選択肢
            $this->supporter->pushUnsavedAnswerOption('使用開始時刻');
            $this->supporter->pushOptions([date('H:i'), '前の項目を修正する', 'キャンセル']);

            $this->supporter->storage['phases'][] = 'askingStart';
        } else if ($lastPhase === 'askingStart') {
            if ($message['type'] !== 'text') {
                $this->supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            if ($message !== '前の項目を修正する') {
                if ($this->storeOrAskAgain('使用開始時刻', $message))
                    return;
            }

            // 質問文送信
            $this->supporter->pushMessage("目的を入力してください。\n備考があれば備考も記入してください。\n例:就職活動。\n使用前からちりくずが散乱していた。", true);

            // 選択肢
            $this->supporter->pushPreviousAnswerOptions('目的・備考');
            $this->supporter->pushUnsavedAnswerOption('目的・備考');
            $this->supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $this->supporter->storage['phases'][] = 'askingPurpose';
        } else if ($lastPhase === 'askingPurpose') {
            if ($message['type'] !== 'text') {
                $this->supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            if ($message !== '前の項目を修正する')
                $this->supporter->storage['unsavedAnswers']['目的・備考'] = $message;

            // 質問文送信
            $this->supporter->pushMessage('使用後の状態を写真で送信してください。', true);

            // 選択肢
            $this->supporter->pushUnsavedAnswerOption('使用後の状態', 'image');
            $this->supporter->pushImageOption();
            $this->supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $this->supporter->storage['phases'][] = 'askingImage';
        } else if ($lastPhase === 'askingImage') {
            if ($message['type'] === 'image') {
                if ($this->storeOrAskAgain('使用後の状態', $message))
                    return;
            } else {
                if ($message['type'] !== 'text') {
                    $this->supporter->askAgainBecauseWrongReply();
                    return;
                }
                $message = $message['text'];

                if ($message !== '前の項目を修正する') {
                    if ($message !== '最後に送信した画像' || !isset($this->supporter->storage['unsavedAnswers']['使用後の状態'])) {
                        $this->supporter->askAgainBecauseWrongReply();
                        return;
                    }
                }
            }

            // 質問文送信
            $this->supporter->pushMessage("使用終了時刻を4桁で入力してください。\n例:1100\n※使用開始時刻より前の時刻は自動的に翌日の時刻と解釈されます。", true);

            // 選択肢
            // unsavedAnswerOption((翌日)を取る)
            if (isset($this->supporter->storage['unsavedAnswers']['使用終了時刻'])) {
                $this->supporter->storage['unsavedAnswers']['使用終了時刻'] = deleteParentheses($this->supporter->storage['unsavedAnswers']['使用終了時刻']);
                $this->supporter->pushUnsavedAnswerOption('使用終了時刻');
            }

            // その他
            $this->supporter->pushOptions([date('H:i'), '前の項目を修正する', 'キャンセル']);

            $this->supporter->storage['phases'][] = 'askingEnd';
        } else if ($lastPhase === 'askingEnd') {
            if ($message['type'] !== 'text') {
                $this->supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            if ($this->storeOrAskAgain('使用終了時刻', $message))
                return;

            // 質問・選択肢
            $this->confirm(['使用後の状態' => 'image']);

            $this->supporter->storage['phases'][] = 'confirming';
        } else {
            if ($message['type'] !== 'text') {
                $this->supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            // 質問・選択肢
            $this->confirming($message);
        }
    }

    protected function applyForm(): void
    {
        $answers = $this->supporter->storage['unsavedAnswers'];

        // ドライブに保存
        $imageFileName = $answers['使用後の状態'];
        $driveFileName = "{$answers['多目的室の種類']}_{$answers['使用開始日']}_{$answers['使用開始時刻']}-{$answers['使用終了時刻']}_{$this->supporter->storage['userName']}.jpg";
        $answers['使用後の状態'] = $this->supporter->saveToDrive($imageFileName, $driveFileName, $this->supporter->config['tamokutekiImageFolder']);

        $answersForSheets = array_values($answers);

        // 日付の曜日と時刻の(翌日)を取る
        $answersForSheets[2] = deleteParentheses($answersForSheets[2]);
        $answersForSheets[4] = deleteParentheses($answersForSheets[4]);

        // 申請
        $this->supporter->applyForm($answers, $answersForSheets);

        // 次回のための回答の記録
        $this->supporter->pushPreviousAnswer('目的・備考', $answers['目的・備考']);
    }

    public function pushAdminMessages(array $profile, array $answers, string $timeStamp, string $receiptNo): bool
    {
        return false;
    }

    protected function storeOrAskAgain(string $type, string|array $message): string
    {
        switch ($type) {
            case '多目的室の種類':
                switch ($message) {
                    case '309号室':
                    case '308号室':
                    case '301号室':
                    case '209号室':
                    case '208号室':
                    case '201号室':
                        $this->supporter->storage['unsavedAnswers']['多目的室の種類'] = $message;
                        return '';
                }
                $this->supporter->askAgainBecauseWrongReply();
                return 'wrong-reply';
            case '使用開始日':
                $date = stringToDate($message);
                if ($date === false) {
                    $year = date('Y');
                    $this->supporter->askAgainBecauseWrongReply("入力の形式が違うか、無効な日付です。\n「0506」または「{$year}0506」のように4桁または8桁で入力してください。");
                    return 'wrong-reply';
                }

                $dateString = dateToDateStringWithDay($date);
                $this->supporter->pushMessage("使用開始日:{$dateString}");
                $this->supporter->storage['unsavedAnswers']['使用開始日'] = $dateString;
                return '';
            case '使用開始時刻':
            case '使用終了時刻':
                $stayTime = stringToTime($message);
                if ($stayTime === false) {
                    if ($type === '滞在開始時刻') {
                        $this->supporter->askAgainBecauseWrongReply("入力の形式が違うか、無効な時刻です。\n「1000」のように4桁で入力してください。");
                    } else {
                        $this->supporter->askAgainBecauseWrongReply("入力の形式が違うか、無効な時刻です。\n「1100」のように4桁で入力してください。");
                    }
                    return 'wrong-reply';
                }

                $stayTimeString = date('H:i', $stayTime);
                if ($type === '使用開始時刻') {
                    $this->supporter->pushMessage("使用開始時刻:{$stayTimeString}");
                    $this->supporter->storage['unsavedAnswers']['使用開始時刻'] = $stayTimeString;
                    return '';
                } else {
                    if ($stayTime <= stringToTime($this->supporter->storage['unsavedAnswers']['使用開始時刻']))
                        $stayTimeString .= '(翌日)';

                    $this->supporter->pushMessage("使用終了時刻:{$stayTimeString}");
                    insertToAssociativeArray($this->supporter->storage['unsavedAnswers'], 4, ['使用終了時刻' => $stayTimeString]);
                    return '';
                }
            case '使用後の状態':
                $fileName = $this->supporter->downloadContent($message);
                $this->supporter->storage['unsavedAnswers']['使用後の状態'] = $fileName;

                // 将来的にゴミ箱へ移動するための予約
                if (!isset($this->supporter->storage['cache']['一時ファイル']))
                    $this->supporter->storage['cache']['一時ファイル'] = [];
                $this->supporter->storage['cache']['一時ファイル'][] = $fileName;
                return '';
        }
    }
}
