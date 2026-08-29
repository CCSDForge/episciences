CREATE TABLE `data_descriptor` (
                                   `id` int UNSIGNED NOT NULL,
                                   `uid` int UNSIGNED NOT NULL,
                                   `docid` int UNSIGNED NOT NULL,
                                   `fileid` int UNSIGNED NOT NULL,
                                   `version` float UNSIGNED NOT NULL DEFAULT '1',
                                   `submission_date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


ALTER TABLE `data_descriptor`
    ADD PRIMARY KEY (`id`),
  ADD KEY `docid` (`docid`),
  ADD KEY `fileid` (`fileid`),
  ADD KEY `version` (`version`),
  ADD KEY `submission_date` (`submission_date`),
  ADD KEY `INDEX_UID` (`uid`);


ALTER TABLE `data_descriptor`
    MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;