-- ============================================================================
-- Migration: Visibility columns from JSON to ENUM/SET
-- Date: 2026-06-03
-- Author: Migration script for Episciences
-- ============================================================================
--
-- CONTEXT:
-- --------
-- The `visibility` columns in `news` and `pages` tables were originally stored
-- as JSON arrays (e.g., '["public"]', '["chief_editor","administrator"]').
-- This migration converts them to native MySQL types for:
--   - Better query performance (no JSON parsing needed)
--   - Native indexing support
--   - Type safety and validation
--   - Smaller storage footprint
--
-- STRATEGY:
-- ---------
-- We add new columns (`visibility_enum` for news, `visibility_set` for pages)
-- to allow the application code to be updated BEFORE dropping the old columns.
-- This enables a zero-downtime migration:
--   1. Add new columns (this script, Phase 1-2)
--   2. Update application code to read/write new columns
--   3. Deploy updated code
--   4. Drop old columns (this script, Phase 4)
--
-- IMPORTANT: The new columns keep their names (`visibility_set` for pages)
-- and are NOT renamed to `visibility`. This avoids code changes after cleanup.
--
-- ============================================================================
-- PHASE 1: Add New Columns
-- ============================================================================
-- These columns are added alongside the existing JSON columns.
-- The application will write to BOTH columns during the transition period.

-- NEWS: Add ENUM column
-- ---------------------
-- News visibility is binary: either public (visible to all) or private
-- (visible only to journal members/editors).
-- ENUM is ideal for single-value selection with a fixed set of options.
ALTER TABLE news
    ADD COLUMN visibility_enum ENUM('public', 'private') DEFAULT 'public'
AFTER visibility;

-- PAGES: Add SET column
-- ---------------------
-- Pages can be visible to multiple user roles simultaneously.
-- SET allows storing multiple values in a single column (e.g., 'editor,chief_editor').
-- Maximum 8 values supported by SET type, which fits our role list.
--
-- Available roles:
--   - public: Visible to everyone (anonymous users included)
--   - member: Logged-in users who are members of the journal
--   - editor: Editors assigned to papers
--   - chief_editor: Chief editors with full editorial control
--   - administrator: System administrators
--   - secretary: Editorial secretaries
--   - webmaster: Journal webmasters
--   - guest_editor: Guest editors for special issues
ALTER TABLE pages
    ADD COLUMN visibility_set SET(
    'public',
    'member',
    'editor',
    'chief_editor',
    'administrator',
    'secretary',
    'webmaster',
    'guest_editor'
) DEFAULT 'public'
AFTER visibility;

-- ============================================================================
-- PHASE 2: Migrate Existing Data
-- ============================================================================
-- Convert JSON array values to the new column types.
-- This is a one-time data migration.

-- NEWS: JSON array -> ENUM
-- ------------------------
-- JSON format: '["public"]' or '["private"]'
-- We extract the first element and map it to the ENUM value.
-- If the JSON is invalid or contains unexpected values, defaults to 'private'.
UPDATE news
SET visibility_enum = CASE
                          WHEN JSON_UNQUOTE(JSON_EXTRACT(visibility, '$[0]')) = 'public' THEN 'public'
                          ELSE 'private'
    END
WHERE visibility IS NOT NULL;

-- PAGES: JSON array -> SET
-- ------------------------
-- JSON format: '["chief_editor","administrator"]' or '["public"]'
-- We extract all elements and concatenate them with commas.
-- MySQL SET type naturally accepts comma-separated values.
--
-- Example transformations:
--   '["public"]'                        -> 'public'
--   '["chief_editor","administrator"]'  -> 'chief_editor,administrator'
--   '["editor","member","public"]'      -> 'editor,member,public'
UPDATE pages
SET visibility_set = (
    SELECT GROUP_CONCAT(JSON_UNQUOTE(jt.val) SEPARATOR ',')
    FROM JSON_TABLE(
                 visibility,
                 '$[*]' COLUMNS (val JSON PATH '$')
         ) AS jt
)
WHERE JSON_VALID(visibility);

-- ============================================================================
-- PHASE 3: Verification Queries
-- ============================================================================
-- Run these queries BEFORE proceeding to Phase 4 to ensure data integrity.
-- DO NOT execute Phase 4 until all verifications pass!

-- 3.1: Visual inspection of NEWS migration
-- Compare JSON values with their ENUM equivalents
SELECT id, visibility AS json_value, visibility_enum AS enum_value
FROM news
LIMIT 20;

-- 3.2: Visual inspection of PAGES migration
-- Compare JSON values with their SET equivalents
SELECT id, visibility AS json_value, visibility_set AS set_value
FROM pages
LIMIT 50;

-- 3.3: Check for NULL values in new columns
-- Both queries should return 0 (no NULL values after migration)
SELECT 'news_null_count' AS check_name, COUNT(*) AS count
FROM news WHERE visibility_enum IS NULL;

SELECT 'pages_null_count' AS check_name, COUNT(*) AS count
FROM pages WHERE visibility_set IS NULL;

-- 3.4: Verify all SET values are valid
-- This should return 0 rows (no invalid role names)
SELECT id, visibility_set
FROM pages
WHERE visibility_set = ''
  AND visibility IS NOT NULL
  AND visibility != '[]';

-- ============================================================================
-- PHASE 4: Cleanup (FINAL STEP - IRREVERSIBLE)
-- ============================================================================
-- WARNING: Execute this phase ONLY when:
--   1. All verification queries in Phase 3 pass
--   2. Application code has been updated to use new columns
--   3. Application code no longer writes to old `visibility` column
--   4. A database backup has been taken
--
-- You have TWO options for cleanup. Choose ONE based on your preference:
--
-- ============================================================================
-- OPTION A: Keep current column names (RECOMMENDED)
-- ============================================================================
-- Advantages:
--   - No code changes required after cleanup
--   - Application already uses `visibility_enum` and `visibility_set`
--   - Simpler migration process
--   - Column names clearly indicate the data type (ENUM vs SET)
--
-- Disadvantages:
--   - Column names are slightly longer
--   - Different naming convention from original design

-- 4.1a: Drop the old JSON columns only
ALTER TABLE news DROP COLUMN visibility;
ALTER TABLE pages DROP COLUMN visibility;

-- ============================================================================
-- OPTION B: Rename columns to original `visibility` name
-- ============================================================================
-- Advantages:
--   - Cleaner column name (just `visibility`)
--   - Consistent with original schema design
--   - Shorter column name
--
-- Disadvantages:
--   - Requires updating application code AFTER cleanup to use `visibility`
--     instead of `visibility_enum` / `visibility_set`
--   - Two-step code deployment:
--     1. First deploy: code reads from new columns, writes to both
--     2. Run cleanup SQL (drop + rename)
--     3. Second deploy: code uses renamed `visibility` column
--
-- If we choose this option, comment out OPTION A above and uncomment below:

-- 4.1b: Drop the old JSON columns
-- ALTER TABLE news DROP COLUMN visibility;
-- ALTER TABLE pages DROP COLUMN visibility;

-- 4.2b: Rename new columns to `visibility`
-- This makes the schema cleaner but requires code updates after execution.
-- ALTER TABLE news CHANGE visibility_enum visibility ENUM('public', 'private') NOT NULL DEFAULT 'public';
-- ALTER TABLE pages CHANGE visibility_set visibility SET('public','member','editor','chief_editor','administrator','secretary','webmaster','guest_editor') NOT NULL DEFAULT 'public';

