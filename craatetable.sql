--
-- テーブルの構造 `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `regist_date` datetime NOT NULL,
  `update_date` datetime DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `paid` tinyint(1) NOT NULL DEFAULT '0',
  `role` varchar(10) NOT NULL DEFAULT 'regular',
  `passkey_hash` varchar(255) DEFAULT NULL,
  `login_attempts` tinyint NOT NULL DEFAULT '0',
  `locked_until` datetime DEFAULT NULL,
  `memo` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- テーブルのインデックス `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

-- テーブルの AUTO_INCREMENT `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

--
-- テーブルの構造 `user_learning`
--

CREATE TABLE `user_learning` (
  `user_id` int NOT NULL,
  `learning_id` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- テーブルのインデックス `user_learning`
--
ALTER TABLE `user_learning`
  ADD PRIMARY KEY (`user_id`,`learning_id`);

--
-- テーブルの制約 `user_learning`
--
ALTER TABLE `user_learning`
  ADD CONSTRAINT `user_learning_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

--
-- テーブルの構造 `groups`
--

CREATE TABLE `groups` (
  `id` int NOT NULL,
  `gname` varchar(255) NOT NULL,
  `learning_id` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- テーブルのインデックス `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`id`);
--
-- テーブルの AUTO_INCREMENT `groups`
--
ALTER TABLE `groups`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;
--
-- テーブルの構造 `subgroups`
--

CREATE TABLE `subgroups` (
  `id` int NOT NULL,
  `gid` int NOT NULL,
  `sname` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
--
-- テーブルのインデックス `subgroups`
--
ALTER TABLE `subgroups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gid` (`gid`);
--
-- テーブルの AUTO_INCREMENT `subgroups`
--
ALTER TABLE `subgroups`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
--
-- テーブルの制約 `subgroups`
--
ALTER TABLE `subgroups`
  ADD CONSTRAINT `subgroups_ibfk_1` FOREIGN KEY (`gid`) REFERENCES `groups` (`id`) ON DELETE CASCADE;
COMMIT;

--
-- テーブルの構造 `frequencies`
--

CREATE TABLE `frequencies` (
  `id` int NOT NULL,
  `sid` int NOT NULL,
  `jname` varchar(255) NOT NULL,
  `ename` varchar(255) DEFAULT NULL,
  `flist` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
--
-- テーブルのインデックス `frequencies`
--
ALTER TABLE `frequencies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sid` (`sid`);

--
-- テーブルの AUTO_INCREMENT `frequencies`
--
ALTER TABLE `frequencies`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- テーブルの制約 `frequencies`
--
ALTER TABLE `frequencies`
  ADD CONSTRAINT `frequencies_ibfk_1` FOREIGN KEY (`sid`) REFERENCES `subgroups` (`id`) ON DELETE CASCADE;
COMMIT;

-- 
-- カスタムデータテーブル
--
CREATE TABLE IF NOT EXISTS `user_custom_frequencies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `flist` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_custom_frequencies_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
