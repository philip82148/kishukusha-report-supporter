<?php

namespace KishukushaReportSupporter\Forms;

use KishukushaReportSupporter\KishukushaReportSupporter;
use KishukushaReportSupporter\SubmittableForm;

class Gaiburaihousha extends SubmittableForm
{
    public const HEADER = ['関係舎生の氏名', '外部来訪者名', '来訪日', '滞在開始時刻', '滞在終了時刻'];

    public static function form(KishukushaReportSupporter $supporter, array $message): void
    {
        if ($message['type'] !== 'text') {
            $supporter->askAgainBecauseWrongReply();
            return;
        }
        $message = $message['text'];

        // 一番最初
        if (count($supporter->storage['phases']) === 0) {
            $supporter->storage['unsavedAnswers']['関係舎生の氏名'] = $supporter->storage['userName'];

            // 質問文送信
            $supporter->pushText("外部来訪者の名前を「、」か改行で区切ってすべて入力してください。\n例:山田太郎、佐藤花子", true);

            // 選択肢表示
            $supporter->pushPreviousAnswerOptions('外部来訪者名');
            $supporter->pushUnsavedAnswerOption('外部来訪者名');
            $supporter->pushOptions(['キャンセル']);

            $supporter->storage['phases'][] = 'askingNames';
            return;
        }

        $lastPhase = $supporter->storage['phases'][count($supporter->storage['phases']) - 1];
        if ($lastPhase === 'askingNames') {
            if ($message !== '前の項目を修正する') {
                if (self::storeOrAskAgain($supporter, '外部来訪者名', $message))
                    return;
            }

            // 質問文送信
            $supporter->pushText('外部来訪者の人数を数値で入力してください。', true);

            // 選択肢
            $supporter->pushUnsavedAnswerOption('外部来訪者数');
            $supporter->pushOptions(['(自動算出)' => $supporter->storage['cache']['外部来訪者数']]);
            $supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $supporter->storage['phases'][] = 'askingNumber';
        } else if ($lastPhase === 'askingNumber') {
            if ($message !== '前の項目を修正する') {
                if (self::storeOrAskAgain($supporter, '外部来訪者数', $message))
                    return;
            }

            // 質問文
            $year = date('Y');
            $supporter->pushText("来訪日を4桁(年無し)または8桁(年有り)で入力してください。\n例:0506、{$year}0506", true);

            // 選択肢
            $supporter->pushUnsavedAnswerOption('来訪日');
            $supporter->pushOptions([dateToDateStringWithDay(), '前の項目を修正する', 'キャンセル']);

            $supporter->storage['phases'][] = 'askingDay';
        } else if ($lastPhase === 'askingDay') {
            if ($message !== '前の項目を修正する') {
                if (self::storeOrAskAgain($supporter, '来訪日', $message))
                    return;
            }

            // 質問文
            $supporter->pushText("滞在開始時刻を4桁で入力してください。\n例:1030", true);

            // 選択肢
            $supporter->pushUnsavedAnswerOption('滞在開始時刻');
            $now = date('H:i');
            if (self::checkIfGaiburaihouAllowed($now))
                $supporter->pushOptions([$now]);
            $supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $supporter->storage['phases'][] = 'askingStart';
        } else if ($lastPhase === 'askingStart') {
            if ($message !== '前の項目を修正する') {
                if (self::storeOrAskAgain($supporter, '滞在開始時刻', $message))
                    return;
            }

            // 質問文送信
            $supporter->pushText("滞在終了時刻を4桁で入力してください。\n例:1700", true);

            // 選択肢
            // unsavedAnswerOption
            $startTime = stringToTime($supporter->storage['unsavedAnswers']['滞在開始時刻']);
            $endTimeString = $supporter->storage['unsavedAnswers']['滞在終了時刻'] ?? '';
            if ($endTimeString !== '') {
                if (stringToTime($endTimeString) > $startTime)
                    $supporter->pushUnsavedAnswerOption('滞在終了時刻');
            }

            // 現在時刻
            $now = date('H:i');
            if (self::checkIfGaiburaihouAllowed($now)) {
                if (stringToTime($now) > $startTime)
                    $supporter->pushOptions([$now]);
            }

            $supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $supporter->storage['phases'][] = 'askingEnd';
        } else if ($lastPhase === 'askingEnd') {
            if (self::storeOrAskAgain($supporter, '滞在終了時刻', $message))
                return;

            self::confirm($supporter);

            $supporter->storage['phases'][] = 'confirming';
        } else if ($lastPhase === 'confirming') {
            if (!self::confirming($supporter, $message, false))
                return;

            // 質問
            $supporter->pushText('最後に、女性の数を入力すると舎生Lineへの告知文を生成します。', true);

            // 選択肢
            self::pushPreviousAnswerOptions($supporter, '外部来訪者の女性の数');
            $supporter->pushOptions(['キャンセル']);

            // 前の項目を修正する対策笑
            $supporter->storage['phases'] = ['askingFemale', 'askingFemale', 'askingFemale'];
        } else {
            $supporter->storage['phases'] = ['askingFemale', 'askingFemale', 'askingFemale'];
            if (self::storeOrAskAgain($supporter, '外部来訪者の女性の数', $message))
                return;

            $supporter->resetForm();
        }
    }

