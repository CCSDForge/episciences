<?php

class Episciences_Submit
{
    protected $_db = null;

    public function __construct()
    {
        $this->_db = Zend_Db_Table_Abstract::getDefaultAdapter();
    }

    /**
     * @param array $settings
     * @param null $defaults
     * @return Ccsd_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */

    public static function getForm($settings = [], $defaults = null)
    {
        $translator = Zend_Registry::get('Zend_Translate');
        $review = Episciences_ReviewsManager::find(RVID);
        $review->loadSettings();

        $form = new Ccsd_Form();
        $form->setAttrib('enctype', 'multipart/form-data'); // Possibilite de joindre un fichier
        $form->addElementPrefixPath('Episciences_Form_Decorator', 'Episciences/Form/Decorator/', 'decorator');
        $form->setName('submit_doc');
        $form->setAttrib('class', 'form-horizontal');
        $form->getDecorator('FormRequired')->setOption('style', 'margin-top: 20px; margin-bottom: 20px;');
        $form->getDecorator('FormRequired')->setOption('id', 'form_required'); // (voir js/submit/function.js)

        // Recherche du document (subform) **************************************************************************
        $subform = new Ccsd_Form_SubForm();


        // Récupération des repositories
        if (array_key_exists('repositories', $settings) && !empty($settings['repositories'])) {
            $repositories = $settings['repositories'];
        } else {
            $repositories = array_keys(Episciences_Repositories::getRepositories());
            unset($repositories[0]);
        }

        $options = [];

        foreach ($repositories as $repoId) {
            $options[$repoId] = Episciences_Repositories::getLabel($repoId);
        }

        // Select: repositories
        $subform->addElement('select', 'repoId', [
            'label' => 'Archive',
            'multiOptions' => $options,
            'style' => 'width:auto;',
        ]);


        // Champ texte : identifiant du document
        $subform->addElement('text', 'docId', [
            'label' => 'Identifiant du document',
            'required' => true,
            'description' => $translator->translate("Saisir l'identifiant du document") . '.',
            'style' => 'width:auto;',
        ]);

        // Champ texte : version du document
        $subform->addElement('text', 'version', [
            'label' => 'Version',
            'required' => true,
            'description' => $translator->translate("Saisir la version du document (nombre uniquement)."),
            'value' => '',
            'style' => 'width:17%;']);


        // Bouton : Rechercher
        $subform->addElement('button', 'getPaper', [
            'label' => 'Rechercher',
            'class' => 'btn btn-primary',
            'style' => 'width:auto;',
            'decorators' => [
                ['ViewHelper', ['class' => 'form-control input']],
                ['HtmlTag', ['tag' => 'div', 'class' => "col-md-9 col-md-offset-3"]]
            ]
        ]);

        $form->addSubForm($subform, 'search_doc');
        $form->getSubForm('search_doc')->setDecorators([
            'FormElements',
            [['wrapper2' => 'HtmlTag'], ['tag' => 'div', 'class' => 'panel-body']],
            [['wrapper1' => 'HtmlTag'], ['tag' => 'div', 'class' => 'panel panel-default', 'id' => 'searchForm']],
        ]);


        // Formulaire de soumission du document ***********************************************************************
        $group = [];
        $xml = new Zend_Form_Element_Hidden('xml');
        $xml->setDecorators(['ViewHelper']);
        $form->addElement($xml);
        $group[] = 'xml';

        // Si il s'agit d'un nouveau document (et non une nouvelle version)
        if (!array_key_exists('newVersionOf', $settings)) {

            // Code pour soumission dans un volume spécial (si la revue l'autorise) *******
            if (array_key_exists(Episciences_Review::SETTING_SPECIAL_ISSUE_ACCESS_CODE, $settings) && $settings[Episciences_Review::SETTING_SPECIAL_ISSUE_ACCESS_CODE] == 1) {

                $submit_code = '<div class="input-group-btn"><span id="submit-code" style="margin-left: 5px;"></span></div>';
                // Code d'accès au volume spécial
                $form->addElement('text', 'specialIssueAccessCode', [
                    'label' => 'Code pour soumission dans un volume spécial :',
                    'decorators' => [
                        ['ViewHelper', ['class' => 'form-control input-sm']],
                        [['submit-code' => 'Html'],
                            ['html' => $submit_code, 'placement' => 'append']],
                        'Errors',
                        ['Description', ['tag' => 'span', 'class' => 'help-block']],
                        ['HtmlTag', ['tag' => 'div', 'class' => "col-md-9 input-group", 'style' => 'width: 33%']],
                        ['Label', ['tag' => 'label', 'class' => "col-md-3 control-label"]]
                    ]]);
                $group[] = 'specialIssueAccessCode';
            }

            // Choix du volume par l'auteur (si la revue l'autorise) *******
            if (array_key_exists(Episciences_Review::SETTING_CAN_CHOOSE_VOLUME, $settings) && $settings[Episciences_Review::SETTING_CAN_CHOOSE_VOLUME] == 1) {
                // Récupération des volumes
                $options = $review->getVolumesOptions();

                if (!empty(array_slice($options, 1))) {
                    // Volume dans lequel déposer l'article (select)
                    $form->addElement('select', 'volumes', [
                        'label' => 'Proposer pour le volume :',
                        'multiOptions' => $options,
                        'style' => 'width:33%']);
                    /** @var Zend_Form_Element_Select $volumeElement */
                    $volumeElement = $form->getElement('volumes');
                    $volumeElement->setRegisterInArrayValidator(false);
                    $group[] = 'volumes';
                }
            }

            // En fonction des parametres de la revue , l'auteur pourrait choisir la section
            if (array_key_exists(Episciences_Review::SETTING_CAN_PICK_SECTION, $settings) && $settings[Episciences_Review::SETTING_CAN_PICK_SECTION] > 0) {
                // Choix de la rubrique par l'auteur ********
                // Récupération des rubriques
                $sections = Episciences_SectionsManager::getList();
                $options = ['Hors rubrique'];
                /** @var Episciences_Section $oSection */
                foreach ($sections as $oSection) {
                    $oSection->loadSettings();
                    if ($oSection->getSetting('status') == 1) {
                        $options[$oSection->getSid()] = $oSection->getNameKey();
                    }
                }
                if (!empty($sections)) {
                    // Rubrique dans laquelle déposer l'article (select)
                    $elementOptions = [
                        'label' => 'Proposer dans la rubrique :',
                        'multiOptions' => $options,
                        'style' => 'width:33%'
                    ];

                    if ($review->getSetting(Episciences_Review::SETTING_CAN_PICK_SECTION) == 2) {
                        $elementOptions['required'] = true;
                    }

                    $form->addElement('select', 'sections', $elementOptions);
                    $group[] = 'sections';
                }
            }

            // Choix des relecteurs par l'auteur (si la revue l'autorise) ********
            if (array_key_exists('canSuggestReviewers', $settings) && $settings['canSuggestReviewers']) {

                $form->addElement('multiTextSimple', 'suggestReviewers', [
                    'style' => 'width: 33%',
                    'label' => 'Je souhaite être relu par : ',
                    'display' => 'advanced'
                ]);
                $group[] = 'suggestReviewers';
            }


            // Relecteurs non désirés par l'auteur (si la revue l'autorise) ********
            if (array_key_exists('canSpecifyUnwantedReviewers', $settings) && $settings['canSpecifyUnwantedReviewers']) {

                $form->addElement('multiTextSimple', 'unwantedReviewers', [
                    'label' => 'Je ne souhaite pas être relu par : ',
                    'display' => 'advanced'
                ]);
                $group[] = 'unwantedReviewers';

            }

            // Choix des rédacteurs par l'auteur (si la revue l'autorise)
            if (array_key_exists('canPickEditors', $settings) && $settings['canPickEditors'] > 0) {
                // Récupération et tri des valeurs du select
                $options = [];
                $users = Episciences_UsersManager::getUsersWithRoles([Episciences_Acl::ROLE_CHIEF_EDITOR, Episciences_Acl::ROLE_EDITOR]);
                /* @var  $user Episciences_User */
                foreach ($users as $uid => $user) {
                    // Liste des rédacteurs et rédacteurs en chef (on filtre root, ainsi que le compte connecté)
                    if ($uid != Episciences_Auth::getUid() && $uid != 1) {
                        $options[$uid] = Ccsd_Tools::formatUser($user->getLastname(), $user->getFirstname());
                    }
                }
                asort($options);
                foreach ($options as $uid => &$option) {
                    /** @var Episciences_User $user */
                    $user = $users[$uid];
                    $option = $user->getFullName();
                }

                // Select
                if ($options) {

                    $editorsElementType = 'multiselect';

                    $editorsAttribs = [
                        'label' => 'Je souhaite que mon article soit supervisé par : ',
                        'attribs' => ['multiple' => 'multiple'],
                        'multiOptions' => $options,
                        'required' => (int)$settings['canPickEditors'] > 1
                    ];

                    if ($settings['canPickEditors'] == 3) {
                        $editorsElementType = 'select';
                        unset($editorsAttribs['attribs']);
                    }

                    $form->addElement($editorsElementType, 'suggestEditors', $editorsAttribs);


                }

                $group[] = 'suggestEditors';
            }

        }

        // Author's comments and Cover Letter
        // Keep in sync with paper views where these roles have access to the comments and cover letter
        $allowedToSeeCoverLetterTranslated = [];

        foreach ([Episciences_Acl::ROLE_CHIEF_EDITOR_PLURAL, Episciences_Acl::ROLE_EDITOR_PLURAL, Episciences_Acl::ROLE_REVIEWER_PLURAL] as $roleAllowedToSee) {
            $allowedToSeeCoverLetterTranslated[] = Zend_Registry::get('Zend_Translate')->translate($roleAllowedToSee);
        }

        $descriptionAllowedToSeeCoverLetterTranslated = Zend_Registry::get('Zend_Translate')->translate('Visible par : ') . implode(', ', $allowedToSeeCoverLetterTranslated);


        $form->addElement('textarea', 'author_comment', [
            'label' => 'Commentaire', 'rows' => 5,
            'description' => $descriptionAllowedToSeeCoverLetterTranslated,
            'validators' => [['StringLength', false, ['max' => MAX_INPUT_TEXTAREA]]]
        ]);
        $group[] = 'author_comment';

        // Attached file
        $extensions = ALLOWED_EXTENSIONS;
        $implode_extensions = implode(',', $extensions);
        $description = Episciences_Tools::buildAttachedFilesDescription($extensions, '.&nbsp;' . $descriptionAllowedToSeeCoverLetterTranslated);

        $form->addElement('file', 'file_comment_author', [
            'label' => "Lettre d'accompagnement",
            'description' => $description,
            'valueDisabled' => true,
            'maxFileSize' => MAX_FILE_SIZE,
            'validators' => [
                'Count' => [false, 1],
                'Extension' => [false, $implode_extensions],
                'Size' => [false, MAX_FILE_SIZE]
            ]
        ]);
        $group[] = 'file_comment_author';

        $form->addElement('checkbox', 'disclaimer1', [
            'required' => true,
            'uncheckedValue' => null,
            'label' => "Je certifie être l'auteur de cet article, ou être mandaté par l'un des auteurs",
            'belongsTo' => "disclaimers",
            'decorators' => [
                'ViewHelper',
                ['Label', ['placement' => 'APPEND']],
                ['HtmlTag', ['tag' => 'div', 'class' => 'col-md-9 col-md-offset-3']],
                ['Errors', ['placement' => 'APPEND']]
            ]
        ]);
        $group[] = 'disclaimer1';


        $form->addElement('checkbox', 'disclaimer2', [
            'required' => true,
            'uncheckedValue' => null,
            'label' => "Je certifie ne pas avoir publié/soumis cet article ailleurs",
            'belongsTo' => "disclaimers",
            'decorators' => [
                'ViewHelper',
                ['Label', ['placement' => 'APPEND']],
                ['HtmlTag', ['tag' => 'div', 'class' => 'col-md-9 col-md-offset-3']],
                ['Errors', ['placement' => 'APPEND']]
            ]
        ]);
        $group[] = 'disclaimer2';

        $form->addElement('button', 'searchAgain', [
            'label' => 'Annuler',
            'class' => 'btn btn-default',
            'decorators' => [
                ['ViewHelper', ['class' => 'form-control input']],
                ['HtmlTag', ['tag' => 'div', 'class' => "col-md-2 col-md-offset-3", 'style' => 'margin-top: 20px; margin-bottom: 20px']]
            ]
        ]);
        $group[] = 'searchAgain';


        $form->addElement('button', 'submitPaper', [
            'type' => 'submit',
            'label' => 'Proposer cet article',
            'disabled' => true,
            'aria-disabled' => true,
            'class' => 'btn btn-primary',
            'decorators' => [
                ['ViewHelper', ['class' => 'form-control input']],
                ['HtmlTag', ['tag' => 'div', 'class' => "col-md-3", 'style' => 'margin-top: 20px; margin-bottom: 20px']]
            ]
        ]);
        $group[] = 'submitPaper';


        $form->addDisplayGroup($group, 'submitDoc');
        $form->getDisplayGroup('submitDoc')->setDecorators([
            'FormElements',
            [['wrapper2' => 'HtmlTag'], ['tag' => 'div', 'class' => 'panel-body']],
            [['wrapper1' => 'HtmlTag'], ['tag' => 'div', 'class' => 'panel panel-default', 'style' => 'display: none', 'id' => 'submitForm']]
        ]);


        if ($defaults) {
            $form->setDefaults($defaults);
        }


        return $form;
    }

