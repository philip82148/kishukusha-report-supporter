<?php

namespace KishukushaReportSupporter\Forms;

use KishukushaReportSupporter\FormTemplate;

class Gaiburaihousha extends FormTemplate
{
    public const HEADER = ['関係舎生の氏名', '外部来訪者名', '来訪日', '滞在開始時刻', '滞在終了時刻'];

    public function form(array $message): void
    {
        if ($message['type'] !== 'text') {
            $this->supporter->askAgainBecauseWrongReply();
            return;
        }
        $message = $message['text'];

        // 一番最初
        if (count($this->supporter->storage['phases']) === 0) {
            $this->supporter->storage['unsavedAnswers']['関係舎生の氏名'] = $this->supporter->storage['userName'];

            // 質問文送信
            $this->supporter->pushMessage("外部来訪者の名前を「、」か改行で区切ってすべて入力してください。\n例:山田太郎、佐藤花子", true);

            // 選択肢表示
            $this->supporter->pushPreviousAnswerOptions('外部来訪者名');
            $this->supporter->pushUnsavedAnswerOption('外部来訪者名');
            $this->supporter->pushOptions(['キャンセル']);

            $this->supporter->storage['phases'][] = 'askingNames';
            return;
        }

        $lastPhase = $this->supporter->storage['phases'][count($this->supporter->storage['phases']) - 1];
        if ($lastPhase === 'askingNames') {
            if ($message !== '前の項目を修正する') {
                if ($this->storeOrAskAgain('外部来訪者名', $message))
                    return;
            }

            // 質問文送信
            $this->supporter->pushMessage('外部来訪者の人数を数値で入力してください。', true);

            // 選択肢
            $this->supporter->pushUnsavedAnswerOption('外部来訪者数');
            $this->supporter->pushOptions(['(自動算出)' => $this->supporter->storage['cache']['外部来訪者数']]);
            $this->supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $this->supporter->storage['phases'][] = 'askingNumber';
        } else if ($lastPhase === 'askingNumber') {
            if ($message !== '前の項目を修正する') {
                if ($this->storeOrAskAgain('外部来訪者数', $message))
                    return;
            }

            // 質問文
            $year = date('Y');
            $this->supporter->pushMessage("来訪日を4桁(年無し)または8桁(年有り)で入力してください。\n例:0506、{$year}0506", true);

            // 選択肢
            $this->supporter->pushUnsavedAnswerOption('来訪日');
            $this->supporter->pushOptions([dateToDateStringWithDay(), '前の項目を修正する', 'キャンセル']);

            $this->supporter->storage['phases'][] = 'askingDay';
        } else if ($lastPhase === 'askingDay') {
            if ($message !== '前の項目を修正する') {
                if ($this->storeOrAskAgain('来訪日', $message))
                    return;
            }

            // 質問文
            $this->supporter->pushMessage("滞在開始時刻を4桁で入力してください。\n例:1030", true);

            // 選択肢
            $this->supporter->pushUnsavedAnswerOption('滞在開始時刻');
            $now = date('H:i');
            if ($this->checkIfGaiburaihouAllowed($now))
                $this->supporter->pushOptions([$now]);
            $this->supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $this->supporter->storage['phases'][] = 'askingStart';
        } else if ($lastPhase === 'askingStart') {
            if ($message !== '前の項目を修正する') {
                if ($this->storeOrAskAgain('滞在開始時刻', $message))
                    return;
            }

            // 質問文送信
            $this->supporter->pushMessage("滞在終了時刻を4桁で入力してください。\n例:1700", true);

            // 選択肢
            // unsavedAnswerOption
            $startTime = stringToTime($this->supporter->storage['unsavedAnswers']['滞在開始時刻']);
            $endTimeString = $this->supporter->storage['unsavedAnswers']['滞在終了時刻'] ?? '';
            if ($endTimeString !== '') {
                if (stringToTime($endTimeString) > $startTime)
                    $this->supporter->pushUnsavedAnswerOption('滞在終了時刻');
            }

            // 現在時刻
            $now = date('H:i');
            if ($this->checkIfGaiburaihouAllowed($now)) {
                if (stringToTime($now) > $startTime)
                    $this->supporter->pushOptions([$now]);
            }

            $this->supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $this->supporter->storage['phases'][] = 'askingEnd';
        } else if ($lastPhase === 'askingEnd') {
            if ($this->storeOrAskAgain('滞在終了時刻', $message))
                return;

            $this->confirm();

            $this->supporter->storage['phases'][] = 'confirming';
        } else if ($lastPhase === 'confirming') {
            if (!$this->confirming($message, false))
                return;

            // 質問
            $this->supporter->pushMessage('最後に、女性の数を入力すると舎生Lineへの告知文を生成します。', true);

            // 選択肢
            $this->supporter->pushPreviousAnswerOptions('外部来訪者の女性の数');
            $this->supporter->pushOptions(['キャンセル']);

            // 前の項目を修正する対策笑
            $this->supporter->storage['phases'] = ['askingFemale', 'askingFemale', 'askingFemale'];
        } else {
            $this->supporter->storage['phases'] = ['askingFemale', 'askingFemale', 'askingFemale'];
            if ($this->storeOrAskAgain('外部来訪者の女性の数', $message))
                return;

            $this->supporter->resetForm();
        }
    }

