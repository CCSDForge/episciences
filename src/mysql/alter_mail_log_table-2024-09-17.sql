ALTER TABLE `MAIL_LOG` ADD `UID` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Sender identifier (via the mailing module or the article page)' AFTER `ID`, ADD INDEX `UID` (`UID`);