

INSERT INTO pages (code, uid, date_creation, date_updated, title, content, visibility, page_code)
VALUES (
           'epijinfo',  -- Replace with actual journal code (rvcode)
           1495020,  -- Replace with actual user ID
           NOW(),
           NOW(),
           JSON_OBJECT(
                   'en', 'For reviewers',
                   'fr', 'Pour les relecteurs'
           ),
           JSON_OBJECT(
                   'en', '# For reviewers\n\nContent to be added.',
                   'fr', '# Pour les relecteurs \n\nContenu à ajouter.'
           ),
           JSON_ARRAY('public'),
           'for-reviewers'
       );

INSERT INTO pages (code, uid, date_creation, date_updated, title, content, visibility, page_code)
VALUES (
           'epijinfo',  -- Replace with actual journal code (rvcode)
           1495020,  -- Replace with actual user ID
           NOW(),
           NOW(),
           JSON_OBJECT(
                   'en', 'For conference organisers',
                   'fr', 'Pour les organisateurs de conférence'
           ),
           JSON_OBJECT(
                   'en', '# For conference organisers\n\nContent to be added.',
                   'fr', '# Pour les organisateurs de conférence\n\nContenu à ajouter.'
           ),
           JSON_ARRAY('public'),
           'for-conference-organisers'
       );