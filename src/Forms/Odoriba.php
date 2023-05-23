<?php

namespace KishukushaReportSupporter\Forms;

use KishukushaReportSupporter\FormTemplate;

class Odoriba extends FormTemplate
{
    public const HEADER = ['氏名', '保管品', '保管品の画像'];

    public function form(array $message): void
    {
        // 一番最初
        if (count($this->supporter->storage['phases']) === 0) {
            $this->supporter->storage['unsavedAnswers']['氏名'] = $this->supporter->storage['userName'];

            // 質問
            $this->supporter->pushMessage("保管品を入力してください。\n例:赤いハードキャリーケース", true);

            // 選択肢
            $this->supporter->pushUnsavedAnswerOption('保管品');
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

            if ($message !== '前の項目を修正する')
                $this->supporter->storage['unsavedAnswers']['保管品'] = $message;

            // 質問
            $this->supporter->pushMessage("保管品の画像を送ってください。", true);

            // 選択肢
            $this->supporter->pushUnsavedAnswerOption('保管品の画像', 'image');
            $this->supporter->pushImageOption();
            $this->supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $this->supporter->storage['phases'][] = 'askingImage';
        } else if ($lastPhase === 'askingImage') {
            if ($message['type'] === 'image') {
                if ($this->storeOrAskAgain('保管品の画像', $message))
                    return;
            } else {
                if ($message['type'] !== 'text' || $message['text'] !== '最後に送信した画像') {
                    $this->supporter->askAgainBecauseWrongReply();
                    return;
                } else if (!isset($this->supporter->storage['unsavedAnswers']['保管品の画像'])) {
                    $this->supporter->askAgainBecauseWrongReply();
                    return;
                }
            }

            // 質問・選択肢
            $this->confirm(['保管品の画像' => 'image']);

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
        $imageFileName = $answers['保管品の画像'];
        $itemName = mb_substr($answers['保管品'], 0, 15);
        $driveFileName = "踊り場_{$this->supporter->storage['userName']}_{$itemName}.jpg";
        $answers['保管品の画像'] = $this->supporter->saveToDrive($imageFileName, $driveFileName, $this->supporter->config['odoribaImageFolder']);
        $answersForSheets = array_values($answers);

        // 申請
        $this->supporter->applyForm($answers, $answersForSheets, false, '保管品はロビーの踊り場私物配備許可証を記入の上貼り付けて保管してください。');
    }

    public function pushAdminMessages(array $profile, array $answers, string $timeStamp, string $receiptNo): bool
    {
        $this->supporter->pushMessage(
            "{$answers['氏名']}(`{$profile['displayName']}`)が踊り場私物配備届を提出しました。
(TS:{$timeStamp})

チェック済み:

未チェックの項目:
保管品:{$answers['保管品']}
保管品の画像:
{$answers['保管品の画像']}
(ドライブに保存済み)",
            false,
            'text',
            ['name' => $profile['displayName'], 'iconUrl' => $profile['pictureUrl'] ?? 'https://dummy.com/']
        );
        $this->supporter->setLastQuestions();
        return false;
    }

    protected function storeOrAskAgain(string $type, string|array $message): string
    {
        switch ($type) {
            case '保管品の画像':
                $fileName = $this->supporter->downloadContent($message);
                $this->supporter->storage['unsavedAnswers'][$type] = $fileName;

                // 将来的にゴミ箱へ移動するための予約
                if (!isset($this->supporter->storage['cache']['一時ファイル']))
                    $this->supporter->storage['cache']['一時ファイル'] = [];
                $this->supporter->storage['cache']['一時ファイル'][] = $fileName;
                return '';
        }
    }
}
