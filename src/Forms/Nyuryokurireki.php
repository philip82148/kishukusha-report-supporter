<?php

namespace KishukushaReportSupporter\Forms;

use KishukushaReportSupporter\FormTemplateBasic;

class Nyuryokurireki extends FormTemplateBasic
{
    public function form(array $message): void
    {
        if ($message['type'] !== 'text') {
            $this->supporter->askAgainBecauseWrongReply();
            return;
        }
        $message = $message['text'];

        // 一番最初
        if (count($this->supporter->storage['phases']) === 0) {
            // 質問文送信
            $this->supporter->pushText("ボットに保存された入力履歴を削除します。\nよろしいですか？", true);

            // 選択肢表示
            $this->supporter->pushOptions(['はい', 'キャンセル']);

            $this->supporter->storage['phases'][] = 'confirming';
            return;
        }

        // 確認
        switch ($message) {
            case 'はい':
                $this->supporter->storage['previousAnswers'] = [];
                $this->supporter->pushText("入力履歴を削除しました。");
                $this->supporter->resetForm();
                break;
            default:
                $this->supporter->askAgainBecauseWrongReply();
                break;
        }
    }
}
