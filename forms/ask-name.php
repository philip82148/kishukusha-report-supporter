<?php

require_once __DIR__ . '/../includes.php';

class AskName extends FormTemplateBasic
{
    public function form(array $message): void
    {
        if ($message === []) {
            $message = '';
        } else {
            if ($message['type'] !== 'text') {
                $this->supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];
        }

        // 一番最初
        if (count($this->supporter->storage['phases']) === 0) {
            // 質問文送信
            if ($this->supporter->storage['userName'] === '') {
                // 名前がまだ登録されていない
                $this->supporter->pushMessage("あなたの名前を和文フルネームで入力してください。
(初回のみ)
例:山田 太郎
※スマホでLINE名が和文フルネームの場合はクイックリプライが使用できます。", true);
            } else {
                // 名前が過去に登録されたことがある
                $this->supporter->pushMessage("あなたの名前を和文フルネームで入力してください。
例:山田 太郎
現在の登録名:{$this->supporter->storage['userName']}", true);
            }

            // 選択肢表示
            $displayName = $this->supporter->fetchDisplayName();
            $this->supporter->pushOptions(['(LINE名より)' => $displayName]);
            $this->supporter->pushUnsavedAnswerOption('名前');
            if ($this->supporter->storage['userName'] !== '') {
                // すでに一度登録済みなら、キャンセルを用意しておく
                $this->supporter->pushOptions(['キャンセル']);
            }

            $this->supporter->storage['phases'][] = 'askingName';
            return;
        }

        $lastPhase = $this->supporter->storage['phases'][count($this->supporter->storage['phases']) - 1];
        if ($lastPhase === 'askingName') {
            if ($this->storeOrAskAgain('ユーザー名', $message))
                return;

            // 質問
            $name = $this->supporter->storage['unsavedAnswers']['名前'];
            // 質問文送信
            if ($this->supporter->storage['userName'] === '') {
                // 名前がまだ登録されていない
                $this->supporter->pushMessage("届出の際に使用する名前を以下で登録します。
よろしいですか？
(初回のみ)
※和文フルネームであることを確認してください。
※後で変更できます。
※クイックリプライはスマホでのみ利用できます。

届出者氏名:{$name}", true);
            } else {
                // 名前が過去に登録されたことがある
                $this->supporter->pushMessage("届出の際に使用する名前を以下で登録します。
よろしいですか？
※和文フルネームであることを確認してください。

届出者氏名:{$name}", true);
            }

            // 選択肢
            $this->supporter->pushOptions(['はい', '前の項目を修正する']);
            if ($this->supporter->storage['userName'] !== '') {
                // 名前が登録されているときだけキャンセルを用意
                $this->supporter->pushOptions(['キャンセル']);
            }

            $this->supporter->storage['phases'][] = 'confirming';
        } else {
            // 確認
            switch ($message) {
                case 'はい':
                    $this->supporter->storage['userName'] = $this->supporter->storage['unsavedAnswers']['名前'];
                    $this->supporter->pushMessage('名前を登録しました。');
                    $this->supporter->resetForm();
                    return;
                default:
                    $this->supporter->askAgainBecauseWrongReply();
                    return;
            }
        }
    }

    protected function storeOrAskAgain(string $type, string|array $message): string
    {
        switch ($type) {
            case 'ユーザー名':
                switch ($message) {
                    case 'はい':
                    case 'いいえ':
                        $this->supporter->askAgainBecauseWrongReply();
                        return 'wrong-reply';
                    default:
                        $message = preg_replace('/[\x00\s]++/u', ' ', $message);
                        $this->supporter->storage['unsavedAnswers']['名前'] = $message;
                        return '';
                }
        }
    }
}