    protected static function submitForm(KishukushaReportSupporter $supporter): void
    {
        $answers = $supporter->storage['unsavedAnswers'];
        unset($answers['外部来訪者数']);
        $answersForSheets = array_values($answers);

        // 日付の曜日を取る
        $answersForSheets[2] = deleteParentheses($answersForSheets[2]);

        // 申請
        $supporter->submitForm($answers, $answersForSheets);

        // 次回のための回答の記録
        $supporter->pushPreviousAnswer('外部来訪者名', $answers['外部来訪者名']);
    }

    public static function pushAdminMessages(KishukushaReportSupporter $supporter, array $profile, array $answers, string $timeStamp, string $receiptNo): bool
    {
        $supporter->pushText(
            "{$answers['関係舎生の氏名']}が外部来訪者届を提出しました。
(TS:{$timeStamp})

チェック済み:
来訪日:{$answers['来訪日']}
来舎時間:{$answers['滞在開始時刻']}
退舎時間:{$answers['滞在終了時刻']}

未チェックの項目:
外部来訪者名:{$answers['外部来訪者名']}",
            false,
            ['name' => $profile['displayName'], 'iconUrl' => $profile['pictureUrl'] ?? 'https://dummy.com/']
        );
        $supporter->setLastQuestions();
        return false;
    }