    protected function applyForm(): void
    {
        $answers = $this->supporter->storage['unsavedAnswers'];
        unset($answers['外部来訪者数']);
        $answersForSheets = array_values($answers);

        // 日付の曜日を取る
        $answersForSheets[2] = deleteParentheses($answersForSheets[2]);

        // 申請
        $this->supporter->applyForm($answers, $answersForSheets);

        // 次回のための回答の記録
        $this->supporter->pushPreviousAnswer('外部来訪者名', $answers['外部来訪者名']);
    }

    public function pushAdminMessages(array $profile, array $answers, string $timeStamp, string $receiptNo): bool
    {
        $this->supporter->pushMessage(
            "{$answers['関係舎生の氏名']}(`{$profile['displayName']}`)が外部来訪者届を提出しました。
(TS:{$timeStamp})

チェック済み:
来訪日:{$answers['来訪日']}
来舎時間:{$answers['滞在開始時刻']}
退舎時間:{$answers['滞在終了時刻']}

未チェックの項目:
外部来訪者名:{$answers['外部来訪者名']}",
            false,
            'text',
            ['name' => $profile['displayName'], 'iconUrl' => $profile['pictureUrl'] ?? 'https://dummy.com']
        );
        $this->supporter->setLastQuestions();
        return false;
    }

