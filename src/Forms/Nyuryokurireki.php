<?php

namespace KishukushaReportSupporter\Forms;

use KishukushaReportSupporter\KishukushaReportSupporter;
use KishukushaReportSupporter\UnsubmittableForm;

class Nyuryokurireki extends UnsubmittableForm
{
    public static function form(KishukushaReportSupporter $supporter, array $message): void
    {
        if ($message['type'] !== 'text') {
            $supporter->askAgainBecauseWrongReply();
            return;
        }
        $message = $message['text'];

        // 一番最初
        if (count($supporter->storage['phases']) === 0) {
            // 質問文送信
            $supporter->pushText("ボットに保存された入力履歴を削除します。\nよろしいですか？", true);

            // 選択肢表示
            $supporter->pushOptions([はい, キャンセル]);

            $supporter->storage['phases'][] = 'confirming';
            return;
        }

        // 確認
        switch ($message) {
            case はい:
                $supporter->storage['previousAnswers'] = [];
                $supporter->pushText("入力履歴を削除しました。");
                $supporter->resetForm();
                break;
            default:
                $supporter->askAgainBecauseWrongReply();
                break;
        }
    }
}
