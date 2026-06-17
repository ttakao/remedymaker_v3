<?php
// src/import_update.php
header('Content-Type: text/plain; charset=utf-8');
require_once 'config.php';

// ★実行設定：インポートするファイル名と、割り当てる learning_id を指定します
$update_dir = "update_data/";
$update_file = "advanced3.csv"; // update_data/ フォルダの中にあるファイル名
$learning_id = "Advanced3";             // 'Advanced', 'Advanced2', 'Advanced3' など

$full_path = $update_dir . $update_file;

if (!file_exists($full_path)) {
    die("エラー：指定されたアップデートファイルが見つかりません: " . htmlspecialchars($full_path) . "\n");
}

$pdo = get_db_connection();

// 新旧IDの翻訳用マッピングテーブル
$group_id_map = [];
$subgroup_id_map = [];

// ファイルの内容を1行ずつ解析するための分類配列
$group_rows = [];
$subgroup_rows = [];
$frequency_rows = [];

echo "--- アップデートファイルの解析を開始します (ファイル名: {$full_path}, 割当ID: {$learning_id}) ---\n";

// ファイルの読み込みとデータ分類
$lines = file($full_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    // UTF-8のBOMがある場合は除去
    $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
    
    // セミコロンで分割
    $parts = explode(';', $line);
    if (count($parts) < 3) continue;

    $type = trim($parts[0]);
    if ($type === 'groups') {
        $group_rows[] = $parts;
    } elseif ($type === 'subgroups') {
        $subgroup_rows[] = $parts;
    } elseif ($type === 'frequencies') {
        $frequency_rows[] = $parts;
    }
}

echo "解析完了：グループ " . count($group_rows) . "件、サブグループ " . count($subgroup_rows) . "件、周波数 " . count($frequency_rows) . "件\n\n";

// --------------------------------------------------------
// ① groups のインサート
// --------------------------------------------------------
echo "1. グループデータを登録中...\n";
$stmt = $pdo->prepare("INSERT INTO `groups` (`gname`, `learning_id`) VALUES (?, ?)");
foreach ($group_rows as $row) {
    $old_id = trim($row[1]);
    $gname = trim($row[2]);

    $stmt->execute([$gname, $learning_id]);
    $new_id = $pdo->lastInsertId();

    $group_id_map[$old_id] = $new_id;
}
echo "グループ登録完了。\n\n";

// --------------------------------------------------------
// ② subgroups のインサート
// --------------------------------------------------------
echo "2. サブグループデータを登録中...\n";
$stmt = $pdo->prepare("INSERT INTO `subgroups` (`gid`, `sname`) VALUES (?, ?)");
foreach ($subgroup_rows as $row) {
    $old_id = trim($row[1]);
    $old_gid = trim($row[2]);
    $sname = trim($row[3]);

    $new_gid = $group_id_map[$old_gid] ?? null;
    if ($new_gid === null) {
        echo "[警告] グループID {$old_gid} が見つからないため、サブグループID {$old_id} をスキップしました。\n";
        continue;
    }

    $stmt->execute([$new_gid, $sname]);
    $new_id = $pdo->lastInsertId();

    $subgroup_id_map[$old_id] = $new_id;
}
echo "サブグループ登録完了。\n\n";

// --------------------------------------------------------
// ③ frequencies のインサート
// --------------------------------------------------------
echo "3. 周波数リストを登録中...\n";
$stmt = $pdo->prepare("INSERT INTO `frequencies` (`sid`, `jname`, `ename`, `flist`) VALUES (?, ?, ?, ?)");
foreach ($frequency_rows as $row) {
    $old_id = trim($row[1]);
    $old_sid = trim($row[2]);
    $jname = trim($row[3]);
    $ename = isset($row[4]) ? trim($row[4]) : '';
    $flist = isset($row[5]) ? trim($row[5]) : '';

    if (empty($flist) && !empty($ename) && preg_match('/^[0-9.,]+$/', $ename)) {
        $flist = $ename;
        $ename = '';
    }

    $new_sid = $subgroup_id_map[$old_sid] ?? null;
    if ($new_sid === null) {
        echo "[警告] サブグループID {$old_sid} が見つからないため、周波数データID {$old_id} をスキップしました。\n";
        continue;
    }

    $stmt->execute([$new_sid, $jname, $ename, $flist]);
}
echo "周波数登録完了。\n\n";
echo "--- アップデート処理がすべて成功しました！ ---";