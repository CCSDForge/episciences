-- ============================================================================
-- Migration: Visibility columns from JSON to ENUM/SET
-- Date: 2026-06-03
-- Description: Migrate visibility columns from JSON arrays to native MySQL types
--              for better performance and indexing
-- ============================================================================

-- ============================================================================
-- PHASE 1: Add Temporary Columns
-- ============================================================================

-- NEWS: Add ENUM column (single value: public or private)
ALTER TABLE news
    ADD COLUMN visibility_enum ENUM('public', 'private') DEFAULT 'public'
AFTER visibility;

-- PAGES: Add SET column (multiple values possible)
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
-- PHASE 2: Migrate Data
-- ============================================================================

-- NEWS: JSON → ENUM
-- Converts ["public"] to 'public', anything else to 'private'
UPDATE news
SET visibility_enum = CASE
                          WHEN JSON_UNQUOTE(JSON_EXTRACT(visibility, '$[0]')) = 'public' THEN 'public'
                          ELSE 'private'
    END
WHERE visibility IS NOT NULL;

-- PAGES: JSON → SET
-- Handles all cases:
--   ["chief_editor,administrator"] → 'chief_editor,administrator'
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
-- PHASE 3: Verification (Run these queries to verify migration)
-- ============================================================================

-- Verify NEWS migration
SELECT id, visibility, visibility_enum FROM news LIMIT 20;

-- Verify PAGES migration
SELECT id, visibility, visibility_set FROM pages LIMIT 50;

-- Check for any NULL values (should be 0)
SELECT COUNT(*) FROM news WHERE visibility_enum IS NULL;
SELECT COUNT(*) FROM pages WHERE visibility_set IS NULL;

-- ============================================================================
-- PHASE 4: Cleanup (Only after all projects are migrated)
-- ============================================================================

-- WARNING: Only execute this phase when all projects using this database have been updated to use the new columns!

-- Remove old JSON columns
ALTER TABLE news DROP COLUMN visibility;
ALTER TABLE pages DROP COLUMN visibility;

-- Rename new columns to original names
ALTER TABLE news CHANGE visibility_enum visibility ENUM('public', 'private') NOT NULL DEFAULT 'public';
ALTER TABLE pages CHANGE visibility_set visibility SET('public','member','editor','chief_editor','administrator','secretary','webmaster','guest_editor') NOT NULL DEFAULT 'public';
