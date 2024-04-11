<?php

namespace KishukushaReportSupporter;

use KishukushaReportSupporter\Forms;

class KishukushaReportSupporter
{
    public const VERSION = '1.1.9';

    /* 届出を追加する際はここの編集とsrc/Formsフォルダへのファイルの追加が必要 */
    public const FORMS = [
        '外部来訪者届' => Forms\Gaiburaihousha::class,
        '長期外泊届' => Forms\Chokigaihaku::class,
        '舎生大会・諸行事届' => Forms\Shogyoji::class,
        '踊り場私物配備届' => Forms\Odoriba::class,
        '309私物配備届' => Forms\Haibi309::class,
        '自転車・バイク配備届' => Forms\Bikes::class,
        '滞納届' => Forms\Tainou::class,
        '入力履歴を削除する' => Forms\Nyuryokurireki::class,
        '自分の名前を変更する' => Forms\AskName::class,
        'マニュアルを見る' => Forms\UserManual::class
    ];

    public const MAX_PREVIOUS_ANSWERS = 7;

    public string $userId;
    public JsonDatabase $database;
    public array $config;
    public array $storage;

    private string $replyToken;
    private string $pushUserId;

    private array $messages;
    private array $questions;
    private ?array $quickReply;
    private array $imagesToOpenAccess;
    private array $uniqueTextOptions;

    private array $lastQuestions;
    private ?array $lastQuickReply;
    private array $lastImagesToOpenAccess;

    private static \Google\Client $googleClient;

    // getEventInfo()用
    private array $lastEvent;
    private int $lastStorageUpdatedTime;
    private string $displayName;
    private static string $lastPushUserId;
    private static array $lastPushMessages;

    public function __construct(string $userId, JsonDatabase $database, ?array $config = null)
    {
        $this->userId = $userId;
        $this->database = $database;
        $this->restoreConfig($config);
        $this->restoreStorage();

        // storageの方が書き換わっても、setLastQuestions()したときは必ず前回の質問になる
        $this->lastQuestions = $this->storage['lastQuestions'];
        $this->lastQuickReply = $this->storage['lastQuickReply'];
        $this->lastImagesToOpenAccess = $this->storage['lastImagesToOpenAccess'];

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
            if (DEBUGGING) {
                $errorMsg = "{$e}";
            } else {
                if ($e instanceof ExceptionWrapper) {
                    $msg = $e->exception->getMessage();
                    $name = $e->exception::class;
                    $errorMsg = "{$name}: {$msg}\n{$e->additionalMsg}";
                } else {
                    $msg = $e->getMessage();
                    $name = $e::class;
                    $errorMsg = "{$name}: {$msg}";
                }
            }
            $this->pushText("【エラーが発生しました】
{$errorMsg}

エラーが発生しました。
もう一度試してください。");
            $this->setLastQuestions();
            $this->confirmReply();
            // storageは保存しない

            throw $e;
        } finally {
            $this->lastEvent = $event;
        }
    }

