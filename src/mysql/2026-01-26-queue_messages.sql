CREATE TABLE `queue_messages`(
    `id` INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    `type` VARCHAR(50) NOT NULL DEFAULT '',
    `message` JSON NOT NULL,
    `created_at` INT UNSIGNED NOT NULL,
    `timeout` INT UNSIGNED NOT NULL DEFAULT '120',
    `processed` TINYINT(1) NOT NULL DEFAULT '0',
    `created_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 ROW_FORMAT = DYNAMIC;
ALTER TABLE `queue_messages` ADD KEY `idx_status`(`processed`),
    ADD KEY `idx_timeout`(`timeout`),
    ADD KEY `idx_created`(`created_at`),
    ADD KEY `idx_time_status`(`processed`, `created_at`),
    ADD KEY `idx_ready_messages`(`processed`, `timeout`, `created_at`),
    ADD KEY `idx_type`(`type`);


    ALTER TABLE `queue_messages` CHANGE `created_date` `rvcode` VARCHAR(50) NOT NULL AFTER `id`;
    ALTER TABLE `queue_messages` ADD INDEX `idx_rvcode` (`rvcode`);