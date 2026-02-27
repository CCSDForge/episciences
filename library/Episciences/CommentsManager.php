<?php

use Episciences\AppRegistry;

class Episciences_CommentsManager
{
    // possible comment types
    public const TYPE_INFO_REQUEST = 0; // Request for clarification (Reviewer to author)
    // comment from contributor
    public const TYPE_INFO_ANSWER = 1; // Response for clarification (From author to reviewer)
    public const TYPE_REVISION_REQUEST = 2;
    public const TYPE_REVISION_ANSWER_COMMENT = 3;
    public const TYPE_AUTHOR_COMMENT = 4; // From Author (Cover letter)
    #git #320
    public const TYPE_REVISION_CONTACT_COMMENT = 5;
    public const TYPE_REVISION_ANSWER_TMP_VERSION = 6;
    public const TYPE_REVISION_ANSWER_NEW_VERSION = 7;
    // comment for editors
    public const TYPE_SUGGESTION_ACCEPTATION = 8;
    public const TYPE_SUGGESTION_REFUS = 9;
    public const TYPE_SUGGESTION_NEW_VERSION = 10;
    public const TYPE_CONTRIBUTOR_TO_REVIEWER = 11;
    public const TYPE_EDITOR_COMMENT = 12;
    // refus de gérer l'article
    public const TYPE_EDITOR_MONITORING_REFUSED = 13;
    public const TYPE_AUTHOR_SOURCES_DEPOSED_ANSWER = 14;
    public const TYPE_WAITING_FOR_AUTHOR_FORMATTING_REQUEST = 15;
    public const TYPE_AUTHOR_FORMATTING_ANSWER = 16;
    // invitation à déposer la version définitive
    public const TYPE_AUTHOR_FORMATTING_VALIDATED_REQUEST = 17;
    public const TYPE_REVIEW_FORMATTING_DEPOSED_REQUEST = 18;
    public const TYPE_CE_AUTHOR_FINAL_VERSION_SUBMITTED = 19;
    public const TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST = 20;
    public const TYPE_ACCEPTED_ASK_AUTHOR_VALIDATION = 21;
    // Author to assigned editors communication
    public const TYPE_AUTHOR_TO_EDITOR = 22;
    // Editor response to author message
    public const TYPE_EDITOR_TO_AUTHOR_RESPONSE = 23;

    public const COPY_EDITING_SOURCES = 'copy_editing_sources';
    public const TYPE_ANSWER_REQUEST = 'answerRequest';
    public static array $suggestionTypes = [
        self::TYPE_SUGGESTION_ACCEPTATION,
        self::TYPE_SUGGESTION_REFUS,
        self::TYPE_SUGGESTION_NEW_VERSION
    ];