    private function _handleEvent(array $event): void
    {
        if ($event['type'] === 'follow') {
            Forms\AskName::form($this, []);
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
            if ($text === 前の項目を修正する) {
                array_pop($this->storage['phases']); // 今聞いている質問をもう一度聞くフェーズへ
                array_pop($this->storage['phases']); // その前の質問をもう一度聞くフェーズへ
            } else if ($text === キャンセル) {
                if ($this->storage['userName'] !== '') {
                    if ($this->storage['formType'] !== '')
                        $this->pushText('キャンセルしました。');
                    $this->resetForm();
                    return;
                } else {
                    // 名前がまだ確定していないとき
                    $this->storage['phases'] = [];
                }
            } else if ($text === OK) {
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
                $this->pushOptions([キャンセル]);
                $this->resetStorage();
                break;
            case '管理者設定':
                if ($this->isThisAdmin()) {
                    Forms\AdminSettings::form($this, $message);
                    break;
                }
            default:
                $formClass = self::FORMS[$this->storage['formType']] ?? '';
                if ($formClass !== '') {
                    $formClass::form($this, $message);
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
        $this->pushText('新しくフォームに入力を始める場合は「回答を始める」と入力してください。

※クイックリプライはスマホと一部のパソコンでのみ利用できます。
※利用規約: https://github.com/philip82148/kishukusha-report-supporter/blob/main/terms-of-use.md

(現在のバージョン:' . self::VERSION . ')', true);
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
            case 承認する:
                try {
                    $spreadsheetService = new \Google\Service\Sheets(self::getGoogleClient());

                    // 書き込み
                    $spreadsheetService->spreadsheets_values->update(
                        $this->config['outputSheetId'],
                        $lastPhase['checkboxRange'],
                        new \Google\Service\Sheets\ValueRange([
                            'values' => [['TRUE']]
                        ]),
                        ['valueInputOption' => 'USER_ENTERED']
                    );
                } catch (\Throwable $e) {
                    throw new ExceptionWrapper($e, "スプレッドシートへの書き込み中にエラーが発生しました。\nGoogleのサービスが一時的に利用できなかったか、シートが削除された、またはボットに編集権限がない可能性があります。");
                }

                try {
                    // 申請した本人への通知
                    $this->initPush($lastPhase['userId']);
                    $adminProfile = $this->fetchProfile();
                    $this->pushText("{$lastPhase['formType']}が承認されました。\n(届出番号:{$lastPhase['receiptNo']})", false, ['name' => $adminProfile['displayName'], 'iconUrl' => $adminProfile['pictureUrl'] ?? 'https://dummy.com/']);
                    $this->pushOptions([OK]);
                    $this->confirmPush(true);
                } catch (\Throwable $e) {
                    $this->initReply();
                    throw new ExceptionWrapper($e, "スプレッドシートへの書き込みは成功しましたが、本人への通知中にエラーが発生しました。\nもう一度「承認する」を押すと本人への通知のみを再試行します。");
                }

                $this->restoreStorage();
                if (count($this->storage['adminPhase']) !== $unapprovedFormCount) {
                    $e = new \RuntimeException('New form submitted during approval');
                    throw new ExceptionWrapper($e, "スプレッドシートへの書きこみ及び本人への通知に成功しましたが、その最中に新たな申請がありました。\nデータの衝突を避けるために今回の承認操作は記録されません。\n届出番号{$lastPhase['receiptNo']}の{$lastPhase['userName']}の{$lastPhase['formType']}は後でもう一度承認してください(再度書きこみと通知が行われます)。");
                }

                // 管理者への通知
                $this->initReply();
                $this->pushText("{$lastPhase['userName']}の{$lastPhase['formType']}を承認しました。\nスプレッドシートへのチェックと、本人への通知を行いました。\n(届出番号:{$lastPhase['receiptNo']})");
                break;
            case 直接伝えた:
                try {
                    // 申請した本人への通知
                    $this->initPush($lastPhase['userId']);
                    $adminProfile = $this->fetchProfile();
                    $this->pushText("届出番号{$lastPhase['receiptNo']}の{$lastPhase['formType']}を{$lastPhase['adminType']}は確認しましたが、ボットを使用した承認は行われませんでした。

これについて{$lastPhase['adminType']}から直接連絡がなかった場合は手動でスプレッドシートにチェックを入れた可能性があります。

まず、スプレッドシートにチェックが入っているかを確認し、入っていない場合は{$lastPhase['adminType']}に直接問い合わせてください。", false, ['name' => $adminProfile['displayName'], 'iconUrl' => $adminProfile['pictureUrl'] ?? 'https://dummy.com/']);
                    $this->pushOptions([OK]);
                    $this->confirmPush(true);
                } catch (\Throwable $e) {
                    $this->initReply();
                    $this->pushText("{$e}\n届出番号{$lastPhase['receiptNo']}の{$lastPhase['userName']}の{$lastPhase['formType']}について、ボットを使用した承認が行われなかった旨の本人への通知中にエラーが発生しました。\n必要ならば手動で本人に通知してください。");
                    break;
                }

                $this->restoreStorage();
                if (count($this->storage['adminPhase']) !== $unapprovedFormCount) {
                    $e = new \RuntimeException('New form submitted during approval');
                    throw new ExceptionWrapper($e, "本人への通知に成功しましたが、その最中に新たな申請がありました。\nデータの衝突を避けるために今回の操作は記録されません。\n届出番号{$lastPhase['receiptNo']}の{$lastPhase['userName']}の{$lastPhase['formType']}は後でもう一度承認/非承認を行ってください(再度通知が行われます)。");
                }

                // 管理者への通知
                $this->initReply();
                $this->pushText("{$lastPhase['userName']}の{$lastPhase['formType']}について、ボットを使用した承認が行われなかった旨を本人へ通知しました。\n(届出番号:{$lastPhase['receiptNo']})");
                break;
            case 一番最後に見る:
                if (count($this->storage['adminPhase']) === 1) {
                    // 次の質問は承認するかどうかの質問ではない
                    // もう一度同じ質問を聞く
                    $this->pushText('他に承認が必要な届出はありません。');
                    $this->setLastQuestions();
                    return true;
                }

                // 次に最後の質問を聞く
                $this->setLastQuestions($lastPhase['lastQuestions'], $lastPhase['lastQuickReply'], $lastPhase['lastImagesToOpenAccess']);
                array_pop($this->storage['adminPhase']);

                // adminPhaseの一番最後の質問の保持
                $lastLastQuestions = $this->storage['adminPhase'][0]['lastQuestions'];
                $lastLastQuickReply = $this->storage['adminPhase'][0]['lastQuickReply'];
                $lastLastImagesToOpenAccess = $this->storage['adminPhase'][0]['lastImagesToOpenAccess'];

                // この前に聞いた質問を一番最初へ
                $this->storage['adminPhase'][0]['lastQuestions'] = $this->storage['lastQuestions'];
                $this->storage['adminPhase'][0]['lastQuickReply'] = $this->storage['lastQuickReply'];
                $this->storage['adminPhase'][0]['lastImagesToOpenAccess'] = $this->storage['lastImagesToOpenAccess'];

                // この前に聞いた質問の答えをそれよりも前へ
                array_unshift($this->storage['adminPhase'], $lastPhase);

                // adminPhaseの一番最後の質問を戻す
                $this->storage['adminPhase'][0]['lastQuestions'] = $lastLastQuestions;
                $this->storage['adminPhase'][0]['lastQuickReply'] = $lastLastQuickReply;
                $this->storage['adminPhase'][0]['lastImagesToOpenAccess'] = $lastLastImagesToOpenAccess;

                return true;
            default:
                $this->askAgainBecauseWrongReply();
                return true;
        }
        // 管理者への通知(続き)
        $this->setLastQuestions($lastPhase['lastQuestions'], $lastPhase['lastQuickReply'], $lastPhase['lastImagesToOpenAccess']);
        array_pop($this->storage['adminPhase']);

        return true;
    }

    private function changeAdminIfPasswordSet(array $message): bool
    {
        // パスワードが登録されている
        if (!isset($this->config['password']) && !isset($this->config['zaimuPassword'])) return false;

        // テキストタイプのみ
        if ($message['type'] !== 'text') return false;
        $message = $message['text'];

        // パスワードと一致した
        if ($message === $this->config['password']) {
            $admin = $this->createAdmin();
            $adminType = '管理者';
        } else if ($message === $this->config['zaimuPassword']) {
            $admin = $this->createZaimu();
            $adminType = '財務';
        } else {
            return false;
        }

        // ここまでで他に発生したトランザクションについて更新(adminPhase等)
        $admin->restoreStorage(); // 他に発生したトランザクションについて更新

        // configの変更
        if ($adminType === '管理者') {
            $this->config['adminId'] = $this->userId;
            unset($this->config['password']);
        } else {
            $this->config['zaimuId'] = $this->userId;
            unset($this->config['zaimuPassword']);
        }
        $this->storeConfig();

        // なおこれ以降restoreStorageで元管理者のadminPhaseが消え、
        // 新管理者にadminPhaseが現れるようになる

        // パスワードを打ったのは元管理者(自分自身)でない
        if ($this->userId !== $admin->userId) {
            // 元管理者への通知
            try {
                $admin->initPush();
                $newAdminProfile = $this->fetchProfile();
                $admin->pushText("{$adminType}が変更されました。", false, ['name' => $newAdminProfile['displayName'], 'iconUrl' => $newAdminProfile['pictureUrl'] ?? 'https://dummy.com/']);
                $admin->pushOptions([OK]);
                $admin->confirmPush(true);
            } catch (\Throwable) {
            }
        }

        // 財務で、元管理者が風紀でない場合追加で風紀にも通知する
        if ($adminType === '財務' && !$admin->isThisAdmin() && !$this->isThisAdmin()) {
            // 風紀への通知
            try {
                $fuki = $this->createOrTransferAdmin();
                $fuki->initPush();
                $newFukiProfile = $this->fetchProfile();
                $fuki->pushText("{$adminType}が変更されました。", false, ['name' => $newFukiProfile['displayName'], 'iconUrl' => $newFukiProfile['pictureUrl'] ?? 'https://dummy.com/']);
                $fuki->pushOptions([OK]);
                $fuki->confirmPush(true);
            } catch (\Throwable) {
            }
        }

        // 新管理者への通知とマニュアルの表示
        $this->pushText("{$adminType}が変更されました。");
        if ($adminType === '管理者') {
            $this->pushText(ADMIN_MANUAL);
            $this->pushText(SERVER_MANUAL);
            $this->pushText('これらのマニュアルは「管理者設定」>「管理者用マニュアル表示」からいつでも確認できます。');
        }
        $this->pushOptions([OK]);

        // adminPhaseの移動(なおここで$adminは元管理者)
        $adminPhase = $admin->storage['adminPhase'];
        unset($admin->storage['adminPhase']);
        if (!empty($adminPhase)) {
            // adminの前回の質問を引き継ぐ(なおここでOKの選択肢は削除される)
            $this->setLastQuestions($admin->storage['lastQuestions'], $admin->storage['lastQuickReply'], $admin->storage['lastImagesToOpenAccess']);

            // adminPhaseの一番最後の質問を元adminに返す
            $admin->storage['lastQuestions'] = $adminPhase[0]['lastQuestions'];
            $admin->storage['lastQuickReply'] = $adminPhase[0]['lastQuickReply'];
            $admin->storage['lastImagesToOpenAccess'] = $adminPhase[0]['lastImagesToOpenAccess'];

            // 元管理者が存在しなければ保存はしない
            if ($admin->doesThisExist())
                $admin->storeStorage();

            $this->restoreStorage(); // 他に発生したトランザクションについて更新

            // 新adminの前回の質問を最も最後のlastQuestionsへ
            // 新adminのlastQuestionsは上のsetLastQuestionsでこの後書き換えられる
            $adminPhase[0]['lastQuestions'] = $this->storage['lastQuestions'];
            $adminPhase[0]['lastQuickReply'] = $this->storage['lastQuickReply'];
            $adminPhase[0]['lastImagesToOpenAccess'] = $this->storage['lastImagesToOpenAccess'];

            $this->storage['adminPhase'] = array_merge($this->storage['adminPhase'], $adminPhase);
        } else {
            // 元管理者が存在しなければ保存はしない
            if ($admin->doesThisExist())
                $admin->storeStorage();
            // このあとstoreすることになるので、ともかくrestoreする
            $this->restoreStorage(); // 他に発生したトランザクションについて更新
        }

        return true;
    }

    public function submitForm(array $answers, array $answersForSheets, bool $needCheckbox = false, string $message = '', ?self $admin = null, string $adminType = '風紀'): void
    {
        try {
            $timeStamp = date('Y/m/d H:i:s');
            $appendRow = array_merge([$timeStamp], $answersForSheets);
            if ($needCheckbox) {
                // 承認のチェック
                if ($this->isThisAdmin($admin)) {
                    $appendRow[] = 'TRUE';
                } else {
                    $appendRow[] = 'FALSE';
                }
            }

            // スプレッドシートに書き込み
            $spreadsheetService = new \Google\Service\Sheets(self::getGoogleClient());

            // 結果を追加
            $response = $this->appendToResultSheets($this->storage['formType'], $appendRow, $spreadsheetService);

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
                $spreadsheet = $spreadsheetService->spreadsheets->get($this->config['outputSheetId']);
                $sheetId = $this->getSheetId($spreadsheet, $this->storage['formType']);

                // チェックボックス追加
                $spreadsheetService->spreadsheets->batchUpdate(
                    $this->config['outputSheetId'],
                    new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
                        'requests' => [
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
                        ]
                    ])
                );
            }
        } catch (\Throwable $e) {
            throw new ExceptionWrapper($e, "スプレッドシートへの書き込み中にエラーが発生しました。\nGoogleのサービスが一時的に利用できなかったか、ボットに指定のシートの編集権限がない可能性があります。");
        }

        // 自分が管理者でない、かつ、承認が必要なら、管理者に通知
        if (!$this->isThisAdmin($admin) && $needCheckbox) {
            if (!isset($admin)) $admin = $this->createOrTransferAdmin();
            try {
                $receiptNo = $admin->notifySubmittedForm($this, $answers, $timeStamp, $checkboxRange ?? '', $adminType);
            } catch (\Throwable $e) {
                throw new ExceptionWrapper($e, "スプレッドシートへの書き込みは成功しましたが、{$adminType}への通知中にエラーが発生しました。");
            }
        }

        // ユーザーへの通知
        if ($this->isThisAdmin($admin)) {
            if ($needCheckbox) {
                $this->pushText("{$this->storage['formType']}を提出しました。\n(※承認済み)");
            } else {
                if ($message !== '') $message = "\n{$message}";
                $this->pushText("{$this->storage['formType']}を提出しました。{$message}");
            }
        } else {
            if ($needCheckbox) {
                $this->pushText("{$this->storage['formType']}を申請しました。\n{$adminType}の承認をお待ちください。\n(届出番号:{$receiptNo})");
            } else {
                if ($message !== '') $message = "\n{$message}";
                $this->pushText("{$this->storage['formType']}を提出しました。{$message}\n※この届出に{$adminType}の承認はありません。");
            }
        }
    }

    private function appendToResultSheets(string $formType, array $row, \Google\Service\Sheets $spreadsheetService, string $resultSheetId = null): \Google\Service\Sheets\AppendValuesResponse
    {
        if (!isset($resultSheetId))
            $resultSheetId = $this->config['outputSheetId'];

        // $formTypeのシートがあるか確認
        $spreadsheet = $spreadsheetService->spreadsheets->get($resultSheetId);
        $sheetId = $this->getSheetId($spreadsheet, $formType);

        // 存在する->書き込み
        if (isset($sheetId)) {
            $response = $spreadsheetService->spreadsheets_values->append(
                $resultSheetId,
                "'{$formType}'!A1",
                new \Google\Service\Sheets\ValueRange([
                    'values' => [$row]
                ]),
                ['valueInputOption' => 'USER_ENTERED']
            );

            return $response;
        }

        // シートが存在しない->作成
        $response = $spreadsheetService->spreadsheets->batchUpdate(
            $resultSheetId,
            new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
                'requests' => [
                    'addSheet' => [
                        'properties' => [
                            'title' => $formType
                        ]
                    ]
                ]
            ])
        );
        $sheetId = $response->getReplies()[0]->getAddSheet()->getProperties()->sheetId;

