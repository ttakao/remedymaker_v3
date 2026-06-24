<?php
// src/api.php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

// DBコネクションの確立
$pdo = get_db_connection();

// リクエストパラメータの取得
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// 汎用レスポンス関数
function respond($status, $message, $extra = []) {
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

// 認証チェック関数
function check_auth() {
    if (!isset($_SESSION['user_id'])) {
        respond('error', '認証されていません。再ログインしてください。');
    }
}

// 管理者チェック関数
function check_admin() {
    check_auth();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        respond('error', '管理者権限がありません。');
    }
}

switch ($action) {
    // --------------------------------------------------------
    // A-1. ログインリクエスト
    // --------------------------------------------------------
    case 'login_request':
        $email = trim($data['email'] ?? '');
        if (empty($email)) {
            respond('error', 'メールアドレスを入力してください。');
        }

        $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `email` = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            respond('error', '登録されていないメールアドレスです。');
        }

        // ロック判定
        if ($user['locked_until']) {
            $locked_time = strtotime($user['locked_until']);
            if ($locked_time > time()) {
                $remaining = ceil(($locked_time - time()) / 60);
                respond('error', "アカウントはロックされています。解除まであと約 {$remaining} 分です。");
            } else {
                $stmt = $pdo->prepare("UPDATE `users` SET `login_attempts` = 0, `locked_until` = NULL WHERE `id` = ?");
                $stmt->execute([$user['id']]);
            }
        }

        // 8桁の「数字のみ」のランダムコードを生成
        $code = sprintf("%08d", random_int(0, 99999999));
        $hash = password_hash($code, PASSWORD_DEFAULT);

        // PHP側の日本標準時刻を明示的にバインドして時差問題を防止します
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("UPDATE `users` SET `passkey_hash` = ?, `update_date` = ? WHERE `id` = ?");
        $stmt->execute([$hash, $now, $user['id']]);

        $_SESSION['debug_otp'] = $code; 

        if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
            respond('success', '【デバッグモード】認証コードを発行しました。画面の指示に従いコードを入力してください。', [
                'debug_otp' => $code
            ]);
        }

        // メール送信処理
        mb_language("Japanese");
        mb_internal_encoding("UTF-8");
        $subject = "【レメディシステム】認証コードのお知らせ";
        $message = "認証コード: {$code}\n※有効期限は5分間です。3回間違えると30分間ロックされます。";
        
        // config.phpに定義した定数 MAIL_SENDER を結合してヘッダーを作成します
        $headers = "From: " . MAIL_SENDER . "\r\n";

        if (mb_send_mail($email, $subject, $message, $headers, "-f" . MAIL_SENDER)) {
            respond('success', '認証コードをメールで送信しました。');
        } else {
            respond('success', '認証コードを発行しました（ローカル確認用）。', ['debug_otp' => $code]);
        }
        break;

    // --------------------------------------------------------
    // A-2. 認証コード照合
    // --------------------------------------------------------
    case 'login_verify':
        $email = trim($data['email'] ?? '');
        $code = trim($data['code'] ?? '');

        if (empty($email) || empty($code)) {
            respond('error', 'メールアドレスと認証コードを入力してください。');
        }

        $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `email` = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            respond('error', 'ユーザーが見つかりません。');
        }

        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            respond('error', 'アカウントはロックされています。');
        }

        // データベースから取得した登録時間と、PHPの現在時間を日本時間基準で比較します
        $update_time = strtotime($user['update_date']);
        if ((time() - $update_time) > 300) {
            respond('error', '認証コードの有効期限（5分）が切れています。再度送信してください。');
        }

        if (password_verify($code, $user['passkey_hash'])) {
            $stmt = $pdo->prepare("UPDATE `users` SET `login_attempts` = 0, `locked_until` = NULL, `passkey_hash` = NULL WHERE `id` = ?");
            $stmt->execute([$user['id']]);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            respond('success', 'ログインに成功しました。', [
                'user' => [
                    'name' => $user['name'],
                    'role' => $user['role']
                ]
            ]);
        } else {
            $attempts = $user['login_attempts'] + 1;
            if ($attempts >= 3) {
                $locked_until = date('Y-m-d H:i:s', time() + 1800); // 30分ロック
                $stmt = $pdo->prepare("UPDATE `users` SET `login_attempts` = ?, `locked_until` = ? WHERE `id` = ?");
                $stmt->execute([$attempts, $locked_until, $user['id']]);
                respond('error', '認証コードが異なります。3回間違えたため、アカウントを30分間ロックしました。');
            } else {
                $stmt = $pdo->prepare("UPDATE `users` SET `login_attempts` = ? WHERE `id` = ?");
                $stmt->execute([$attempts, $user['id']]);
                $remaining = 3 - $attempts;
                respond('error', "認証コードが異なります。あと {$remaining} 回間違えるとロックされます。");
            }
        }
        break;

    // --------------------------------------------------------
    // A-3. ログアウト
    // --------------------------------------------------------
    case 'logout':
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        respond('success', 'ログアウトしました。');
        break;

    // --------------------------------------------------------
    // B-1. データ取得
    // --------------------------------------------------------
    case 'get_data':
        check_auth();
        $user_id = $_SESSION['user_id'];

        $stmt = $pdo->prepare("SELECT `learning_id` FROM `user_learning` WHERE `user_id` = ?");
        $stmt->execute([$user_id]);
        $learnings = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $learnings[] = ''; 

        $in_clause = str_repeat('?,', count($learnings) - 1) . '?';
        $stmt = $pdo->prepare("SELECT * FROM `groups` WHERE `learning_id` IS NULL OR `learning_id` IN ($in_clause)");
        $stmt->execute($learnings);
        $groups = $stmt->fetchAll();

        $gids = array_column($groups, 'id');
        $subgroups = [];
        $frequencies = [];

        if (!empty($gids)) {
            $gid_clause = str_repeat('?,', count($gids) - 1) . '?';
            
            $stmt = $pdo->prepare("SELECT * FROM `subgroups` WHERE `gid` IN ($gid_clause)");
            $stmt->execute($gids);
            $subgroups = $stmt->fetchAll();

            $sids = array_column($subgroups, 'id');
            if (!empty($sids)) {
                $sid_clause = str_repeat('?,', count($sids) - 1) . '?';
                
                $stmt = $pdo->prepare("SELECT * FROM `frequencies` WHERE `sid` IN ($sid_clause)");
                $stmt->execute($sids);
                $frequencies = $stmt->fetchAll();
            }
        }

        $customs = [];
        try {
            $stmt = $pdo->prepare("SELECT * FROM `user_custom_frequencies` WHERE `user_id` = ?");
            $stmt->execute([$user_id]);
            $customs = $stmt->fetchAll();
        } catch (PDOException $e) {
            $customs = [];
        }

        respond('success', 'データ取得成功', [
            'groups' => $groups,
            'subgroups' => $subgroups,
            'frequencies' => $frequencies,
            'custom_frequencies' => $customs
        ]);
        break;

    // --------------------------------------------------------
    // E. カスタムデータ保存・削除
    // --------------------------------------------------------
    case 'save_custom_frequency':
        check_auth();
        $user_id = $_SESSION['user_id'];
        $id = $data['id'] ?? null;
        $name = trim($data['name'] ?? '');
        $flist = trim($data['flist'] ?? '');

        if (empty($name) || empty($flist)) {
            respond('error', '名称と周波数リストを入力してください。');
        }

        if (!preg_match('/^[0-9.,]+$/', $flist)) {
            respond('error', '周波数リストには半角数字、小数点、カンマのみを使用してください。');
        }

        if ($id) {
            $stmt = $pdo->prepare("UPDATE `user_custom_frequencies` SET `name` = ?, `flist` = ? WHERE `id` = ? AND `user_id` = ?");
            $stmt->execute([$name, $flist, $id, $user_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO `user_custom_frequencies` (`user_id`, `name`, `flist`) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $name, $flist]);
        }
        respond('success', 'カスタムデータを保存しました。');
        break;

    case 'delete_custom_frequency':
        check_auth();
        $user_id = $_SESSION['user_id'];
        $id = $data['id'] ?? null;

        $stmt = $pdo->prepare("DELETE FROM `user_custom_frequencies` WHERE `id` = ? AND `user_id` = ?");
        $stmt->execute([$id, $user_id]);
        respond('success', 'カスタムデータを削除しました。');
        break;

    // --------------------------------------------------------
    // F. 管理機能 (ユーザー管理)
    // --------------------------------------------------------
    case 'admin_get_users':
        check_admin();
        $users = $pdo->query("SELECT `id`, `regist_date`, `name`, `email`, `paid`, `role`, `login_attempts`, `locked_until`, `memo` FROM `users` ORDER BY `id` DESC")->fetchAll();
        
        foreach ($users as &$u) {
            $stmt = $pdo->prepare("SELECT `learning_id` FROM `user_learning` WHERE `user_id` = ?");
            $stmt->execute([$u['id']]);
            $u['learnings'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        respond('success', 'ユーザー一覧取得', ['users' => $users]);
        break;

    case 'admin_save_user':
        check_admin();
        $id = $data['id'] ?? null;
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $paid = (int)($data['paid'] ?? 0);
        $role = trim($data['role'] ?? 'regular');
        $memo = trim($data['memo'] ?? '');
        $learnings = $data['learnings'] ?? [];

        if (empty($name) || empty($email)) {
            respond('error', '名前とメールアドレスは必須です。');
        }

        $pdo->beginTransaction();
        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE `users` SET `name` = ?, `email` = ?, `paid` = ?, `role` = ?, `memo` = ? WHERE `id` = ?");
                $stmt->execute([$name, $email, $paid, $role, $memo, $id]);
                $user_id = $id;
            } else {
                $stmt = $pdo->prepare("INSERT INTO `users` (`regist_date`, `name`, `email`, `paid`, `role`, `memo`) VALUES (NOW(), ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $paid, $role, $memo]);
                $user_id = $pdo->lastInsertId();
            }

            $stmt = $pdo->prepare("DELETE FROM `user_learning` WHERE `user_id` = ?");
            $stmt->execute([$user_id]);

            if (!empty($learnings)) {
                $stmt = $pdo->prepare("INSERT INTO `user_learning` (`user_id`, `learning_id`) VALUES (?, ?)");
                foreach ($learnings as $lid) {
                    if (!empty(trim($lid))) {
                        $stmt->execute([$user_id, trim($lid)]);
                    }
                }
            }

            $pdo->commit();
            respond('success', 'ユーザー情報を保存しました。');
        } catch (Exception $e) {
            $pdo->rollBack();
            respond('error', 'ユーザー保存に失敗しました: ' . $e->getMessage());
        }
        break;

    case 'admin_delete_user':
        check_admin();
        $id = $data['id'] ?? null;
        if ($id == $_SESSION['user_id']) {
            respond('error', '自身を削除することはできません。');
        }
        $stmt = $pdo->prepare("DELETE FROM `users` WHERE `id` = ?");
        $stmt->execute([$id]);
        respond('success', 'ユーザーを削除しました。');
        break;

    case 'admin_unlock_user':
        check_admin();
        $id = $data['id'] ?? null;
        $stmt = $pdo->prepare("UPDATE `users` SET `login_attempts` = 0, `locked_until` = NULL WHERE `id` = ?");
        $stmt->execute([$id]);
        respond('success', 'アカウントロックを解除しました。');
        break;

    // --------------------------------------------------------
    // F. 管理機能 (周波数マスタ管理)
    // --------------------------------------------------------
    case 'admin_get_masters':
        check_admin();
        $groups = $pdo->query("SELECT * FROM `groups` ORDER BY `id` ASC")->fetchAll();
        $subgroups = $pdo->query("SELECT * FROM `subgroups` ORDER BY `id` ASC")->fetchAll();
        $frequencies = $pdo->query("SELECT * FROM `frequencies` ORDER BY `id` ASC")->fetchAll();
        respond('success', 'マスタデータ取得', [
            'groups' => $groups,
            'subgroups' => $subgroups,
            'frequencies' => $frequencies
        ]);
        break;

    case 'admin_save_group':
        check_admin();
        $id = $data['id'] ?? null;
        $gname = trim($data['gname'] ?? '');
        $learning_id = trim($data['learning_id'] ?? '');
        if (empty($learning_id)) $learning_id = null;

        if (empty($gname)) respond('error', 'グループ名を入力してください。');

        if ($id) {
            $stmt = $pdo->prepare("UPDATE `groups` SET `gname` = ?, `learning_id` = ? WHERE `id` = ?");
            $stmt->execute([$gname, $learning_id, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO `groups` (`gname`, `learning_id`) VALUES (?, ?)");
            $stmt->execute([$gname, $learning_id]);
        }
        respond('success', 'グループを保存しました。');
        break;

    case 'admin_save_subgroup':
        check_admin();
        $id = $data['id'] ?? null;
        $gid = (int)($data['gid'] ?? 0);
        $sname = trim($data['sname'] ?? '');

        if (!$gid || empty($sname)) respond('error', '親グループの選択とサブグループ名は必須です。');

        if ($id) {
            $stmt = $pdo->prepare("UPDATE `subgroups` SET `gid` = ?, `sname` = ? WHERE `id` = ?");
            $stmt->execute([$gid, $sname, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO `subgroups` (`gid`, `sname`) VALUES (?, ?)");
            $stmt->execute([$gid, $sname]);
        }
        respond('success', 'サブグループを保存しました。');
        break;

    case 'admin_save_frequency':
        check_admin();
        $id = $data['id'] ?? null;
        $sid = (int)($data['sid'] ?? 0);
        $jname = trim($data['jname'] ?? '');
        $ename = trim($data['ename'] ?? '');
        $flist = trim($data['flist'] ?? '');

        if (!$sid || empty($jname) || empty($flist)) {
            respond('error', '必要な入力が不足しています。');
        }
        if (!preg_match('/^[0-9.,]+$/', $flist)) {
            respond('error', '半角数字、小数点、カンマのみが許可されています。');
        }

        if ($id) {
            $stmt = $pdo->prepare("UPDATE `frequencies` SET `sid` = ?, `jname` = ?, `ename` = ?, `flist` = ? WHERE `id` = ?");
            $stmt->execute([$sid, $jname, $ename, $flist, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO `frequencies` (`sid`, `jname`, `ename`, `flist`) VALUES (?, ?, ?, ?)");
            $stmt->execute([$sid, $jname, $ename, $flist]);
        }
        respond('success', '周波数データを保存しました。');
        break;

    case 'admin_delete_master':
        check_admin();
        $type = $data['type'] ?? '';
        $id = $data['id'] ?? null;

        if (!$id) respond('error', 'IDが指定されていません。');

        if ($type === 'group') {
            $stmt = $pdo->prepare("DELETE FROM `groups` WHERE `id` = ?");
        } elseif ($type === 'subgroup') {
            $stmt = $pdo->prepare("DELETE FROM `subgroups` WHERE `id` = ?");
        } elseif ($type === 'frequency') {
            $stmt = $pdo->prepare("DELETE FROM `frequencies` WHERE `id` = ?");
        } else {
            respond('error', '不正な削除タイプです。');
        }

        $stmt->execute([$id]);
        respond('success', 'データを削除しました。');
        break;

    case 'get_session_status':
        if (isset($_SESSION['user_id'])) {
            respond('success', '認証済みです。', [
                'user' => [
                    'name' => $_SESSION['user_name'],
                    'role' => $_SESSION['role']
                ]
            ]);
        } else {
            respond('error', '未ログイン');
        }
        break;

    default:
        respond('error', '無効なリクエストアクションです。');
        break;
}