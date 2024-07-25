ALTER TABLE `USER` CHANGE `REGISTRATION_DATE` `REGISTRATION_DATE` TIMESTAMP NULL DEFAULT NULL COMMENT 'Date the profile was created';
ALTER TABLE `USER` CHANGE `MODIFICATION_DATE` `MODIFICATION_DATE` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date the profile was updated'