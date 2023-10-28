<?php

namespace KishukushaReportSupporter\Forms;

use KishukushaReportSupporter\SubmittableForm;
use KishukushaReportSupporter\KishukushaReportSupporter;

class Tamokuteki extends SubmittableForm
{
    public const HEADER = ['氏名', '多目的室の種類', '使用開始日', '使用開始時刻', '使用終了時刻', '目的・備考', '使用後の状態'];

    public static function form(KishukushaReportSupporter $supporter, array $message): void
    {
        // 一番最初
        if (count($supporter->storage['phases']) === 0) {
            $supporter->storage['unsavedAnswers']['氏名'] = $supporter->storage['userName'];

            // 質問
            $supporter->pushText("使用した(する)多目的室を選んでください。", true);

            // 選択肢
            $supporter->pushOptions(['309号室', '308号室', '301号室', '209号室', '208号室', '201号室'], true);
            $supporter->pushUnsavedAnswerOption('多目的室の種類');
            $supporter->pushOptions(['キャンセル']);

            $supporter->storage['phases'][] = 'askingWhichRoom';
            return;
        }

        $lastPhase = $supporter->storage['phases'][count($supporter->storage['phases']) - 1];
        if ($lastPhase === 'askingWhichRoom') {
            if ($message['type'] !== 'text') {
                $supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            if ($message !== '前の項目を修正する') {
                if (self::storeOrAskAgain($supporter, '多目的室の種類', $message))
                    return;
            }

            // 質問文
            $year = date('Y');
            $supporter->pushText("使用開始日を4桁(年無し)または8桁(年有り)で入力してください。\n例:0506、{$year}0506", true);

            // 選択肢
            $supporter->pushUnsavedAnswerOption('使用開始日');
            $supporter->pushOptions([dateToDateStringWithDay(), '前の項目を修正する', 'キャンセル']);

            $supporter->storage['phases'][] = 'askingDay';
        } else if ($lastPhase === 'askingDay') {
            if ($message['type'] !== 'text') {
                $supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            if ($message !== '前の項目を修正する') {
                if (self::storeOrAskAgain($supporter, '使用開始日', $message))
                    return;
            }

            // 質問文
            $supporter->pushText("使用開始時刻を4桁で入力してください。\n例:1000", true);

            // 選択肢
            $supporter->pushUnsavedAnswerOption('使用開始時刻');
            $supporter->pushOptions([date('H:i'), '前の項目を修正する', 'キャンセル']);

            $supporter->storage['phases'][] = 'askingStart';
        } else if ($lastPhase === 'askingStart') {
            if ($message['type'] !== 'text') {
                $supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            if ($message !== '前の項目を修正する') {
                if (self::storeOrAskAgain($supporter, '使用開始時刻', $message))
                    return;
            }

            // 質問文送信
            $supporter->pushText("目的を入力してください。\n備考があれば備考も記入してください。\n例:就職活動。\n使用前からちりくずが散乱していた。", true);

            // 選択肢
            $supporter->pushPreviousAnswerOptions('目的・備考');
            $supporter->pushUnsavedAnswerOption('目的・備考');
            $supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $supporter->storage['phases'][] = 'askingPurpose';
        } else if ($lastPhase === 'askingPurpose') {
            if ($message['type'] !== 'text') {
                $supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            if ($message !== '前の項目を修正する')
                $supporter->storage['unsavedAnswers']['目的・備考'] = $message;

            // 質問文送信
            $supporter->pushText('使用後の状態を写真で送信してください。', true);

            // 選択肢
            $supporter->pushUnsavedAnswerOption('使用後の状態', 'image');
            $supporter->pushImageOption();
            $supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $supporter->storage['phases'][] = 'askingImage';
        } else if ($lastPhase === 'askingImage') {
            if ($message['type'] === 'image') {
                if (self::storeOrAskAgain($supporter, '使用後の状態', $message))
                    return;
            } else {
                if ($message['type'] !== 'text') {
                    $supporter->askAgainBecauseWrongReply();
                    return;
                }
                $message = $message['text'];

                if ($message !== '前の項目を修正する') {
                    if ($message !== '最後に送信した画像' || !isset($supporter->storage['unsavedAnswers']['使用後の状態'])) {
                        $supporter->askAgainBecauseWrongReply();
                        return;
                    }
                }
            }

            // 質問文送信
            $supporter->pushText("使用終了時刻を4桁で入力してください。\n例:1100\n※使用開始時刻より前の時刻は自動的に翌日の時刻と解釈されます。", true);

            // 選択肢
            // unsavedAnswerOption((翌日)を取る)
            if (isset($supporter->storage['unsavedAnswers']['使用終了時刻'])) {
                $supporter->storage['unsavedAnswers']['使用終了時刻'] = deleteParentheses($supporter->storage['unsavedAnswers']['使用終了時刻']);
                $supporter->pushUnsavedAnswerOption('使用終了時刻');
            }

            // その他
            $supporter->pushOptions([date('H:i'), '前の項目を修正する', 'キャンセル']);

            $supporter->storage['phases'][] = 'askingEnd';
        } else if ($lastPhase === 'askingEnd') {
            if ($message['type'] !== 'text') {
                $supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            if (self::storeOrAskAgain($supporter, '使用終了時刻', $message))
                return;

            // 質問・選択肢
            self::confirm($supporter, ['使用後の状態' => 'image']);

            $supporter->storage['phases'][] = 'confirming';
        } else {
            if ($message['type'] !== 'text') {
                $supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            // 質問・選択肢
            self::confirming($supporter, $message);
        }
    }

    protected static function submitForm(KishukushaReportSupporter $supporter): void
    {
        $answers = $supporter->storage['unsavedAnswers'];

        // ドライブに保存
        $imageFileName = $answers['使用後の状態'];
        $driveFileName = "{$answers['多目的室の種類']}_{$answers['使用開始日']}_{$answers['使用開始時刻']}-{$answers['使用終了時刻']}_{$supporter->storage['userName']}.jpg";
        $answers['使用後の状態'] = $supporter->saveToDrive($imageFileName, $driveFileName, $supporter->config['generalImageFolderId'], '多目的室使用届');

        $answersForSheets = array_values($answers);

        // 日付の曜日と時刻の(翌日)を取る
        $answersForSheets[2] = deleteParentheses($answersForSheets[2]);
        $answersForSheets[4] = deleteParentheses($answersForSheets[4]);

        // 申請
        $supporter->submitForm($answers, $answersForSheets);

        // 次回のための回答の記録
        $supporter->pushPreviousAnswer('目的・備考', $answers['目的・備考']);
    }

    public static function pushAdminMessages(KishukushaReportSupporter $supporter, array $profile, array $answers, string $timeStamp, string $receiptNo): bool
    {
        return false;
    }

    protected static function storeOrAskAgain(KishukushaReportSupporter $supporter, string $type, string|array $message): string
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
                        $supporter->storage['unsavedAnswers']['多目的室の種類'] = $message;
                        return '';
                }
                $supporter->askAgainBecauseWrongReply();
                return 'wrong-reply';
            case '使用開始日':
                $date = stringToDate($message);
                if ($date === false) {
                    $year = date('Y');
                    $supporter->askAgainBecauseWrongReply("入力の形式が違うか、無効な日付です。\n「0506」または「{$year}0506」のように4桁または8桁で入力してください。");
                    return 'wrong-reply';
                }

                $dateString = dateToDateStringWithDay($date);
                $supporter->pushText("使用開始日:{$dateString}");
                $supporter->storage['unsavedAnswers']['使用開始日'] = $dateString;
                return '';
            case '使用開始時刻':
            case '使用終了時刻':
                $stayTime = stringToTime($message);
                if ($stayTime === false) {
                    if ($type === '滞在開始時刻') {
                        $supporter->askAgainBecauseWrongReply("入力の形式が違うか、無効な時刻です。\n「1000」のように4桁で入力してください。");
                    } else {
                        $supporter->askAgainBecauseWrongReply("入力の形式が違うか、無効な時刻です。\n「1100」のように4桁で入力してください。");
                    }
                    return 'wrong-reply';
                }

                $stayTimeString = date('H:i', $stayTime);
                if ($type === '使用開始時刻') {
                    $supporter->pushText("使用開始時刻:{$stayTimeString}");
                    $supporter->storage['unsavedAnswers']['使用開始時刻'] = $stayTimeString;
                    return '';
                } else {
                    if ($stayTime <= stringToTime($supporter->storage['unsavedAnswers']['使用開始時刻']))
                        $stayTimeString .= '(翌日)';

                    $supporter->pushText("使用終了時刻:{$stayTimeString}");
                    insertToAssociativeArray($supporter->storage['unsavedAnswers'], 4, ['使用終了時刻' => $stayTimeString]);
                    return '';
                }
            case '使用後の状態':
                $fileName = $supporter->downloadContent($message);
                $supporter->storage['unsavedAnswers']['使用後の状態'] = $fileName;

                // 将来的にゴミ箱へ移動するための予約
                if (!isset($supporter->storage['cache']['一時ファイル']))
                    $supporter->storage['cache']['一時ファイル'] = [];
                $supporter->storage['cache']['一時ファイル'][] = $fileName;
                return '';
        }
    }
}
