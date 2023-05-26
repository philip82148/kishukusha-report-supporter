<?php

namespace KishukushaReportSupporter;

use KishukushaReportSupporter\Forms;

class KishukushaReportSupporter
{
    public const VERSION = '9.0.1';

    /* 届出を追加する際はここの編集とsrc/Formsフォルダへのファイルの追加が必要 */
    public const FORMS = [
        '多目的室使用届' => Forms\Tamokuteki::class,
        '外部来訪者届' => Forms\Gaiburaihousha::class,
        '長期外泊届' => Forms\Chokigaihaku::class,
        '舎生大会・諸行事届' => Forms\Shogyoji::class,
        '踊り場私物配備届' => Forms\Odoriba::class,
        '309私物配備届' => Forms\Haibi309::class,
        '自転車・バイク配備届' => Forms\Bikes::class,
        '入力履歴を削除する' => Forms\Nyuryokurireki::class,
        '自分の名前を変更する' => Forms\AskName::class,
        'マニュアルを見る' => Forms\UserManual::class
    ];

    public const MAX_PREVIOUS_ANSWERS = 5;

    public string $userId;
    public array $config;
    public JsonDatabase $database;
    public self $admin;

    public array $storage;

    private string $replyToken;
    private string $pushUserId;
    private array $messages;
    private array $questions;
    private ?array $quickReply;
    private array $uniqueTextOptions;
    private array $lastQuestions;
    private ?array $lastQuickReply;
    private static \Google_Client $googleClient;

    // getEventInfo()用
    private array $lastEvent;
    private int $lastStorageUpdatedTime;
    private string $displayName;
    private static string $lastPushUserId;
    private static array $lastPushMessages;

    public function __construct(string $userId, JsonDatabase $database, ?self $admin = null)
    {
        $this->userId = $userId;
        $this->database = $database;
        $this->restoreConfig();
        $this->restoreStorage();

        $this->admin = match (true) {
            $userId === $this->config['adminId'] => $this,
            isset($admin) => $admin,
            default => new self($this->config['adminId'], $this->database)
        };

        // storageの方が書き換わっても、setLastQuestions()したときは必ず前回の質問になる
        $this->lastQuestions = $this->storage['lastQuestions'];
        $this->lastQuickReply = $this->storage['lastQuickReply'];

        // getEventInfo()用
        $this->lastStorageUpdatedTime = $this->getLastStorageUpdatedTime();
        $this->displayName = $this->storage['displayName'];
    }

