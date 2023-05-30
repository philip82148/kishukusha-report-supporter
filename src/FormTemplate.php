<?php

namespace KishukushaReportSupporter;

abstract class FormTemplate extends FormTemplateBasic
{
    public const HEADER = [];

    abstract protected function submitForm(): void;
    abstract public function pushAdminMessages(array $profile, array $answers, string $timeStamp, string $receiptNo): bool;

    protected function confirm(array $types = []): void
    {
        /* // 一番長いラベルを探す
        $maxLabelLength = 2;
        foreach ($this->supporter->storage['unsavedAnswers'] as $label => $value) {
            $length = mb_strlen($label);
            if ($length > $maxLabelLength) $maxLabelLength = $length;
        } */

        // 質問
        $reply = '';
        $images = [];
        foreach ($this->supporter->storage['unsavedAnswers'] as $label => $value) {
            // ラベル
            $reply .= $label; // . str_repeat('　', $maxLabelLength - mb_strlen($label));

            // 値
            if (isset($types[$label])) {
                if ($types[$label] === 'image') {
                    $reply .= ":下の画像\n";
                    $images[] = $value;
                    continue;
                }
            }

            $reply .= ":{$value}\n";
        }
        $reply .= "で申請します。よろしいですか？";
        $this->supporter->pushText($reply, true);

        // 画像があれば画像も送信
        foreach ($images as $filename)
            $this->supporter->pushImage($this->supporter->getImageUrl($filename), true);

        // 選択肢
        $this->supporter->pushOptions(['はい', '前の項目を修正する', 'キャンセル']);
    }

    protected function confirming(string $message, bool $reset = true): bool
    {
        switch ($message) {
            case 'はい':
                $this->submitForm();
                if ($reset) $this->supporter->resetForm();
                return true;
            default:
                $this->supporter->askAgainBecauseWrongReply();
                return false;
        }
    }
}
