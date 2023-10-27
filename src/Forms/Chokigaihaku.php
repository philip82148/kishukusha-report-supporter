<?php

namespace KishukushaReportSupporter\Forms;

use KishukushaReportSupporter\KishukushaReportSupporter;
use KishukushaReportSupporter\SubmittableForm;

class Chokigaihaku extends SubmittableForm
{
    public const HEADER = ['氏名', '出舎日', '帰舎日', '外泊理由', '外泊理由の詳細', '滞在先住所', '連絡先電話番号', '風紀の承認'];

    public static function form(KishukushaReportSupporter $supporter, array $message): void
    {
        // 一番最初
        if (count($supporter->storage['phases']) === 0) {
            $supporter->storage['unsavedAnswers']['氏名'] = $supporter->storage['userName'];

            // 質問
            $year = date('Y');
            $supporter->pushText("出舎日を4桁(年無し)または8桁(年有り)で入力してください。\n例:0731、{$year}0731", true);

            // 選択肢
            $nextWeek = array_map(function ($i) {
                return dateToDateStringWithDay(strtotime("+{$i} day"));
            }, range(0, 6));
            $supporter->pushOptions($nextWeek);
            $supporter->pushUnsavedAnswerOption('出舎日'); // ラベル変更
            $supporter->pushOptions(['キャンセル']);

            $supporter->storage['phases'][] = 'askingStart';
            return;
        }

        $lastPhase = $supporter->storage['phases'][count($supporter->storage['phases']) - 1];
        if ($lastPhase === 'askingStart') {
            if ($message['type'] !== 'text') {
                $supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            if ($message !== '前の項目を修正する') {
                if (self::storeOrAskAgain($supporter, '出舎日', $message))
                    return;
            }

            // 質問
            $year = date('Y');
            $supporter->pushText("帰舎日を4桁(年無し)または8桁(年有り)で入力してください。\n例:0903、{$year}0903", true);

            // 選択肢
            $startDate = stringToDate($supporter->storage['unsavedAnswers']['出舎日']);
            $nextWeek = array_map(function ($i) use ($startDate) {
                return dateToDateStringWithDay(strtotime("+{$i} day", $startDate));
            }, range(1, 7));
            $supporter->pushOptions($nextWeek);

            if (isset($supporter->storage['unsavedAnswers']['帰舎日']) && stringToDate($supporter->storage['unsavedAnswers']['帰舎日']) > $startDate)
                $supporter->pushUnsavedAnswerOption('帰舎日'); // ラベル変更

            $supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $supporter->storage['phases'][] = 'askingEnd';
        } else if ($lastPhase === 'askingEnd') {
            if ($message['type'] !== 'text') {
                $supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            if ($message !== '前の項目を修正する') {
                switch (self::storeOrAskAgain($supporter, '帰舎日', $message)) {
                    case '':
                        break;
                    case 'booking':
                        $supporter->storage['phases'][] = 'confirmingPeriod';
                    default:
                        return;
                }
            }

            // 質問
            $supporter->pushText("外泊理由を選んでください。", true);

            // 選択肢
            $supporter->pushOptions(['帰省', '合宿', '旅行', 'その他'], true);
            $supporter->pushUnsavedAnswerOption('外泊理由');
            $supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $supporter->storage['phases'][] = 'askingReason';
        } else if ($lastPhase === 'confirmingPeriod') {
            if ($message['type'] !== 'text') {
                $supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            switch ($message) {
                case 'はい':
                    break;
                default:
                    $supporter->askAgainBecauseWrongReply();
                    return;
            }

            // 質問
            $supporter->pushText("外泊理由を選んでください。", true);

            // 選択肢
            $supporter->pushOptions(['帰省', '合宿', '旅行', 'その他'], true);
            $supporter->pushUnsavedAnswerOption('外泊理由');
            $supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            // askingEndの方で質問する
            array_pop($supporter->storage['phases']);
            $supporter->storage['phases'][] = 'askingReason';
        } else if ($lastPhase === 'askingReason') {
            if ($message['type'] !== 'text') {
                $supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            if ($message !== '前の項目を修正する') {
                if (self::storeOrAskAgain($supporter, '外泊理由', $message))
                    return;
            }

            // 質問
            $supporter->pushText("外泊理由の詳細を入力してください。\n※延泊の場合は延泊である旨も記載してください。\n例:サークルの合宿で福島に行ってまいります。", true);

            // 選択肢
            $supporter->pushUnsavedAnswerOption('外泊理由の詳細');
            $supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $supporter->storage['phases'][] = 'askingDetailedReason';
        } else if ($lastPhase === 'askingDetailedReason') {
            if ($message['type'] !== 'text') {
                $supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            if ($message !== '前の項目を修正する')
                $supporter->storage['unsavedAnswers']['外泊理由の詳細'] = $message;

            // 質問
            $supporter->pushText("滞在先住所を入力してください。\n例:108-8345 東京都港区三田2-15-45", true);

            // 選択肢
            $supporter->pushPreviousAnswerOptions('滞在先住所');
            $supporter->pushUnsavedAnswerOption('滞在先住所');
            $supporter->pushLocaleOptions();
            $supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $supporter->storage['phases'][] = 'askingAddress';
        } else if ($lastPhase === 'askingAddress') {
            if ($message['type'] === 'text') {
                $message = $message['text'];
                if ($message !== '前の項目を修正する')
                    $supporter->storage['unsavedAnswers']['滞在先住所'] = $message;
            } else {
                if (self::storeOrAskAgain($supporter, '滞在先住所', $message))
                    return;
            }

            // 質問
            $supporter->pushText("連絡先電話番号を入力してください。\n例:09011223344", true);

            // 選択肢
            $supporter->pushPreviousAnswerOptions('連絡先電話番号');
            $supporter->pushUnsavedAnswerOption('連絡先電話番号');
            $supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $supporter->storage['phases'][] = 'askingTel';
        } else if ($lastPhase === 'askingTel') {
            if ($message['type'] !== 'text') {
                $supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            if (self::storeOrAskAgain($supporter, '連絡先電話番号', $message))
                return;

            // 質問・選択肢
            self::confirm($supporter);

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
        $answersForSheets = array_values($answers);

        // 日付の曜日を取る
        $answersForSheets[1] = deleteParentheses($answersForSheets[1]);
        $answersForSheets[2] = deleteParentheses($answersForSheets[2]);

        // 連絡先電話番号を数値で表示するようにする
        $answersForSheets[6] = "'" . $answersForSheets[6];

        // 申請
        $supporter->submitForm($answers, $answersForSheets, true);

        // 次回のための回答の記録
        $supporter->pushPreviousAnswer('滞在先住所', $answers['滞在先住所']);
        $supporter->pushPreviousAnswer('連絡先電話番号', $answers['連絡先電話番号']);
    }

    public static function pushAdminMessages(KishukushaReportSupporter $supporter, array $profile, array $answers, string $timeStamp, string $receiptNo): bool
    {
        $startDate = stringToDate($answers['出舎日']);
        $endDate = stringToDate($answers['帰舎日']);

        // 外泊期間の計算
        $periodDays = (int)(($endDate - $startDate) / 60 / 60 / 24);
        $period = '';

        $periodWeeks = (int)($periodDays / 7);
        if ($periodWeeks > 0)
            $period .= $periodWeeks . '週間';

        $periodDays %= 7;
        if ($periodDays > 0) {
            if ($period)
                $period .= 'と';
            $period .= $periodDays . '日';
        }

        // 行事と被っていないか調べる
        $conflictingEvents = self::getConflictingEvents($supporter, $startDate, $endDate);
        if ($conflictingEvents  !== '') {
            $messageAboutDate = "※行事{$conflictingEvents}と被っています！";
        } else {
            $messageAboutDate = "※被っている行事はありません。";
        }

        // 任期内か調べる
        $isDateInTerm = $supporter->checkInTerm($endDate);
        if (!$isDateInTerm)
            $messageAboutDate = "※任期外の日付を含んでいます！\n{$messageAboutDate}";

        if ($conflictingEvents !== '' || !$isDateInTerm) {
            $supporter->pushText(
                "{$answers['氏名']}(`{$profile['displayName']}`)が長期外泊届を提出しました。
承認しますか？
(TS:{$timeStamp})
(届出番号:{$receiptNo})

チェック済み:
外泊理由:{$answers['外泊理由']}

危険な項目:
出舎日:{$answers['出舎日']}
帰舎日:{$answers['帰舎日']}
{$messageAboutDate}
※外泊期間:{$period}

未チェックの項目:
外泊理由の詳細:{$answers['外泊理由の詳細']}
滞在先住所:{$answers['滞在先住所']}
連絡先電話番号:{$answers['連絡先電話番号']}",
                true,
                ['name' => $profile['displayName'], 'iconUrl' => $profile['pictureUrl'] ?? 'https://dummy.com/']
            );
            $supporter->pushOptions(['承認する', '直接伝えた', '一番最後に見る']);
            return true;
        }

        $supporter->pushText(
            "{$answers['氏名']}(`{$profile['displayName']}`)が長期外泊届を提出しました。
承認しますか？
(TS:{$timeStamp})
(届出番号:{$receiptNo})

チェック済み:
外泊理由:{$answers['外泊理由']}
出舎日:{$answers['出舎日']}
帰舎日:{$answers['帰舎日']}
{$messageAboutDate}
※外泊期間:{$period}

未チェックの項目:
外泊理由の詳細:{$answers['外泊理由の詳細']}
滞在先住所:{$answers['滞在先住所']}
連絡先電話番号:{$answers['連絡先電話番号']}",
            true,
            ['name' => $profile['displayName'], 'iconUrl' => $profile['pictureUrl'] ?? 'https://dummy.com/']
        );
        $supporter->pushOptions(['承認する', '直接伝えた', '一番最後に見る']);
        return true;
    }

    protected static function storeOrAskAgain(KishukushaReportSupporter $supporter, string $type, string|array $message): string
    {
        switch ($type) {
            case '出舎日':
            case '帰舎日':
                $date = stringToDate($message);
                if ($date === false) {
                    $year = date('Y');
                    if ($type === '出舎日') {
                        $supporter->askAgainBecauseWrongReply("入力の形式が違うか、無効な日付です。\n「0731」または「{$year}0731」のように4桁または8桁で入力してください。");
                    } else {
                        $supporter->askAgainBecauseWrongReply("入力の形式が違うか、無効な日付です。\n「0903」または「{$year}0903」のように4桁または8桁で入力してください。");
                    }
                    return 'wrong-reply';
                }

                $dateString = dateToDateStringWithDay($date);
                $supporter->pushText("{$type}:{$dateString}");

                if ($type === '出舎日') {
                    $today = getDateAt0AM();
                    if ($date < $today) {
                        $supporter->askAgainBecauseWrongReply('今日以降の日付を入力してください。');
                        return 'wrong-reply';
                    }

                    $supporter->storage['unsavedAnswers']['出舎日'] = $dateString;
                    return '';
                }

                // 出舎日の1日後から有効
                $startDate = stringToDate($supporter->storage['unsavedAnswers']['出舎日']);
                $oneDayAfterStartDay = strtotime('+1 day', $startDate);
                if ($date < $oneDayAfterStartDay) {
                    $supporter->askAgainBecauseWrongReply("出舎日の1日後以降の日付を入力してください。\nなお、24時を2回周らない外泊の場合は申請不要です。");
                    return 'wrong-reply';
                }
                $supporter->storage['unsavedAnswers']['帰舎日'] = $dateString;

                // 出舎日と帰舎日のデータがそろった
                // 行事と被ってないか調べる
                $endDate = $date;
                $conflictingEvents = self::getConflictingEvents($supporter, $startDate, $endDate);
                if ($conflictingEvents !== '') {
                    $supporter->pushText("その期間は行事{$conflictingEvents}と被っています。
よろしいですか？
※委員会行事を欠席または遅刻、早退する場合は舎生大会・諸行事届の届け出が必要になります。", true);
                    $supporter->pushOptions(['はい', '前の項目を修正する', 'キャンセル']);

                    return 'booking';
                }

                return '';
            case '外泊理由':
                switch ($message) {
                    case '帰省':
                    case '合宿':
                    case '旅行':
                    case 'その他':
                        $supporter->storage['unsavedAnswers']['外泊理由'] = $message;
                        return '';
                }
                // 有効でなかった、もう一度質問文送信
                $supporter->askAgainBecauseWrongReply();
                return 'wrong-reply';
            case '滞在先住所':
                if ($message['type'] !== 'location') {
                    $supporter->askAgainBecauseWrongReply();
                    return 'wrong-reply';
                }

                if (!isset($message['address'])) {
                    $supporter->askAgainBecauseWrongReply("住所がありません。\nもう一度入力して下さい。");
                    return 'wrong-reply';
                }

                $address = $message['address'];
                if (isset($message['title'])) $address .= "({$message['title']})";
                $supporter->pushText("滞在先住所:{$address}");
                $supporter->storage['unsavedAnswers']['滞在先住所'] = $address;
                return '';
            case '連絡先電話番号':
                $message = toHalfWidth($message);
                if (mb_strlen(preg_replace('/\D/', '', $message)) < 10) {
                    $supporter->askAgainBecauseWrongReply("入力が不正です。\n10桁以上の数値を含めてください。");
                    return 'wrong-reply';
                }
                $supporter->storage['unsavedAnswers']['連絡先電話番号'] = $message;
                return '';
        }
    }

    private static function getConflictingEvents(KishukushaReportSupporter $supporter, int $startDate, int $endDate): string
    {
        $conflictingEvents = '';
        $events = $supporter->fetchEvents();
        foreach ($events as $event) {
            if ($endDate < stringToDate($event['開始日']) || $startDate > stringToDate($event['終了日'])) continue;

            // 被っていた
            if ($conflictingEvents !== '')
                $conflictingEvents .= '、';
            if ($event['開始日'] === $event['終了日']) {
                $conflictingEvents .= "「{$event['行事名']}」({$event['開始日']})";
            } else {
                $conflictingEvents .= "「{$event['行事名']}」({$event['開始日']} - {$event['終了日']})";
            }
        }

        return $conflictingEvents;
    }
}