    public function handleEvent(array $event): void
    {
        $this->initReply($event['replyToken'] ?? null);

        // テキストタイプの場合は前後のスペースを除いておく
        if ($event['type'] === 'message' && $event['message']['type'] === 'text')
            $event['message']['text'] = trimString($event['message']['text']);

        try {
            $this->_handleEvent($event);
            $this->confirmReply();
            $this->storeStorage();
        } catch (\Throwable $e) {
            $this->pushText("エラー内容:
{$e}

エラーが発生しました。
もう一度試してください。
何度試してもエラーになる場合はGoogle Formsを使用し、エラーの発生を佐々木に報告してください。");
            $this->setLastQuestions();
            $this->confirmReply();
            // storageは保存しない
            throw new \RuntimeException('メッセージの送信は完了しています。');
        } finally {
            $this->lastEvent = $event;
        }
    }

    private function _handleEvent(array $event): void
    {
        if ($event['type'] === 'follow') {
            (new Forms\AskName($this))->form([]);
            return;
        } else if ($event['type'] === 'unfollow') {
            $this->deleteStorage();
            return;
        }

        // この処理の対象をイベントタイプがメッセージに限定
        if ($event['type'] !== 'message') return;
        $message = $event['message'];

        // 管理者変更用
        if ($this->changeAdminIfPasswordSet($message))
            return;

        // 管理者、承認用
        if ($this->approving($message))
            return;

        // まだ名前が確定していない
        if ($this->storage['userName'] === '')
            $this->storage['formType'] = '自分の名前を変更する';

        if ($message['type'] === 'text') {
            // テキストタイプに限定
            $text = $message['text'];
            if ($text === '前の項目を修正する') {
                array_pop($this->storage['phases']); // 今聞いている質問をもう一度聞くフェーズへ
                array_pop($this->storage['phases']); // その前の質問をもう一度聞くフェーズへ
            } else if ($text === 'キャンセル') {
                if ($this->storage['userName'] !== '') {
                    if ($this->storage['formType'] !== '')
                        $this->pushText('キャンセルしました。');
                    $this->resetForm();
                    return;
                } else {
                    // 名前がまだ確定していないとき
                    $this->storage['phases'] = [];
                }
            } else if ($text === 'OK') {
                $this->setLastQuestions();
                return;
            } else {
                // 何の届け出を出すかが決まっていない
                if ($this->storage['formType'] === '')
                    $this->storage['formType'] = $text; // これが届け出の種類
            }
        } else {
            // テキストタイプでない
            // 何の届け出を出すかが決まっていない
            if ($this->storage['formType'] === '') {
                $this->askAgainBecauseWrongReply();
                return;
            }
        }

        switch ($this->storage['formType']) {
            case '回答を始める':
                $this->pushText('申請するものを選んでください。', true);
                $this->pushOptions(array_keys(self::FORMS), true);
                if ($this->isThisAdmin())
                    $this->pushOptions(['管理者設定'], true);
                $this->pushOptions(['キャンセル']);
                $this->resetStorage();
                break;
            case '管理者設定':
                if ($this->isThisAdmin()) {
                    (new Forms\AdminSettings($this))->form($message);
                    break;
                }
            default:
                $formClass = self::FORMS[$this->storage['formType']] ?? '';
                if ($formClass !== '') {
                    (new $formClass($this))->form($message);
                    break;
                }
                $this->askAgainBecauseWrongReply();
                $this->resetStorage();
                break;
        }
    }

    public function resetForm(): void
    {
        // 各変数初期化
        $this->resetStorage();

        // 質問
        $this->pushText("新しくフォームに入力を始める場合は「回答を始める」と入力してください。

このボットを使用した場合、風紀への報告は自動で行われるため不要です。", true);
        $this->pushText("※クイックリプライはスマホでのみ利用できます。
※何らかのエラーが起こったときは佐々木に報告して、Google Formsを使用してください。

VERSION\n", true);
        $this->pushOptions(['回答を始める']);
    }

    // 管理者、届出承認用
    private function approving(array $message): bool
    {
        // (管理者でないまたは)届け出がない場合はreturn
        if (empty($this->storage['adminPhase']))
            return false;

        // テキストタイプのみ
        if ($message['type'] !== 'text') {
            $this->askAgainBecauseWrongReply();
            return true;
        }
        $message = $message['text'];

        $unapprovedFormCount = count($this->storage['adminPhase']);
        $lastPhase = $this->storage['adminPhase'][$unapprovedFormCount - 1];
        switch ($message) {
            case '承認する':
                try {
                    $spreadsheet_service = new \Google_Service_Sheets(self::getGoogleClient());

                    // 書き込み
                    $spreadsheet_service->spreadsheets_values->update(
                        $this->config['resultSheets'],
                        $lastPhase['checkboxRange'],
                        new \Google_Service_Sheets_ValueRange([
                            'values' => [['TRUE']]
                        ]),
                        ['valueInputOption' => 'USER_ENTERED']
                    );
                } catch (\Throwable $e) {
                    throw new BottomMessageExceptionWrapper($e, "スプレッドシートへの書き込み中にエラーが発生しました。\nシートが削除されたか、ボットに編集権限がない可能性があります。");
                }

                try {
                    // 申請した本人への通知
                    $this->initPush($lastPhase['userId']);
                    $adminProfile = $this->fetchProfile();
                    $this->pushText("{$lastPhase['formType']}が承認されました。\n(届出番号:{$lastPhase['receiptNo']})", false, ['name' => $adminProfile['displayName'], 'iconUrl' => $adminProfile['pictureUrl'] ?? 'https://dummy.com/']);
                    $this->pushOptions(['OK']);
                    $this->confirmPush(true);
                } catch (\Throwable $e) {
                    $this->initReply();
                    throw new BottomMessageExceptionWrapper($e, "スプレッドシートへの書き込みは成功しましたが、本人への通知中にエラーが発生しました。\nもう一度「承認する」を押すと本人への通知のみを再試行します。");
                }

                $this->restoreStorage();
                if (count($this->storage['adminPhase']) !== $unapprovedFormCount) {
                    $e = new \RuntimeException('New form submitted during approval');
                    throw new BottomMessageExceptionWrapper($e, "スプレッドシートへの書きこみ及び本人への通知に成功しましたが、その最中に新たな申請がありました。\nデータの衝突を避けるために今回の承認操作は記録されません。\n届出番号{$lastPhase['receiptNo']}の{$lastPhase['userName']}の{$lastPhase['formType']}は後でもう一度承認してください(再度書きこみと通知が行われます)。");
                }

                // 管理者への通知
                $this->initReply();
                $this->pushText("{$lastPhase['userName']}の{$lastPhase['formType']}を承認しました。\nスプレッドシートへのチェックと、本人への通知を行いました。\n(届出番号:{$lastPhase['receiptNo']})");
                break;
            case '直接伝えた':
                try {
                    // 申請した本人への通知
                    $this->initPush($lastPhase['userId']);
                    $adminProfile = $this->fetchProfile();
                    $this->pushText("届出番号{$lastPhase['receiptNo']}の{$lastPhase['formType']}を風紀は確認しましたが、ボットを使用した承認は行われませんでした。

これについて風紀から直接連絡がなかった場合は手動でスプレッドシートにチェックを入れた可能性があります。

まず、スプレッドシートにチェックが入っているかを確認し、入っていない場合は風紀に直接問い合わせてください。", false, ['name' => $adminProfile['displayName'], 'iconUrl' => $adminProfile['pictureUrl'] ?? 'https://dummy.com/']);
                    $this->pushOptions(['OK']);
                    $this->confirmPush(true);
                } catch (\Throwable $e) {
                    $this->initReply();
                    $this->pushText("{$e}\n届出番号{$lastPhase['receiptNo']}の{$lastPhase['userName']}の{$lastPhase['formType']}について、ボットを使用した承認が行われなかった旨の本人への通知中にエラーが発生しました。\n必要ならば手動で本人に通知してください。");
                    break;
                }

                $this->restoreStorage();
                if (count($this->storage['adminPhase']) !== $unapprovedFormCount) {
                    $e = new \RuntimeException('New form submitted during approval');
                    throw new BottomMessageExceptionWrapper($e, "本人への通知に成功しましたが、その最中に新たな申請がありました。\nデータの衝突を避けるために今回の操作は記録されません。\n届出番号{$lastPhase['receiptNo']}の{$lastPhase['userName']}の{$lastPhase['formType']}は後でもう一度承認/非承認を行ってください(再度通知が行われます)。");
                }

                // 管理者への通知
                $this->initReply();
                $this->pushText("{$lastPhase['userName']}の{$lastPhase['formType']}について、ボットを使用した承認が行われなかった旨を本人へ通知しました。\n(届出番号:{$lastPhase['receiptNo']})");
                break;
            case '一番最後に見る':
                if (count($this->storage['adminPhase']) === 1) {
                    // 次の質問は承認するかどうかの質問ではない
                    // もう一度同じ質問を聞く
                    $this->pushText('他に承認が必要な届出はありません。');
                    $this->setLastQuestions();
                    return true;
                }

                // 次に最後の質問を聞く
                $this->setLastQuestions($lastPhase['lastQuestions'], $lastPhase['lastQuickReply']);
                array_pop($this->storage['adminPhase']);

                // adminPhaseの一番最後の質問の保持
                $lastLastQuestions = $this->storage['adminPhase'][0]['lastQuestions'];
                $lastLastQuickReply = $this->storage['adminPhase'][0]['lastQuickReply'];

                // この前に聞いた質問を一番最初へ
                $this->storage['adminPhase'][0]['lastQuestions'] = $this->storage['lastQuestions'];
                $this->storage['adminPhase'][0]['lastQuickReply'] = $this->storage['lastQuickReply'];

                // この前に聞いた質問の答えをそれよりも前へ
                array_unshift($this->storage['adminPhase'], $lastPhase);

                // adminPhaseの一番最後の質問を戻す
                $this->storage['adminPhase'][0]['lastQuestions'] = $lastLastQuestions;
                $this->storage['adminPhase'][0]['lastQuickReply'] = $lastLastQuickReply;
                return true;
            default:
                $this->askAgainBecauseWrongReply();
                return true;
        }
        // 管理者への通知(続き)
        $this->setLastQuestions($lastPhase['lastQuestions'], $lastPhase['lastQuickReply']);
        array_pop($this->storage['adminPhase']);

        return true;
    }

    private function changeAdminIfPasswordSet(array $message): bool
    {
        // パスワードが登録されている
        if (!isset($this->config['password'])) return false;

        // テキストタイプのみ
        if ($message['type'] !== 'text') return false;
        $message = $message['text'];

        // パスワードと一致した
        if ($message !== $this->config['password']) return false;

        // パスワードを打った人を管理者にする

        // ここまでで他に発生したトランザクションについて更新(adminPhase等)
        $this->admin->restoreStorage(); // 他に発生したトランザクションについて更新

        // configの変更
        $this->config['adminId'] = $this->userId;
        unset($this->config['password']);
        $this->storeConfig();

        // なおこれ以降restoreStorageで元管理者のadminPhaseが消え、
        // 新管理者にadminPhaseが現れるようになる

        // パスワードを打ったのは元管理者(自分自身)でない
        if ($this->userId !== $this->admin->userId) {
            // 元管理者への通知
            try {
                $this->admin->initPush();
                $newAdminProfile = $this->fetchProfile();
                $this->admin->pushText('管理者が変更されました。', false, ['name' => $newAdminProfile['displayName'], 'iconUrl' => $newAdminProfile['pictureUrl'] ?? 'https://dummy.com/']);
                $this->admin->pushOptions(['OK']);
                $this->admin->confirmPush(true);
            } catch (\Throwable $e) {
            }
        }

        // 新管理者への通知とマニュアルの表示
        $this->pushText('管理者が変更されました。');
        $this->pushText(ADMIN_MANUAL);
        $this->pushText(SERVER_MANUAL);
        $this->pushText('これらのマニュアルは「管理者設定」>「管理者用マニュアル表示」からいつでも確認できます。');
        $this->pushOptions(['OK']);

        // adminPhaseの移動(なおここで$this->adminは元管理者)
        $adminPhase = $this->admin->storage['adminPhase'];
        unset($this->admin->storage['adminPhase']);
        if (!empty($adminPhase)) {
            // adminの前回の質問を引き継ぐ(なおここで'OK'の選択肢は削除される)
            $this->setLastQuestions($this->admin->storage['lastQuestions'], $this->admin->storage['lastQuickReply']);

            // adminPhaseの一番最後の質問を元adminに返す
            $this->admin->storage['lastQuestions'] = $adminPhase[0]['lastQuestions'];
            $this->admin->storage['lastQuickReply'] = $adminPhase[0]['lastQuickReply'];

            // 元管理者が存在しなければ保存はしない
            if ($this->admin->doesThisExist())
                $this->admin->storeStorage();

            $this->restoreStorage(); // 他に発生したトランザクションについて更新

            // 新adminの前回の質問を最も最後のlastQuestionsへ
            // 新adminのlastQuestionsは上のsetLastQuestionsでこの後書き換えられる
            $adminPhase[0]['lastQuestions'] = $this->storage['lastQuestions'];
            $adminPhase[0]['lastQuickReply'] = $this->storage['lastQuickReply'];

            $this->storage['adminPhase'] = array_merge($this->storage['adminPhase'], $adminPhase);
        } else {
            // 元管理者が存在しなければ保存はしない
            if ($this->admin->doesThisExist())
                $this->admin->storeStorage();
            // このあとstoreすることになるので、ともかくrestoreする
            $this->restoreStorage(); // 他に発生したトランザクションについて更新
        }

        // 変更
        $this->admin->admin = $this;
        $this->admin = $this;

        return true;
    }

    public function applyForm(array $answers, array $answersForSheets, bool $needCheckbox = false, string $message = ''): void
    {
        // 承認が必要な届出だが、管理者の存在が確認できない場合
        if ($needCheckbox && !$this->isThisAdmin() && !$this->admin->doesThisExist()) {
            // このユーザーを管理者にする
            // configの書き換え
            $this->config['adminId'] = $this->userId;
            $this->storeConfig();

            // 変更
            $this->admin->admin = $this;
            $this->admin = $this;

            // 通知
            $this->pushText("ボットに登録された管理者のアカウントの存在が確認できませんでした。
管理者がボットのブロックやLINEアカウントの削除等をした可能性があります。
管理者のアカウントの存在確認が出来なくなってから初めて承認が必要な届出を行ったあなたのアカウントに管理者権限を移行しました。
あなたが風紀でない場合は風紀に連絡、あなたが現役舎生でない場合はボットをブロックしてください。");
        }

        try {
            $timeStamp = date('Y/m/d H:i:s');
            $appendRow = array_merge([$timeStamp], $answersForSheets);
            if ($needCheckbox) {
                // 承認のチェック
                if ($this->isThisAdmin()) {
                    $appendRow[] = 'TRUE';
                } else {
                    $appendRow[] = 'FALSE';
                }
            }

            // スプレッドシートに書き込み
            $spreadsheet_service = new \Google_Service_Sheets(self::getGoogleClient());

            // 結果を追加
            $response = $this->appendToResultSheets($this->storage['formType'], $appendRow, $spreadsheet_service);

            // チェックボックスを追加
            if ($needCheckbox) {
                // 行・列番号取得
                $updatedRange = $response->getUpdates()->getUpdatedRange();
                $matches = [];
                preg_match('/(?<sheetName>([^!]+!)?)([^:]+:)?(?<columnAlphabet>\D+)(?<rowNo>\d+)/', $updatedRange, $matches);
                $checkboxRowNo = $matches['rowNo'] - 1;
                $checkboxColumnNo = count($appendRow) - 1;
                $checkboxRange = $matches['sheetName'] . $matches['columnAlphabet'] . $matches['rowNo'];

                // シートIDを取得(追加直後なので存在するはず)
                $response = $spreadsheet_service->spreadsheets->get($this->config['resultSheets']);
                $sheetId = $this->getSheetId($response->getSheets(), $this->storage['formType']);

                // チェックボックス追加
                $requestBody = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest();
                $requestBody->setRequests([
                    'setDataValidation' => [
                        'range' =>  [
                            'sheetId' => $sheetId,
                            'startRowIndex' => $checkboxRowNo,
                            'endRowIndex' => $checkboxRowNo + 1,
                            'startColumnIndex' => $checkboxColumnNo,
                            'endColumnIndex' => $checkboxColumnNo + 1
                        ],
                        'rule' => [
                            'condition' => [
                                'type' => 'BOOLEAN',
                            ],
                            'strict' => true
                        ]
                    ]
                ]);
                $response = $spreadsheet_service->spreadsheets->batchUpdate(
                    $this->config['resultSheets'],
                    $requestBody
                );
            }
        } catch (\Throwable $e) {
            throw new BottomMessageExceptionWrapper($e, "スプレッドシートへの書き込み中にエラーが発生しました。\nシートが削除されたか、ボットに編集権限がない可能性があります。");
        }

        // 自分が管理者でない、かつ、承認が必要なら、管理者に通知
        if (!$this->isThisAdmin() && $needCheckbox) {
            try {
                $receiptNo = $this->admin->notifyAppliedForm($this, $answers, $timeStamp, $checkboxRange ?? '');
            } catch (\Throwable $e) {
                throw new BottomMessageExceptionWrapper($e, "スプレッドシートへの書き込みは成功しましたが、風紀への通知中にエラーが発生しました。");
            }
        }

        // ユーザーへの通知
        if ($this->isThisAdmin()) {
            if ($needCheckbox) {
                $this->pushText("{$this->storage['formType']}を提出しました。\n(※承認済み)");
            } else {
                if ($message !== '')
                    $message = "\n{$message}";
                $this->pushText("{$this->storage['formType']}を提出しました。{$message}");
            }
        } else {
            if ($needCheckbox) {
                $this->pushText("{$this->storage['formType']}を申請しました。\n風紀の承認をお待ちください。\n(届出番号:{$receiptNo})");
            } else {
                if ($message !== '')
                    $message = "\n{$message}";
                $this->pushText("{$this->storage['formType']}を提出しました。{$message}\n※この届出に風紀の承認はありません。");
            }
        }
    }

    private function appendToResultSheets(string $formType, array $row, $spreadsheet_service, string $resultSheetId = null)
    {
        if (!isset($resultSheetId))
            $resultSheetId = $this->config['resultSheets'];

        try {
            // 書き込み
            $response = $spreadsheet_service->spreadsheets_values->append(
                $resultSheetId,
                "'{$formType}'!A1",
                new \Google_Service_Sheets_ValueRange([
                    'values' => [$row]
                ]),
                ['valueInputOption' => 'USER_ENTERED']
            );

            return $response;
        } catch (\Throwable $e) {
            $header = array_merge(['タイムスタンプ'], self::FORMS[$formType]::HEADER);

            // シートが存在しない場合作成
            $requestBody = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
                'requests' => [
                    'addSheet' => [
                        'properties' => [
                            'title' => $formType
                        ]
                    ]
                ]
            ]);
            $response = $spreadsheet_service->spreadsheets->batchUpdate($resultSheetId, $requestBody);
            $sheetId = $response->getReplies()[0]->getAddSheet()->getProperties()->sheetId;

            // 一行目固定、セル幅指定
            $requestBody = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
                'requests' => [
                    new \Google_Service_Sheets_Request([
                        'update_sheet_properties' => [
                            'properties' => [
                                'sheet_id' => $sheetId,
                                'grid_properties' => ['frozen_row_count' => 1]
                            ],
                            'fields' => 'gridProperties.frozenRowCount'
                        ]
                    ]),
                    new \Google_Service_Sheets_Request([
                        'updateDimensionProperties' => [
                            'range' =>  [
                                'sheetId' => $sheetId,
                                'dimension' => 'COLUMNS',
                                'startIndex' => 0,
                                'endIndex' => count($header)
                            ],
                            'properties' => [
                                'pixelSize' => 150
                            ],
                            'fields' => 'pixelSize'
                        ]
                    ])
                ]
            ]);
            $spreadsheet_service->spreadsheets->batchUpdate(
                $this->config['resultSheets'],
                $requestBody
            );

            // 見出しと共に書き込み
            $response = $spreadsheet_service->spreadsheets_values->append(
                $resultSheetId,
                "'{$formType}'!A1",
                new \Google_Service_Sheets_ValueRange([
                    'values' => [$header, $row]
                ]),
                ['valueInputOption' => 'USER_ENTERED']
            );

            return $response;
        }
    }

    private function getSheetId($sheets, $sheetName): ?int
    {
        foreach ($sheets as $sheet) {
            if ($sheet['properties']['title'] !== $sheetName) continue;
            return $sheet['properties']['sheetId'];
        }
        return null;
    }

    private function notifyAppliedForm(self $supporter, array $answers, string $timeStamp, string $checkboxRange): string
    {
        $timeStamp = date('Y/m/d H:i', strtotime($timeStamp));
        $profile = $supporter->fetchProfile();
        $pushMessageCount = $this->fetchPushMessageCount();
        if ($pushMessageCount === false) $pushMessageCount = 9999;

        $this->initPush();
        // 他に発生したトランザクションについて更新する
        $this->restoreStorage();

        $unapprovedFormCount = count($this->storage['adminPhase']) + 1;
        $receiptNo = sprintf('#%d%02d', $unapprovedFormCount, $pushMessageCount);

        $formClass = self::FORMS[$supporter->storage['formType']];
        $needApproval = (new $formClass($this))->pushAdminMessages($profile, $answers, $timeStamp, $receiptNo);

        // 通知
        if ($needApproval) {
            $this->storage['adminPhase'][] = [
                'userId' => $supporter->userId,
                'userName' => $supporter->storage['userName'],
                'formType' => $supporter->storage['formType'],
                'receiptNo' => $receiptNo,
                'checkboxRange' => $checkboxRange,
                'lastQuickReply' => $this->storage['lastQuickReply'],
                'lastQuestions' => $this->storage['lastQuestions']
            ];
            $this->confirmPush(true);
            $this->storeStorage();
        } else {
            $this->confirmPush(false);
        }

        return $receiptNo;
    }

    public function saveToDrive(string $imageFilename, string $driveFilename, string $parentFolder, bool $returnId = false): string
    {
        try {
            // ドライブに保存
            $drive_service = new \Google_Service_Drive(self::getGoogleClient());
            $file = $drive_service->files->create(new \Google_Service_Drive_DriveFile([
                'name' => $driveFilename, // なんかバリデーションは要らないらしい
                'parents' => [$parentFolder],
            ]), [
                'data' => file_get_contents(IMAGE_FOLDER_PATH . $imageFilename),
                'mimeType' => 'image/jpeg',
                'fields' => 'id',
                'supportsAllDrives' => true,
            ]);

            if ($returnId)
                return $file->getId();

            return $this->googleIdToUrl($file->getId());
        } catch (\Throwable $e) {
            throw new BottomMessageExceptionWrapper($e, "画像のドライブへの保存に失敗しました。\nボットに指定のフォルダへのファイル追加権限がない可能性があります。");
        }
    }

    public function googleIdToUrl(string $id): string
    {
        // return "https://drive.google.com/uc?id={$id}";
        return "https://drive.google.com/file/d/{$id}/view?usp=sharing";
    }

    public function fetchEvents(bool $forceUpdate = false): array
    {
        if (!$forceUpdate) {
            // データベースから取得
            $events = $this->database->restore('events');
            if (isset($events)) return $events;
        }

        try {
            // データベースに無かった
            // スプレッドシートから取得
            $spreadsheet_service = new \Google_Service_Sheets(self::getGoogleClient());

            // 読み取り
            $response = $spreadsheet_service->spreadsheets_values->get($this->config['variableSheets'], "'行事'!A1:C", [
                'valueRenderOption' => 'UNFORMATTED_VALUE',
                'dateTimeRenderOption' => 'SERIAL_NUMBER'
            ]);
            $rows = $response->getValues();

            // 有効な日付であれば配列にする
            $events = [];
            foreach ($rows as $row) {
                $event = [
                    '行事名' => trimString($row[0] ?? ''),
                    '開始日' => $this->serialNumberToDateStringWithDay($row[1] ?? ''),
                    '終了日' => $this->serialNumberToDateStringWithDay($row[2] ?? '')
                ];
                if ($event['開始日'] === false) continue;
                if ($event['終了日'] === false) {
                    $event['終了日'] = $event['開始日'];
                } else if (stringToDate($event['開始日']) > stringToDate($event['終了日'])) {
                    continue;
                }

                $events[] = $event;
            }

            // 開始日順にソート
            usort($events, fn ($a, $b) => stringToDate($a['開始日']) <=> stringToDate($b['開始日']));

            // データベースに保存
            $this->database->store('events', $events);

            return $events;
        } catch (\Throwable $e) {
            throw new BottomMessageExceptionWrapper($e, '行事データの読み込みに失敗しました。');
        }
    }

    private function serialNumberToDateStringWithDay(mixed $serialNumber): string|false
    {
        if (is_numeric($serialNumber)) {
            $unixTimeStamp =  strtotime('1899/12/30') + $serialNumber * 60 * 60 * 24;
        } else {
            $unixTimeStamp = strtotime($serialNumber);
            if ($unixTimeStamp === false)
                return false;
        }

        return dateToDateStringWithDay($unixTimeStamp);
    }

    public function checkValidGoogleItem(string $type, string $id): bool
    {
        try {
            switch ($type) {
                case 'variableSheets':
                case 'resultSheets':
                    $spreadsheet_service = new \Google_Service_Sheets(self::getGoogleClient());
                    if ($type === 'variableSheets') {
                        // 読み取り
                        $response = $spreadsheet_service->spreadsheets_values->get($id, "'行事'!A1:C");
                    } else {
                        // 何か一つ届出のシートを書き込む
                        foreach (self::FORMS as $formType => $formClass) {
                            if (is_subclass_of($formClass, FormTemplate::class)) break;
                        }
                        $this->appendToResultSheets($formType, [], $spreadsheet_service, $id);
                    }
                    break;
                case 'shogyojiImageFolder':
                case 'odoribaImageFolder':
                case '309ImageFolder':
                case 'bikesImageFolder':
                case 'tamokutekiImageFolder':
                    $fileId = $this->saveToDrive(TEST_IMAGE_FILENAME, 'TEST', $id, true);
                    if ($type === 'shogyojiImageFolder') {
                        $drive_service = new \Google_Service_Drive(self::getGoogleClient());
                        $drive_service->files->delete($fileId, ['supportsAllDrives' => true]);
                    }
                    break;
            }
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function getGoogleClient(): \Google_Client
    {
        if (!isset(self::$googleClient)) {
            self::$googleClient = new \Google_Client();
            self::$googleClient->setScopes([
                \Google_Service_Sheets::SPREADSHEETS, // スプレッドシート
                \Google_Service_Sheets::DRIVE, // ドライブ
            ]);
            self::$googleClient->setAuthConfig(CREDENTIALS_PATH);
        }

        return self::$googleClient;
    }

    public function checkInTerm(int $date): bool
    {
        $endOfTermDate = $this->updateEndOfTerm();
        if ($date > $endOfTermDate) return false;

        return true;
    }

    private function updateEndOfTerm(): int
    {
        $endOfTermDate = stringToDate($this->config['endOfTerm']);
        $today = getDateAt0AM();
        // endOfTermが設定されていて未来の時刻なら
        if ($endOfTermDate !== false && $endOfTermDate >= $today) return $endOfTermDate;

        $year = (int)date('Y');
        $endOfTermDate = strtotime("{$year}/05/31");
        if ($endOfTermDate < $today) {
            $endOfTermDate = strtotime("{$year}/11/30");
            if ($endOfTermDate < $today) {
                $nextYear = $year + 1;
                $endOfTermDate = strtotime("{$nextYear}/05/31");
            }
        }

        $this->config['endOfTerm'] = dateToDateStringWithDay($endOfTermDate);
        $this->storeConfig();

        return $endOfTermDate;
    }

    public function isThisAdmin(): bool
    {
        // configを書き換えるとisThisAdminが効かなくなる
        return $this->userId === $this->config['adminId'];
    }

    public function initReply(?string $replyToken = null): void
    {
        // replyTokenはある時だけ更新
        if (isset($replyToken))
            $this->replyToken = $replyToken;
        $this->initMessages();
    }

    public function initPush(?string $userId = null): void
    {
        if (isset($userId)) {
            $this->pushUserId = $userId;
        } else {
            $this->pushUserId = $this->userId;
        }
        $this->initMessages();
    }

    private function initMessages(): void
    {
        $this->messages = [];
        $this->questions = [];
        $this->quickReply = null;
        $this->uniqueTextOptions = [];
    }

    public function pushText(string $text, bool $isQuestion = false, ?array $sender = null): void
    {
        // 回答は4500文字以下に
        // (サロゲートペアは2文字以上とカウントされるので、本来5000文字まで許容できるが余裕をもって4500文字とする)
        if (mb_strlen($text) > 4500)
            $text = mb_substr($text, 0, 4500) . "…\n\n送信する文字数が多すぎるため、残りの文字が省略されました。";
        $message = [
            'type' => 'text',
            'text' => $text
        ];

        if (isset($sender)) $message['sender'] = $sender;

        if ($isQuestion) {
            $this->questions[] = $message;
        } else {
            $this->messages[] = $message;
        }
    }

    public function pushImage(string $url, bool $isQuestion = false, ?array $sender = null): void
    {
        // なお$urlは2000文字以下とすること
        $message = [
            'type' => 'image',
            'originalContentUrl' => $url,
            'previewImageUrl' => $url
        ];

        if (isset($sender)) $message['sender'] = $sender;

        if ($isQuestion) {
            $this->questions[] = $message;
        } else {
            $this->messages[] = $message;
        }
    }

    public function pushOptions(array $options, bool $ifDisplayInMessage = false, bool $isInputHistory = false): void
    {
        if (!isset($this->quickReply))
            $this->quickReply = ['items' =>  []];

        foreach ($options as $labelSuffix => $option) {
            if (!is_string($labelSuffix)) {
                if ($isInputHistory) {
                    $labelSuffix = '(履歴)';
                } else {
                    $labelSuffix = '';
                }
            }

            // ラベル作成
            $suffixLength = mb_strlen($labelSuffix);
            if (mb_strlen($option) + $suffixLength <= 20) {
                $label = $option . $labelSuffix;
            } else {
                $label = mb_substr($option, 0, 19 - $suffixLength) . '…' . $labelSuffix;
            }

            // なお$optionは300文字以下とすること(ここでカットは行わない)

            // まだ追加したことがない選択肢である
            if (!isset($this->uniqueTextOptions[$option])) {
                $addedIndex = count($this->quickReply['items']);
                if ($addedIndex >= 13) continue;

                // quickReplyへの追加と必要ならばディスプレイへの表示
                $this->quickReply['items'][] = [
                    'type' => 'action',
                    'action' => [
                        'type' => 'message',
                        'label' => $label,
                        'text' => $option
                    ]
                ];
                if ($ifDisplayInMessage && count($this->questions))
                    $this->questions[count($this->questions) - 1]['text'] .= "\n・{$option}";

                $this->uniqueTextOptions[$option] = $addedIndex;
                continue;
            }

            // suffixがある場合はそちらに変更する
            if ($labelSuffix !== '') {
                $addedIndex = $this->uniqueTextOptions[$option];
                $this->quickReply['items'][$addedIndex]['action']['label'] = $label;
            }
        }
    }

    public function pushImageOption(): void
    {
        if (!isset($this->quickReply))
            $this->quickReply = ['items' => []];

        $this->quickReply['items'][] = [
            'type'  => 'action',
            'action' => [
                'type' => 'camera',
                'label' => '画像を撮影する'
            ]
        ];
    }

    public function pushLocaleOptions(): void
    {
        if (!isset($this->quickReply))
            $this->quickReply = ['items' => []];

        $this->quickReply['items'][] = [
            'type' => 'action',
            'action' => [
                'type' => 'location',
                'label' => '位置情報を開く'
            ]
        ];
        $this->quickReply['items'][] = [
            'type' => 'action',
            'action' => [
                'type' => 'uri',
                'label' => 'Google Mapsで住所を調べる',
                'uri' => 'https://goo.gl/maps/7wWao8Dz94SuhVT36'
            ]
        ];
    }

    public function pushUnsavedAnswerOption(string $type, string $messageType = 'text'): void
    {
        if (isset($this->storage['unsavedAnswers'][$type])) {
            switch ($messageType) {
                case 'text':
                    // 回答が290文字以下の場合のみpush
                    // (サロゲートペアは2文字以上とカウントされるので、本来300文字まで許容できるが余裕をもって290文字とする)
                    if (mb_strlen($this->storage['unsavedAnswers'][$type]) <= 290)
                        $this->pushOptions(['(最後の回答)' => $this->storage['unsavedAnswers'][$type]]);
                    break;
                case 'image':
                    $this->pushOptions(['最後に送信した画像']);
                    break;
            }
        }
    }

    public function pushPreviousAnswerOptions(string $type): void
    {
        if (!isset($this->storage['previousAnswers'][$type]))
            return;

        switch ($type) {
            case '外部来訪者の女性の数':
                $name = $this->storage['unsavedAnswers']['外部来訪者名'];
                if (isset($this->storage['previousAnswers'][$type][$name]))
                    $this->pushOptions($this->storage['previousAnswers'][$type][$name], false, true);
                break;
            default:
                $this->pushOptions($this->storage['previousAnswers'][$type], false, true);
                break;
        }
    }

    public function pushPreviousAnswer(string $type, string $previousAnswer): void
    {
        // 回答が290文字以下の場合のみpush
        // (サロゲートペアは2文字以上とカウントされるので、本来300文字まで許容できるが余裕をもって290文字とする)
        if (mb_strlen($previousAnswer) > 290) return;

        switch ($type) {
            case '外部来訪者の女性の数':
                // 初めての回答
                if (!isset($this->storage['previousAnswers'][$type]))
                    $this->storage['previousAnswers'][$type] = [];

                // 記録
                $this->storage['previousAnswers'][$type][$this->storage['unsavedAnswers']['外部来訪者名']] = [$previousAnswer];

                // もうすでに記録されていない外部来訪者名の女性の数は消す
                foreach ($this->storage['previousAnswers'][$type] as $name => $number) {
                    if (!in_array($name, $this->storage['previousAnswers']['外部来訪者名'], true))
                        unset($this->storage['previousAnswers'][$type][$name]);
                }
                return;
        }

        // 初めての回答
        if (!isset($this->storage['previousAnswers'][$type])) {
            $this->storage['previousAnswers'][$type] = [$previousAnswer];
            return;
        }

        // 同じものがなければ3個目に追加、4個目以上は削除
        $index = array_search($previousAnswer, $this->storage['previousAnswers'][$type], true);
        if ($index === false) {
            array_splice($this->storage['previousAnswers'][$type], self::MAX_PREVIOUS_ANSWERS - 1, null, $previousAnswer);
            return;
        }

        // 同じものがあった、一つ先頭側に移動する
        if ($index >= 1) {
            $this->storage['previousAnswers'][$type][$index] = $this->storage['previousAnswers'][$type][$index - 1];
            $this->storage['previousAnswers'][$type][$index - 1] = $previousAnswer;
        }
    }

    public function askAgainBecauseWrongReply(string $message = "選択肢外の回答です。\nもう一度入力してください。"): void
    {
        /*
        if (isset($this->storage['lastQuickReply'])) {
            $undisplayedOptions = [];
            foreach ($this->storage['lastQuickReply']['items'] ?? [] as $item) {
                switch ($item['action']['label'] ?? '') {
                    case 'はい':
                        $undisplayedOptions[] = '良い場合は「はい」';
                        break;
                    case '前の項目を修正する':
                        $undisplayedOptions[] = '前の項目を修正する場合は「前の項目を修正する」';
                        break;
                    case 'キャンセル':
                        $undisplayedOptions[] = '最初からやり直す場合は「キャンセル」';
                        break;
                    case '承認する':
                        $undisplayedOptions[] = '承認する場合は「承認する」';
                        break;
                    case '直接伝えた':
                        $undisplayedOptions[] = 'スプレッドシートへのチェックを行わない場合は「直接伝えた」';
                        break;
                    case '一番最後に見る':
                        $undisplayedOptions[] = '他の届出を先に見る場合は「一番最後に見る」';
                        break;
                    default:
                        continue 2;
                }
            }
            if (!empty($undisplayedOptions))
                $message .= "\n\n" . implode("、\n", $undisplayedOptions) . 'と入力してください。';
        }*/
        $this->pushText($message);
        $this->setLastQuestions();
    }

    public function setLastQuestions(?array $questions = null, ?array $quickReply = null): void
    {
        // confirmReply/Push後に呼び出されるとstorageの方は書き変わっている可能性があるので、
        // プロパティを使う。これで確実に前回の質問
        if (!isset($questions))
            $questions = $this->lastQuestions;
        if (!isset($quickReply))
            $quickReply = $this->lastQuickReply;

        $this->questions = $questions;
        $this->quickReply = $quickReply;
    }

    public function confirmReply(): void
    {
        if (!$this->confirmMessages()) return;

        // 返信
        $ch = curl_init('https://api.line.me/v2/bot/message/reply');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . CHANNEL_ACCESS_TOKEN
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'replyToken' => $this->replyToken,
            'messages' => $this->messages
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // 成功するまで繰り返す
        $retryCount = 0;
        while (1) {
            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

            // 成功
            if ($statusCode >= 200 && $statusCode < 300) break;
            // Conflict:すでにリクエストは受理済み
            if ($statusCode === 409) break;

            // 400番台のエラーは再試行しても変わらないのでthrow
            if (++$retryCount >= 4 || ($statusCode >= 400 && $statusCode < 500)) {
                $e = new \RuntimeException(curl_error($ch) . "\nRetry Count:{$retryCount}\n{$response}");
                throw new BottomMessageExceptionWrapper($e, "返信処理に失敗しました。");
            }

            sleep(2 ** $retryCount);
        }

        curl_close($ch);
    }

    public function confirmPush(bool $notification = true): void
    {
        if (!$this->confirmMessages()) return;

        // 送信
        $ch = curl_init('https://api.line.me/v2/bot/message/push');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . CHANNEL_ACCESS_TOKEN,
            'X-Line-Retry-Key: ' . generateUUID()
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'to' => $this->pushUserId,
            'messages' => $this->messages,
            'notificationDisabled' => !$notification
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // getEventInfo()用記録
        self::$lastPushUserId = $this->pushUserId;
        self::$lastPushMessages = $this->messages;

        // 成功するまで繰り返す
        $retryCount = 0;
        while (1) {
            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

            // 成功
            if ($statusCode >= 200 && $statusCode < 300) break;
            // Conflict:すでにリクエストは受理済み
            if ($statusCode === 409) break;

            // 400番台のエラーは再試行しても変わらないのでthrow
            if (++$retryCount >= 4 || ($statusCode >= 400 && $statusCode < 500))
                // \RuntimeExceptionなのは後でMessageAppendingされる前提だから
                throw new \RuntimeException(curl_error($ch) . "\nRetry Count:{$retryCount}\n{$response}");

            sleep(2 ** $retryCount);
        }

        curl_close($ch);
    }

    private function confirmMessages(): bool
    {
        // 質問をつなげる
        array_push($this->messages, ...$this->questions);

        $lastIndex = count($this->messages) - 1;
        if ($lastIndex === -1) return false;

        // クイックリプライの追加
        if (isset($this->quickReply))
            $this->messages[$lastIndex]['quickReply'] = $this->quickReply;

        // バージョン部分の書き換え
        if (isset($this->messages[$lastIndex]['text']))
            $this->messages[$lastIndex]['text'] = preg_replace('/VERSION\n$/', '(現在のバージョン:' . self::VERSION . ')', $this->messages[$lastIndex]['text']);

        // 質問があり、deleteStorage()されていなければ保存
        if ($this->questions !== [] && $this->storage !== []) {
            $this->storage['lastQuestions'] = $this->questions;
            $this->storage['lastQuickReply'] = $this->quickReply ?? null;
        }

        return true;
    }

    public function downloadContent(array $message): string
    {
        $filename = generateUUID() . '.jpg';

        // 受信
        $ch = curl_init("https://api-data.line.me/v2/bot/message/{$message['id']}/content");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . CHANNEL_ACCESS_TOKEN
        ));
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        $result = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno !== CURLE_OK) {
            $e = new \RuntimeException($error);
            throw new BottomMessageExceptionWrapper($e, '画像処理に失敗しました。');
        }

        // ファイル書き込み
        file_put_contents(IMAGE_FOLDER_PATH . $filename, $result, LOCK_EX);
        return $filename;
    }

