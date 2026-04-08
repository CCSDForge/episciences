-- SQL script to add volume title editing restriction setting
-- Date: 2025-12-08
-- Description: Adds a new setting to journal configuration (RVID 3):
--   allowEditVolumeTitleWithPublishedArticles: Allow editing volume titles even when the volume contains published articles

-- Note: This setting is disabled by default (value 0)
-- When disabled (0): Volume titles cannot be edited if the volume contains published articles
-- When enabled (1): Volume titles can be edited regardless of article publication status
-- This setting can be enabled for each journal via the administration interface

INSERT INTO REVIEW_SETTING (RVID, SETTING, VALUE)
VALUES (3, 'allowEditVolumeTitleWithPublishedArticles', 0);