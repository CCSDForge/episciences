--
-- Table structure
--
CREATE TABLE `paper_datasets` (
                                  `id` int NOT NULL,
                                  `doc_id` int NOT NULL,
                                  `code` varchar(50) NOT NULL,
                                  `name` varchar(200) NOT NULL,
                                  `value` varchar(500) NOT NULL,
                                  `link` varchar(750) NOT NULL,
                                  `source_id` int NOT NULL,
                                  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Index
--
ALTER TABLE `paper_datasets`
    ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique` (`doc_id`,`code`,`name`,`value`,`source_id`) USING BTREE,
  ADD KEY `doc_id` (`doc_id`),
  ADD KEY `source_id` (`source_id`),
  ADD KEY `code` (`code`),
  ADD KEY `name` (`name`) USING BTREE;