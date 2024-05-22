SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


-- --------------------------------------------------------

--
-- Table structure for table `news`
--

CREATE TABLE `news` (
                        `id` int UNSIGNED NOT NULL,
                        `legacy_id` int UNSIGNED DEFAULT NULL COMMENT 'Legacy News id',
                        `code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Journal code rvcode',
                        `uid` int UNSIGNED NOT NULL,
                        `date_creation` datetime DEFAULT NULL,
                        `date_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        `title` json NOT NULL COMMENT 'Page title',
                        `content` json DEFAULT NULL,
                        `link` json DEFAULT NULL,
                        `visibility` json NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `news`
--
ALTER TABLE `news`
    ADD PRIMARY KEY (`id`),
  ADD KEY `uid` (`uid`),
  ADD KEY `code` (`code`);
COMMIT;
