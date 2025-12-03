-- Script to insert the page codes for the pages introduction-board, reviewers-board, and operating-charter-board into the pages table
-- This creates template entries for the introduction-board, reviewers-board, and operating-charter-board pages

INSERT INTO pages (code, uid, date_creation, date_updated, title, content, visibility, page_code)
VALUES (
           'dev',  -- Replace with actual journal code (rvcode)
           666,  -- Replace with actual user ID
           NOW(),
           NOW(),
           JSON_OBJECT(
                   'en', 'Boards presentation',
                   'fr', 'Présentation des comités'
           ),
           JSON_OBJECT(
                   'en', '# Boards presentation\n\nContent to be added.',
                   'fr', '# Présentation des comités\n\nContenu à ajouter.'
           ),
           JSON_ARRAY('public'),
           'introduction-board'
       );

INSERT INTO pages (code, uid, date_creation, date_updated, title, content, visibility, page_code)
VALUES (
           'dev',  -- Replace with actual journal code (rvcode)
           666,  -- Replace with actual user ID
           NOW(),
           NOW(),
           JSON_OBJECT(
                   'en', 'Reviewers',
                   'fr', 'Comité de relecture'
           ),
           JSON_OBJECT(
                   'en', '# Reviewers\n\nContent to be added.',
                   'fr', '# Comité de relecture\n\nContenu à ajouter.'
           ),
           JSON_ARRAY('public'),
           'reviewers-board'
       );

INSERT INTO pages (code, uid, date_creation, date_updated, title, content, visibility, page_code)
VALUES (
           'dev',  -- Replace with actual journal code (rvcode)
           666,  -- Replace with actual user ID
           NOW(),
           NOW(),
           JSON_OBJECT(
                   'en', 'Operating Charter',
                   'fr', 'Charte de fonctionnement'
           ),
           JSON_OBJECT(
                   'en', '# Operating Charter\n\nContent to be added.',
                   'fr', '# Charte de fonctionnement\n\nContenu à ajouter.'
           ),
           JSON_ARRAY('public'),
           'operating-charter-board'
       );