    public static array $_typeLabel = [
        self::TYPE_INFO_REQUEST => "demande d'éclaircissements",
        self::TYPE_INFO_ANSWER => "réponse à une demande d'éclaircissements",
        self::TYPE_REVISION_REQUEST => "demande de modifications",
        self::TYPE_REVISION_ANSWER_COMMENT => "réponse à une demande de modifications (commentaire)",
        self::TYPE_REVISION_ANSWER_TMP_VERSION => "réponse à une demande de modifications (version temporaire)",
        self::TYPE_REVISION_ANSWER_NEW_VERSION => "réponse à une demande de modifications (nouvelle version)",
        self::TYPE_SUGGESTION_ACCEPTATION => "suggestion d'acceptation du papier",
        self::TYPE_SUGGESTION_REFUS => "suggestion de refus du papier",
        self::TYPE_SUGGESTION_NEW_VERSION => "suggestion de demande de modifications du papier",
        self::TYPE_EDITOR_MONITORING_REFUSED => "refus d'assurer le suivi",
        self::TYPE_EDITOR_COMMENT => "Commentaire du rédacteur",
        self::TYPE_AUTHOR_COMMENT => "Commentaire de l'auteur / lettre d'accompagnement",
        self::TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST => "Préparation de copie : en attente des sources auteurs",
        self::TYPE_AUTHOR_SOURCES_DEPOSED_ANSWER => "Préparation de copie : sources déposées",
        self::TYPE_WAITING_FOR_AUTHOR_FORMATTING_REQUEST => "Préparation de copie : en attente de la mise en forme par l'auteur",
        self::TYPE_AUTHOR_FORMATTING_ANSWER => 'Préparation de copie : version finale déposée, en attente de validation',
        self::TYPE_AUTHOR_FORMATTING_VALIDATED_REQUEST => 'Préparation de copie : la version formatée est validée, en attente de la version définitive',
        self::TYPE_REVIEW_FORMATTING_DEPOSED_REQUEST => 'Préparation de copie : la mise en forme par la revue est terminée, en attente de la version finale',
        self::TYPE_CE_AUTHOR_FINAL_VERSION_SUBMITTED => 'Préparation de copie : version finale soumise',
        self::TYPE_ACCEPTED_ASK_AUTHOR_VALIDATION => "Accepté - en attente de validation par l'auteur",
        self::TYPE_REVISION_CONTACT_COMMENT => "réponse à une demande de modifications (sans dépôt de version)",
        self::TYPE_AUTHOR_TO_EDITOR => "message de l'auteur aux rédacteurs assignés",
        self::TYPE_EDITOR_TO_AUTHOR_RESPONSE => "réponse du rédacteur à l'auteur",
        self::TYPE_CONTRIBUTOR_TO_REVIEWER => "commentaire du contributeur au relecteur",
    ];

    public static array $_copyEditingRequestTypes = [
        self::TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST,
        self::TYPE_WAITING_FOR_AUTHOR_FORMATTING_REQUEST,
        self::TYPE_AUTHOR_FORMATTING_VALIDATED_REQUEST,
        self::TYPE_REVIEW_FORMATTING_DEPOSED_REQUEST
    ];

    public static array $_copyEditingAnswerTypes = [
        self::TYPE_AUTHOR_SOURCES_DEPOSED_ANSWER,
        self::TYPE_AUTHOR_FORMATTING_ANSWER,
        self::TYPE_CE_AUTHOR_FINAL_VERSION_SUBMITTED
    ];

    // demander la version définitive si la mise en page est faite soit par la revue ou bien par l'auteur
    public static array $_copyEditingFinalVersionRequest = [
        self::TYPE_AUTHOR_FORMATTING_VALIDATED_REQUEST,
        self::TYPE_REVIEW_FORMATTING_DEPOSED_REQUEST,
        self::TYPE_ACCEPTED_ASK_AUTHOR_VALIDATION
    ];

    public static array $_UploadFilesRequest = [
        Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_FORMATTING_REQUEST,
        Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST
    ];

    /**
     * @param $parentId
     */
    public static function getParents($parentId, $settings = null): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        // fetch paper ids
        $select = $db->select()
            ->from(T_PAPERS)
            ->where('DOCID = ? ', $parentId)
            ->orWhere('PAPERID = ? ', $parentId)
            ->order('DOCID DESC');

        $papers = $db->fetchAssoc($select);
        $papersIds = array_keys($papers);

        $select = $db->select()->from(T_PAPER_COMMENTS)->where('DOCID IN (?)', $papersIds)->order('WHEN DESC');

        if (isset($settings['types']) && is_array($settings['types'])) {
            $select->where('TYPE IN (?)', $settings['types']);
        }

        if (isset($settings['type'])) {
            $select->where('TYPE = ?', $settings['type']);
        }

        // fetch reviewer alias
        $select->joinLeft(T_ALIAS,
            T_PAPER_COMMENTS . '.UID = ' . T_ALIAS . '.UID AND ' .
            T_PAPER_COMMENTS . '.DOCID = ' . T_ALIAS . '.DOCID',
            ['ALIAS']);

        $select->joinLeftUsing(T_USERS, 'UID');