        // 一行目固定、セル幅指定
        $header = array_merge(['タイムスタンプ'], self::FORMS[$formType]::HEADER);
        $spreadsheetService->spreadsheets->batchUpdate(
            $this->config['outputSheetId'],
            new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
                'requests' => [
                    new \Google\Service\Sheets\Request([
                        'update_sheet_properties' => [
                            'properties' => [
                                'sheet_id' => $sheetId,
                                'grid_properties' => ['frozen_row_count' => 1]
                            ],
                            'fields' => 'gridProperties.frozenRowCount'
                        ],
                    ]),
                    new \Google\Service\Sheets\Request([
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
            ])
        );

        // 見出しと共に書き込み
        $response = $spreadsheetService->spreadsheets_values->append(
            $resultSheetId,
            "'{$formType}'!A1",
            new \Google\Service\Sheets\ValueRange([
                'values' => [$header, $row]
            ]),
            ['valueInputOption' => 'USER_ENTERED']
        );

        return $response;
    }

    private function getSheetId(\Google\Service\Sheets\Spreadsheet $spreadsheet, string $sheetName): ?int
    {
        foreach ($spreadsheet->getSheets() as $sheet) {
            if ($sheet['properties']['title'] !== $sheetName) continue;
            return $sheet['properties']['sheetId'];
        }
        return null;
    }

