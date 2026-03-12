SET NAMES 'utf8mb4';

CREATE TABLE IF NOT EXISTS `mailing_lists` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `rvid` int(11) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `rvid` (`rvid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mailing_list_users` (
  `list_id` int(11) UNSIGNED NOT NULL,
  `uid` int(11) UNSIGNED NOT NULL,
  PRIMARY KEY (`list_id`, `uid`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mailing_list_roles` (
  `list_id` int(11) UNSIGNED NOT NULL,
  `role` varchar(50) NOT NULL,
  PRIMARY KEY (`list_id`, `role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- View for External Program Access
-- Resolves all members (individual + roles) and aggregates them into JSON
-- ---------------------------------------------------------
CREATE OR REPLACE VIEW `v_mailing_lists_resolved` AS
WITH list_members_data AS (
    SELECT DISTINCT
        ml.id as list_id,
        u.FIRSTNAME,
        u.LASTNAME,
        u.EMAIL
    FROM mailing_lists ml
    JOIN (
        -- Individual members
        SELECT list_id, uid FROM mailing_list_users
        UNION
        -- Role based members
        SELECT mlr.list_id, ur.UID as uid
        FROM mailing_list_roles mlr
        JOIN mailing_lists ml_inner ON ml_inner.id = mlr.list_id
        JOIN USER_ROLES ur ON ur.ROLEID = mlr.role AND ur.RVID = ml_inner.rvid
    ) lm ON ml.id = lm.list_id
    JOIN USER u ON lm.uid = u.UID
    WHERE u.IS_VALID = 1
)
SELECT 
    ml.rvid as journal_id,
    ml.name as list_name,
    ml.type as list_type,
    IF(ml.status = 1, 'open', 'closed') as list_status,
    IF(COUNT(lmd.EMAIL) = 0, 
       JSON_ARRAY(), 
       JSON_ARRAYAGG(
           JSON_OBJECT(
               'firstname', lmd.FIRSTNAME,
               'lastname', lmd.LASTNAME,
               'email', lmd.EMAIL
           )
       )
    ) as members
FROM mailing_lists ml
LEFT JOIN list_members_data lmd ON ml.id = lmd.list_id
GROUP BY ml.id, ml.rvid, ml.name, ml.type, ml.status;
