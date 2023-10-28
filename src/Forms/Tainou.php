<?php

namespace KishukushaReportSupporter\Forms;

use KishukushaReportSupporter\KishukushaReportSupporter;
use KishukushaReportSupporter\SubmittableForm;

class Tainou extends SubmittableForm
{
    public const HEADER = ['氏名', '滞納月', '理由', '予定納入日', '財務の承認'];

    public static function form(KishukushaReportSupporter $supporter, array $message): void
    {
        if ($message['type'] !== 'text') {
            $supporter->askAgainBecauseWrongReply();
            return;
        }
        $message = $message['text'];

        // 一番最初
        if (count($supporter->storage['phases']) === 0) {
            $supporter->storage['unsavedAnswers']['氏名'] = $supporter->storage['userName'];

            // 質問
            $year = date('Y');
            $supporter->pushText("滞納月を2桁(年無し)または6桁(年有り)で入力してください。\n例:10、{$year}10", true);

            // 選択肢
            $supporter->pushUnsavedAnswerOption('滞納月');
            $supporter->pushOptions([monthToString(), 'キャンセル']);

            $supporter->storage['phases'][] = 'askingMonth';
            return;
        }

        $lastPhase = $supporter->storage['phases'][count($supporter->storage['phases']) - 1];
        if ($lastPhase === 'askingMonth') {
            if ($message !== '前の項目を修正する') {
                if (self::storeOrAskAgain($supporter, '滞納月', $message))
                    return;
            }

            // 質問
            $supporter->pushText("理由を入力してください。", true);

            // 選択肢
            $supporter->pushUnsavedAnswerOption('理由');
            $supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $supporter->storage['phases'][] = 'askingReason';
        } else if ($lastPhase === 'askingReason') {
            if ($message !== '前の項目を修正する')
                $supporter->storage['unsavedAnswers']['理由'] = $message;

            // 質問
            $year = date('Y');
            $supporter->pushText("予定納入日を4桁(年無し)または8桁(年有り)で入力してください。\n例:1106、{$year}1106", true);

            // 選択肢
            $supporter->pushUnsavedAnswerOption('予定納入日');
            $supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $supporter->storage['phases'][] = 'askingPlan';
        } else if ($lastPhase === 'askingPlan') {
            if ($message !== '前の項目を修正する') {
                if (self::storeOrAskAgain($supporter, '予定納入日', $message))
                    return;
            }

            // 質問・選択肢
            self::confirm($supporter);

            $supporter->storage['phases'][] = 'confirming';
        } else {
            // 質問・選択肢
            self::confirming($supporter, $message);
        }
    }

    protected static function submitForm(KishukushaReportSupporter $supporter): void
    {
        $answers = $supporter->storage['unsavedAnswers'];
        $answersForSheets = array_values($answers);

        // 日付の曜日を取る
        $answersForSheets[3] = deleteParentheses($answersForSheets[3]);

        // 申請
        $supporter->submitForm($answers, $answersForSheets, true, '', $supporter->createOrTransferZaimu());
    }

    public static function pushAdminMessages(KishukushaReportSupporter $supporter, array $profile, array $answers, string $timeStamp, string $receiptNo): bool
    {
        $supporter->pushText(
            "{$answers['氏名']}(`{$profile['displayName']}`)が滞納届を提出しました。
(TS:{$timeStamp})

チェック済み:
滞納月:{$answers['滞納月']}

未チェックの項目:
理由:{$answers['理由']}
予定納入日:{$answers['予定納入日']}",
            true,
            ['name' => $profile['displayName'], 'iconUrl' => $profile['pictureUrl'] ?? 'https://dummy.com/']
        );
        $supporter->pushOptions(['承認する', '直接伝えた', '一番最後に見る']);
        return true;
    }

    protected static function storeOrAskAgain(KishukushaReportSupporter $supporter, string $type, string|array $message): string
    {
        switch ($type) {
            case '滞納月':
                $month = stringToMonth($message);
                if ($month === false) {
                    $year = date('Y');
                    $supporter->askAgainBecauseWrongReply("入力の形式が違うか、無効な月です。\n「10」または「{$year}10」のように2桁または6桁で入力してください。");
                    return 'wrong-reply';
                }

                $monthString = monthToString($month);
                $supporter->pushText("滞納月:{$monthString}");

                $thisMonth = getMonthOn1st();
                if ($month < $thisMonth) {
                    $supporter->askAgainBecauseWrongReply('今月以降の月を入力してください。');
                    return 'wrong-reply';
                }

                $supporter->storage['unsavedAnswers']['滞納月'] = $monthString;
                return '';
            case '予定納入日':
                $date = stringToDate($message);
                if ($date === false) {
                    $year = date('Y');
                    $supporter->askAgainBecauseWrongReply("入力の形式が違うか、無効な日付です。\n「1106」または「{$year}1106」のように4桁または8桁で入力してください。");
                    return 'wrong-reply';
                }

                $dateString = dateToDateStringWithDay($date);
                $supporter->pushText("{$type}:{$dateString}");

                $today = getDateAt0AM();
                if ($date < $today) {
                    $supporter->askAgainBecauseWrongReply('今日以降の日付を入力してください。');
                    return 'wrong-reply';
                }

                $supporter->storage['unsavedAnswers']['予定納入日'] = $dateString;
                return '';
        }
    }
}