    private function notifySubmittedForm(self $supporter, array $answers, string $timeStamp, string $checkboxRange, string $adminType): string
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
        $needApproval = $formClass::pushAdminMessages($this, $profile, $answers, $timeStamp, $receiptNo);

        // 通知
        if ($needApproval) {
            $this->storage['adminPhase'][] = [
                'userId' => $supporter->userId,
                'userName' => $supporter->storage['userName'],
                'formType' => $supporter->storage['formType'],
                'receiptNo' => $receiptNo,
                'checkboxRange' => $checkboxRange,
                'adminType' => $adminType,
                'lastQuickReply' => $this->storage['lastQuickReply'],
                'lastQuestions' => $this->storage['lastQuestions'],
                'lastImagesToOpenAccess' => $this->storage['lastImagesToOpenAccess']
            ];
            $this->confirmPush(true);
            $this->storeStorage();
        } else {
            $this->confirmPush(false);
        }

        return $receiptNo;
    }

    public function saveToDrive(string $imageFilename, string $driveFilename, string $folderId, ?string $folderName = null, bool $returnId = false): string
    {
        try {
            // ドライブに保存
            $driveService = new \Google\Service\Drive(self::getGoogleClient());

            // フォルダー名がある場合は検索、無い場合は作成する
            if (isset($folderName)) {
                // 検索
                $escapedFolderName = str_replace("'", "\\'", $folderName);
                $driveFiles = $driveService->files->listFiles([
                    'q' => "'{$folderId}' in parents and name='{$escapedFolderName}' and mimeType='application/vnd.google-apps.folder'",
                    'fields' => 'files(id)',
                    'corpora' => 'allDrives',
                    'includeItemsFromAllDrives' => true,
                    'supportsAllDrives' => true,
                ])->getFiles();

                // あった
                if (count($driveFiles)) {
                    $parentFolderId = $driveFiles[0]->getId();
                } else {
                    // ない->作成
                    $file = $driveService->files->create(new \Google\Service\Drive\DriveFile([
                        'name' => $folderName, // なんかバリデーションは要らないらしい
                        'mimeType' => 'application/vnd.google-apps.folder',
                        'parents' => [$folderId],
                    ]), [
                        'fields' => 'id',
                        'supportsAllDrives' => true,
                    ]);
                    $parentFolderId = $file->getId();
                }
            } else {
                $parentFolderId = $folderId;
            }

            $file = $driveService->files->create(new \Google\Service\Drive\DriveFile([
                'name' => $driveFilename, // なんかバリデーションは要らないらしい
                'parents' => [$parentFolderId],
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
            throw new ExceptionWrapper($e, "画像のGoogleドライブへの保存に失敗しました。\nGoogleのサービスが一時的に利用できなかったか、ボットに指定のフォルダへのファイル追加権限がない可能性があります。");
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
            $spreadsheetService = new \Google\Service\Sheets(self::getGoogleClient());

            // 読み取り
            $valueRange = $spreadsheetService->spreadsheets_values->get($this->config['eventSheetId'], "'行事'!A1:C", [
                'valueRenderOption' => 'UNFORMATTED_VALUE',
                'dateTimeRenderOption' => 'SERIAL_NUMBER'
            ]);
            $rows = $valueRange->getValues();

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
            throw new ExceptionWrapper($e, '行事データの読み込みに失敗しました。');
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
                case 'eventSheetId':
                case 'outputSheetId':
                    $spreadsheetService = new \Google\Service\Sheets(self::getGoogleClient());
                    if ($type === 'eventSheetId') {
                        // 読み取り
                        $valueRange = $spreadsheetService->spreadsheets_values->get($id, "'行事'!A1:C");
                    } else {
                        // 何か一つ届出のシートを書き込む
                        foreach (self::FORMS as $formType => $formClass) {
                            if (is_subclass_of($formClass, SubmittableForm::class)) break;
                        }
                        $this->appendToResultSheets($formType, [], $spreadsheetService, $id);
                    }
                    break;
                case 'shogyojiImageFolderId':
                    $fileId = $this->saveToDrive(TEST_IMAGE_FILENAME, 'テスト', $id, null, true);
                    $driveService = new \Google\Service\Drive(self::getGoogleClient());
                    $driveService->files->delete($fileId, ['supportsAllDrives' => true]);
                    break;
                case 'generalImageFolderId':
                    $fileId = $this->saveToDrive(TEST_IMAGE_FILENAME, 'テスト', $id, 'テスト', true);
                    break;
            }
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function getGoogleClient(): \Google\Client
    {
        if (!isset(self::$googleClient)) {
            self::$googleClient = new \Google\Client();
            self::$googleClient->setScopes([
                \Google\Service\Sheets::SPREADSHEETS, // スプレッドシート
                \Google\Service\Sheets::DRIVE, // ドライブ
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

    public function createOrTransferAdmin(): self
    {
        $admin = $this->createAdmin();
        if ($admin->doesThisExist()) return $admin;

        // 管理者の存在が確認できない場合
        // このユーザーを管理者にする
        // configの書き換え
        $this->config['adminId'] = $this->userId;
        $this->storeConfig();

        // 通知
        $this->pushText("ボットに登録された管理者のアカウントの存在が確認できませんでした。
管理者がボットのブロックやLINEアカウントの削除等をした可能性があります。
管理者のアカウントの存在確認が出来なくなってから初めて承認が必要な届出を行ったあなたのアカウントに管理者権限を移行しました。
あなたが風紀でない場合は風紀に連絡、あなたが現役舎生でない場合はボットをブロックしてください。");

        return $this;
    }

    public function createAdmin(): self
    {
        if ($this->isThisAdmin()) return $this;

        return new self($this->config['adminId'], $this->database, $this->config);
    }

    public function isThisAdmin(?self $admin = null): bool
    {
        if (isset($admin)) return $this->userId === $admin->userId;

        // configを書き換えるとisThisAdminが効かなくなる
        return $this->userId === $this->config['adminId'];
    }

    public function createOrTransferZaimu(): self
    {
        $zaimu = $this->createZaimu();
        if ($zaimu->doesThisExist()) return $zaimu;

        // 財務の存在が確認できない場合、風紀を財務にする
        $admin = $this->createOrTransferAdmin();
        $this->config['zaimuId'] = $this->config['adminId'];
        $this->storeConfig();

        return $admin;
    }

    public function createZaimu(): self
    {
        if ($this->isThisZaimu()) return $this;

        return new self($this->config['zaimuId'], $this->database, $this->config);
    }

    public function isThisZaimu(): bool
    {
        // configを書き換えるとisThisZaimuが効かなくなる
        return $this->userId === $this->config['zaimuId'];
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
        $this->imagesToOpenAccess = [];
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

    public function pushOptions(array $options, bool $displayInMessage = false, bool $isInputHistory = false): void
    {
        if (!isset($this->quickReply))
            $this->quickReply = ['items' =>  []];

        foreach ($options as $labelSuffix => $option) {
            if (!is_string($labelSuffix)) $labelSuffix = $isInputHistory ? '(履歴)' : '';

            // ラベル作成
            $suffixLength = mb_strlen($labelSuffix);
            if (mb_strlen($option) + $suffixLength <= 20) {
                $label = $option . $labelSuffix;
            } else {
                $label = mb_substr($option, 0, 19 - $suffixLength) . '…' . $labelSuffix;
            }

            // $optionを290文字以下にカット
            // (サロゲートペアは2文字以上とカウントされるので、本来300文字まで許容できるが余裕をもって290文字とする)
            if (mb_strlen($option) > 290) $option = mb_substr($option, 0, 270) . "…\n\nクイックリプライの文字数が多すぎます。";

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
                if ($displayInMessage && count($this->questions))
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

        $this->pushOptions($this->storage['previousAnswers'][$type], false, true);
    }

    public function pushPreviousAnswer(string $type, string $previousAnswer): void
    {
        // 回答が290文字以下の場合のみpush
        // (サロゲートペアは2文字以上とカウントされるので、本来300文字まで許容できるが余裕をもって290文字とする)
        if (mb_strlen($previousAnswer) > 290) return;

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
                    case はい:
                        $undisplayedOptions[] = '良い場合は「はい」';
                        break;
                    case 前の項目を修正する:
                        $undisplayedOptions[] = '前の項目を修正する場合は「前の項目を修正する」';
                        break;
                    case キャンセル:
                        $undisplayedOptions[] = '最初からやり直す場合は「キャンセル」';
                        break;
                    case 承認する:
                        $undisplayedOptions[] = '承認する場合は「承認する」';
                        break;
                    case 直接伝えた:
                        $undisplayedOptions[] = 'スプレッドシートへのチェックを行わない場合は「直接伝えた」';
                        break;
                    case 一番最後に見る:
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

    public function setLastQuestions(?array $questions = null, ?array $quickReply = null, ?array $imagesToOpenAccess = null): void
    {
        // confirmReply/Push後に呼び出されるとstorageの方は書き変わっている可能性があるので、
        // プロパティを使う。これで確実に前回の質問
        if (!isset($questions))
            $questions = $this->lastQuestions;
        if (!isset($quickReply))
            $quickReply = $this->lastQuickReply;
        if (!isset($imagesToOpenAccess))
            $imagesToOpenAccess = $this->lastImagesToOpenAccess;

        $this->questions = $questions;
        $this->quickReply = $quickReply;

        $this->imagesToOpenAccess = [];
        array_walk($imagesToOpenAccess, fn ($expirationTime, $filename) => $this->openAccessToImage($filename));
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
                throw new ExceptionWrapper($e, "返信処理に失敗しました。");
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

        // 質問があり、deleteStorage()されていなければ保存
        if ($this->questions !== [] && $this->storage !== []) {
            $this->storage['lastQuestions'] = $this->questions;
            $this->storage['lastQuickReply'] = $this->quickReply;
            $this->storage['lastImagesToOpenAccess'] = $this->imagesToOpenAccess;
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
            throw new ExceptionWrapper($e, '画像処理に失敗しました。');
        }

        // ファイル書き込み
        file_put_contents(IMAGE_FOLDER_PATH . $filename, $result, LOCK_EX);
        return $filename;
    }

    public function openAccessToImage($filename): string
    {
        $this->imagesToOpenAccess[$filename] = date("Y/m/d H:i:s", strtotime("+1 minute"));

        return WEBHOOK_PARENT_URL . "/image.php?userId={$this->userId}&filename={$filename}";
    }

    public function isAccessibleImage($filename): bool
    {
        if (!isset($this->storage['lastImagesToOpenAccess'][$filename])) return false;

        $now = time();
        $expirationTime = $this->storage['lastImagesToOpenAccess'][$filename];
        if ($now >= $expirationTime) return false;

        return true;
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
            'lastImagesToOpenAccess' => $storage['lastImagesToOpenAccess'] ?? [],
            'phases' => $storage['phases'] ?? [],
            'cache' => $storage['cache'] ?? [],
            'userName' => $storage['userName'] ?? '',
            'previousAnswers' => $storage['previousAnswers'] ?? [],
            'lastVersion' => $storage['lastVersion'] ?? ''
        ];

        // 風紀または財務なら
        if ($this->isThisAdmin() || $this->isThisZaimu()) {
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

    public function restoreConfig(?array $config = null): void
    {
        $config = $config ?? $this->database->restore('config');
        if (isset($config)) {
            $this->config = $config;
            return;
        }

        $this->config = DEFAULT_CONFIG;
        $this->config['adminId'] = $this->userId;
        $this->config['zaimuId'] = $this->userId;

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
            $whatUserDid = 'followed me';
        } else if ($this->lastEvent['type'] === 'message') {
            $message = $this->lastEvent['message'];
            switch ($message['type']) {
                case 'text';
                    $whatUserDid = "messaged '{$message['text']}'";
                    break;
                case 'location':
                    $whatUserDid = "sent a {$message['type']}(Title:'{$message['title']}', Addr.:'{$message['address']}', Lat.:{$message['latitude']}, Lng.:{$message['longitude']})";
                    break;
                case 'sticker':
                    $whatUserDid = "sent a {$message['type']}(Pack. ID:{$message['packageId']}, Sti. ID:{$message['stickerId']})";
                    break;
                case 'image':
                case 'audio':
                    $whatUserDid = "sent an {$message['type']}(Msg. ID:{$message['id']})";
                    break;
                default:
                    $whatUserDid = "sent a {$message['type']}(Msg. ID:{$message['id']})";
                    break;
            }
        } else if ($this->lastEvent['type'] === 'unfollow') {
            $whatUserDid = 'unfollowed me';
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
                    $replies .= " as `{$sender['name']}`({$sender['iconUrl']})";
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
                    $replies .= '(Quick Replies:' . implode(', ', $quickReply) . ')';
                }
            }

            if ($replyType === 'push') {
                $pushUserId = self::$lastPushUserId;
                $pushUserDisplayName = $this->fetchProfile($pushUserId)['displayName'] ?? '';
                $whatIDid[] = "pushed(tried to push) to `{$pushUserDisplayName}`({$pushUserId}) {$replies}";
            } else {
                $whatIDid[] .= "replied {$replies}";
            }
        }

        return "`{$this->displayName}`(User ID:{$this->userId}) {$whatUserDid} and I " . implode(' and ', $whatIDid) . '.';
    }
}
