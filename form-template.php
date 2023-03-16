<?php

require_once __DIR__ . '/kishukusha-form-supporter.php';

abstract class FormTemplateBasic
{
    public function __construct(protected KishukushaFormSupporter $supporter)
    {
    }
    abstract public function form(array $message): void;
}

abstract class FormTemplate extends FormTemplateBasic
{
    abstract protected function applyForm(): void;
    abstract public function pushAdminMessages(string $displayName, array $answers, string $timeStamp, string $receiptNo): bool;

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
        $this->supporter->pushMessage($reply, true);

        // 画像があれば画像も送信
        foreach ($images as $filename)
            $this->supporter->pushMessage($this->supporter->getImageUrl($filename), true, 'image');

        // 選択肢
        $this->supporter->pushOptions(['はい', '前の項目を修正する', 'キャンセル']);
    }

    protected function confirming(string $message, bool $reset = true): bool
    {
        switch ($message) {
            case 'はい':
                $this->applyForm();
                if ($reset) $this->supporter->resetForm();
                return true;
            default:
                $this->supporter->askAgainBecauseWrongReply();
                return false;
        }
    }
}
