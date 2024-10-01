--
-- Table structure for table `paper_classifications`
--

CREATE TABLE `paper_classifications`
(
    `id`                  int UNSIGNED NOT NULL,
    `docid`               int UNSIGNED NOT NULL,
    `classification_code` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    `classification_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    `source_id`           int UNSIGNED NOT NULL,
    `updated_at`          timestamp                                                     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `paper_classifications`
--
ALTER TABLE `paper_classifications`
    ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniqClassification` (`docid`,`classification_code`,`classification_name`),
  ADD KEY `source_id` (`source_id`),
  ADD KEY `docid` (`docid`),
  ADD KEY `classification_code` (`classification_code`),
  ADD KEY `classification_name` (`classification_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `paper_classifications`
--
ALTER TABLE `paper_classifications`
    MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;