    /**
     * Retourne le formulaire de réponse avec une version temporaire (réponse à une demande de modification)
     * @param Episciences_Comment|null $comment
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     */
    public static function getTmpVersionForm(Episciences_Comment $comment = null)
    {
        $form = new Ccsd_Form;
        $form->setAttrib('enctype', 'multipart/form-data');
        $form->setAttrib('class', 'form-horizontal');
        $docId = $comment->getDocid();
        $paper = Episciences_PapersManager::get($docId);
        $paperId = ($paper->getPaperid()) ? $paper->getPaperid() : $docId;

        if($comment){
            $id = $comment->getPcid();
            $form->addElement('hidden', 'attachments_path_type_' . $id, [
                'id' => 'attachments_path_type_' . $id,
                'value' => 'tmp_attachments',
                'docId' => $docId,
                'paperId' => $paperId,
                'pcId' => $id
            ]);
        }

        // attachments
        $form->setDecorators([[
            'ViewScript', [
                'viewScript' => '/paper/attachments.phtml'
            ]],
            $form->getDecorator('FormRequired'),
        ]);
        // comment
        $form->addElement('textarea', 'comment', ['label' => 'Commentaire', 'rows' => 5]);

        return $form;
    }

    /**
     * save a temporary version of a paper
     * @param Episciences_Paper $paper
     * @return mixed
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_File_Transfer_Exception
     */
    public static function saveTmpVersion(Episciences_Paper $paper)
    {
        $result['code'] = 0;
        $result['docId'] = null;

        $paperId = $paper->getPaperid();
        if (!$paperId) {
            $paperId = $paper->getDocid();
        }

        // Enregistrement du fichier PDF
        $uploads = Episciences_Tools::uploadFiles(REVIEW_FILES_PATH . $paperId . '/tmp/');
        if ($uploads) {
            $file = array_shift($uploads);
        } else {
            $file = null;
        }

        // Préparation de l'insertion en BDD
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $values['PAPERID'] = $paperId;
        $values['RVID'] = $paper->getRvid();
        $values['VID'] = $paper->getVid();
        $values['UID'] = $paper->getUid();
        $values['STATUS'] = Episciences_Paper::STATUS_BEING_REVIEWED;
        $values['IDENTIFIER'] = $paperId . '/' . $file;
        $values['VERSION'] = (float)$paper->getVersion() + 0.01;
        $values['REPOID'] = 0;
        $values['WHEN'] = new Zend_DB_Expr('NOW()');

        // Modification de l'enregistrement XML
        $xml = $paper->getRecord();
        $record = simplexml_load_string($xml);
        $record->header->version = $values['VERSION'];
        $record->header->docURL = Episciences_Repositories::getDocUrl(0, $values['IDENTIFIER']);
        $record->header->paperURL = Episciences_Repositories::getPaperUrl(0, $values['IDENTIFIER']);
        $xml = $record->asXML();
        $values['RECORD'] = $xml;

        // Enregistrement en BDD des infos de la version temporaire
        if ($db->insert(T_PAPERS, $values)) {

            $result['code'] = 1;
            $result['message'] = "La version temporaire a bien été enregistrée";
            $result['docId'] = $db->lastInsertId();

            // Les versions précédentes deviennent obsolètes
            $values = array('STATUS' => Episciences_Paper::STATUS_OBSOLETE);
            $where = array('DOCID != ?' => $result['docId'], 'PAPERID = ?' => $paperId);
            $db->update(T_PAPERS, $values, $where);

        } else {
            $result['message'] = "La version temporaire n'a pas pu être enregistrée";
        }

        return $result;
    }

