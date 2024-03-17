<?php

namespace KishukushaReportSupporter\Forms;

use KishukushaReportSupporter\KishukushaReportSupporter;
use KishukushaReportSupporter\SubmittableForm;

class Bikes extends SubmittableForm
{
    public const HEADER = ['届出提出者名', '車体の種類', '防犯登録者名または名義人名', '防犯登録番号またはナンバーの画像', '車体の画像'];

    public static function form(KishukushaReportSupporter $supporter, array $message): void
    {
        // 一番最初
        if (count($supporter->storage['phases']) === 0) {
            $supporter->storage['unsavedAnswers']['届出提出者名'] = $supporter->storage['userName'];

            // 質問
            $supporter->pushText('登録するものを選んでください。', true);

            // 選択肢
            $supporter->pushOptions(['自転車', 'バイク', '原付'], true);
            $supporter->pushUnsavedAnswerOption('車体の種類');
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

            if ($message !== '前の項目を修正する') {
                if (self::storeOrAskAgain($supporter, '車体の種類', $message))
                    return;
            }

            // 質問
            switch ($supporter->storage['unsavedAnswers']['車体の種類']) {
                case '自転車':
                    $supporter->pushText('防犯登録者名を入力してください。', true);
                    // 選択肢
                    $supporter->pushUnsavedAnswerOption('防犯登録者名');
                    break;
                case 'バイク':
                    $supporter->pushText('バイクの名義人の名前を入力してください。', true);
                    // 選択肢
                    $supporter->pushUnsavedAnswerOption('名義人名');
                    break;
                case '原付':
                    $supporter->pushText('原付の名義人の名前を入力してください。', true);
                    // 選択肢
                    $supporter->pushUnsavedAnswerOption('名義人名');
                    break;
            }

            // 選択肢
            $supporter->pushOptions([$supporter->storage['userName'], '前の項目を修正する', 'キャンセル']);

            $supporter->storage['phases'][] = 'askingRegistrantName';
        } else if ($lastPhase === 'askingRegistrantName') {
            if ($message['type'] !== 'text') {
                $supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            // 答えの格納・質問・選択肢
            switch ($supporter->storage['unsavedAnswers']['車体の種類']) {
                case '自転車':
                    if ($message !== '前の項目を修正する') {
                        unset($supporter->storage['unsavedAnswers']['名義人名']);
                        insertToAssociativeArray($supporter->storage['unsavedAnswers'], 2, ['防犯登録者名' => $message]);
                    }
                    $supporter->pushText('防犯登録番号を入力してください。', true);
                    $supporter->pushUnsavedAnswerOption('防犯登録番号');
                    break;
                case 'バイク':
                case '原付':
                    if ($message !== '前の項目を修正する') {
                        unset($supporter->storage['unsavedAnswers']['防犯登録者名']);
                        insertToAssociativeArray($supporter->storage['unsavedAnswers'], 2, ['名義人名' => $message]);
                    }
                    $supporter->pushText('ナンバーの画像を送ってください。', true);
                    $supporter->pushUnsavedAnswerOption('ナンバーの画像', 'image');
                    $supporter->pushImageOption();
                    break;
            }

            // 選択肢(続き)
            $supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $supporter->storage['phases'][] = 'askingNumber';
        } else if ($lastPhase === 'askingNumber') {
            if ($message['type'] !== 'text' || $message['text'] !== '前の項目を修正する') {
                switch ($supporter->storage['unsavedAnswers']['車体の種類']) {
                    case '自転車':
                        if ($message['type'] !== 'text') {
                            $supporter->askAgainBecauseWrongReply();
                            return;
                        }
                        $message = $message['text'];

                        unset($supporter->storage['unsavedAnswers']['ナンバーの画像']);
                        insertToAssociativeArray($supporter->storage['unsavedAnswers'], 3, ['防犯登録番号' => $message]);
                        break;
                    case 'バイク':
                    case '原付':
                        if ($message['type'] === 'image') {
                            if (self::storeOrAskAgain($supporter, 'ナンバーの画像', $message))
                                return;
                        } else {
                            if ($message['type'] !== 'text' || $message['text'] !== '最後に送信した画像') {
                                $supporter->askAgainBecauseWrongReply();
                                return;
                            } else if (!isset($supporter->storage['unsavedAnswers']['ナンバーの画像'])) {
                                $supporter->askAgainBecauseWrongReply();
                                return;
                            }
                        }
                        break;
                }
            }

            // 質問
            $supporter->pushText('車体全体の画像を送ってください。', true);

            // 選択肢
            $supporter->pushUnsavedAnswerOption('車体の画像', 'image');
            $supporter->pushImageOption();
            $supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $supporter->storage['phases'][] = 'askingImage';
        } else if ($lastPhase === 'askingImage') {
            if ($message['type'] === 'image') {
                if (self::storeOrAskAgain($supporter, '車体の画像', $message))
                    return;
            } else {
                if ($message['type'] !== 'text' || $message['text'] !== '最後に送信した画像') {
                    $supporter->askAgainBecauseWrongReply();
                    return;
                } else if (!isset($supporter->storage['unsavedAnswers']['車体の画像'])) {
                    $supporter->askAgainBecauseWrongReply();
                    return;
                }
            }

            // 質問・選択肢
            self::confirm($supporter, ['ナンバーの画像' => 'image', '車体の画像' => 'image']);

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
        $bodyType = $supporter->storage['unsavedAnswers']['車体の種類'];
        switch ($bodyType) {
            case '自転車':
                $answers['防犯登録番号またはナンバーの画像'] = $answers['防犯登録番号'];
                unset($answers['防犯登録番号']);
                break;
            case 'バイク':
            case '原付':
                $imageFileName = $answers['ナンバーの画像'];
                $driveFileName = "{$bodyType}_{$supporter->storage['userName']}_ナンバー.jpg";
                $answers['防犯登録番号またはナンバーの画像'] = $supporter->saveToDrive($imageFileName, $driveFileName, $supporter->config['generalImageFolderId'], '自転車・バイク配備届');
                unset($answers['ナンバーの画像']);
                break;
        }

        $imageFileName = $answers['車体の画像'];
        $driveFileName = "{$bodyType}_{$supporter->storage['userName']}_車体.jpg";
        unset($answers['車体の画像']); // 順番を最後にするため
        $answers['車体の画像'] = $supporter->saveToDrive($imageFileName, $driveFileName, $supporter->config['generalImageFolderId'], '自転車・バイク配備届');

        $answersForSheets = array_values($answers);

        // 申請
        $supporter->submitForm($answers, $answersForSheets);
    }

    public static function pushAdminMessages(KishukushaReportSupporter $supporter, array $profile, array $answers, string $timeStamp, string $receiptNo): bool
    {
        switch ($answers['車体の種類']) {
            case '自転車':
                $supporter->pushText(
                    "{$answers['届出提出者名']}({$profile['displayName']})が自転車・バイク配備届を提出しました。
(TS:{$timeStamp})

チェック済み:
車体の種類:{$answers['車体の種類']}

未チェックの項目:
防犯登録者名または名義人名:{$answers['防犯登録者名または名義人名']}
防犯登録番号またはナンバーの画像:{$answers['防犯登録番号またはナンバーの画像']}
車体の画像:
{$answers['車体の画像']}
(ドライブに保存済み)",
                    false,
                    ['name' => $profile['displayName'], 'iconUrl' => $profile['pictureUrl'] ?? 'https://dummy.com/']
                );
                $supporter->pushImage($answers['車体の画像'], false,  ['name' => $profile['displayName'], 'iconUrl' => $profile['pictureUrl'] ?? 'https://dummy.com/']);
                break;
            case 'バイク':
            case '原付':
                $supporter->pushText(
                    "{$answers['届出提出者名']}({$profile['displayName']})が自転車・バイク配備届を提出しました。
(TS:{$timeStamp})

チェック済み:
車体の種類:{$answers['車体の種類']}

未チェックの項目:
防犯登録者名または名義人名:{$answers['防犯登録者名または名義人名']}
防犯登録番号またはナンバーの画像:
{$answers['防犯登録番号またはナンバーの画像']}
(ドライブに保存済み)
車体の画像:
{$answers['車体の画像']}
(ドライブに保存済み)",
                    false,
                    ['name' => $profile['displayName'], 'iconUrl' => $profile['pictureUrl'] ?? 'https://dummy.com/']
                );
                $supporter->pushImage($answers['防犯登録番号またはナンバーの画像'], false, ['name' => $profile['displayName'], 'iconUrl' => $profile['pictureUrl'] ?? 'https://dummy.com/']);
                $supporter->pushImage($answers['車体の画像'], false, ['name' => $profile['displayName'], 'iconUrl' => $profile['pictureUrl'] ?? 'https://dummy.com/']);
                break;
        }
        $supporter->setLastQuestions();
        return false;
    }

    protected static function storeOrAskAgain(KishukushaReportSupporter $supporter, string $type, string|array $message): string
    {
        switch ($type) {
            case '車体の種類':
                switch ($message) {
                    case '自転車':
                    case 'バイク':
                    case '原付':
                        $supporter->storage['unsavedAnswers']['車体の種類'] = $message;
                        return '';
                }
                $supporter->askAgainBecauseWrongReply();
                return 'wrong-reply';
            case 'ナンバーの画像':
            case '車体の画像':
                $fileName = $supporter->downloadContent($message);
                if ($type === 'ナンバーの画像') {
                    unset($supporter->storage['unsavedAnswers']['防犯登録番号']);
                    insertToAssociativeArray($supporter->storage['unsavedAnswers'], 3, ['ナンバーの画像' => $fileName]);
                } else {
                    $supporter->storage['unsavedAnswers'][$type] = $fileName;
                }

                // 将来的にゴミ箱へ移動するための予約
                if (!isset($supporter->storage['cache']['一時ファイル']))
                    $supporter->storage['cache']['一時ファイル'] = [];
                $supporter->storage['cache']['一時ファイル'][] = $fileName;
                return '';
        }
    }
}
