-- Instructions of a review request type (#319), shown to the invited person on the page of the answer without an
-- account; default instructions for the quick opinion.
-- Requires 2026-11-12-review-request-type.sql.
-- Created: 2026-11-13

SET NAMES utf8mb4;

ALTER TABLE `review_request_type`
    ADD COLUMN `instructions` json DEFAULT NULL COMMENT 'Instructions per language, e.g. {"fr": "...", "en": "..."}' AFTER `labels`;

UPDATE `review_request_type`
SET `instructions` = JSON_OBJECT(
    'fr', 'Quelques lignes sur l''intérêt, l''originalité et la pertinence de l''article pour la revue suffisent ; il ne s''agit pas d''un rapport de relecture détaillé.',
    'en', 'A few lines on the interest, originality and relevance of the article for the journal are enough; this is not a detailed review report.')
WHERE `code` = 'quick_opinion' AND `instructions` IS NULL;