    /**
     * Retourne le formulaire permettant la soumission de la nouvelle version
     * @param null $settings
     * @param null $defaults
     * @return Ccsd_Form|null
     */
    public static function getNewVersionForm($settings = null, $defaults = null)
    {
        $options = [];
        try {
            $form = new Ccsd_Form();
            $form->setName('submit_doc');
            $form->setAttrib('class', 'form-horizontal');
            $form->getDecorator('FormRequired')->setOption('style', 'margin-top: 20px; margin-bottom: 20px;');
            $form->getDecorator('FormRequired')->setOption('id', 'form_required'); // (voir js/submit/function.js)


            $subform = new Ccsd_Form_SubForm();

            // Champ texte : identifiant du document
            $subform->addElement('text', 'docId', array(
                'label' => 'Identifiant du document',
                'disabled' => true,
                'required' => true,
                'placeholder' => Zend_Registry::get('Zend_Translate')->translate("Saisir l'identifiant du document"),
                'style' => 'width:33%;',
            ));

            $subform->addElement('hidden', 'h_docId');

            // Champ texte : version du document
            $subform->addElement('text', 'version', [
                'label' => 'Version',
                'description' => "Veuillez indiquer la version du document (nombre uniquement).",
                'value' => '1',
                'required' => true,
                'style' => 'width:33%']
            );

            // Récupération des repositories
            if (array_key_exists('repositories', $settings) && !empty($settings['repositories'])) {
                $repositories = $settings['repositories'];
            } else {
                $repositories = array_keys(Episciences_Repositories::getRepositories());
                unset($repositories[0]);
            }
            foreach ($repositories as $repoId) {
                $options[$repoId] = Episciences_Repositories::getLabel($repoId);
            }

            // Select: repositories
            $subform->addElement('select', 'repoId', array(
                'label' => 'Archive',
                'disabled' => true,
                'multiOptions' => $options,
                'style' => 'width:33%;',
            ));

            $subform->addElement('hidden', 'h_repoId');

            // Pour gérer la recherche d'un document lors de la soumission d'une nouvelle version suite à une demande de modification
            if (array_key_exists('newVersionOf', $settings)) {
                $subform->addElement('hidden', 'newVersionOf', ['value' => $settings['newVersionOf']]);

                // Soumission d'un nouvelle version suite à une demande de modifications de la version temporaire
                $paper = Episciences_PapersManager::get($settings['newVersionOf']);

                if ($paper->getRepoid() == 0 &&
                    explode('/', $paper->getIdentifier()[0] == $paper->getPaperid()) &&
                    ($paper->getStatus() == Episciences_Paper::STATUS_WAITING_FOR_MINOR_REVISION ||
                        $paper->getStatus() == Episciences_Paper::STATUS_WAITING_FOR_MAJOR_REVISION
                    )
                ) {
                    $lastPaper = Episciences_PapersManager::getLastPaper($paper->getPaperid());
                    if ($lastPaper) { //  //#git 259 : Laisser le champ version vide quand on en soumet une nouvelle (requête : demander la version définitive)
                        $tmp_defaults ['docId'] = $lastPaper->getIdentifier();
                        $tmp_defaults['repoId'] = $lastPaper->getRepoid();
                        $tmp_defaults['version'] = '';
                        $tmp_defaults['h_docId'] = $tmp_defaults['docId'];
                        $tmp_defaults['h_repoId'] = $tmp_defaults['repoId'];
                        $subform->setDefaults($tmp_defaults);
                    }
                }

            }

            // Bouton : Rechercher
            $subform->addElement('button', 'getPaper', array(
                'label' => 'Rechercher',
                'class' => 'btn btn-default',
                'style' => 'width:33%',
                'decorators' => array(
                    array('ViewHelper', array('class' => 'form-control input-sm')),
                    array('HtmlTag', array('tag' => 'div', 'class' => "col-md-9 col-md-offset-3"))
                )
            ));

            $form->addSubForm($subform, 'search_doc');
            $form->getSubForm('search_doc')->setDecorators(array(
                'FormElements',
                array(array('wrapper2' => 'HtmlTag'), array('tag' => 'div', 'class' => 'panel-body')),
                array(array('wrapper1' => 'HtmlTag'), array('tag' => 'div', 'class' => 'panel panel-default', 'id' => 'searchForm')),
            ));


            // Formulaire de soumission du document
            $group = array();
            $xml = new Zend_Form_Element_Hidden('xml');
            $xml->setDecorators(array('ViewHelper'));
            $form->addElement($xml);
            $group[] = 'xml';

            // Author's comments and Cover Letter [new version]
            // Keep in sync with paper views where these roles have access to the comments and cover letter
            $allowedToSeeCoverLetterTranslated = [];
            foreach ([Episciences_Acl::ROLE_CHIEF_EDITOR_PLURAL, Episciences_Acl::ROLE_EDITOR_PLURAL, Episciences_Acl::ROLE_REVIEWER_PLURAL] as $roleAllowedToSee) {
                $allowedToSeeCoverLetterTranslated[] = Zend_Registry::get('Zend_Translate')->translate($roleAllowedToSee);
            }

            $descriptionAllowedToSeeCoverLetterTranslated = Zend_Registry::get('Zend_Translate')->translate('Visible par : ') . implode(', ', $allowedToSeeCoverLetterTranslated);

            $form->addElement('textarea', 'new_author_comment', [
                'label' => 'Commentaire', 'rows' => 5,
                'decscription' => $descriptionAllowedToSeeCoverLetterTranslated,
                'validators' => [[
                    'StringLength', false, ['max' => MAX_INPUT_TEXTAREA]
                ]]
            ]);
            $group[] = 'new_author_comment';


            // Attached file [new version]
            $extensions = ALLOWED_EXTENSIONS;
            $implode_extensions = implode(',', $extensions);
            $description = Episciences_Tools::buildAttachedFilesDescription($extensions,'.&nbsp;' . $descriptionAllowedToSeeCoverLetterTranslated);
            $form->addElement('file', 'file_new_version_comment_author', array(
                'label' => "Lettre d'accompagnement",
                'description' => $description,
                'valueDisabled' => true,
                'maxFileSize' => MAX_FILE_SIZE,
                'validators' => array(
                    'Count' => array(false, 1),
                    'Extension' => array(false, $implode_extensions),
                    'Size' => array(false, MAX_FILE_SIZE)
                )
            ));
            $group[] = 'file_new_version_comment_author';


            $form->addElement('checkbox', 'disclaimer1', array(
                'required' => true,
                'uncheckedValue' => null,
                'label' => "Je certifie être l'auteur de cet article, ou être mandaté par l'un des auteurs",
                'belongsTo' => "disclaimers",
                'decorators' => array(
                    'ViewHelper',
                    array('Label', array('placement' => 'APPEND')),
                    array('HtmlTag', array('tag' => 'div', 'class' => 'col-md-9 col-md-offset-3')),
                    array('Errors', array('placement' => 'APPEND'))
                )
            ));
            $group[] = 'disclaimer1';


            $form->addElement('checkbox', 'disclaimer2', array(
                'required' => true,
                'uncheckedValue' => null,
                'label' => "Je certifie ne pas avoir publié/soumis cet article ailleurs",
                'belongsTo' => "disclaimers",
                'decorators' => array(
                    'ViewHelper',
                    array('Label', array('placement' => 'APPEND')),
                    array('HtmlTag', array('tag' => 'div', 'class' => 'col-md-9 col-md-offset-3')),
                    array('Errors', array('placement' => 'APPEND'))
                )
            ));
            $group[] = 'disclaimer2';


            $form->addElement('button', 'submitPaper', array(
                'type' => 'submit',
                'label' => 'Proposer cet article',
                'disabled' => true,
                'aria-disabled' => true,
                'class' => 'btn btn-primary',
                'decorators' => array(
                    array('ViewHelper', array('class' => 'form-control input-sm')),
                    array('HtmlTag', array('tag' => 'div', 'class' => "col-md-3  col-md-offset-3", 'style' => 'margin-top: 20px; margin-bottom: 20px'))
                )
            ));
            $group[] = 'submitPaper';


            $form->addElement('button', 'searchAgain', array(
                'label' => 'Annuler',
                'class' => 'btn btn-default',
                'decorators' => array(
                    array('ViewHelper', array('class' => 'form-control input-sm')),
                    array('HtmlTag', array('tag' => 'div', 'class' => "col-md-3", 'style' => 'margin-top: 20px; margin-bottom: 20px'))
                )
            ));
            $group[] = 'searchAgain';

            $form->addDisplayGroup($group, 'submitDoc');
            $form->getDisplayGroup('submitDoc')->setDecorators(array(
                'FormElements',
                array(array('wrapper2' => 'HtmlTag'), array('tag' => 'div', 'class' => 'panel-body')),
                array(array('wrapper1' => 'HtmlTag'), array('tag' => 'div', 'class' => 'panel panel-default', 'style' => 'display: none', 'id' => 'submitForm'))
            ));

            if ($defaults) {
                //#git 259 : Laisser le champ version vide quand on en soumet une nouvelle
                $defaults['version'] = '';
                $defaults['h_docId'] = $defaults['docId'];
                $defaults['h_repoId'] = $defaults['repoId'];
                $form->setDefaults($defaults);
            }

            return $form;

        } catch (Exception $e) {
            return null;
        }

    }

