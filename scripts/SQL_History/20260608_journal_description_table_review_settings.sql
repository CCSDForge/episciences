-- ============================================================================
-- Migration: Add Journal Backoffice Settings
-- Date: 2026-06-08
-- Description: Initialize backoffice settings for all existing journals
-- ============================================================================

-- Add 'journalDescription' setting for journals that don't have it yet
INSERT INTO REVIEW_SETTING (RVID, SETTING, VALUE)
SELECT RVID, 'journalDescription', NULL
FROM REVIEW
WHERE RVID NOT IN (
    SELECT RVID FROM REVIEW_SETTING WHERE SETTING = 'journalDescription'
);

-- Add 'journalKeywords' setting for journals that don't have it yet
INSERT INTO REVIEW_SETTING (RVID, SETTING, VALUE)
SELECT RVID, 'journalKeywords', NULL
FROM REVIEW
WHERE RVID NOT IN (
    SELECT RVID FROM REVIEW_SETTING WHERE SETTING = 'journalKeywords'
);

-- Add 'journalCreationYear' setting for journals that don't have it yet
INSERT INTO REVIEW_SETTING (RVID, SETTING, VALUE)
SELECT RVID, 'journalCreationYear', NULL
FROM REVIEW
WHERE RVID NOT IN (
    SELECT RVID FROM REVIEW_SETTING WHERE SETTING = 'journalCreationYear'
);
