SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


CREATE TABLE `paper_licences` (
  `id` int(11) UNSIGNED NOT NULL,
  `licence` varchar(255) CHARACTER SET utf8mb4 NOT NULL,
  `docid` int(10) UNSIGNED NOT NULL,
  `source_id` int(10) UNSIGNED NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `paper_licences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_docid` (`id`),
  ADD KEY `docid` (`docid`),
  ADD KEY `source_id` (`source_id`);

ALTER TABLE `paper_licences`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;