    /**
     * @param $repoId
     * @param $id
     * @param null $version
     * @param bool $isNewVersionOf
     * @return array
     * @throws Zend_Exception
     */
    public static function getDoc($repoId, $id, $version = null, $isNewVersionOf = false)
    {
        $id = trim($id);
        $identifier = Episciences_Repositories::getIdentifier($repoId, $id, $version);
        $baseUrl = Episciences_Repositories::getBaseUrl($repoId);
        $translator = Zend_Registry::get('Zend_Translate');

        $oai = new Episciences_Oai_Client($baseUrl, 'xml');
        $result = [];

        try {
            // version, identifier, repoid
            $paper = new Episciences_Paper(['rvid' => RVID, 'version' => $version, 'repoid' => $repoId, 'identifier' => $id]);
            // On prend pas en compte la version de l'artcile lors de la vérification de son existance en local.
            if (!$isNewVersionOf) { // resoumettre un article via "proposer un article" (submit/index)
                $paper->setVersion(null);
            }

            $result['status'] = (!$docId = $paper->alreadyExists()) ? 1 : 2;

            if ($result['status'] === 2) {
                $paper = Episciences_PapersManager::get($docId);
                $result['newVerErrors'] = $paper->manageNewVersionErrors(['version' => $version, 'isNewVersionOf' => $isNewVersionOf]);
            }

            $result['record'] = $oai->getRecord($identifier);

            //Bloquer la soumission dans hal d'une notice vide :git #109
            if ('Hal' === Episciences_Repositories::getLabel($repoId)) {
                $isNotice = self::isHalNotice($result['record'], $id, 'dc');
                if (!$isNotice) { // pas une notice

                    $date = self::extractEmbargoDate($oai->getRecord($identifier, 'oai_dcterms'));

                    if ($date > date('Y-m-d')) {
                        $date = ('9999-12-31' === $date) ? $translator->translate('Jamais') : Episciences_View_Helper_Date::Date($date, Episciences_Tools::getLocale());
                        $error = $translator->translate("Vous ne pouvez pas soumettre ce document; le fichier est non disponible; fin d'embargo : ") . '<strong class="alert-warning">' . $date . '</strong>';
                        throw new Ccsd_Error('docUnderEmbargo: ' . $error);
                    }

                } else {
                    throw new Ccsd_Error('docIsNotice:');
                }

            }

            //  OAI interface supports only the notion of an arXiv article and not access to individual versions.
            if ('arXiv' === Episciences_Repositories::getLabel($repoId)) {
                $arXivRawRecord = $oai->getArXivRawRecord($identifier);
                $versionHistory = self::extractVersionsFromArXivRaw($arXivRawRecord);
                if (!in_array($version, $versionHistory)) {
                    $error = 'arXivVersionDoesNotExist:';
                    throw new Ccsd_Error($error);
                }
            }

        } catch (Ccsd_Error $e) { // message personalisé : visible à l'utilisateur
            $result['status'] = 0;
            $result['error'] = '<b style="color: red;">' . $translator->translate('Erreur') . '</b> : ' . $translator->translate($e->parseError());
            return ($result);
        } catch (Exception $e) { // autre exception : message générique
            $result['status'] = 0;
            $result['error'] = '<b style="color: red;">' . $translator->translate('Erreur') . '</b> : ' . $translator->translate("Le document n'a pas été trouvé ou n'a pas pu être chargé.");
            return ($result);
        }

        return ($result);
    }

