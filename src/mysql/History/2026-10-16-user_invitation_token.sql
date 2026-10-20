-- Per-invitation random value used to derive the reviewer invitation link token.
-- Legacy values were never read and all belong to expired invitations: they are cleared so that
-- a NULL TOKEN unambiguously means "invitation created before the link token".
UPDATE `USER_INVITATION` SET `TOKEN` = NULL WHERE `TOKEN` IS NOT NULL;

ALTER TABLE `USER_INVITATION`
    MODIFY `TOKEN` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL
        COMMENT 'Per-invitation random value for the link token; NULL = legacy invitation';
