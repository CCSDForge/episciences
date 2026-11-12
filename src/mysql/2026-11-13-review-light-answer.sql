-- Answers given without an account to a review request of a "light answer" type (#319): a quick opinion typed or
-- uploaded from the invitation link. One answer per invitation, editable until the due date of the request.
-- Kept apart from REVIEWER_REPORT, whose UID is an account: the invited person may have no account.
-- Created: 2026-11-13

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `review_light_answer` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `aid` int unsigned NOT NULL COMMENT 'Original (pending) assignment of the invitation: USER_INVITATION.AID',
  `rvid` int unsigned NOT NULL,
  `docid` int unsigned NOT NULL,
  `request_type_id` int unsigned NOT NULL,
  `uid` int unsigned NOT NULL COMMENT 'Invited person: account UID, or temporary user id when tmp_user = 1',
  `tmp_user` tinyint unsigned NOT NULL DEFAULT '0',
  `message` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'Plain text',
  `file` varchar(255) DEFAULT NULL COMMENT 'Attached file name, in <docid>/reports/light_<aid>/',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_aid` (`aid`),
  KEY `idx_docid` (`docid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Review answers given without an account';