    /**
     * @param $data
     * @param null $paperId
     * @param null $vid
     * @param null $sid
     * @return array
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Json_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    public function saveDoc($data, $paperId = null, $vid = null, $sid = null)
    {

        // Initialisation
        $canReplace = (boolean)Ccsd_Tools::ifsetor($data['can_replace'], false); // remplacer ou pas la version V-1
        $oldStatus = (int)Ccsd_Tools::ifsetor($data['old_paper_status'], 0);
        $oldVersion = (int)Ccsd_Tools::ifsetor($data['old_version'], 1);
        $oldDocId = (int)Ccsd_Tools::ifsetor($data['old_docid'], 0);

        /**Zend_Translate $translator */
        $translator = Zend_Registry::get('Zend_Translate');
        /** @var Zend_Controller_Action_Helper_Redirector $redirector */
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        /** @var Zend_Controller_Action_Helper_FlashMessenger $messenger */
        $messenger = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');

        $result = [
            'code' => 0,
            'message' => ''
        ];

        // Préparation du populate de l'article
        $values = $this->buildValuesToPopulatePaper($data, $paperId,  $vid, $sid);

        $paper = new Episciences_Paper($values);

        if ($paper->alreadyExists()) {
            $message = '<strong>' . $translator->translate("L'article que vous tentez d'envoyer existe déjà.") . '</strong>';
            $messenger->setNamespace('error')->addMessage($message);
            $redirector->gotoUrl('submit');
        }

        if ($paper->save()) {
            $docId = $paper->getDocid();
            if (!$canReplace && !$paper->getPaperid()) {
                $paper->setPaperid($docId);
                $paper->save();
            }

            !$canReplace ?
                $paper->log(Episciences_Paper_Logger::CODE_STATUS, Episciences_Auth::getUid(), ['status' => Episciences_Paper::STATUS_SUBMITTED]) :
                $paper->log(Episciences_Paper_Logger::CODE_PAPER_UPDATED, Episciences_Auth::getUid(), ['user' => Episciences_Auth::getUser()->toArray(), 'version' => ['old' => $oldVersion, 'new' => $paper->getVersion()]]);

            $result['docId'] = (int)$docId;

        } else {
            $message = '<strong>' . $translator->translate("Une erreur s'est produite pendant l'enregistrement de votre article.") . '</strong>';
            $messenger->setNamespace('error')->addMessage($message);
            $redirector->gotoUrl('submit');

        }

        $coverLetter = [
            "message" => $values['AUTHOR_COMMENT'],
            "attachedFile" => $values['FILE_AUTHOR']
        ];

        $this->saveCoverLetter($paper, $coverLetter );

        $recipients = [];

        // Message de confirmation
        // Avant l'envoi des mails, pour éviter conflit avec les traductions du template
        // ?? je ne comprends pas où est le Pb de conflit ??
        // Envoi des mails (soumission d'un nouvel article) OU sa mise à jour //

        $authorTemplateKy = Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_AUTHOR_COPY;

        if (!$canReplace) {
            // Assigner automatiquement des rédacteurs à l'article pour une nouvelle soumission pas pour une mise à jour
            $recipients += $this->assignEditors($paper, $this->getSuggestedEditorsFromPost($data), $values['SID'], $values['VID']);

        } else { // updated version : send mails to editors + admin + secretaries + chief editors
            $authorTemplateKy = Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_UPDATED_AUTHOR_COPY;
            $recipients += $paper->getEditors(true, true);
        }

        // Mail à l'auteur
        // Préparation du lien vers l'article
        $view = Zend_Layout::getMvcInstance()->getView();

        $paperUrl = $view->url(array(
            'controller' => 'paper',
            'action' => 'view',
            'id' => $paper->getDocid()));

        $paperUrl = HTTP . '://' . $_SERVER['SERVER_NAME'] . $paperUrl;

            //Author infos
            /** @var Episciences_User $author */
            $author = Episciences_Auth::getUser();
            $aLocale = $author->getLangueid();

