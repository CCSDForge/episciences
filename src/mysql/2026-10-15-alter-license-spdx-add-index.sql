-- Add an index on the `recommended` column of the `license_spdx` table
-- Used to speed up queries filtering recommended licenses
-- Modified: 2026-10-15

ALTER TABLE `license_spdx` ADD INDEX (`recommended`);
