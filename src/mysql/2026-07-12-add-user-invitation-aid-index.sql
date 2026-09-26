-- USER_INVITATION.AID has no index and no FK constraint despite being the join column
-- to USER_ASSIGNMENT.ID everywhere in the codebase (Episciences_PapersManager::
-- getLatestInvitationByDocIdQuery(), StatsQuery::latestInvitationPerAssignmentSql(), ...).
-- An unindexed self-join dedup on this column took several minutes on a large journal and
-- caused a gateway timeout on /reviewersstats. A window-function rewrite fixed the query
-- itself, but this index removes the remaining filesort and speeds up every other join on
-- AID across the codebase.
ALTER TABLE `USER_INVITATION` ADD INDEX `AID_SENDING_DATE` (`AID`, `SENDING_DATE`);