    protected static function storeOrAskAgain(KishukushaReportSupporter $supporter, string $type, string|array $message): string
    {
        switch ($type) {
            case '外部来訪者名':
                $supporter->storage['unsavedAnswers']['外部来訪者名'] = $message;

                // 人数を数える
                $commaCount = mb_substr_count($message, ',') + mb_substr_count($message, '、') + mb_substr_count($message, "\n");
                $supporter->storage['cache']['外部来訪者数'] = ($commaCount + 1) . '人';

                return '';
            case '外部来訪者数':
            case '外部来訪者の女性の数':
                $message = toHalfWidth($message);
                $count = preg_replace('/\D+/', '', $message);
                if ($count === '') {
                    $supporter->askAgainBecauseWrongReply("入力が不正です。\n数値で答えてください。");
                    return 'wrong-reply';
                }

                $count = (int)$count;
                if ($type === '外部来訪者数') {
                    $supporter->pushText("外部来訪者数:{$count}人");

                    if ($count === 0) {
                        $supporter->askAgainBecauseWrongReply("正しくない人数です。\nもう一度入力してください。");
                        return 'wrong-reply';
                    } else if (!self::checkGaiburaihoushasuu($supporter, $count)) {
                        $supporter->askAgainBecauseWrongReply("現在外部来訪者数は{$supporter->config['maxGaiburaihoushasuu']}人に制限されています。\nもう一度入力してください。");
                        return 'wrong-reply';
                    }

                    $supporter->storage['unsavedAnswers']['外部来訪者数'] = $count . '人';
                    return '';
                }

                // 女性の数
                $totalCount = (int)preg_replace('/\D+/', '', $supporter->storage['unsavedAnswers']['外部来訪者数']);
                if ($count > $totalCount) {
                    $supporter->askAgainBecauseWrongReply("申請した人数を超えています。\nもう一度入力してください。");
                    return 'wrong-reply';
                }

                $femaleCount = $count;
                $maleCount = $totalCount - $femaleCount;

                $announcement = "【外部来訪者の件】\n";
                if ($maleCount) $announcement .= "男性{$maleCount}名、";
                if ($femaleCount) $announcement .= "女性{$femaleCount}名、";
                $announcement .= "{$supporter->storage['unsavedAnswers']['滞在開始時刻']}~{$supporter->storage['unsavedAnswers']['滞在終了時刻']}です。\nよろしくお願いいたします。";
                $supporter->pushText($announcement);
                self::pushPreviousAnswer($supporter, '外部来訪者の女性の数', $femaleCount . '人');
                return '';
            case '来訪日':
                $date = stringToDate($message);
                if ($date === false) {
                    $year = date('Y');
                    $supporter->askAgainBecauseWrongReply("入力の形式が違うか、無効な日付です。\n「0506」または「{$year}0506」のように4桁または8桁で入力してください。");
                    return 'wrong-reply';
                }

                $dateString = dateToDateStringWithDay($date);
                $supporter->pushText("来訪日:{$dateString}");
                $supporter->storage['unsavedAnswers']['来訪日'] = $dateString;
                return '';
            case '滞在開始時刻':
            case '滞在終了時刻':
                $stayTime = stringToTime($message);
                if ($stayTime === false) {
                    if ($type === '滞在開始時刻') {
                        $supporter->askAgainBecauseWrongReply("入力の形式が違うか、無効な時刻です。\n「1030」のように4桁で入力してください。");
                    } else {
                        $supporter->askAgainBecauseWrongReply("入力の形式が違うか、無効な時刻です。\n「1700」のように4桁で入力してください。");
                    }
                    return 'wrong-reply';
                }

                $stayTimeString = date('H:i', $stayTime);
                $supporter->pushText("{$type}:" . $stayTimeString);

                if (!self::checkIfGaiburaihouAllowed($stayTimeString)) {
                    $supporter->askAgainBecauseWrongReply("外部来訪者が認められているのは8:00~22:30です。\nもう一度入力してください。");
                    return 'wrong-reply';
                }

                if ($type === '滞在開始時刻') {
                    $supporter->storage['unsavedAnswers']['滞在開始時刻'] = $stayTimeString;
                    return '';
                } else {
                    if ($stayTime <= stringToTime($supporter->storage['unsavedAnswers']['滞在開始時刻'])) {
                        // 有効でなかった、もう一度質問文送信
                        $supporter->askAgainBecauseWrongReply("滞在開始時刻以前の時刻です。\nもう一度入力してください。");
                        return 'wrong-reply';
                    }

                    $supporter->storage['unsavedAnswers']['滞在終了時刻'] = $stayTimeString;
                    return '';
                }
        }
    }

    private static function pushPreviousAnswerOptions(KishukushaReportSupporter $supporter, string $type): void
    {
        if ($type !== '外部来訪者の女性の数') {
            $supporter->pushPreviousAnswerOptions($type);
            return;
        }

        if (!isset($supporter->storage['previousAnswers'][$type]))
            return;

        $name = $supporter->storage['unsavedAnswers']['外部来訪者名'];
        if (isset($supporter->storage['previousAnswers'][$type][$name]))
            $supporter->pushOptions($supporter->storage['previousAnswers'][$type][$name], false, true);
    }

    private static function pushPreviousAnswer(KishukushaReportSupporter $supporter, string $type, string $previousAnswer): void
    {
        if ($type !== '外部来訪者の女性の数') {
            $supporter->pushPreviousAnswer($type, $previousAnswer);
            return;
        }

        // 初めての回答
        if (!isset($supporter->storage['previousAnswers'][$type]))
            $supporter->storage['previousAnswers'][$type] = [];

        // 記録
        $supporter->storage['previousAnswers'][$type][$supporter->storage['unsavedAnswers']['外部来訪者名']] = [$previousAnswer];

        // もうすでに記録されていない外部来訪者名の女性の数は消す
        foreach ($supporter->storage['previousAnswers'][$type] as $name => $number) {
            if (!in_array($name, $supporter->storage['previousAnswers']['外部来訪者名'], true))
                unset($supporter->storage['previousAnswers'][$type][$name]);
        }
    }

    private static function checkGaiburaihoushasuu(KishukushaReportSupporter $supporter, int $count): bool
    {
        if ($supporter->config['maxGaiburaihoushasuu'] && $count > $supporter->config['maxGaiburaihoushasuu']) return false;
        return true;
    }

    private static function checkIfGaiburaihouAllowed(string $time): bool
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