    public function getImageUrl(string $filename): string
    {
        // imageの取得に署名が必要になったらphpで処理する
        return IMAGE_FOLDER_URL . $filename;
    }

    private function fetchPushMessageCount(): int|false
    {
        // 取得
        $ch = curl_init('https://api.line.me/v2/bot/message/quota/consumption');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . CHANNEL_ACCESS_TOKEN
        ));
        $result = curl_exec($ch);
        curl_close($ch);

        return json_decode($result, true)['totalUsage'] ?? false;
    }

    public function fetchProfile(?string $userId = null): array
    {
        if (!isset($userId))
            $userId = $this->userId;

        // profile取得
        $ch = curl_init("https://api.line.me/v2/bot/profile/{$userId}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . CHANNEL_ACCESS_TOKEN
        ));
        $result = curl_exec($ch);
        curl_close($ch);

        $profile = json_decode($result, true);
        $displayName = $profile['displayName'] ?? '';
        if ($displayName && $userId === $this->userId)
            $this->displayName = $this->storage['displayName'] = $displayName;

        return $profile;
    }

    private function doesThisExist(): bool
    {
        $profile = $this->fetchProfile();

        if (isset($profile["message"]) && $profile["message"] === 'Not found')
            return false;

        return true;
    }

    private function restoreStorage(): void
    {
        $storageKey = $this->getStorageKey();
        $storage = $this->database->restore($storageKey) ?? [];
        $this->storage = [
            'displayName' => $storage['displayName'] ?? '', // なおこれはログ用であり、プログラム中で使うことはない
            'lastQuestions' => $storage['lastQuestions'] ?? [],
            'formType' => $storage['formType'] ?? '',
            'unsavedAnswers' => $storage['unsavedAnswers'] ?? [],
            'lastQuickReply' => $storage['lastQuickReply'] ?? null,
            'phases' => $storage['phases'] ?? [],
            'cache' => $storage['cache'] ?? [],
            'userName' => $storage['userName'] ?? '',
            'previousAnswers' => $storage['previousAnswers'] ?? [],
            'lastVersion' => $storage['lastVersion'] ?? ''
        ];

        // 管理者なら
        if ($this->isThisAdmin()) {
            $this->storage['adminPhase'] = $storage['adminPhase'] ?? [];
            if (!is_array($this->storage['adminPhase'])) $this->storage['adminPhase'] = []; // 初期化されることが少ないので一応
        }
    }

    private function storeStorage(): void
    {
        // deleteStorage()されていなければ保存
        if ($this->storage !== []) {
            $this->storage['lastVersion'] = self::VERSION;
            $storageKey = $this->getStorageKey();
            $this->database->store($storageKey, $this->storage);
        }
    }

    private function resetStorage(): void
    {
        // 一時ファイルの削除
        if (isset($this->storage['cache']['一時ファイル'])) {
            foreach ($this->storage['cache']['一時ファイル'] as $filename) {
                unlink(IMAGE_FOLDER_PATH . $filename);
            }
        }

        $this->storage['formType'] = '';
        $this->storage['unsavedAnswers'] = [];
        $this->storage['phases'] = [];
        $this->storage['cache'] = [];
    }

    private function deleteStorage(): void
    {
        // 一時ファイル削除
        $this->resetStorage();

        $storageKey = $this->getStorageKey();
        $this->database->delete($storageKey);
        $this->storage = [];
    }

    private function getLastStorageUpdatedTime(): int
    {
        $storageKey = $this->getStorageKey();
        return $this->database->getUpdatedTime($storageKey);
    }

    public function getStorageKey(): string
    {
        return 'storage' . $this->userId;
    }

    public function restoreConfig(): void
    {
        $config = $this->database->restore('config');
        if (isset($config)) {
            $this->config = $config;
            return;
        }

        $this->config = DEFAULT_CONFIG;
        $this->config['adminId'] = $this->userId;

        $this->storeConfig();
    }

    public function storeConfig(): void
    {
        $this->database->store('config', $this->config);
    }

    public function getEventInfo(): string
    {
        // 今日初めてメッセージした場合はdisplayNameを更新する
        $today = getDateAt0AM();
        if ($this->lastStorageUpdatedTime < $today) {
            $this->restoreStorage();
            // ここでdisplayNameが更新される
            $profile = $this->fetchProfile();
            // (unfollowの場合は取得できずにstoreStorage()されない)
            if (isset($profile['displayName']))
                $this->storeStorage();
        }

        if ($this->lastEvent['type'] === 'follow') {
            $whatUserDid = 'Followed me';
        } else if ($this->lastEvent['type'] === 'message') {
            $message = $this->lastEvent['message'];
            switch ($message['type']) {
                case 'text';
                    $whatUserDid = "Messaged '{$message['text']}'";
                    break;
                case 'location':
                    $whatUserDid = "Sent a {$message['type']}(Title:'{$message['title']}', Addr.:'{$message['address']}', Lat.:{$message['latitude']}, Lng.:{$message['longitude']})";
                    break;
                case 'sticker':
                    $whatUserDid = "Sent a {$message['type']}(Pack. ID:{$message['packageId']}, Sti. ID:{$message['stickerId']})";
                    break;
                case 'image':
                case 'audio':
                    $whatUserDid = "Sent an {$message['type']}(Msg. ID:{$message['id']})";
                    break;
                default:
                    $whatUserDid = "Sent a {$message['type']}(Msg. ID:{$message['id']})";
                    break;
            }
        } else if ($this->lastEvent['type'] === 'unfollow') {
            $whatUserDid = 'Unfollowed me';
        } else {
            $whatUserDid = "{$this->lastEvent['type']}";
        }

        $whatIDid = [];
        foreach (['push', 'reply'] as $replyType) {
            if ($replyType === 'push') {
                if (!isset(self::$lastPushUserId)) continue;
                $messages = self::$lastPushMessages;
            } else {
                $messages = $this->messages;
            }

            $replies = 'nothing';
            foreach ($messages as $i => $message) {
                if ($i === 0) {
                    $replies = '';
                } else if ($i < count($messages) - 1) {
                    $replies .= ', ';
                } else {
                    $replies .= ' and ';
                }

                if ($message['type'] === 'text') {
                    $replies .= "'{$message['text']}'";
                } else if ($message['type'] === 'image') {
                    $replies .= "a photo({$message['originalContentUrl']})";
                }

                if (isset($message['sender'])) {
                    $sender = $message['sender'];
                    $replies .= " as '{$sender['name']}'({$sender['iconUrl']})";
                }

                if (isset($message['quickReply'])) {
                    $quickReply = [];
                    foreach ($message['quickReply']['items'] ?? [] as $item) {
                        $label = "'{$item['action']['label']}'";
                        if (isset($item['action']['text'])) {
                            $action = "'{$item['action']['text']}'";
                        } else {
                            $action = $item['action']['type'];
                            if ($action === 'uri') $action .= "({$item['action']['uri']})";
                        }
                        if ($label === $action) {
                            $quickReply[] = $label;
                        } else {
                            $quickReply[] = "{$label}({$action})";
                        }
                    }
                    $replies .= '(QuickReply:' . implode(', ', $quickReply) . ')';
                }
            }

            if ($replyType === 'push') {
                $pushUserId = self::$lastPushUserId;
                $pushUserDisplayName = $this->fetchProfile($pushUserId)['displayName'] ?? '';
                $whatIDid[] = "Pushed(tried to Push) to `{$pushUserDisplayName}`({$pushUserId}) {$replies}";
            } else {
                $whatIDid[] .= "Replied {$replies}";
            }
        }

        return "`{$this->displayName}`(User ID:{$this->userId}) {$whatUserDid} and I " . implode(' and ', $whatIDid) . '.';
    }
}
