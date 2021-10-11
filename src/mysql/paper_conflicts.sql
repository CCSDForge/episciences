--
-- `paper_conflicts` table  structure
--

CREATE TABLE `paper_conflicts` (
  `cid` int UNSIGNED NOT NULL,
  `paper_id` int UNSIGNED NOT NULL,
  `by` int UNSIGNED NOT NULL COMMENT 'uid',
  `answer` enum('yes','no') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='conflicts handling';

--
-- `paper_conflicts` table index
--
alter table `paper_conflicts`
  ADD PRIMARY KEY (`cid`),
  ADD UNIQUE KEY `U_PAPERID_BY` (`paper_id`,`by`) USING BTREE,
  ADD KEY `BY_UID` (`by`),
  ADD KEY `PAPERID` (`paper_id`);

alter table `paper_conflicts`
  MODIFY `cid` int UNSIGNED NOT NULL AUTO_INCREMENT;
