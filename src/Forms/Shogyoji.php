<?php

namespace KishukushaReportSupporter\Forms;

use KishukushaReportSupporter\FormTemplate;
use KishukushaReportSupporter\KishukushaReportSupporter;
use KishukushaReportSupporter\JsonDatabase;
use KishukushaReportSupporter\LogDatabase;

class Shogyoji extends FormTemplate
{
    public const HEADER = ['氏名', '委員会行事', '開催日', '出欠', '理由', '理由の詳細', '舎生大会の議決の委任への同意', '風紀の承認'];

    public function form(array $message): void
    {
        // 一番最初
        if (count($this->supporter->storage['phases']) === 0) {
            $this->supporter->storage['unsavedAnswers']['氏名'] = $this->supporter->storage['userName'];

            // 行事名に対する日付の辞書を作る
            $events = $this->supporter->fetchEvents();
            $today = getDateAt0AM();
            $events_to_dates = ['舎生大会' => [], '委員会' => []]; // 最初に表示させる
            $passed_events_to_dates = []; // 同時に過ぎた行事の辞書も作る
            foreach ($events as $event) {
                // 今日以降の行事でなければ過ぎた行事の辞書へ
                if (stringToDate($event['開始日']) >= $today) {
                    if (!isset($events_to_dates[$event['行事名']])) {
                        if (count($events_to_dates) < 11)
                            $events_to_dates[$event['行事名']] = [$event['開始日']];
                        continue;
                    }

                    if (count($events_to_dates[$event['行事名']]) >= 10) continue;
                    $events_to_dates[$event['行事名']][] = $event['開始日'];
                } else {
                    if (!isset($passed_events_to_dates[$event['行事名']])) {
                        if (count($passed_events_to_dates) < 11)
                            $passed_events_to_dates[$event['行事名']] = [$event['開始日']];
                        continue;
                    }

                    if (count($passed_events_to_dates[$event['行事名']]) >= 10) continue;
                    $passed_events_to_dates[$event['行事名']][] = $event['開始日'];
                }
            }
            // 過ぎた方は最新の方から並べる
            $passed_events_to_dates = array_slice(array_reverse($passed_events_to_dates), 0, 10);

            // 質問
            $this->supporter->pushMessage('該当する委員会行事を選んでください。', true);

            // 選択肢
            $this->supporter->pushOptions(array_keys($events_to_dates), true);
            $this->supporter->pushOptions(['その他'], true);
            $this->supporter->pushOptions(['キャンセル']);

            $this->supporter->storage['cache']['eventsToDates'] = $events_to_dates;
            $this->supporter->storage['cache']['passedEventsToDates'] = $passed_events_to_dates;

            $this->supporter->storage['phases'][] = 'askingEvent';
            return;
        }

        $lastPhase = $this->supporter->storage['phases'][count($this->supporter->storage['phases']) - 1];
        if ($lastPhase === 'askingEvent') {
            if ($message['type'] !== 'text') {
                $this->supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            switch ($message) {
                case '前の項目を修正する':
                    break;
                case 'その他':
                    // 質問
                    $this->supporter->pushMessage('具体的な委員会行事名を入力してください。', true);

                    // 選択肢
                    // 取得した行事を選択肢に加える(ただし、舎生大会と委員会は除く)
                    $events = array_keys($this->supporter->storage['cache']['passedEventsToDates']);
                    $events = array_filter($events, function ($event) {
                        return $event !== '舎生大会' && $event !== '委員会';
                    });
                    $this->supporter->pushUnsavedAnswerOption('委員会行事');
                    $this->supporter->pushOptions($events);
                    $this->supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

                    // 次その他にはならないのでここの質問に戻ってこないようにする
                    array_pop($this->supporter->storage['phases']);
                    $this->supporter->storage['phases'][] = 'askingEventDetail';
                    return;
                default:
                    if ($this->storeOrAskAgain('委員会行事', $message))
                        return;
            }

            // 質問
            $year = date('Y');
            $this->supporter->pushMessage('開催日(の開始日)を選んでください。', true);

            // 選択肢
            $event = $this->supporter->storage['unsavedAnswers']['委員会行事'];
            $this->supporter->pushOptions($this->supporter->storage['cache']['eventsToDates'][$event] ?? [], true);
            $this->supporter->pushOptions(['その他'], true);
            $this->supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $this->supporter->storage['phases'][] = 'askingStart';
            return;
        } else if ($lastPhase === 'askingEventDetail') {
            if ($message['type'] !== 'text') {
                $this->supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            if ($message !== '前の項目を修正する')
                $this->supporter->storage['unsavedAnswers']['委員会行事'] = $message;

            // 質問
            $year = date('Y');
            $this->supporter->pushMessage("開催日(の開始日)を4桁(年無し)または8桁(年有り)で入力してください。\n例:1006、{$year}1006", true);

            // 選択肢
            $event = $this->supporter->storage['unsavedAnswers']['委員会行事'];
            $this->supporter->pushUnsavedAnswerOption('開催日');
            $this->supporter->pushOptions($this->supporter->storage['cache']['passedEventsToDates'][$event] ?? []);
            $this->supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $this->supporter->storage['phases'][] = 'askingStartManually';
            return;
        } else if ($lastPhase === 'askingStart') {
            if ($message['type'] !== 'text') {
                $this->supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            switch ($message) {
                case '前の項目を修正する':
                    break;
                case 'その他':
                    // 質問
                    $year = date('Y');
                    $this->supporter->pushMessage("開催日(の開始日)を4桁(年無し)または8桁(年有り)で入力してください。\n例:1006、{$year}1006", true);

                    // 選択肢
                    $event = $this->supporter->storage['unsavedAnswers']['委員会行事'];
                    $this->supporter->pushUnsavedAnswerOption('開催日');
                    $this->supporter->pushOptions($this->supporter->storage['cache']['passedEventsToDates'][$event] ?? []);
                    $this->supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

                    // 次その他にはならないのでここの質問に戻ってこないようにする
                    array_pop($this->supporter->storage['phases']);
                    $this->supporter->storage['phases'][] = 'askingStartManually';
                    return;
                default:
                    if ($this->storeOrAskAgain('開催日', $message))
                        return;
            }

            // 質問
            $this->supporter->pushMessage('出欠の種類を選択してください。', true);

            // 選択肢
            $this->supporter->pushOptions([
                '欠席',
                '遅刻',
                '早退',
                '遅刻と早退'
            ], true);
            $this->supporter->pushUnsavedAnswerOption('出欠');
            $this->supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $this->supporter->storage['phases'][] = 'askingAttendance';
            return;
        } else if ($lastPhase === 'askingStartManually') {
            if ($message['type'] !== 'text') {
                $this->supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            if ($message !== '前の項目を修正する') {
                if ($this->storeOrAskAgain('開催日(手動入力)', $message))
                    return;
            }

            // 質問
            $this->supporter->pushMessage('出欠の種類を選択してください。', true);

            // 選択肢
            $this->supporter->pushOptions([
                '欠席',
                '遅刻',
                '早退',
                '遅刻と早退'
            ], true);
            $this->supporter->pushUnsavedAnswerOption('出欠');
            $this->supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $this->supporter->storage['phases'][] = 'askingAttendance';
            return;
        } else if ($lastPhase === 'askingAttendance') {
            if ($message['type'] !== 'text') {
                $this->supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            if ($message !== '前の項目を修正する') {
                if ($this->storeOrAskAgain('出欠', $message))
                    return;
            }

            // 質問
            $this->supporter->pushMessage('理由を選択してください。', true);

            // 選択肢
            $this->supporter->pushOptions(['疾病', '體育會', '冠婚葬祭', '資格試験', '就職活動'], true);
            $this->supporter->pushOptions(['専門学校の試験', 'サークルの大会および合宿', '大学のカリキュラム', 'その他'], true);
            $this->supporter->pushUnsavedAnswerOption('理由');
            $this->supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $this->supporter->storage['phases'][] = 'askingReason';
            return;
        } else if ($lastPhase === 'askingReason') {
            if ($message['type'] !== 'text') {
                $this->supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            if ($message !== '前の項目を修正する') {
                if ($this->storeOrAskAgain('理由', $message))
                    return;
            }

            // 質問
            $this->supporter->pushMessage("理由の詳細を入力してください。\n例:熱があるため欠席させていただきます。\nこの度は大変失礼しました。", true);

            // 選択肢
            $this->supporter->pushUnsavedAnswerOption('理由の詳細');
            $this->supporter->pushOptions(['前の項目を修正する', 'キャンセル']);

            $this->supporter->storage['phases'][] = 'askingReasonDetail';
            return;
        } else if ($lastPhase === 'askingReasonDetail') {
            if ($message['type'] !== 'text') {
                $this->supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            if ($message !== '前の項目を修正する') {
                if ($this->storeOrAskAgain('理由の詳細', $message))
                    return;
            }

            // 質問
            $this->supporter->pushMessage("証拠の画像を送信してください。
※「風紀に相談済み」として直接風紀に証拠画像や資料を送っても構いません。
証拠画像がない場合も風紀に直接連絡してください。
このボットを使用した場合、証拠画像は五役のみが閲覧可能なGoogle Driveのフォルダにアップロードされ、該当する委員会行事の開催日(の開始日)後一日以内に自動で削除されます。
証拠資料が画像形式でない場合はスクリーンショット等で証拠として十分な部分を画像化してください。", true);

            // 選択肢
            if (isset($this->supporter->storage['unsavedAnswers']['証拠画像']) && $this->supporter->storage['unsavedAnswers']['証拠画像'] !== '風紀に相談済み')
                $this->supporter->pushUnsavedAnswerOption('証拠画像', 'image');
            $this->supporter->pushImageOption();
            $this->supporter->pushOptions(['風紀に相談済み', '前の項目を修正する', 'キャンセル']);

            $this->supporter->storage['phases'][] = 'askingEvidence';
            return;
        } else if ($lastPhase === 'askingEvidence') {
            if ($message['type'] === 'image') {
                if ($this->storeOrAskAgain('証拠画像', $message))
                    return;
            } else {
                if ($message['type'] !== 'text') {
                    $this->supporter->askAgainBecauseWrongReply();
                    return;
                }
                $message = $message['text'];

                if ($message !== '前の項目を修正する') {
                    if ($message === '風紀に相談済み') {
                        $this->supporter->storage['unsavedAnswers']['証拠画像'] = '風紀に相談済み';
                    } else {
                        if ($message !== '最後に送信した画像') {
                            $this->supporter->askAgainBecauseWrongReply();
                            return;
                        }
                        if (!isset($this->supporter->storage['unsavedAnswers']['証拠画像'])) {
                            $this->supporter->askAgainBecauseWrongReply();
                            return;
                        }
                    }
                }
            }

            if ($this->supporter->storage['unsavedAnswers']['委員会行事'] === '舎生大会') {
                // 質問
                $this->supporter->pushMessage("舎内規定第5条の3、4、5により、舎生大会を欠席する場合は議決に関する一切を、
遅刻する場合は風紀の出席確認を得て議決権を有するまでの間の議決に関する一切を、
早退する場合は早退後の議決に関する一切を
舎生大会に委任しなければなりません。

議決の委任に同意しますか？", true);

                // 選択肢
                $this->supporter->pushOptions(['はい', '前の項目を修正する', 'キャンセル']);
                $this->supporter->storage['phases'][] = 'askingConsent';
                return;
            }

            // 質問・選択肢
            unset($this->supporter->storage['unsavedAnswers']['議決の委任']);
            if ($this->supporter->storage['unsavedAnswers']['証拠画像'] === '風紀に相談済み') {
                $this->confirm();
            } else {
                $this->confirm(['証拠画像' => 'image']);
            }

            $this->supporter->storage['phases'][] = 'confirming';
            return;
        } else if ($lastPhase === 'askingConsent') {
            if ($message['type'] !== 'text') {
                $this->supporter->askAgainBecauseWrongReply();
                return;
            }
            $message = $message['text'];

            if ($this->storeOrAskAgain('議決の委任', $message))
                return;

            // 質問・選択肢
            if ($this->supporter->storage['unsavedAnswers']['証拠画像'] === '風紀に相談済み') {
                $this->confirm();
            } else {
                $this->confirm(['証拠画像' => 'image']);
            }

            $this->supporter->storage['phases'][] = 'confirming';
            return;
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
        if ($answers['証拠画像'] !== '風紀に相談済み') {
            $imageFileName = $answers['証拠画像'];
            $eventName = mb_substr($answers['委員会行事'], 0, 15);
            $driveFileName = "諸行事届_{$answers['開催日']}_{$eventName}_{$this->supporter->storage['userName']}.jpg";
            $id = $this->supporter->saveToDrive($imageFileName, $driveFileName, $this->supporter->config['shogyojiImageFolder'], true);
            $this->storeShogyojiImage($answers['開催日'], $id);
            $answers['証拠画像'] = $this->supporter->googleIdToUrl($id);
        }

        $answersForSheets = array_values($answers);

        // 日付の曜日を取る
        $answersForSheets[2] = deleteParentheses($answersForSheets[2]);

        // セルの数合わせ
        if (!isset($answers['議決の委任']))
            $answersForSheets[] = '';

        // 証拠画像削除
        unset($answersForSheets[6]);

        // 申請
        $this->supporter->applyForm($answers, $answersForSheets, true);
    }

    public function pushAdminMessages(array $profile, array $answers, string $timeStamp, string $receiptNo): bool
    {
        $eventDate = stringToDate($answers['開催日']);

        // 告知文
        $unspacedName = preg_replace('/[\x00\s]++/u', '', $answers['氏名']);
        $simplifiedDate = date("m/d", $eventDate);

        $this->supporter->pushMessage("<告知文 {$simplifiedDate} {$answers['委員会行事']} {$unspacedName}>
※(以下敬称略)を記載して使用すること。
また、理由の詳細は適宜要約すること。");

        $this->supporter->pushMessage("・{$unspacedName} {$answers['出欠']}
理由:{$answers['理由']}
{$answers['理由の詳細']}");


        // 任期内かどうかと過去の日付かどうか
        $messageAboutDate = '';
        if (!$this->supporter->checkInTerm($eventDate)) {
            $messageAboutDate = "
※任期外の日付です！";
        } else {
            $today = getDateAt0AM();
            if ($eventDate < $today)
                $messageAboutDate = "
※過去の日付です！";
        }

        // 行事名と開催日のチェック
        $messageAboutEvent = "
※この行事は登録されていません！
実際にある行事の場合は必ず登録してください。";
        $events = $this->supporter->fetchEvents();
        foreach ($events as $event) {
            if ($event['行事名'] === $answers['委員会行事'] && $event['開始日'] === $answers['開催日']) {
                $messageAboutEvent = '';
                break;
            }
        }

        // その他のチェック済みの項目のチェック
        $checkedItems = "出欠:{$answers['出欠']}";
        unset($answers['出欠']);
        if ($answers['理由'] !== 'その他') {
            $checkedItems .= "\n理由:{$answers['理由']}";
            unset($answers['理由']);
        }
        if (isset($answers['議決の委任'])) {
            $checkedItems .= "\n舎生大会の議決の委任への同意:{$answers['議決の委任']}";
            unset($answers['議決の委任']);
        }

        // 全文生成
        if ($messageAboutDate !== '' || $messageAboutEvent !== '') {
            $message = "{$answers['氏名']}(`{$profile['displayName']}`)が舎生大会・諸行事届を提出しました。
承認しますか？
(TS:{$timeStamp})
(届出番号:{$receiptNo})

チェック済み:
{$checkedItems}

危険な項目:
委員会行事:{$answers['委員会行事']}
開催日:{$answers['開催日']}{$messageAboutDate}{$messageAboutEvent}

未チェックの項目:";
        } else {
            $message = "{$answers['氏名']}(`{$profile['displayName']}`)が舎生大会・諸行事届を提出しました。
承認しますか？
(TS:{$timeStamp})
(届出番号:{$receiptNo})

チェック済み:
委員会行事:{$answers['委員会行事']}
開催日:{$answers['開催日']}
{$checkedItems}

未チェックの項目:";
        }
        unset($answers['氏名'], $answers['委員会行事'], $answers['開催日']);

        // 未チェックの項目諸々
        if ($answers['証拠画像'] !== '風紀に相談済み')
            $answers['証拠画像'] = "\n{$answers['証拠画像']}\n(ドライブに保存済み)";
        foreach ($answers as $label => $value) {
            $message .= "\n{$label}:{$value}";
        }

        $this->supporter->pushMessage($message, true, 'text', ['name' => $profile['displayName'], 'iconUrl' => $profile['pictureUrl'] ?? 'https://dummy.com']);
        $this->supporter->pushOptions(['承認する', '直接伝えた', '一番最後に見る']);
        return true;
    }

    protected function storeOrAskAgain(string $type, string|array $message): string
    {
        switch ($type) {
            case '委員会行事':
                if (isset($this->supporter->storage['cache']['eventsToDates'][$message]) || $message === 'その他') {
                    $this->supporter->storage['unsavedAnswers']['委員会行事'] = $message;
                    return '';
                }
                // 有効でなかった、もう一度質問文送信
                $this->supporter->askAgainBecauseWrongReply();
                return 'wrong-reply';
            case '開催日':
                $event = $this->supporter->storage['unsavedAnswers']['委員会行事'];
                if (in_array($message, $this->supporter->storage['cache']['eventsToDates'][$event] ?? [], true)) {
                    $this->supporter->storage['unsavedAnswers']['開催日'] = $message;
                    return '';
                }
                // 有効でなかった、もう一度質問文送信
                $this->supporter->askAgainBecauseWrongReply();
                return 'wrong-reply';
            case '開催日(手動入力)':
                $date = stringToDate($message);
                if ($date === false) {
                    $year = date('Y');
                    $this->supporter->askAgainBecauseWrongReply("入力の形式が違うか、無効な日付です。\n「1006」または「{$year}1006」のように4桁または8桁で入力してください。");
                    return 'wrong-reply';
                }

                $dateString = dateToDateStringWithDay($date);
                $this->supporter->pushMessage("開催日:{$dateString}");
                $this->supporter->storage['unsavedAnswers']['開催日'] = $dateString;
                return '';
            case '出欠':
                switch ($message) {
                    case '欠席':
                    case '遅刻':
                    case '早退':
                    case '遅刻と早退':
                        $this->supporter->storage['unsavedAnswers']['出欠'] = $message;
                        return '';
                }
                $this->supporter->askAgainBecauseWrongReply();
                return 'wrong-reply';
            case '理由':
                switch ($message) {
                    case '疾病':
                    case '體育會':
                    case '冠婚葬祭':
                    case '資格試験':
                    case '就職活動':
                    case '専門学校の試験':
                    case 'サークルの大会および合宿':
                    case '大学のカリキュラム':
                    case 'その他':
                        $this->supporter->storage['unsavedAnswers']['理由'] = $message;
                        return '';
                }
                $this->supporter->askAgainBecauseWrongReply();
                return 'wrong-reply';
            case '理由の詳細':
                $this->supporter->storage['unsavedAnswers']['理由の詳細'] = $message;
                return '';
            case '証拠画像':
                $fileName = $this->supporter->downloadContent($message);
                $this->supporter->storage['unsavedAnswers']['証拠画像'] = $fileName;

                // 将来的にゴミ箱へ移動するための予約
                if (!isset($this->supporter->storage['cache']['一時ファイル']))
                    $this->supporter->storage['cache']['一時ファイル'] = [];
                $this->supporter->storage['cache']['一時ファイル'][] = $fileName;
                return '';
            case '議決の委任':
                switch ($message) {
                    case 'はい':
                        $this->supporter->storage['unsavedAnswers']['議決の委任'] = $message;
                        return '';
                }
                $this->supporter->askAgainBecauseWrongReply();
                return 'wrong-reply';
        }
    }

    private function storeShogyojiImage(string $eventDate, string $id): void
    {
        $eventDate = deleteParentheses($eventDate);
        $shogyojiImages = $this->supporter->database->restore('shogyojiImages') ?? [];
        if (isset($shogyojiImages[$eventDate])) {
            $shogyojiImages[$eventDate][] = $id;
        } else {
            $shogyojiImages[$eventDate] = [$id];
        }
        $this->supporter->database->store('shogyojiImages', $shogyojiImages);
    }

    public static function deleteShogyojiImages(JsonDatabase $database, LogDatabase $logDatabase): void
    {
        // 昨日の0:00より前の行事の写真を取得
        $shogyojiImages = $database->restore('shogyojiImages') ?? [];
        $yesterday =  getDateAt0AM(time() - 60 * 60 * 24);
        $idsToDelete = [];
        foreach ($shogyojiImages as $eventDate => $ids) {
            if (strtotime($eventDate) < $yesterday) {
                $idsToDelete = array_merge($idsToDelete, $ids);
                unset($shogyojiImages[$eventDate]);
            }
        }

        if (!$idsToDelete) return;
        $database->store('shogyojiImages', $shogyojiImages);

        // 削除
        $drive = new \Google_Service_Drive(KishukushaReportSupporter::getGoogleClient());
        $deletedFileUrls = 'Nothing';
        $failureMessage = '';
        foreach ($idsToDelete as $i => $id) {
            try {
                // $drive->files->trash($id, ['supportsAllDrives' => true]); // ゴミ箱に移動
                $drive->files->delete($id, ['supportsAllDrives' => true]); // 完全に削除

                if ($i === 0) {
                    $deletedFileUrls = '';
                } else if ($i < count($ids) - 1) {
                    $deletedFileUrls .= ', ';
                } else {
                    $deletedFileUrls .= ' and ';
                }

                $deletedFileUrls .= "https://drive.google.com/file/d/{$id}/view?usp=sharing";
            } catch (\Throwable $e) {
                if ($failureMessage) $failureMessage .= "\n";
                $failureMessage .= "An error occurred. Please delete https://drive.google.com/file/d/{$id}/view?usp=sharing manually.\nError Message:\n{$e}";
            }
        }

        // ログの記録
        $log = "deleteShogyojiImages: {$deletedFileUrls}";
        if ($failureMessage) $log .= "error: {$failureMessage}";
        $logDatabase->log($log);
    }
}
