-- Script to insert the journal-acknowledgments page code into the pages table
-- This creates a template entry for the Acknowledgments page


INSERT INTO pages (code, uid, date_creation, date_updated, title, content, visibility, page_code)
VALUES (
    'dev',  -- Replace with actual journal code (rvcode)
    666,  -- Replace with actual user ID
    NOW(),
    NOW(),
    JSON_OBJECT(
        'en', 'Acknowledgments',
        'fr', 'Remerciements'
    ),
    JSON_OBJECT(
        'en', '# Acknowledgments\n\nContent to be added.',
        'fr', '# Remerciements\n\nContenu à ajouter.'
    ),
    JSON_ARRAY('public'),
    'journal-acknowledgments'
);