<?php

namespace KishukushaReportSupporter\Forms;

use KishukushaReportSupporter\FormTemplate;

class Bikes extends FormTemplate
{
    public const HEADER = ['届出提出者名', '車体の種類', '防犯登録者名または名義人名', '防犯登録番号またはナンバーの画像', '車体の画像'];

    public function form(array $message): void
    {
        // 一番最初
        if (count($this->supporter->storage['phases']) === 0) {
            $this->supporter->storage['unsavedAnswers']['届出提出者名'] = $this->supporter->storage['userName'];

            // 質問
            $this->supporter->pushMessage('登録するものを選んでください。', true);

            // 選択肢
            $this->supporter->pushOptions(['自転車', 'バイク', '原付'], true);
            $this->supporter->pushUnsavedAnswerOption('車体の種類');
            $this->supporter->pushOptions(['キャンセル']);

            $this->supporter->storage['phases'][] = 'askingItem';
            return;
        }

        $lastPhase = $this->supporter->storage['phases'][count($this->supporter->storage['phases']) - 1];
        if ($lastPhase === 'askingItem') {
            if ($message['type'] !== 'text') {
                $this->supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            if ($message !== '前の項目を修正する') {
                if ($this->storeOrAskAgain('車体の種類', $message))
                    return;
            }

            // 質問
            switch ($this->supporter->storage['unsavedAnswers']['車体の種類']) {
                case '自転車':
                    $this->supporter->pushMessage('防犯登録者名を入力してください。', true);
                    // 選択肢
                    $this->supporter->pushUnsavedAnswerOption('防犯登録者名');
                    break;
                case 'バイク':
                    $this->supporter->pushMessage('バイクの名義人の名前を入力してください。', true);
                    // 選択肢
                    $this->supporter->pushUnsavedAnswerOption('名義人名');
                    break;
                case '原付':
                    $this->supporter->pushMessage('原付の名義人の名前を入力してください。', true);
                    // 選択肢
                    $this->supporter->pushUnsavedAnswerOption('名義人名');
                    break;
            }

            // 選択肢
            $this->supporter->pushOptions([$this->supporter->storage['userName'], '前の項目を修正する', 'キャンセル']);

            $this->supporter->storage['phases'][] = 'askingRegistrantName';
        } else if ($lastPhase === 'askingRegistrantName') {
            if ($message['type'] !== 'text') {
                $this->supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            // 答えの格納・質問・選択肢
            switch ($this->supporter->storage['unsavedAnswers']['車体の種類']) {
                case '自転車':
                    if ($message !== '前の項目を修正する') {
                        unset($this->supporter->storage['unsavedAnswers']['名義人名']);
                        insertToAssociativeArray($this->supporter->storage['unsavedAnswers'], 2, ['防犯登録者名' => $message]);
                    }
                    $this->supporter->pushMessage('防犯登録番号を入力してください。', true);
                    $this->supporter->pushUnsavedAnswerOption('防犯登録番号');
                    break;
                case 'バイク':
                case '原付':
                    if ($message !== '前の項目を修正する') {
                        unset($this->supporter->storage['unsavedAnswers']['防犯登録者名']);
                        insertToAssociativeArray($this->supporter->storage['unsavedAnswers'], 2, ['名義人名' => $message]);
                    }
                    $this->supporter->pushMessage('ナンバーの画像を送ってください。', true);
                    $this->supporter->pushUnsavedAnswerOption('ナンバーの画像', 'image');
                    $this->supporter->pushImageOption();
                    break;
            }

            // 選択肢(続き)
            $this->supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $this->supporter->storage['phases'][] = 'askingNumber';
        } else if ($lastPhase === 'askingNumber') {
            if ($message['type'] !== 'text' || $message['text'] !== '前の項目を修正する') {
                switch ($this->supporter->storage['unsavedAnswers']['車体の種類']) {
                    case '自転車':
                        if ($message['type'] !== 'text') {
                            $this->supporter->askAgainBecauseWrongReply();
                            return;
                        }
                        $message = $message['text'];

                        unset($this->supporter->storage['unsavedAnswers']['ナンバーの画像']);
                        insertToAssociativeArray($this->supporter->storage['unsavedAnswers'], 3, ['防犯登録番号' => $message]);
                        break;
                    case 'バイク':
                    case '原付':
                        if ($message['type'] === 'image') {
                            if ($this->storeOrAskAgain('ナンバーの画像', $message))
                                return;
                        } else {
                            if ($message['type'] !== 'text' || $message['text'] !== '最後に送信した画像') {
                                $this->supporter->askAgainBecauseWrongReply();
                                return;
                            } else if (!isset($this->supporter->storage['unsavedAnswers']['ナンバーの画像'])) {
                                $this->supporter->askAgainBecauseWrongReply();
                                return;
                            }
                        }
                        break;
                }
            }

            // 質問
            $this->supporter->pushMessage('車体全体の画像を送ってください。', true);

            // 選択肢
            $this->supporter->pushUnsavedAnswerOption('車体の画像', 'image');
            $this->supporter->pushImageOption();
            $this->supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $this->supporter->storage['phases'][] = 'askingImage';
        } else if ($lastPhase === 'askingImage') {
            if ($message['type'] === 'image') {
                if ($this->storeOrAskAgain('車体の画像', $message))
                    return;
            } else {
                if ($message['type'] !== 'text' || $message['text'] !== '最後に送信した画像') {
                    $this->supporter->askAgainBecauseWrongReply();
                    return;
                } else if (!isset($this->supporter->storage['unsavedAnswers']['車体の画像'])) {
                    $this->supporter->askAgainBecauseWrongReply();
                    return;
                }
            }

            // 質問・選択肢
            $this->confirm(['ナンバーの画像' => 'image', '車体の画像' => 'image']);

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
        $bodyType = $this->supporter->storage['unsavedAnswers']['車体の種類'];
        switch ($bodyType) {
            case '自転車':
                $answers['防犯登録番号またはナンバーの画像'] = $answers['防犯登録番号'];
                unset($answers['防犯登録番号']);
                break;
            case 'バイク':
            case '原付':
                $imageFileName = $answers['ナンバーの画像'];
                $driveFileName = "{$bodyType}_{$this->supporter->storage['userName']}_ナンバー.jpg";
                $answers['防犯登録番号またはナンバーの画像'] = $this->supporter->saveToDrive($imageFileName, $driveFileName, $this->supporter->config['bikesImageFolder']);
                unset($answers['ナンバーの画像']);
                break;
        }

        $imageFileName = $answers['車体の画像'];
        $driveFileName = "{$bodyType}_{$this->supporter->storage['userName']}_車体.jpg";
        unset($answers['車体の画像']); // 順番を最後にするため
        $answers['車体の画像'] = $this->supporter->saveToDrive($imageFileName, $driveFileName, $this->supporter->config['bikesImageFolder']);

        $answersForSheets = array_values($answers);

        // 申請
        $this->supporter->applyForm($answers, $answersForSheets);
    }

    public function pushAdminMessages(array $profile, array $answers, string $timeStamp, string $receiptNo): bool
    {
        switch ($answers['車体の種類']) {
            case '自転車':
                $this->supporter->pushMessage(
                    "{$answers['届出提出者名']}(`{$profile['displayName']}`)が自転車・バイク配備届を提出しました。
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
                    'text',
                    ['name' => $profile['displayName'], 'iconUrl' => $profile['pictureUrl'] ?? 'https://dummy.com/']
                );
                $this->supporter->pushMessage($answers['車体の画像'], false, 'image', ['name' => $profile['displayName'], 'iconUrl' => $profile['pictureUrl'] ?? 'https://dummy.com/']);
                break;
            case 'バイク':
            case '原付':
                $this->supporter->pushMessage(
                    "{$answers['届出提出者名']}(`{$profile['displayName']}`)が自転車・バイク配備届を提出しました。
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
                    'text',
                    ['name' => $profile['displayName'], 'iconUrl' => $profile['pictureUrl'] ?? 'https://dummy.com/']
                );
                $this->supporter->pushMessage($answers['防犯登録番号またはナンバーの画像'], false, 'image', ['name' => $profile['displayName'], 'iconUrl' => $profile['pictureUrl'] ?? 'https://dummy.com/']);
                $this->supporter->pushMessage($answers['車体の画像'], false, 'image', ['name' => $profile['displayName'], 'iconUrl' => $profile['pictureUrl'] ?? 'https://dummy.com/']);
                break;
        }
        $this->supporter->setLastQuestions();
        return false;
    }

    protected function storeOrAskAgain(string $type, string|array $message): string
    {
        switch ($type) {
            case '車体の種類':
                switch ($message) {
                    case '自転車':
                    case 'バイク':
                    case '原付':
                        $this->supporter->storage['unsavedAnswers']['車体の種類'] = $message;
                        return '';
                }
                $this->supporter->askAgainBecauseWrongReply();
                return 'wrong-reply';
            case 'ナンバーの画像':
            case '車体の画像':
                $fileName = $this->supporter->downloadContent($message);
                if ($type === 'ナンバーの画像') {
                    unset($this->supporter->storage['unsavedAnswers']['防犯登録番号']);
                    insertToAssociativeArray($this->supporter->storage['unsavedAnswers'], 3, ['ナンバーの画像' => $fileName]);
                } else {
                    $this->supporter->storage['unsavedAnswers'][$type] = $fileName;
                }

                // 将来的にゴミ箱へ移動するための予約
                if (!isset($this->supporter->storage['cache']['一時ファイル']))
                    $this->supporter->storage['cache']['一時ファイル'] = [];
                $this->supporter->storage['cache']['一時ファイル'][] = $fileName;
                return '';
        }
    }
}
