SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


--
-- Table structure for table `pages`
--

CREATE TABLE `pages` (
                         `id` int NOT NULL,
                         `code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Journal code rvcode',
                         `uid` int UNSIGNED NOT NULL,
                         `date_creation` datetime DEFAULT NULL,
                         `date_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                         `title` json NOT NULL COMMENT 'Page title',
                         `content` json NOT NULL,
                         `visibility` json NOT NULL,
                         `page_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Page code'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `pages`
--
ALTER TABLE `pages`
    ADD PRIMARY KEY (`id`),
  ADD KEY `uid` (`uid`),
  ADD KEY `rvcode` (`code`) USING BTREE,
  ADD KEY `page_code` (`page_code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
    MODIFY `id` int NOT NULL AUTO_INCREMENT;
COMMIT;
