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
SET visibility_set = COALESCE(
        (SELECT GROUP_CONCAT(JSON_UNQUOTE(jt.val) SEPARATOR ',')
         FROM JSON_TABLE(
                      visibility,
                      '$[*]' COLUMNS (val JSON PATH '$')
              ) AS jt
         ),
       'public'
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
FROM news LIMIT 20;

-- 3.2: Visual inspection of PAGES migration
-- Compare JSON values with their SET equivalents
SELECT id, visibility AS json_value, visibility_set AS set_value
FROM pages LIMIT 50;

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
-- PHASE 4: Cleanup (FINAL STEP - Only after dual-write code is removed)
-- ============================================================================
--
-- !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
-- !!!                                                                        !!!
-- !!!   DO NOT EXECUTE PHASE 4 BEFORE REMOVING DUAL-WRITE CODE!              !!!
-- !!!                                                                        !!!
-- !!!   If you drop the columns while the code still writes to them,         !!!
-- !!!   all INSERT/UPDATE operations on pages and news will FAIL with:       !!!
-- !!!   ERROR 1054: Unknown column 'visibility' in 'field list'              !!!
-- !!!                                                                        !!!
-- !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
--
-- MIGRATION STEPS (2 deployments required):
-- ==========================================
--
-- ┌─────────────────────────────────────────────────────────────────┐
-- │ STEP 1: Deploy current PR (with dual-write)                    │
-- │         → Execute Phase 1, 2, 3 of this SQL script             │
-- │         → Code writes to BOTH columns (old + new)              │
-- │         → Rollback possible if issues                          │
-- └─────────────────────────────────────────────────────────────────┘
--                               ↓
-- ┌─────────────────────────────────────────────────────────────────┐
-- │ STEP 2: Validate in production                                  │
-- │         → Monitor for errors                                    │
-- │         → Run Phase 3 verification queries                      │
-- └─────────────────────────────────────────────────────────────────┘
--                               ↓
-- ┌─────────────────────────────────────────────────────────────────┐
-- │ STEP 3: Remove 5 dual-write lines (NEW PR) + Deploy            │
-- │         → Remove lines listed below                            │
-- │         → Deploy to production (2nd deployment)                │
-- │         → Code now writes ONLY to new columns                  │
-- └─────────────────────────────────────────────────────────────────┘
--                               ↓
-- ┌─────────────────────────────────────────────────────────────────┐
-- │ STEP 4: Execute Phase 4 (this section)                          │
-- │         → Take database backup first                           │
-- │         → Run DROP COLUMN statements below                     │
-- │         → Migration complete                                   │
-- └─────────────────────────────────────────────────────────────────┘
--
--
-- STEP 3 DETAILS - Lines to remove:
-- ==================================
--
--    File: library/Episciences/Page/Manager.php
--    --------------------------------------------
--    Line 95  (in add()):                   'visibility' => self::visibilityToJson($visibilityValue),
--    Line 127 (in update()):                'visibility' => self::visibilityToJson($visibilityValue),
--    Line 161 (in updateWithNewPageCode()): 'visibility' => self::visibilityToJson($visibilityValue),
--
--    File: library/Episciences/JournalNews.php
--    ------------------------------------------
--    Line 250 (in insert()): $news['visibility'] = self::visibilityToJson($visibilityValue);
--    Line 281 (in update()): 'visibility' => self::visibilityToJson($visibilityValue),
--
--    (Optional) Remove unused helper methods after removing the 5 lines:
--    - Page/Manager.php lines 25-32: visibilityToJson()
--    - JournalNews.php lines 234-240: visibilityToJson()
--
--
-- WHY THIS ORDER MATTERS:
-- -----------------------
-- The application currently writes to BOTH columns (dual-write):
--   - visibility_set / visibility_enum (new columns)
--   - visibility (old JSON column)
--
-- After Phase 4, the old 'visibility' column no longer exists.
-- If the code still tries to write to it, MySQL will return an error.
--
-- Reading is NOT affected because SELECT queries use SQL aliases:
--   'visibility' => 'visibility_set'  (reads from new column)
--
-- ============================================================================

-- Drop the old JSON columns only
ALTER TABLE news DROP COLUMN visibility;
ALTER TABLE pages DROP COLUMN visibility;
