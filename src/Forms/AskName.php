<?php

namespace KishukushaReportSupporter\Forms;

use KishukushaReportSupporter\KishukushaReportSupporter;
use KishukushaReportSupporter\UnsubmittableForm;

class AskName extends UnsubmittableForm
{
    public static function form(KishukushaReportSupporter $supporter, array $message): void
    {
        if ($message === []) {
            $message = '';
        } else {
            if ($message['type'] !== 'text') {
                $supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];
        }

        // 一番最初
        if (count($supporter->storage['phases']) === 0) {
            // 質問文送信
            if ($supporter->storage['userName'] === '') {
                // 名前がまだ登録されていない
                $supporter->pushText("あなたの名前を和文フルネームで入力してください。
(初回のみ)
例:山田 太郎
※LINE名が和文フルネームの場合はスマホと一部のパソコンでクイックリプライが使用できます。

※このボットの利用を続けることで利用規約(https://github.com/philip82148/kishukusha-report-supporter/blob/main/terms-of-use.md)に同意したものとみなされます。
同意しない場合はボットをブロックしてください。", true);
            } else {
                // 名前が過去に登録されたことがある
                $supporter->pushText("あなたの名前を和文フルネームで入力してください。
例:山田 太郎
現在の登録名:{$supporter->storage['userName']}", true);
            }

            // 選択肢表示
            $profile = $supporter->fetchProfile();
            if (isset($profile['displayName']))
                $supporter->pushOptions(['(LINE名より)' => $profile['displayName']]);
            $supporter->pushUnsavedAnswerOption('名前');
            if ($supporter->storage['userName'] !== '') {
                // すでに一度登録済みなら、キャンセルを用意しておく
                $supporter->pushOptions(['キャンセル']);
            }

            $supporter->storage['phases'][] = 'askingName';
            return;
        }

        $lastPhase = $supporter->storage['phases'][count($supporter->storage['phases']) - 1];
        if ($lastPhase === 'askingName') {
            if (self::storeOrAskAgain($supporter, 'ユーザー名', $message))
                return;

            // 質問
            $name = $supporter->storage['unsavedAnswers']['名前'];
            // 質問文送信
            if ($supporter->storage['userName'] === '') {
                // 名前がまだ登録されていない
                $supporter->pushText("届出の際に使用する名前を以下で登録します。
よろしいですか？
(初回のみ)
※和文フルネームであることを確認してください。
※後で変更できます。
※クイックリプライはスマホと一部のパソコンでのみ利用できます。

届出者氏名:{$name}", true);
            } else {
                // 名前が過去に登録されたことがある
                $supporter->pushText("届出の際に使用する名前を以下で登録します。
よろしいですか？
※和文フルネームであることを確認してください。

届出者氏名:{$name}", true);
            }

            // 選択肢
            $supporter->pushOptions(['はい', '前の項目を修正する']);
            if ($supporter->storage['userName'] !== '') {
                // 名前が登録されているときだけキャンセルを用意
                $supporter->pushOptions(['キャンセル']);
            }

            $supporter->storage['phases'][] = 'confirming';
        } else {
            // 確認
            switch ($message) {
                case 'はい':
                    $supporter->storage['userName'] = $supporter->storage['unsavedAnswers']['名前'];
                    $supporter->pushText('名前を登録しました。');
                    $supporter->resetForm();
                    return;
                default:
                    $supporter->askAgainBecauseWrongReply();
                    return;
            }
        }
    }

    protected static function storeOrAskAgain(KishukushaReportSupporter $supporter, string $type, string|array $message): string
    {
        switch ($type) {
            case 'ユーザー名':
                switch ($message) {
                    case 'はい':
                    case 'いいえ':
                        $supporter->askAgainBecauseWrongReply();
                        return 'wrong-reply';
                    default:
                        $message = preg_replace('/[\x00\s]++/u', ' ', $message);
                        $supporter->storage['unsavedAnswers']['名前'] = $message;
                        return '';
                }
        }
    }
}
