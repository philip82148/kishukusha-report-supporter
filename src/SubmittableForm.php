<?php

namespace KishukushaReportSupporter;

abstract class SubmittableForm
{
    public const HEADER = [];

    abstract public static function form(KishukushaReportSupporter $supporter, array $message): void;

    public static function pushAdminMessages(KishukushaReportSupporter $supporter, array $profile, array $answers, string $timeStamp, string $receiptNo): bool
    {
        return true;
    }

    abstract protected static function submitForm(KishukushaReportSupporter $supporter): void;

    protected static function storeOrAskAgain(KishukushaReportSupporter $supporter, string $type, string|array $message): string
    {
        return '';
    }

    protected static function confirm(KishukushaReportSupporter $supporter, array $types = []): void
    {
        /* // 一番長いラベルを探す
        $maxLabelLength = 2;
        foreach ($supporter->storage['unsavedAnswers'] as $label => $value) {
            $length = mb_strlen($label);
            if ($length > $maxLabelLength) $maxLabelLength = $length;
        } */

        // 質問
        $reply = '';
        $images = [];
        foreach ($supporter->storage['unsavedAnswers'] as $label => $value) {
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
        $supporter->pushText($reply, true);

        // 画像があれば画像も送信
        foreach ($images as $filename) {
            $url = $supporter->openAccessToImage($filename);
            $supporter->pushImage($url, true);
        }

        // 選択肢
        $supporter->pushOptions([はい, 前の項目を修正する, キャンセル]);
    }

    protected static function confirming(KishukushaReportSupporter $supporter, string $message, bool $reset = true): bool
    {
        switch ($message) {
            case はい:
                static::submitForm($supporter);
                if ($reset) $supporter->resetForm();
                return true;
            default:
                $supporter->askAgainBecauseWrongReply();
                return false;
        }
    }
}
