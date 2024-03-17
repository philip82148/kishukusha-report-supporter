<?php

namespace KishukushaReportSupporter\Forms;

use KishukushaReportSupporter\KishukushaReportSupporter;
use KishukushaReportSupporter\SubmittableForm;

class Odoriba extends SubmittableForm
{
    public const HEADER = ['氏名', '保管品', '保管品の画像'];

    public static function form(KishukushaReportSupporter $supporter, array $message): void
    {
        // 一番最初
        if (count($supporter->storage['phases']) === 0) {
            $supporter->storage['unsavedAnswers']['氏名'] = $supporter->storage['userName'];

            // 質問
            $supporter->pushText("保管品を入力してください。\n例:赤いハードキャリーケース", true);

            // 選択肢
            $supporter->pushUnsavedAnswerOption('保管品');
            $supporter->pushOptions(['キャンセル']);

            $supporter->storage['phases'][] = 'askingItem';
            return;
        }

        $lastPhase = $supporter->storage['phases'][count($supporter->storage['phases']) - 1];
        if ($lastPhase === 'askingItem') {
            if ($message['type'] !== 'text') {
                $supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            if ($message !== '前の項目を修正する')
                $supporter->storage['unsavedAnswers']['保管品'] = $message;

            // 質問
            $supporter->pushText("保管品の画像を送ってください。", true);

            // 選択肢
            $supporter->pushUnsavedAnswerOption('保管品の画像', 'image');
            $supporter->pushImageOption();
            $supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $supporter->storage['phases'][] = 'askingImage';
        } else if ($lastPhase === 'askingImage') {
            if ($message['type'] === 'image') {
                if (self::storeOrAskAgain($supporter, '保管品の画像', $message))
                    return;
            } else {
                if ($message['type'] !== 'text' || $message['text'] !== '最後に送信した画像') {
                    $supporter->askAgainBecauseWrongReply();
                    return;
                } else if (!isset($supporter->storage['unsavedAnswers']['保管品の画像'])) {
                    $supporter->askAgainBecauseWrongReply();
                    return;
                }
            }

            // 質問・選択肢
            self::confirm($supporter, ['保管品の画像' => 'image']);

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
        $imageFileName = $answers['保管品の画像'];
        $itemName = mb_substr($answers['保管品'], 0, 15);
        $driveFileName = "踊り場_{$supporter->storage['userName']}_{$itemName}.jpg";
        $answers['保管品の画像'] = $supporter->saveToDrive($imageFileName, $driveFileName, $supporter->config['generalImageFolderId'], '踊り場私物配備届');
        $answersForSheets = array_values($answers);

        // 申請
        $supporter->submitForm($answers, $answersForSheets, false, '保管品はロビーの踊り場私物配備許可証を記入の上貼り付けて保管してください。');
    }

    public static function pushAdminMessages(KishukushaReportSupporter $supporter, array $profile, array $answers, string $timeStamp, string $receiptNo): bool
    {
        $supporter->pushText(
            "{$answers['氏名']}({$profile['displayName']})が踊り場私物配備届を提出しました。
(TS:{$timeStamp})

チェック済み:

未チェックの項目:
保管品:{$answers['保管品']}
保管品の画像:
{$answers['保管品の画像']}
(ドライブに保存済み)",
            false,
            ['name' => $profile['displayName'], 'iconUrl' => $profile['pictureUrl'] ?? 'https://dummy.com/']
        );
        $supporter->setLastQuestions();
        return false;
    }

    protected static function storeOrAskAgain(KishukushaReportSupporter $supporter, string $type, string|array $message): string
    {
        switch ($type) {
            case '保管品の画像':
                $fileName = $supporter->downloadContent($message);
                $supporter->storage['unsavedAnswers'][$type] = $fileName;

                // 将来的にゴミ箱へ移動するための予約
                if (!isset($supporter->storage['cache']['一時ファイル']))
                    $supporter->storage['cache']['一時ファイル'] = [];
                $supporter->storage['cache']['一時ファイル'][] = $fileName;
                return '';
        }
    }
}