    protected function storeOrAskAgain(string $type, string|array $message): string
    {
        switch ($type) {
            case '外部来訪者名':
                $this->supporter->storage['unsavedAnswers']['外部来訪者名'] = $message;

                // 人数を数える
                $commaCount = mb_substr_count($message, ',') + mb_substr_count($message, '、') + mb_substr_count($message, "\n");
                $this->supporter->storage['cache']['外部来訪者数'] = ($commaCount + 1) . '人';

                return '';
            case '外部来訪者数':
            case '外部来訪者の女性の数':
                $message = toHalfWidth($message);
                $count = preg_replace('/\D+/', '', $message);
                if ($count === '') {
                    $this->supporter->askAgainBecauseWrongReply("入力が不正です。\n数値で答えてください。");
                    return 'wrong-reply';
                }

                $count = (int)$count;
                if ($type === '外部来訪者数') {
                    $this->supporter->pushMessage("外部来訪者数:{$count}人");

                    if ($count === 0) {
                        $this->supporter->askAgainBecauseWrongReply("正しくない人数です。\nもう一度入力してください。");
                        return 'wrong-reply';
                    } else if (!$this->checkGaiburaihoushasuu($count)) {
                        $this->supporter->askAgainBecauseWrongReply("現在外部来訪者数は{$this->supporter->config['maxGaiburaihoushasuu']}人に制限されています。\nもう一度入力してください。");
                        return 'wrong-reply';
                    }

                    $this->supporter->storage['unsavedAnswers']['外部来訪者数'] = $count . '人';
                    return '';
                }

                // 女性の数
                $totalCount = (int)preg_replace('/\D+/', '', $this->supporter->storage['unsavedAnswers']['外部来訪者数']);
                if ($count > $totalCount) {
                    $this->supporter->askAgainBecauseWrongReply("申請した人数を超えています。\nもう一度入力してください。");
                    return 'wrong-reply';
                }

                $femaleCount = $count;
                $maleCount = $totalCount - $femaleCount;

                $announcement = "【外部来訪者の件】\n";
                if ($maleCount) $announcement .= "男性{$maleCount}名、";
                if ($femaleCount) $announcement .= "女性{$femaleCount}名、";
                $announcement .= "{$this->supporter->storage['unsavedAnswers']['滞在開始時刻']}~{$this->supporter->storage['unsavedAnswers']['滞在終了時刻']}です。\nよろしくお願いいたします。";
                $this->supporter->pushMessage($announcement);
                $this->supporter->pushPreviousAnswer('外部来訪者の女性の数', $femaleCount . '人');
                return '';
            case '来訪日':
                $date = stringToDate($message);
                if ($date === false) {
                    $year = date('Y');
                    $this->supporter->askAgainBecauseWrongReply("入力の形式が違うか、無効な日付です。\n「0506」または「{$year}0506」のように4桁または8桁で入力してください。");
                    return 'wrong-reply';
                }

                $dateString = dateToDateStringWithDay($date);
                $this->supporter->pushMessage("来訪日:{$dateString}");
                $this->supporter->storage['unsavedAnswers']['来訪日'] = $dateString;
                return '';
            case '滞在開始時刻':
            case '滞在終了時刻':
                $stayTime = stringToTime($message);
                if ($stayTime === false) {
                    if ($type === '滞在開始時刻') {
                        $this->supporter->askAgainBecauseWrongReply("入力の形式が違うか、無効な時刻です。\n「1030」のように4桁で入力してください。");
                    } else {
                        $this->supporter->askAgainBecauseWrongReply("入力の形式が違うか、無効な時刻です。\n「1700」のように4桁で入力してください。");
                    }
                    return 'wrong-reply';
                }

                $stayTimeString = date('H:i', $stayTime);
                $this->supporter->pushMessage("{$type}:" . $stayTimeString);

                if (!$this->checkIfGaiburaihouAllowed($stayTimeString)) {
                    $this->supporter->askAgainBecauseWrongReply("外部来訪者が認められない時刻です。\nもう一度入力してください。");
                    return 'wrong-reply';
                }

                if ($type === '滞在開始時刻') {
                    $this->supporter->storage['unsavedAnswers']['滞在開始時刻'] = $stayTimeString;
                    return '';
                } else {
                    if ($stayTime <= stringToTime($this->supporter->storage['unsavedAnswers']['滞在開始時刻'])) {
                        // 有効でなかった、もう一度質問文送信
                        $this->supporter->askAgainBecauseWrongReply("滞在開始時刻以前の時刻です。\nもう一度入力してください。");
                        return 'wrong-reply';
                    }

                    $this->supporter->storage['unsavedAnswers']['滞在終了時刻'] = $stayTimeString;
                    return '';
                }
        }
    }

    private function checkGaiburaihoushasuu(int $count): bool
    {
        if ($this->supporter->config['maxGaiburaihoushasuu'] && $count > $this->supporter->config['maxGaiburaihoushasuu']) return false;
        return true;
    }

    private function checkIfGaiburaihouAllowed(string $time): bool
    {
        $time = stringToTime($time);

        // 時間内か
        $start = strtotime('2022/1/1 8:00');
        if ($time < $start) return false;
        $end = strtotime('2022/1/1 22:30');
        if ($time > $end) return false;
        return true;
    }
}
