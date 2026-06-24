
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";



--
-- テーブルのデータのダンプ `users`
--

INSERT INTO `users` (`id`, `regist_date`, `update_date`, `name`, `email`, `paid`, `role`, `passkey_hash`, `login_attempts`, `locked_until`, `memo`) VALUES
(1, '2026-06-11 06:25:27', '2026-06-11 12:32:33', '管理者 太郎', 'admin@example.com', 1, 'admin', NULL, 0, NULL, 'テスト用管理者アカウント'),
(2, '2026-06-11 06:25:27', NULL, 'テスト 次郎', 'user@example.com', 1, 'regular', NULL, 0, NULL, 'テスト用一般会員アカウント');


INSERT INTO `user_learning` (`user_id`, `learning_id`) VALUES
(2, 'advance1');


INSERT INTO `groups` (`id`, `gname`, `learning_id`) VALUES
(1, '基本グループ', NULL),
(2, '応用グループ(Advance1)', 'advance1');


INSERT INTO `subgroups` (`id`, `gid`, `sname`) VALUES
(1, 1, 'ベーシックリセット'),
(2, 2, 'アドバンス波形');





