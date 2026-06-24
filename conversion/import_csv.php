<?php
// src/import_csv.php
header('Content-Type: text/plain; charset=utf-8');
require_once 'config.php';

// 読み込み先フォルダの定義
$data_dir = "old_data/";
$groups_file = $data_dir . "groups.csv";
$subgroups_file = $data_dir . "subgroups.csv";
$frequencies_file = $data_dir . "frequencies.csv";

// 事前のファイル存在確認
if (!file_exists($groups_file) || !file_exists($subgroups_file) || !file_exists($frequencies_file)) {
    die("エラー：'old_data/' フォルダ内に必要なCSVファイル (groups.csv, subgroups.csv, frequencies.csv) が不足しています。\n");
}

$pdo = get_db_connection();

// 1. 既存のテスト用マスタデータをクリアする
echo "--- 既存のマスタデータをクリアしています... ---\n";
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("TRUNCATE TABLE `frequencies`;");
$pdo->exec("TRUNCATE TABLE `subgroups`;");
$pdo->exec("TRUNCATE TABLE `groups`;");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
echo "クリア完了。\n\n";

$group_id_map = []; // [旧グループID => 新グループID]
$subgroup_id_map = []; // [旧サブグループID => 新サブグループID]

// --------------------------------------------------------
// ① groups.csv のインポート
// --------------------------------------------------------
echo "① {$groups_file} をインポート中...\n";
if (($handle = fopen($groups_file, "r")) !== FALSE) {
    $stmt = $pdo->prepare("INSERT INTO `groups` (`gname`, `learning_id`) VALUES (?, NULL)");
    $is_header = true;
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if ($is_header) { $is_header = false; continue; }
        
        $old_id = $data[0];
        
        // ★ 読み込んだ文字列を Shift_JIS (sjis-win / Windows独自記号対応) から UTF-8 へ変換します
        $gname = mb_convert_encoding($data[1], 'UTF-8', 'sjis-win');

        $stmt->execute([$gname]);
        $new_id = $pdo->lastInsertId();
        
        $group_id_map[$old_id] = $new_id;
    }
    fclose($handle);
}
echo "完了（" . count($group_id_map) . "件）。\n\n";

// --------------------------------------------------------
// ② subgroups.csv のインポート
// --------------------------------------------------------
echo "② {$subgroups_file} をインポート中...\n";
if (($handle = fopen($subgroups_file, "r")) !== FALSE) {
    $stmt = $pdo->prepare("INSERT INTO `subgroups` (`gid`, `sname`) VALUES (?, ?)");
    $is_header = true;
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if ($is_header) { $is_header = false; continue; }

        $old_id = $data[0];
        $old_gid = $data[1];
        
        // ★ 同様に UTF-8 へ変換します
        $sname = mb_convert_encoding($data[2], 'UTF-8', 'sjis-win');

        $new_gid = $group_id_map[$old_gid] ?? null;
        if ($new_gid === null) {
            echo "[警告] グループID {$old_gid} が見つからないため、サブグループID {$old_id} をスキップしました。\n";
            continue;
        }

        $stmt->execute([$new_gid, $sname]);
        $new_id = $pdo->lastInsertId();

        $subgroup_id_map[$old_id] = $new_id;
    }
    fclose($handle);
}
echo "完了（" . count($subgroup_id_map) . "件）。\n\n";

// --------------------------------------------------------
// ③ frequencies.csv のインポート
// --------------------------------------------------------
echo "③ {$frequencies_file} をインポート中...\n";
if (($handle = fopen($frequencies_file, "r")) !== FALSE) {
    $stmt = $pdo->prepare("INSERT INTO `frequencies` (`sid`, `jname`, `ename`, `flist`) VALUES (?, ?, ?, ?)");
    $is_header = true;
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if ($is_header) { $is_header = false; continue; }

        $old_id = $data[0];
        $old_sid = $data[1];
        
        // ★ 同様に UTF-8 へ変換します
        $jname = mb_convert_encoding($data[2], 'UTF-8', 'sjis-win');
        $ename = isset($data[3]) ? mb_convert_encoding($data[3], 'UTF-8', 'sjis-win') : '';
        $flist = $data[4] ?? '';

        $new_sid = $subgroup_id_map[$old_sid] ?? null;
        if ($new_sid === null) {
            echo "[警告] サブグループID {$old_sid} が見つからないため、周波数ID {$old_id} をスキップしました。\n";
            continue;
        }

        $stmt->execute([$new_sid, $jname, $ename, $flist]);
    }
    fclose($handle);
}
echo "完了。\n\n";
echo "--- すべてのCSVの移行が完了しました！ ---";