        return $db->fetchAssoc($select);

    }

    public static function getList(int $docId, array $settings = [], bool $fetchReviewer = true): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = self::findByQuery($db, $docId);
        if (isset($settings['UID'])) {
            $select->where('UID = ? ', $settings['UID']);
        }

        // fetch unanswered comments
        if (isset($settings['unanswered'])) {
            self::fetchUnansweredComments($db, $docId, $select);
        }

        // fetch comments of given types
        if (isset($settings['types']) && is_array($settings['types'])) {
            $select->where('TYPE IN (?)', $settings['types']);
        }

        if (isset($settings['type'])) {
            $select->where('TYPE = ?', $settings['type']);
        }

        // exclude comments of given types
        if (isset($settings['excludeTypes'])) {
            foreach ($settings['excludeTypes'] as $typeId) {
                $select->where('TYPE != ?', $typeId);
            }
        }

        $select->order('WHEN DESC');

        if ($fetchReviewer) {
            self::fetchReviewersAlias($select);
        }

        $result = $db->fetchAssoc($select);

        if (!$result) {
            return [];
        }

        $result = array_filter($result, static function ($value): bool {

            $isEmptyCommentsAccepted = in_array((int)$value['TYPE'], self::$suggestionTypes, true);

            return
                $isEmptyCommentsAccepted ||
                ($value['MESSAGE'] ?? '') !== '' ||
                ($value['FILE'] ?? '') !== '';
        });

        // sort comment array
        return self::sortComments($result);
    }

    /**
     * @param $pcid
     * @return mixed
     */
    public static function getComment($pcid)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(T_PAPER_COMMENTS)->where('PCID = ? ', $pcid);
        $sql->joinLeft(
            T_ALIAS,
            T_PAPER_COMMENTS . '.UID = ' . T_ALIAS . '.UID AND ' .
            T_PAPER_COMMENTS . '.DOCID = ' . T_ALIAS . '.DOCID',
            ['ALIAS']);

        // if admin, get reviewer names
        if (Episciences_Auth::isSecretary()) {
            $sql->joinLeftUsing(T_USERS, 'UID');
        }

        return $db->fetchRow($sql);
    }

    /**
     * Comment form
     * @param string $name Form name (used for unique CSRF token)
     * @param bool $modal If true, submit button is not added
     * @param bool $withCancelButton If true, adds a cancel button (for author to editor form)
     * @param string|null $fileFieldSuffix Optional suffix for file field to ensure unique IDs (e.g., pcid for reply forms)
     * @return Ccsd_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getForm($name = '', $modal = false, $withCancelButton = false, $fileFieldSuffix = null): \Ccsd_Form
    {
        $form = new Ccsd_Form();

        if ($name !== '') {
            $form->setName($name);
        }

        // Use unique salt per form to support multiple forms on same page
        $csrfSalt = ($name !== '') ? 'csrf_' . $name : 'unique';
        $csrfElementName = ($name !== '') ? 'csrf_' . $name : 'no_csrf_foo';
        $form->addElement('hash', $csrfElementName, array('salt' => $csrfSalt));
        $form->getElement($csrfElementName)->setTimeout(3600);

        $form->setAttrib('enctype', 'multipart/form-data');
        $form->setAttrib('class', 'form-horizontal');
        $form->addElementPrefixPath('Episciences_Form_Decorator', 'Episciences/Form/Decorator/', 'decorator');

        // Use unique field names if suffix is provided (for reply forms with multiple forms on same page)
        $commentFieldName = ($fileFieldSuffix !== null) ? 'comment_' . $fileFieldSuffix : 'comment';
        $fileFieldName = ($fileFieldSuffix !== null) ? 'file_' . $fileFieldSuffix : 'file';

        $form->addElement('textarea', $commentFieldName, array(
            'label' => 'Commentaire',
            'description' => 'Les commentaires et fichiers associés sont stockés, envoyés et affichés ici.',
            'required' => true,
            'rows' => 5));

        $descriptions = self::getDescriptions();

        $form->addElement('file', $fileFieldName, array(
            'label' => 'Fichier',
            'description' => $descriptions['description'],
            'valueDisabled' => true,
            'maxFileSize' => MAX_FILE_SIZE,
            'validators' => array(
                'Count' => array(false, 1),
                'Extension' => array(false, $descriptions['extensions']),
                'Size' => array(false, MAX_FILE_SIZE))));

        if (!$modal) {
            // For author to editor form, use "Envoyer" button
            $buttonLabel = ($name === 'authorToEditorForm') ? 'Envoyer' : 'Enregistrer';

            $form->setActions(true)->createSubmitButton('postComment', array(
                'label' => $buttonLabel,
                'class' => 'btn btn-primary'));

            //  For author to editor form, use "cancel" button
            if ($withCancelButton) {
                $form->setActions(true)->createCancelButton('cancel', array(
                    'label' => 'Annuler',
                    'class' => 'btn btn-default'));
            }
        }

        return $form;
    }


    /**
     * Comment reply form (contributor to reviewer)
     * Reuses getForm() and clears elements to avoid code duplication (similar to getEditAuthorCommentForm)
     * @param $comments
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getReplyForms($comments): false|array
    {
        $forms = [];

        if (!$comments) {
            return false;
        }

        // Get translator for multilingual support
        try {
            $translator = Zend_Registry::isRegistered('Zend_Translate') ? Zend_Registry::get('Zend_Translate') : null;
        } catch (Exception) {
            $translator = null;
        }

        foreach ($comments as $id => $comment) {

            // Create form directly without using getForm() to avoid auto-population issues
            $form = new Ccsd_Form();
            $form->setName('replyForm_' . $id);
            $form->setMethod('post');
            $form->setAttrib('class', 'form-horizontal');
            $form->setAttrib('enctype', 'multipart/form-data');

            // Add CSRF protection with unique name
            $form->addElement('hash', 'csrf_token_' . $id, ['salt' => 'unique_' . $id]);
            $form->getElement('csrf_token_' . $id)->setTimeout(3600);

            // Add comment field with unique name
            $replyLabel = $translator ? $translator->translate('Répondre :') : 'Répondre :';
            $replyDescription = $translator ? $translator->translate('Votre réponse et les fichiers associés seront stockés, envoyés et affichés ici.') : 'Votre réponse et les fichiers associés seront stockés, envoyés et affichés ici.';

            $form->addElement('textarea', 'comment_' . $id, [
                'label' => $replyLabel,
                'description' => $replyDescription,
                'required' => true,
                'rows' => 5,
            ]);

            // Add file upload field with unique name
            $descriptions = self::getDescriptions();
            $form->addElement('file', 'file_' . $id, [
                'label' => 'Fichier',
                'description' => $descriptions['description'],
                'valueDisabled' => true,
                'maxFileSize' => MAX_FILE_SIZE,
                'validators' => [
                    'Count' => [false, 1],
                    'Extension' => [false, $descriptions['extensions']],
                    'Size' => [false, MAX_FILE_SIZE]
                ]
            ]);

            // Add submit button with unique name
            $form->setActions(true)->createSubmitButton('postReply_' . $id, [
                'label' => 'Envoyer',
                'class' => 'btn btn-primary'
            ]);

            // Add cancel button with proper JavaScript to hide the form
            $form->setActions(true)->createCancelButton('cancel_' . $id, [
                'label' => 'Annuler',
                'class' => 'btn btn-default cancel-reply-form',
                'data-reply-form-id' => 'reply-form-' . $id,
                'type' => 'button',
                'onclick' => "var form = document.getElementById('reply-form-{$id}'); var btn = document.querySelector('.toggle-reply-form[data-reply-form-id=\"reply-form-{$id}\"]'); if(form) form.style.display='none'; if(btn) btn.style.display='inline-block'; var textarea = form ? form.querySelector('textarea') : null; if(textarea) textarea.value=''; return false;"
            ]);

            $forms[$id] = $form;

        }

        return $forms;
    }

    /**
     * Revision request reply form (contributor to editor)
     * @throws Zend_Form_Exception
     */
    public static function answerRevisionForm(string $fromAnswerType = self::TYPE_ANSWER_REQUEST): \Ccsd_Form
    {
        $form = new Ccsd_Form();
        $form->setName('comment_only');

        $form->addPrefixPath('Episciences_Form_Element', 'Episciences/Form/Element/', Zend_Form::ELEMENT);
        $form->addPrefixPath('Episciences_Form_Decorator', 'Episciences/Form/Decorator/', 'decorator');

        $form->addElement(new Ccsd_Form_Element_Textarea([
            'id' => 'comment_only_' . $fromAnswerType,
            'name' => 'comment',
            'class' => 'form-control',
            'label' => 'Répondre :',
            'rows' => 5,
            'required' => true,
            'decorators' => ['Label', 'Description', 'Errors', 'ViewHelper']
        ]));


        $descriptions = self::getDescriptions();

        $form->addElement('file', 'file', [
                'label' => 'Fichier',
                'description' => $descriptions['description'],
                'valueDisabled' => true,
                'maxFileSize' => MAX_FILE_SIZE,
                'validators' => [
                    'Count' => [false, 1],
                    'Extension' => [false, $descriptions['extensions']],
                    'Size' => [false, MAX_FILE_SIZE]
                ]
            ]
        );

        return $form;
    }

    /**
     * Save a comment to db
     * @param $docId
     * @param $data
     * @param $type
     * @param bool $replyTo
     * @return bool|string
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_File_Transfer_Exception
     */
    public static function save($docId, array $data, $type, $replyTo = false, $file = null, $deadline = null)
    {
        // Input validation
        if (!is_numeric($docId) || (int)$docId <= 0) {
            trigger_error("Invalid docId: $docId", E_USER_WARNING);
            return false;
        }

        if (!isset($data['comment']) || !is_string($data['comment'])) {
            trigger_error("Invalid or missing comment data", E_USER_WARNING);
            return false;
        }

        // Validate replyTo (PARENTID) - must be false, null, or a positive integer
        $parentId = null;
        if ($replyTo !== false && $replyTo !== null) {
            if (!is_numeric($replyTo) || (int)$replyTo <= 0) {
                trigger_error("Invalid replyTo value: " . var_export($replyTo, true), E_USER_WARNING);
                return false;
            }
            $parentId = (int)$replyTo;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        // receive file and get its name
        $path = Episciences_PapersManager::buildDocumentPath($docId) . '/comments/';
        $uploads = Episciences_Tools::uploadFiles($path);
        if ($uploads) {
            $file = array_shift($uploads);
        }

        $values = ['DOCID' => (int)$docId, 'PARENTID' => $parentId, 'UID' => Episciences_Auth::getUid(), 'TYPE' => (int)$type, 'MESSAGE' => $data['comment'], 'FILE' => $file['name'] ?? $file, 'DEADLINE' => $deadline, 'WHEN' => new Zend_DB_Expr('NOW()')];

        if ($db->insert(T_PAPER_COMMENTS, $values)) {
            return $db->lastInsertId();
        }

        return false;
    }

    /**
     * Met à jour l'UID de l'utilisateur
     * @param int $oldUid : l'UID à supprimer
     * @param int $newUid : Nouvel UID
     * @return int: le nombre de lignes affectées
     * @throws Zend_Db_Adapter_Exception
     */

    public static function updateUid(int $oldUid = 0, int $newUid = 0): int
    {

        if ($oldUid <= 0 || $newUid <= 0) {
            return 0;
        }
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $data['UID'] = (int)$newUid;
        $where['UID = ?'] = (int)$oldUid;
        return $db->update(T_PAPER_COMMENTS, $data, $where);
    }

    /**
     * Retourne le formulaire d'edition de commentaire de l'auteur
     * @param array | null $values
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getEditAuthorCommentForm(array $values = null): \Ccsd_Form
    {
        $translator = Zend_Registry::get('Zend_Translate');
        $form = self::getForm();
        $form->clearElements();
        // Author's comments and Cover Letter
        // Keep in sync with paper views where these roles have access to the comments and cover letter
        foreach ([Episciences_Acl::ROLE_CHIEF_EDITOR_PLURAL, Episciences_Acl::ROLE_EDITOR_PLURAL, Episciences_Acl::ROLE_REVIEWER_PLURAL] as $roleAllowedToSee) {
            $allowedToSeeCoverLetterTranslated[] = $translator->translate($roleAllowedToSee);
        }
        $descriptionAllowedToSeeCoverLetterTranslated = $translator->translate('Visible par : ') . implode(', ', $allowedToSeeCoverLetterTranslated);
        $form->addElement('textarea', Episciences_Submit::COVER_LETTER_COMMENT_ELEMENT_NAME, [
            'label' => 'Commentaire', 'rows' => 5,
            'value' => $values['MESSAGE'] ?? '',
            'description' => $descriptionAllowedToSeeCoverLetterTranslated,
            'validators' => [['StringLength', false, ['max' => MAX_INPUT_TEXTAREA]]],
            'required' => !isset($values['MESSAGE'])
        ]);
        $group[] = Episciences_Submit::COVER_LETTER_COMMENT_ELEMENT_NAME;
        // Attached file
        $descriptions = self::getDescriptions();
        $description = $descriptions['description'];
        $description .= '.&nbsp;' . $descriptionAllowedToSeeCoverLetterTranslated;
        $form->addElement('file', Episciences_Submit::COVER_LETTER_FILE_ELEMENT_NAME, [
            'label' => "Lettre d'accompagnement",
            'description' => $description,
            'valueDisabled' => true,
            'validators' => [
                'Count' => [false, 1],
                'Extension' => [false, $descriptions['extensions']],
                'Size' => [false, MAX_FILE_SIZE]
            ]
        ]);

        $group[] = Episciences_Submit::COVER_LETTER_FILE_ELEMENT_NAME;
        if (isset($values['FILE'])) {
            $href = '<a href="/docfiles/comments/' . $values['DOCID'] . '/' . $values['FILE'] . '">' . $values['FILE'] . '</a>';

            $infos = $translator->translate('Ci-dessous votre ancienne lettre d’accompagnement, son remplacement est possible en joignant un nouveau fichier à votre commentaire.')
                . '<br>' . $translator->translate('Ces modifications seront prises en compte une fois le formulaire est validé.');

            $form->addElement('note', 'note_cover_letter', [
                'label' => $translator->translate('Note:'),
                'value' => $href,
                'description' => $infos,
            ]);

            $group[] = 'note_cover_letter';
        }

        $form->createCancelButton('exitComment', [
            'label' => 'Annuler',
            'class' => 'btn btn-default',
            'onclick' => 'history.back()'
        ]);
        return $form;

    }

    private static function getDescriptions(array $extensions = ALLOWED_EXTENSIONS): array
    {
        $descriptions = [];
        $implode_extensions = implode(',', $extensions);
        $description = Episciences_Tools::buildAttachedFilesDescription();
        $descriptions['description'] = $description;
        $descriptions['extensions'] = $implode_extensions;
        return $descriptions;
    }


    /**
     * Delete all comments of a paper by DOCID
     */
    public static function deleteByDocid(int $docid): bool
    {

        if ($docid < 1) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $db->delete(T_PAPER_COMMENTS, ['DOCID = ?' => $docid]);
        return true;
    }


    /**
     * Copy editing comment replay (contributor to copy editor)
     * @param $comments
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getCopyEditingReplyForms($comments, Episciences_Paper $paper, $zIdentifier = null): false|array
    {

        $forms = [];

        if (!$comments) {
            return false;
        }

        /** @var  array $comment */
        foreach ($comments as $id => $comment) {

            $commentType = (int)$comment['TYPE'];
            $commentUid = (int)$comment['UID'];

            if (in_array($commentType, self::$_copyEditingFinalVersionRequest, true)) {// load form

                $options = ['newVersionOf' => $paper->getDocid(), 'commentType' => $comment['TYPE']];

                if ($zIdentifier) {
                    $options['zIdentifier'] = $zIdentifier;
                }

                $form = Episciences_Submit::getNewVersionForm($paper, $options);
                $form->addElement('hidden', 'copyEditingNewVersion'); // distinguer la nouvelle version suite à une demande de révision de celle de travail éditorial
                $form->setAttrib('id', 'reply_with_new_version_' . $id);
                $form->setAction('/paper/savenewversion?docid=' . $comment['DOCID'] . '&pcid=' . $comment['PCID']);

            } else {

                $row = ($defaultMessage = self::buildAnswerMessage($commentUid, $commentType)) === '' || ($defaultMessage = self::buildAnswerMessage($commentUid, $commentType)) === '0' ? 5 : 7;
                $strElement = $id . '_element';
                $form = new Ccsd_Form();
                $form->setName('copy_editing_form_' . $strElement);
                $form->setAttrib('id', 'copy_editing_form_' . $strElement);
                $form->setAttrib('enctype', 'multipart/form-data');
                $form->addElementPrefixPath('Episciences_Form_Decorator', 'Episciences/Form/Decorator/', 'decorator');

                // Même nomenclature pour les noms des champs pour les commentaires (voir paperController::saveEpisciencesUserComment)
                $form->addElement('textarea', 'comment_' . $id, [
                    'id' => 'ce_answer_message' . $id,
                    'value' => $defaultMessage,
                    'label' => 'Répondre :',
                    'rows' => $row,
                    'required' => true
                ]);

                $form->addElement('hidden', 'request_comment_type' . $id, [
                    'id' => 'request_comment_type' . $id,
                    'value' => $comment['TYPE']
                ]);

                // Important : en cas d'ajout des elements hidden, l'élement ci-dessous doit être le dernier, sinon on écrasera
                // l'initialisation de certaines valeurs dans copy_editing_form.phtml
                $form->addElement('hidden', 'attachments_path_type_' . $id, [
                    'id' => 'attachments_path_type_' . $id,
                    'value' => 'ce_attachments',
                    'docId' => $comment['DOCID'],
                    'pcId' => $comment['PCID']
                ]);

                $form->setDecorators([[
                    'ViewScript', [
                        'viewScript' => '/paper/copy_editing_form.phtml'
                    ]],
                    $form->getDecorator('FormRequired'),
                ]);
            }

            $form->setAttrib('method', 'post');

            $forms[$id] = $form;

        }

        return $forms;
    }

    /**
     * @param $docId
     */
    private static function findByQuery(Zend_Db_Adapter_Abstract $db, int $docId): \Zend_Db_Select
    {
        return $db->select()
            ->from(T_PAPER_COMMENTS)
            ->where(T_PAPER_COMMENTS . '.DOCID = ? ', $docId)
            ->order('PCID DESC');
    }

    /**
     * @param $docId
     */
    private static function fetchUnansweredComments(Zend_Db_Adapter_Abstract $db, int $docId, Zend_Db_Select $select): \Zend_Db_Select
    {

        $subQuery = $db->select()
            ->from(T_PAPER_COMMENTS, 'PARENTID')
            ->where(T_PAPER_COMMENTS . '.DOCID = ?', $docId)
            ->where('PARENTID IS NOT NULL');
        $select->where('PCID NOT IN ?', $subQuery);
        $select->where('PARENTID IS NULL');
        return $select;
    }

    /**
     * fetch reviewer alias
     */
    private static function fetchReviewersAlias(Zend_Db_Select $select): \Zend_Db_Select
    {

        $select->joinLeft(T_ALIAS,
            T_PAPER_COMMENTS . '.UID = ' . T_ALIAS . '.UID AND ' .
            T_PAPER_COMMENTS . '.DOCID = ' . T_ALIAS . '.DOCID',
            ['ALIAS']);
        // fetch reviewer names
        $select->joinLeftUsing(T_USERS, 'UID');
        return $select;
    }

    /**
     * sort comments
     */
    private static function sortComments(array $result): array
    {

        $comments = [];
        $replies = [];

        foreach ($result as $id => $comment) {
            if (!$comment['PARENTID']) {
                $comments[$id] = $comment;
            } else {
                $replies[$id] = $comment;
            }
        }
        foreach ($replies as $id => $reply) {
            $comments[$reply['PARENTID']]['replies'][$id] = $reply;
        }
        return $comments;
    }

    /**
     * @throws Zend_Exception
     */
    private static function buildAnswerMessage(int $commentType): string
    {
        $defaultMessage = '';
        if (in_array($commentType, self::$_UploadFilesRequest)) {
            $translator = Zend_Registry::get('Zend_Translate');
            // Utiliser la langue de l'utilisateur connecté (celui qui répond) au lieu de celle du destinataire
            $currentUser = Episciences_Auth::getInstance()->getIdentity();
            $locale = $currentUser ? $currentUser->getLangueid(true) : $translator->getLocale();
            $defaultMessage .= $translator->translate('Bonjour', $locale);
            $defaultMessage .= ',';
            $defaultMessage .= PHP_EOL;
            $defaultMessage .= PHP_EOL;

            if (self::TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST == $commentType) {

                $defaultMessage .= $translator->translate('Veuillez trouver ci-joint mes fichiers sources pour la mise en forme de l’article', $locale);

            } elseif (self::TYPE_WAITING_FOR_AUTHOR_FORMATTING_REQUEST == $commentType) {

                $defaultMessage .= $translator->translate('Veuillez trouver ci-joint la version formatée de mon article', $locale);
            }

            $defaultMessage .= '.';
            $defaultMessage .= PHP_EOL;
            $defaultMessage .= $translator->translate('Merci', $locale);
            $defaultMessage .= ',';
            $defaultMessage .= PHP_EOL;
            $defaultMessage .= PHP_EOL;
            $defaultMessage .= $translator->translate('Mes meilleures salutations', $locale);

        }

        return $defaultMessage;

    }

    public static function getRevisionRequests(int $docId, array $settings = ['types' => [Episciences_CommentsManager::TYPE_REVISION_REQUEST]]): array
    {
        return self::getList($docId, $settings);

    }

    /**
     * Save author comment and attached file
     * @param bool $ignoreUpload // [true] the attached file has already been uploaded
     */
    public static function saveCoverLetter(Episciences_Paper $paper, array | Episciences_Comment $coverLetter = ["message" => '', "attachedFile" => null], bool $ignoreUpload = false): bool | Episciences_Comment
    {

        if ($coverLetter instanceof Episciences_Comment) {
            $authorComment = $coverLetter;

        } else {
            $authorComment = new Episciences_Comment();
            $authorComment->setMessage($coverLetter["message"]);
            $authorComment->setFile($coverLetter["attachedFile"]);
            $authorComment->setType(self::TYPE_AUTHOR_COMMENT);
            $authorComment->setDocid($paper->getDocid());
        }

        $authorComment->setFilePath(REVIEW_FILES_PATH . $paper->getDocid() . '/comments/');


        if (!$authorComment->getFile() && in_array(trim($authorComment->getMessage()), ['', '0'], true)) { //Avoid inserting an empty row
            return $authorComment;
        }


        if (!$authorComment->save(false, null, $ignoreUpload)) {
            AppRegistry::getMonoLogger()?->warning(sprintf('Failed to save cover letter for document #%s', $paper->getDocid()));
            return false;
        }

        return $authorComment;
    }

    /**
     * Remove comment
     */
    public static function deleteByIdentifier(int $identifier): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return ($db->delete(T_PAPER_COMMENTS, ['PCID = ?' => $identifier]) > 0);
    }


    public static function fetchReviewerAuthorCommentsByDocId(int $docId): array
    {

        $settings = ['types' => [
            self::TYPE_INFO_REQUEST,
            self::TYPE_INFO_ANSWER,
            self::TYPE_CONTRIBUTOR_TO_REVIEWER
        ]];

        return self::getList($docId, $settings);

    }

}