        $commonTags = [
            Episciences_Mail_Tags::TAG_ARTICLE_ID => $paper->getDocId(),
            Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME => $author->getFullName()
        ];

        $authorTags = $commonTags + [
                Episciences_Mail_Tags::TAG_PAPER_URL => $paperUrl,
                Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($aLocale, true),
                Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $paper->formatAuthorsMetadata($aLocale)
            ];

        Episciences_Mail_Send::sendMailFromReview($author, $paper, $authorTemplateKy, $authorTags);

        //Mail aux rédacteurs + selon les paramètres de la revue, aux admins et secrétaires de rédactions.
        Episciences_Review::checkReviewNotifications($recipients);
        unset($recipients[$paper->getUid()]);

        if (!empty($recipients)) {
            self::notifyManagers($paper, $recipients, $oldDocId, $oldStatus, $commonTags, $canReplace);
        }


        // Sauvegarder les options *************************************************************
        $this->saveAllAuthorSuggestions($data, $result);

        if (!empty($result['message'])) {
            return $result;
        }

        $result['message'] = '<strong>' . $translator->translate("Votre article a bien été enregistré.") . '</strong>';
        $result['code'] = 1;

        return $result;

    }

    /**
     * Assigne automatiquement les rédacteurs à un article (git #43), selon les paramètres de la revue
     * @param Episciences_Paper $paper
     * @param array $suggestEditors : editeurs suggérés par l'auteur,
     * @param int $sid : l'ID de la rubrique; Null par defaut
     * @param int $vid : l'ID du volume; Null par defaut
     * @return array : les Editeurs assignés à l'articles
     * @throws Zend_Db_Adapter_Exception
     */
    private function assignEditors(Episciences_Paper $paper, array $suggestEditors = [], $sid = null, $vid = null)
    {
        /** @var Episciences_Review $review */
        $review = Episciences_ReviewsManager::find(RVID);
        $review->loadSettings();

        $autoAssignation = $review->getSetting(Episciences_Review::SETTING_SYSTEM_AUTO_EDITORS_ASSIGNMENT);

        // rédacteurs choisis par l'auteur, s'il y en a et selon les paramètres de la revue
        $editors = (!empty($autoAssignation) && in_array(Episciences_Review::SETTING_SYSTEM_CAN_ASSIGN_SUGGEST_EDITORS, $autoAssignation)) ? $this->findSuggestEditors($suggestEditors) : [];

        if ( !empty($autoAssignation) && in_array(Episciences_Review::SETTING_SYSTEM_CAN_ASSIGN_SECTION_EDITORS, $autoAssignation) && $sid) { // Asignation des rédacteurs d'une section
            /** @var Episciences_Section $section */
            $section = Episciences_SectionsManager::find($sid);
            $sectionEditors = $section->getEditors();
            self::addIfNotExists($sectionEditors, $editors, Episciences_Editor::TAG_SECTION_EDITOR);
        }

        if ($vid) { // Assignation de rédacteurs d'un volume
            $volumeEditors = $this->getVolumeEditors($vid, $review);
            self::addIfNotExists($volumeEditors, $editors, Episciences_Editor::TAG_VOLUME_EDITOR);
        }

        if (!empty($autoAssignation) && in_array(Episciences_Review::SETTING_SYSTEM_CAN_ASSIGN_CHIEF_EDITORS, $autoAssignation)) { // Assignation de rédacteurs en chef
            $chiefEditors = $review->getChiefEditors();
            self::addIfNotExists($chiefEditors, $editors, Episciences_Editor::TAG_CHIEF_EDITOR);
        }

        // On ne permet pas à l'auteur de l'article d'être rédacteur de son propre article
        if (array_key_exists($paper->getUid(), $editors)) {
            unset($editors[$paper->getUid()]);
        }

        if (!empty($editors)) {
            // Enregistrer assignation des rédacteurs
            foreach ($editors as $oEditor) {
                $assignmentValues = [
                    'rvid' => RVID,
                    'item' => Episciences_User_Assignment::ITEM_PAPER,
                    'itemid' => $paper->getDocid(),
                    'uid' => $oEditor->getUid(),
                    'roleid' => Episciences_User_Assignment::ROLE_EDITOR,
                    // TODO : inviter le rédacteur plutôt que l'assigner automatiquement ?
                    'status' => Episciences_User_Assignment::STATUS_ACTIVE
                ];
                $oAssignment = new Episciences_User_Assignment($assignmentValues);
                $oAssignment->save();
                $paper->log(Episciences_Paper_Logger::CODE_EDITOR_ASSIGNMENT, null, ["aid" => $oAssignment->getId(), "user" => $oEditor->toArray()]);
            }
        }
        return $editors;
    }

    /**
     * retourne les rédacteurs assignés à un volume, selon les paramètres de la revue
     * @param $vid
     * @param Episciences_Review $review
     * @return array
     */

    private function getVolumeEditors($vid, Episciences_Review $review)
    {
        /** @var Episciences_Volume $volume */
        $volume = Episciences_VolumesManager::find($vid);
        $volume->loadSettings();

        $volumeEditors = [];

        $isSpecialVolume = ($volume->getSetting(Episciences_Volume::SETTING_SPECIAL_ISSUE) == 1);
        $autoAssignation = $review->getSetting(Episciences_Review::SETTING_SYSTEM_AUTO_EDITORS_ASSIGNMENT);

        $spVCondition = $isSpecialVolume && !empty($autoAssignation) && in_array(Episciences_Review::SETTING_SYSTEM_CAN_ASSIGN_SPECIAL_VOLUME_EDITORS, $autoAssignation);
        $nspVCondition = !$isSpecialVolume && !empty($autoAssignation) && in_array(Episciences_Review::SETTING_SYSTEM_CAN_ASSIGN_VOLUME_EDITORS, $autoAssignation);

        if ($spVCondition || $nspVCondition) {
            $volumeEditors = $volume->getEditors();
        }

        return $volumeEditors;
    }

    /**
     * @param array $suggestEditors
     * @return Episciences_Editor[] || []
     */
    private function findSuggestEditors(array $suggestEditors)
    {

        $editors = [];

        if (!empty($suggestEditors)) {
            foreach ($suggestEditors as $editorId) {
                /** @var Episciences_Editor $oEditor */
                $oEditor = new Episciences_Editor();
                $oEditor->findWithCAS($editorId);
                $oEditor->setTag(Episciences_Editor::TAG_SUGGESTED_EDITOR);
                $editors[$editorId] = $oEditor;
            }
        }

        return $editors;
    }


    /**
     * L'interface OAI implimentée par ArXiv ne supporte pas l'acces à une version individuelle :
     * Extraction de l'historique des versions d'un article
     * @param array $rawRecord
     * @return array
     */
    private static function extractVersionsFromArXivRaw(array $rawRecord)
    {
        $historyVersions = $rawRecord['metadata']['arXivRaw']['version'];
        $versions = [];
        foreach ($historyVersions as $index => $version) {
            if (is_array($historyVersions[$index])) {
                $versions[] = substr($version['version'], 1); // supprimer le caractère 'v'
            } else {
                if ($index === 'version') {
                    $versions[] = substr($historyVersions[$index], 1);
                    // ne pas parcourir les autres elements
                    return $versions;
                }
            }
        }
        return $versions;
    }

    /**
     * Sauvegarde les suggestions de l'auteur dans la BD
     * @param int $docId
     * @param array $data
     * @param string $suggestionType
     * @return bool
     */
    private function saveAuthorSuggestions(int $docId, array $data, string $suggestionType): bool
    {
        $values = [];
        foreach ($data as $value) {
            $option = $this->_db->quote($suggestionType);
            $value = $this->_db->quote($value);
            $values[] = '(' . $docId . ',' . $option . ',' . $value . ')';
        }
        $sql = 'INSERT INTO ' . T_PAPER_SETTINGS . ' (DOCID, SETTING, VALUE) VALUES ' . implode(',', $values);

        if (!$this->_db->query($sql)) {
            error_log('Failed to save ' . $suggestionType . ' for DOCID = ' . $docId);
            return false;
        }
        return true;
    }

    /**
     * Bloquer la soumission à Episciences d'une notice Hal vide de fichier pdf: solution provisoire
     * en attendant la création d'un portail qui contiendra uniquement les preprint avec documents.
     * @param string $record
     * @param string $id
     * @param string $format
     * @return bool
     */
    private static function isHalNotice(string $record, string $id, string $format = 'dcterms')
    {
        $docPattern = '<' . $format . ':identifier>https:\/\/(.)+\.fr\/' . $id . '(v\d+)?\/document<\/' . $format . ':identifier>';
        $word = Episciences_Tools::extractPattern('/' . $docPattern . '/', $record);
        return empty($word);
    }

    /**
     *extraction de la date d'empargo.
     * @param string $record
     * @return string
     */
    private static function extractEmbargoDate(string $record)
    {
        $datePattern = '\d{4}-\d{2}-\d{2}';

        $availPattern = '<dcterms:available>' . $datePattern . '<\/dcterms:available>';

        $available = Episciences_Tools::extractPattern('/' . $availPattern . '/', $record);

        return Episciences_Tools::extractPattern('/' . $datePattern . '/', $available[0])[0];
    }

    /**
     * @param array $input
     * @param array $output
     * @param string $tag
     */
    public static function addIfNotExists(array $input, array &$output, string $tag = '')
    {
        $arrayDiff = array_diff_key($input, $output);
        foreach ($arrayDiff as $uid => $user) {
            if (!empty($tag) && method_exists($user, 'setTag')) {
                $user->setTag($tag);
            }
            $output[$uid] = $user;
        }
    }

    /**
     * @param Episciences_Paper $paper
     * @param array $managers
     * @param int $oldDocId
     * @param int $oldPaperStatus
     * @param array $tags
     * @param bool $canReplace
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */
    private static function notifyManagers(Episciences_Paper $paper, array $managers, $oldDocId, $oldPaperStatus = 0, array $tags = [],  bool $canReplace = false)
    {
        $translator = Zend_Registry::get('Zend_Translate');
        $defaultTemplateKey =  Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_EDITOR_COPY; // default template

        $volume = Episciences_VolumesManager::find($paper->getVid());
        $section = Episciences_SectionsManager::find($paper->getSid());

        $message = '';
        $adminTags = $tags;

        // Préparation du lien vers l'article
        $view = Zend_Layout::getMvcInstance()->getView();

        $paperUrl = $view->url([
            'controller' => 'administratepaper',
            'action' => 'view',
            'id' => $paper->getDocid()
        ]);

        $paperUrl = HTTP . '://' . $_SERVER['SERVER_NAME'] . $paperUrl;

        $adminTags[Episciences_Mail_Tags::TAG_PAPER_URL] = $paperUrl; // Lien de gestion de l'article


        if ($canReplace) {
            if ($oldPaperStatus == Episciences_Paper::STATUS_REFUSED) {// Si l'article a été déjà refusé
                $message = 'Cet article a été précédemment refusé dans sa première version, pour le consulter, merci de suivre ce lien : ';
                // Lien vers l'article qui a été déjà refusé
                $refusedPaperUrl = $view->url([
                    'controller' => 'administratepaper',
                    'action' => 'view',
                    'id' => $oldDocId

                ]);

                $refusedPaperUrl = HTTP . '://' . $_SERVER['SERVER_NAME'] . $refusedPaperUrl;

                // Au lieu d'ajouter un template pour ce cas particulier, on ajoute ce  tags dans le template paper_submission_editor_copy
                $adminTags[Episciences_Mail_Tags::TAG_REFUSED_PAPER_URL] = $refusedPaperUrl;
            } else {
                $defaultTemplateKey = Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_UPDATED_EDITOR_COPY;
            }
        }

        /** @var Episciences_Editor $recipient */
        foreach ($managers as $recipient) {
            // git #230
            $templateKey = ($canReplace || $paper->getEditor($recipient->getUid())) ? $defaultTemplateKey : Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_OTHERS_RECIPIENT_COPY; // re-initialisation
            $locale = $recipient->getLangueid();

            if (array_key_exists(Episciences_Mail_Tags::TAG_REFUSED_PAPER_URL, $adminTags)) {// Si l'article a déjà été refusé
                $message = $translator->translate($message, $locale);
                // Au lieu d'ajouter un template pour ce cas particulier, on ajoute ce tag dans le template paper_submission_editor_copy
                $adminTags[Episciences_Mail_Tags::TAG_REFUSED_ARTICLE_MESSAGE] = $message;
            }

            $vTag = !$volume ? $translator->translate('Hors volume', $locale) : $volume->getName($locale);
            $sTag = !$section ? $translator->translate('Hors rubrique', $locale) : $section->getName($locale);

            $adminTags [Episciences_Mail_Tags::TAG_SENDER_EMAIL] = Episciences_Auth::getEmail();
            $adminTags [Episciences_Mail_Tags::TAG_SENDER_FULL_NAME] = Episciences_Auth::getFullName();
            $adminTags [Episciences_Mail_Tags::TAG_ARTICLE_TITLE] = $paper->getTitle($locale, true);
            $adminTags [Episciences_Mail_Tags::TAG_AUTHORS_NAMES] = $paper->formatAuthorsMetadata($locale);
            $adminTags [Episciences_Mail_Tags::TAG_VOLUME_NAME] = $vTag;
            $adminTags [Episciences_Mail_Tags::TAG_VOL_BIBLIOG_REF] = ($volume && $volume->getBib_reference()) ?: $translator->translate('Aucune', $locale);
            $adminTags [Episciences_Mail_Tags::TAG_SECTION_NAME] = $sTag;

            if (!$canReplace && method_exists($recipient, 'getTag')) { // new submission only
                $rTag = $recipient->getTag();
                if ($rTag === Episciences_Editor::TAG_VOLUME_EDITOR) {
                    $templateKey = Episciences_Mail_TemplatesManager::TYPE_PAPER_VOLUME_EDITOR_ASSIGN;
                } elseif ($rTag === Episciences_Editor::TAG_SECTION_EDITOR) {
                    $templateKey = Episciences_Mail_TemplatesManager::TYPE_PAPER_SECTION_EDITOR_ASSIGN;
                } elseif ($rTag === Episciences_Editor::TAG_SUGGESTED_EDITOR) {
                    $templateKey = Episciences_Mail_TemplatesManager::TYPE_PAPER_SUGGESTED_EDITOR_ASSIGN;
                }
            }

            Episciences_Mail_Send::sendMailFromReview($recipient, $paper, $templateKey, $adminTags);
        }
    }

    /**
     * @param Episciences_Paper $paper
     * @param array $coverLetter
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Json_Exception
     */
    private function saveCoverLetter(Episciences_Paper $paper, array $coverLetter = ["message" => '', "attachedFile" => null])
    {
        // Save author comment and attached file
        $authorComment = new Episciences_Comment();
        $authorComment->setFilePath(REVIEW_FILES_PATH . $paper->getDocid() . '/comments/');
        $authorComment->setType(Episciences_CommentsManager::TYPE_AUTHOR_COMMENT);
        $authorComment->setDocid($paper->getDocid());
        $authorComment->setMessage($coverLetter["message"]);

        //Eviter l'insertion d'une ligne vide dans la table
        if ((!empty($coverLetter['message']) || !empty($coverLetter["attachedFile"])) && !$authorComment->save()) {
            error_log('SAVE_COVER_LETTER_FAILED_FOR_DOCID_ ', $paper->getDocid());
        }
    }

    /**
     * @param array $data
     * @param array $result
     * @throws Zend_Exception
     */
    private function saveAllAuthorSuggestions(array $data, array &$result)
    {
        /**Zend_Translate_Adapter $translator */
        $translator = Zend_Registry::get('Zend_Translate');

        $suggestEditors = $this->getSuggestedEditorsFromPost($data);
        $jump  = false;

        // Suggested reviewers
        if (!$jump &&
            array_key_exists('suggestReviewers', $data) &&
            count($data['suggestReviewers']) &&
            !$this->saveAuthorSuggestions($result['docId'], $data['suggestReviewers'], 'suggestedReviewer')

        ) {
            $result['message'] = $translator->translate("Échec de l'enregistrement de votre suggestion de relecteurs souhaités.");
            $jump = true;

        }

        // Unwanted reviewers
        if (!$jump &&
            array_key_exists('unwantedReviewers', $data) &&
            count($data['unwantedReviewers']) &&
            !$this->saveAuthorSuggestions($result['docId'], $data['unwantedReviewers'], 'unwantedReviewer')

        ) {
            $result['message'] = $translator->translate("Échec de l'enregistrement de votre suggestion de relecteurs non souhaités.");
            $jump = true;
        }

        // Suggested editors
        if (!$jump &&
            count($suggestEditors) && !$this->saveAuthorSuggestions($result['docId'], $suggestEditors, 'suggestedEditor')) {
            $result['message'] = $translator->translate("Échec de l'enregistrement de votre suggestion de rédacteurs.");
        }
    }

    /**
     * @param array $data
     * @param null $paperId
     * @param null $vid
     * @param null $sid
     * @return array
     */
    private function buildValuesToPopulatePaper(array $data, $paperId = null, $vid = null,  $sid = null){

        $values = [];
        $canReplace = (boolean)Ccsd_Tools::ifsetor($data['can_replace'], false); // remplacer ou pas la version V-1
        $values['RVID'] = RVID;
        $values['VERSION'] = (is_numeric($data['search_doc']['version'])) ? $data['search_doc']['version'] : 1;
        $values['VID'] = Ccsd_Tools::ifsetor($data['volumes'], 0);
        $values['SID'] = Ccsd_Tools::ifsetor($data['sections'], 0);
        $values['UID'] = Episciences_Auth::getUid();
        $values['IDENTIFIER'] = trim($data['search_doc']['docId']);
        $values['REPOID'] = $data['search_doc']['repoId'];
        $values['STATUS'] = Episciences_Paper::STATUS_SUBMITTED; // new paper

        // Quand on remplace une version par une autre on garde toujours la même date de soumission de l'article original
        // non plus pour les articles refusés
        if (array_key_exists('old_submissiondate', $data)) {
            $values['SUBMISSION_DATE'] = $data['old_submissiondate'];
        } else {
            $values['SUBMISSION_DATE'] = date("Y-m-d H:i:s");
        }

        $values['RECORD'] = $data['xml'];

        // To populate PAPER_COMMENT
        $values['AUTHOR_COMMENT'] = $data['author_comment'];
        $values['FILE_AUTHOR'] = $data['file_comment_author'];

        if ($paperId) {
            $values['PAPERID'] = $paperId;
            $values['STATUS'] = Episciences_Paper::STATUS_BEING_REVIEWED;
        }

        if ($vid) {
            $values['VID'] = $vid;
        }

        if ($sid) {
            $values['SID'] = $sid;
        }

        // Le papier dont l'id = $value['DOCID'] sera mis à jour.
        if ($canReplace) {
            // info V-1
            if (isset($data['old_docid']) &&
                ($data['old_paper_status'] == Episciences_Paper::STATUS_SUBMITTED || $data['old_paper_status'] == Episciences_Paper::STATUS_OK_FOR_REVIEWING)
            ) { // update version
                $values['DOCID'] = (int)$data['old_docid'];
                $values['STATUS'] = (int)Ccsd_Tools::ifsetor($data['old_paper_status'], $values['STATUS']);
            }
            $values['PAPERID'] = (int)Ccsd_Tools::ifsetor($data['old_paperid'], 0);
            $values['VID'] = (int)Ccsd_Tools::ifsetor($data['old_paper_vid'], 0);
            $values['SID'] = (int)Ccsd_Tools::ifsetor($data['old_paper_sid'], 0);
        }

        return $values;

    }

    /**
     * @param array $post
     * @return array
     */
    private function getSuggestedEditorsFromPost(array $post) : array {
        $suggestedEditors = Ccsd_Tools::ifsetor($post['suggestEditors'], []);

        if (!empty($suggestedEditors)) {
            // choisir un seul et un seul editeur
            $suggestedEditors = (!is_array($post['suggestEditors'])) ? (array)$suggestedEditors: $suggestedEditors;
        }

        return $suggestedEditors;
    }

}
