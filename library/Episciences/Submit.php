<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

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
     * @param bool $isFromZSubmit
     * @return Ccsd_Form
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */

    public static function getForm(array $settings = [], $defaults = null, bool $isFromZSubmit = false): \Ccsd_Form
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

        $options = self::getRepositoriesLabels($settings);

        $repIdElementOptions = [
            'label' => 'Archive',
            'multiOptions' => $options,
            'style' => 'width:auto;',
        ];

        $docIdElementOptions = [
            'label' => 'Identifiant du document',
            'required' => true,
            'description' => $translator->translate("Saisir l'identifiant du document") . '.',
            'style' => 'width:auto; text-align:center;',
        ];

        if ($isFromZSubmit) {
            $repIdElementOptions['disabled'] = true;
            $docIdElementOptions['disabled'] = true;
        }

        // Select: repositories
        $subform->addElement('select', 'repoId', $repIdElementOptions);

        unset($options);

        // Champ texte : identifiant du document
        $subform->addElement('text', 'docId', $docIdElementOptions);

        // Champ texte : version du document
        $isNotRequired = isset($defaults['repoId']) && ((int)$defaults['repoId'] === 4);

        if (!$isNotRequired) {
            $subform->addElement('text', 'version', [
                'label' => 'Version',
                'required' => false,
                'description' => $translator->translate("Saisir la version du document (nombre uniquement)."),
                'value' => '',
                'style' => 'width:17%;']);

        }

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

                unset($options);
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

                unset($options);
            }

            // Choix des relecteurs par l'auteur (si la revue l'autorise) ********
            if (array_key_exists(Episciences_Review::SETTING_CAN_SUGGEST_REVIEWERS, $settings) && $settings[Episciences_Review::SETTING_CAN_SUGGEST_REVIEWERS]) {

                $form->addElement('multiTextSimple', 'suggestReviewers', [
                    'style' => 'width: 33%',
                    'label' => 'Je souhaite être relu par : ',
                    'display' => 'advanced'
                ]);
                $group[] = 'suggestReviewers';
            }


            // Relecteurs non désirés par l'auteur (si la revue l'autorise) ********
            if (array_key_exists(Episciences_Review::SETTING_CAN_SPECIFY_UNWANTED_REVIEWERS, $settings) && $settings[Episciences_Review::SETTING_CAN_SPECIFY_UNWANTED_REVIEWERS]) {

                $form->addElement('multiTextSimple', 'unwantedReviewers', [
                    'label' => 'Je ne souhaite pas être relu par : ',
                    'display' => 'advanced'
                ]);
                $group[] = 'unwantedReviewers';

            }

            // Choix des rédacteurs par l'auteur (si la revue l'autorise)
            if (array_key_exists(Episciences_Review::SETTING_CAN_PICK_EDITOR, $settings) && $settings[Episciences_Review::SETTING_CAN_PICK_EDITOR] > 0) {
                // Récupération et tri des valeurs du select
                $options = [];

                $with = (
                    array_key_exists(Episciences_Review::SETTING_DO_NOT_ALLOW_EDITOR_IN_CHIEF_SELECTION, $settings) &&
                    $settings[Episciences_Review::SETTING_DO_NOT_ALLOW_EDITOR_IN_CHIEF_SELECTION]
                )
                    ? Episciences_Acl::ROLE_EDITOR :
                    [Episciences_Acl::ROLE_CHIEF_EDITOR, Episciences_Acl::ROLE_EDITOR];


                $users = Episciences_UsersManager::getUsersWithRoles($with);

                /* @var  $user Episciences_User */
                foreach ($users as $uid => $user) {
                    // Liste des rédacteurs et rédacteurs en chef (on filtre root, ainsi que le compte connecté)
                    if ($uid !== 1 && $uid !== Episciences_Auth::getUid()) {
                        $options[$uid] = $user->getFullName();
                    }
                }

                asort($options);

                // Select
                if ($options) {

                    $editorsElementType = 'multiselect';

                    $editorsAttribs = [
                        'label' => 'Je souhaite que mon article soit supervisé par : ',
                        'attribs' => ['multiple' => 'multiple'],
                        'multiOptions' => $options,
                        'required' => (int)$settings['canPickEditors'] > 1
                    ];

                    if ((int)$settings['canPickEditors'] === 3) {
                        $editorsElementType = 'select';
                        unset($editorsAttribs['attribs']);

                        // merge array and preserve keys
                        $options = array_replace(['0' => 'Sélectionnez un éditeur :'], $options);
                        $editorsAttribs['multiOptions'] = $options;
                    } else {
                        $info = "Maintenez la touche <mark>CTRL</mark> enfoncée et cliquez sur les éléments d'une liste pour les choisir. Cliquez sur tous les éléments que vous souhaitez sélectionner. Ils n'ont pas besoin d'être côte à côte. Cliquez à nouveau sur un élément pour le désélectionner. N'oubliez pas de maintenir la touche <mark>CTRL</mark> enfoncée.";
                        $editorsAttribs['description'] = $info;
                    }

                    $form->addElement($editorsElementType, 'suggestEditors', $editorsAttribs);

                }

                $group[] = 'suggestEditors';

                unset($options);
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
    public static function getTmpVersionForm(Episciences_Comment $comment = null): \Ccsd_Form
    {
        $form = new Ccsd_Form;
        $form->setAttrib('enctype', 'multipart/form-data');
        $form->setAttrib('class', 'form-horizontal');

        if ($comment) {
            $docId = $comment->getDocid();
            $paper = Episciences_PapersManager::get($docId);
            $paperId = ($paper->getPaperid()) ?: $docId;
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
        //copy co authors
        $paper = Episciences_PapersManager::get($docId);
        if (!empty($paper->getCoAuthors())) {
            $form->addElement('checkbox', 'copy-co-author', array(
                'label' => "Envoyer une copie de ce message aux co-auteur",
                'decorators' => [
                    'ViewHelper',
                    ['Label', array('placement' => 'APPEND')],
                    ['HtmlTag', array('tag' => 'div', 'class' => 'col-md-9 col-md-offset-3')]
                ],
                'options' => ['uncheckedValue' => 0, 'checkedValue' => 1]
            ));
        }
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
            $values = ['STATUS' => Episciences_Paper::STATUS_OBSOLETE];
            $where = ['DOCID != ?' => $result['docId'], 'PAPERID = ?' => $paperId];
            $db->update(T_PAPERS, $values, $where);

        } else {
            $result['message'] = "La version temporaire n'a pas pu être enregistrée";
        }

        return $result;
    }

    /**
     * Retourne le formulaire permettant la soumission de la nouvelle version
     * @param Episciences_Paper $paper
     * @param array $settings
     * @return Ccsd_Form|null
     * @throws Zend_Db_Statement_Exception
     */
    public static function getNewVersionForm(Episciences_Paper $paper, array $settings = []): ?Ccsd_Form
    {
        $defaults = self::fetchNewVersionFormDefaultValues($paper);

        if (array_key_exists('zIdentifier', $settings)) {
            $defaults['docId'] = $settings['zIdentifier'];
        }

        $hasHook = (array_key_exists('hasHook', $defaults) && $defaults['hasHook']) ? $defaults['hasHook'] : false;
        $isNewVersionOf = array_key_exists('newVersionOf', $settings);

        try {
            $form = new Ccsd_Form();
            $form->setName('submit_doc');
            $form->setAttrib('class', 'form-horizontal');
            $form->getDecorator('FormRequired')->setOption('style', 'margin-top: 20px; margin-bottom: 20px;');
            $form->getDecorator('FormRequired')->setOption('id', 'form_required'); // (voir js/submit/function.js)


            $subform = new Ccsd_Form_SubForm();

            $docIdOptions = [
                'label' => 'Identifiant du document',
                'disabled' => true,
                'required' => true,
                'placeholder' => Zend_Registry::get('Zend_Translate')->translate("Saisir l'identifiant du document"),
                'style' => 'width:33%;',
            ];

            if ($hasHook) {
                unset($docIdOptions['disabled']);
            }

            // Champ texte : identifiant du document
            $subform->addElement('text', 'docId', $docIdOptions);

            $subform->addElement('hidden', 'h_docId');

            if (!$hasHook) {

                // Champ texte : version du document
                $subform->addElement('text', 'version', [
                        'label' => 'Version',
                        'description' => self::buildNewVersionDescription($defaults),
                        'value' => '',
                        'required' => true,
                        'style' => 'width:33%']
                );

            }

            $options = self::getRepositoriesLabels($settings);

            // Select: repositories
            $subform->addElement('select', 'repoId', [
                'label' => 'Archive',
                'disabled' => true,
                'multiOptions' => $options,
                'style' => 'width:33%;',
            ]);

            $subform->addElement('hidden', 'h_repoId');

            //To manage the search for a document when submitting a new version following a modification request
            if ($isNewVersionOf) {
                $subform->addElement('hidden', 'newVersionOf', ['value' => $settings['newVersionOf']]);

                // Submission of a new version following a request for changes to the temporary version

                $isTmp = $paper->getRepoid() === 0 &&
                    (
                        $paper->getStatus() === Episciences_Paper::STATUS_WAITING_FOR_MINOR_REVISION ||
                        $paper->getStatus() === Episciences_Paper::STATUS_WAITING_FOR_MAJOR_REVISION
                    ) && (int)explode('/', $paper->getIdentifier())[0] === $paper->getPaperid();

                if ($isTmp) {

                    $subform->addElement('hidden', 'h_hasHook', ['value' => $defaults['hasHook']]);

                    //#git 259 : Leave the version field empty when submitting a new one (request: ask for the final version)

                    $tmp_defaults['repoId'] = $defaults['repoId'];
                    $tmp_defaults['h_repoId'] = $tmp_defaults['repoId'];
                    $tmp_defaults['h_docId'] = $defaults['docId'];

                    $subform->setDefaults($tmp_defaults);

                }
            }


            if ($paper->isContributorCanShareArXivPaperPwd()) {
                $subform = self::addPaperArxivPwdElement($subform, $paper->isRequiredPaperPwd());
            }


            // search button
            $subform->addElement('button', 'getPaper', [
                'label' => 'Rechercher',
                'class' => 'btn btn-default',
                'style' => 'width:33%',
                'decorators' => [
                    ['ViewHelper', ['class' => 'form-control input-sm']],
                    ['HtmlTag', ['tag' => 'div', 'class' => "col-md-9 col-md-offset-3"]]
                ]
            ]);

            $form->addSubForm($subform, 'search_doc');
            $form->getSubForm('search_doc')->setDecorators([
                'FormElements',
                [['wrapper2' => 'HtmlTag'], ['tag' => 'div', 'class' => 'panel-body']],
                [['wrapper1' => 'HtmlTag'], ['tag' => 'div', 'class' => 'panel panel-default', 'id' => 'searchForm']],
            ]);


            // Submission document form
            $group = [];
            $xml = new Zend_Form_Element_Hidden('xml');
            $xml->setDecorators(['ViewHelper']);
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
            $description = Episciences_Tools::buildAttachedFilesDescription($extensions, '.&nbsp;' . $descriptionAllowedToSeeCoverLetterTranslated);
            $form->addElement('file', 'file_new_version_comment_author', [
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
            $group[] = 'file_new_version_comment_author';


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


            $form->addElement('button', 'submitPaper', [
                'type' => 'submit',
                'label' => 'Proposer cet article',
                'disabled' => true,
                'aria-disabled' => true,
                'class' => 'btn btn-primary',
                'decorators' => [
                    ['ViewHelper', ['class' => 'form-control input-sm']],
                    ['HtmlTag', ['tag' => 'div', 'class' => "col-md-3  col-md-offset-3", 'style' => 'margin-top: 20px; margin-bottom: 20px']]
                ]
            ]);
            $group[] = 'submitPaper';


            $form->addElement('button', 'searchAgain', [
                'label' => 'Annuler',
                'class' => 'btn btn-default',
                'decorators' => [
                    ['ViewHelper', ['class' => 'form-control input-sm']],
                    ['HtmlTag', ['tag' => 'div', 'class' => "col-md-3", 'style' => 'margin-top: 20px; margin-bottom: 20px']]
                ]
            ]);
            $group[] = 'searchAgain';

            $form->addDisplayGroup($group, 'submitDoc');
            $form->getDisplayGroup('submitDoc')->setDecorators([
                'FormElements',
                [['wrapper2' => 'HtmlTag'], ['tag' => 'div', 'class' => 'panel-body']],
                [['wrapper1' => 'HtmlTag'], ['tag' => 'div', 'class' => 'panel panel-default', 'style' => 'display: none', 'id' => 'submitForm']]
            ]);

            if (isset($defaults['version'], $defaults['docId'], $defaults['repoId'])) {
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
     * @param int|null $version
     * @param null $latestObsoleteDocId
     * @param bool $manageNewVersionErrors Allow to ignore new version errors for imports
     * @param int|null $rvId
     * @return array
     * @throws Zend_Exception
     */
    public static function getDoc($repoId, $id, int $version = null, $latestObsoleteDocId = null, $manageNewVersionErrors = true, int $rvId = RVID): array
    {
        $isNewVersionOf = !empty($latestObsoleteDocId);
        $result = [];
        $id = trim($id);

        $hookCleanIdentifiers = Episciences_Repositories::callHook('hookCleanIdentifiers', ['id' => $id, 'repoId' => $repoId]);

        if (!empty($hookCleanIdentifiers)) {
            $id = $hookCleanIdentifiers['identifier'];
        }

        $hookApiRecord = Episciences_Repositories::callHook('hookApiRecords', ['identifier' => $id, 'repoId' => $repoId, 'version' => $version]);

        if (!empty($hookApiRecord)) {
            $hookVersion = Episciences_Repositories::callHook('hookVersion', ['identifier' => $id, 'repoId' => $repoId, 'response' => $hookApiRecord]);
        }

        if (isset($hookVersion['version'])) {
            $version = $hookVersion['version'];
            $result['hookVersion'] = $version;
        }

        $identifier = Episciences_Repositories::getIdentifier($repoId, $id, $version);
        $baseUrl = Episciences_Repositories::getBaseUrl($repoId);

        $oai = null;

        if ($baseUrl) {
            $oai = new Episciences_Oai_Client($baseUrl, 'xml');
        }


        $translator = !Ccsd_Tools::isFromCli() ? Zend_Registry::get('Zend_Translate') : null;


        try {
            // version, identifier, repoid
            $paper = new Episciences_Paper(['rvid' => $rvId, 'version' => $version, 'repoid' => $repoId, 'identifier' => $id]);
            // On prend pas en compte la version de l'artcile lors de la vérification de son existance en local.
            if (!$isNewVersionOf) { // resoumettre un article via "proposer un article" (submit/index)
                $paper->setVersion(null);
            }

            if ($oai) {
                $result['record'] = $oai->getRecord($identifier);
            } else {
                $result['record'] = $hookApiRecord ['record'];
                if(isset($hookApiRecord['error']) || empty($result['record'])){
                    throw new Ccsd_Oai_Error('idDoesNotExist', 'identifier', $identifier);
                }
            }

            $conceptIdentifier = null;

            if (isset($hookApiRecord['conceptrecid'])) {
                $conceptIdentifier = $hookApiRecord['conceptrecid'];

            } else {
                $hookConceptIdentifier = Episciences_Repositories::callHook('hookConceptIdentifier', ['repoId' => $paper->getRepoid(), 'response' => $hookApiRecord]);
                if (isset($hookConceptIdentifier['conceptIdentifier'])) {
                    $conceptIdentifier = $hookConceptIdentifier['conceptIdentifier'];
                }
            }

            if ($conceptIdentifier) { // concept identifier
                $paper->setConcept_identifier($conceptIdentifier);
                $result['conceptIdentifier'] = $paper->getConcept_identifier(); //will be added as a hidden element in js/submit/function.js
            }

            $result['status'] = (!$docId = $paper->alreadyExists()) ? 1 : 2;

            if ($result['status'] === 2) {
                $paper = Episciences_PapersManager::get($docId);
                if ($manageNewVersionErrors) {
                    $result['newVerErrors'] = $paper->manageNewVersionErrors(['version' => $version, 'isNewVersionOf' => $isNewVersionOf, 'rvId' => $rvId]);
                }
            }

            //Bloquer la soumission dans hal d'une notice vide :git #109
            if ('Hal' === Episciences_Repositories::getLabel($repoId)) {
                $isNotice = self::isHalNotice($result['record'], $id, 'dc');
                if (!$isNotice) { // pas une notice

                    $date = self::extractEmbargoDate($oai->getRecord($identifier, 'oai_dcterms'));

                    if ($date > date('Y-m-d')) {
                        if (!$translator) {
                            $date = ('9999-12-31' === $date) ?
                                ('Never') :
                                Episciences_View_Helper_Date::Date($date, Episciences_Tools::getLocale());

                            $error = "You can not submit this document; the file is not available; the end date of the embargo: $date";

                        } else {
                            $date = ('9999-12-31' === $date) ?
                                ($translator->translate('Jamais')) :
                                Episciences_View_Helper_Date::Date($date, Episciences_Tools::getLocale());

                            $error = $translator->translate("Vous ne pouvez pas soumettre ce document; le fichier est non disponible; fin d'embargo : ") . '<strong class="alert-warning">' . $date . '</strong>';

                        }
                        throw new Ccsd_Error('docUnderEmbargo: ' . $error);
                    }

                } else {
                    throw new Ccsd_Error('docIsNotice:');
                }

            } elseif ('arXiv' === Episciences_Repositories::getLabel($repoId)) { //  OAI interface supports only the notion of an arXiv article and not access to individual versions.
                $arXivRawRecord = $oai->getArXivRawRecord($identifier);
                $versionHistory = self::extractVersionsFromArXivRaw($arXivRawRecord);

                if (!in_array($version, $versionHistory)) {
                    $error = 'arXivVersionDoesNotExist:';
                    throw new Ccsd_Error($error);
                }

            } else {

                if ($isNewVersionOf) {
                    $oldPaper = Episciences_PapersManager::get($latestObsoleteDocId, false);
                    $hookHasDoiInfoRepresentsAllVersions = Episciences_Repositories::callHook('hookHasDoiInfoRepresentsAllVersions', ['repoId' => $repoId, 'record' => $result['record'], 'conceptIdentifier' => $oldPaper->getConcept_identifier()]);
                    if (array_key_exists('hasDoiInfoRepresentsAllVersions', $hookHasDoiInfoRepresentsAllVersions) && !$hookHasDoiInfoRepresentsAllVersions['hasDoiInfoRepresentsAllVersions']) {
                        $error = 'hookUnboundVersions: ';

                        if (!$translator) {

                            $error = 'You can not submit this document, please check that this is a new version.';

                        } else {
                            $error .= $translator->translate("Vous ne pouvez pas soumettre ce document, veuillez vérifier qu'il s'agit bien d'une nouvelle version.");

                        }

                        throw new Ccsd_Error($error);
                    }
                }

                $hookIsOpenAccessRight = Episciences_Repositories::callHook('hookIsOpenAccessRight', ['repoId' => $repoId, 'record' => $result['record']]);

                if (array_key_exists('isOpenAccessRight', $hookIsOpenAccessRight) && !$hookIsOpenAccessRight['isOpenAccessRight']) {
                    $error = 'hookIsNotOpenAccessRight: ';

                    if (!$translator) {

                        $error = 'You can not submit this document as the files will not be made publicly available and sharing will be made possible only by the approval of depositor of the original file.';

                    } else {

                        $error .= $translator->translate("Vous ne pouvez pas soumettre ce document car les fichiers ne seront pas mis à la disposition du public et le partage ne sera possible qu'avec l'approbation du déposant du fichier.");

                    }

                    throw new Ccsd_Error($error);
                }

            }

        } catch (Ccsd_Error $e) { // customized message : visible to the user
            $result['status'] = 0;

            if (!$translator) {
                $result['error'] = $e->parseError();

            } else {
                $result['error'] = '<b style="color: red;">' . $translator->translate('Erreur') . '</b> : ' . $translator->translate($e->parseError());
            }
            return ($result);
        } catch (Exception $e) { // other exceptions: generic message
            $result['status'] = 0;

            if (!$translator) {
                $result['error'] = $e->getMessage();

            } else {
                $result['error'] = '<b style="color: red;">' . $translator->translate('Erreur') . '</b> : ' . $translator->translate("Le document n'a pas été trouvé ou n'a pas pu être chargé.");

            }

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
     * @throws Zend_Session_Exception|JsonException
     */
    public function saveDoc($data, $paperId = null, $vid = null, $sid = null): array
    {

        $isCoiEnabled = false;

        // Initialisation
        $canReplace = (boolean)Ccsd_Tools::ifsetor($data['can_replace'], false); // remplacer ou pas la version V-1
        $oldStatus = (int)Ccsd_Tools::ifsetor($data['old_paper_status'], 0);
        $oldVersion = (int)Ccsd_Tools::ifsetor($data['old_version'], 1);
        $oldDocId = (int)Ccsd_Tools::ifsetor($data['old_docid'], 0);

        if ($canReplace) {
            try {
                $journalSettings = Zend_Registry::get('reviewSettings');
                $isCoiEnabled = isset($journalSettings[Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED]) && (int)$journalSettings[Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED] === 1;
            } catch (Zend_Exception $e) {
                trigger_error($e->getMessage());
            }
        }

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
        $values = $this->buildValuesToPopulatePaper($data, $paperId, $vid, $sid);

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

            if ($canReplace) {
                // delete all paper datasets
                Episciences_Paper_DatasetsManager::deleteByDocIdAndRepoId($oldDocId, $paper->getRepoid());
                // delete all paper files
                Episciences_Paper_FilesManager::deleteByDocId($oldDocId);
            }

            $response = Episciences_Repositories::callHook('hookFilesProcessing', ['repoId' => $paper->getRepoid(), 'identifier' => $paper->getIdentifier(), 'docId' => $paper->getDocid()]);

            Episciences_Repositories::callHook('hookLinkedDataProcessing', ['repoId' => $paper->getRepoid(), 'identifier' => $paper->getIdentifier(), 'docId' => $paper->getDocid(), 'response' => $response]);


            if (Episciences_Repositories::getApiUrl($paper->getRepoid())) {
                self::datasetsProcessing($paper->getDocid());
            }

            /** @var Episciences_User $user */
            $user = Episciences_Auth::getUser();
            $user->addRole(Episciences_Acl::ROLE_AUTHOR);

        } else {
            $message = '<strong>' . $translator->translate("Une erreur s'est produite pendant l'enregistrement de votre article.") . '</strong>';
            $messenger->setNamespace('error')->addMessage($message);
            $redirector->gotoUrl('submit');

        }

        $coverLetter = [
            "message" => $values['AUTHOR_COMMENT'],
            "attachedFile" => $values['FILE_AUTHOR']
        ];

        $this->saveCoverLetter($paper, $coverLetter);

        // Sauvegarder les options *************************************************************
        $this->saveAllAuthorSuggestions($data, $result);

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

        $paperUrl = $view->url([
            'controller' => 'paper',
            'action' => 'view',
            'id' => $paper->getDocid()]);

        $paperUrl = HTTP . '://' . $_SERVER['SERVER_NAME'] . $paperUrl;

        //Author infos
        /** @var Episciences_User $author */
        $author = Episciences_Auth::getUser();
        $aLocale = $author->getLangueid();

        $commonTags = [
            Episciences_Mail_Tags::TAG_ARTICLE_ID => $paper->getDocId(),
            Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $paper->getPaperid(),
            Episciences_Mail_Tags::TAG_CONTRIBUTOR_FULL_NAME => $author->getFullName()
        ];

        $authorTags = $commonTags + [
                Episciences_Mail_Tags::TAG_PAPER_URL => $paperUrl,
                Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($aLocale, true),
                Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $paper->formatAuthorsMetadata($aLocale)
            ];

        Episciences_Mail_Send::sendMailFromReview($author, $authorTemplateKy, $authorTags, $paper);

        //Mail aux rédacteurs + selon les paramètres de la revue, aux admins et secrétaires de rédactions.
        Episciences_Review::checkReviewNotifications($recipients, !empty($recipients));


        if ($isCoiEnabled) {

            // conflicts UIDs
            $cUidS = Episciences_Paper_ConflictsManager::fetchSelectedCol('by', ['answer' => Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes'], 'paper_id' => $paper->getPaperid()]);

            foreach ($recipients as $recipient) {

                $rUid = $recipient->getUid(); // current uid

                if (array_key_exists($rUid, $cUidS)) {
                    unset($recipients[$rUid]);
                }
            }

        }

        unset($recipients[$paper->getUid()]);

        if (!empty($recipients)) {
            self::notifyManagers($paper, $recipients, $oldDocId, $oldStatus, $commonTags, $canReplace);
        }

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

        if (!empty($autoAssignation) && in_array(Episciences_Review::SETTING_SYSTEM_CAN_ASSIGN_SECTION_EDITORS, $autoAssignation) && $sid) { // Asignation des rédacteurs d'une section
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
    public static function extractVersionsFromArXivRaw(array $rawRecord): array
    {
        $historyVersions = $rawRecord['metadata']['arXivRaw']['version'];
        $versions = [];
        foreach ($historyVersions as $index => $version) {
            if (is_array($version)) {
                $versions[] = substr($version['version'], 1); // supprimer le caractère 'v'
            } else if ($index === 'version') {
                $versions[] = substr($version, 1);
                // ne pas parcourir les autres elements
                return $versions;
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
        $docPattern = '<' . $format . ':identifier>https:\/\/(.*)\/' . $id . '(v\d+)?\/document<\/' . $format . ':identifier>';
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

        if ($available == null) {
            // HAL bug: HAL may reply with an empty dcterms:available element
            $available[0] = date('Y-m-d');
        }

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
    private static function notifyManagers(Episciences_Paper $paper, array $managers, $oldDocId, $oldPaperStatus = 0, array $tags = [], bool $canReplace = false)
    {
        $translator = Zend_Registry::get('Zend_Translate');
        $defaultTemplateKey = Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_EDITOR_COPY; // default template

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

            Episciences_Mail_Send::sendMailFromReview($recipient, $templateKey, $adminTags, $paper);
        }
    }

    /**
     * @param Episciences_Paper $paper
     * @param array $coverLetter
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Json_Exception
     */
    private function saveCoverLetter(Episciences_Paper $paper, array $coverLetter = ["message" => '', "attachedFile" => null]): void
    {
        // Save author comment and attached file
        $authorComment = new Episciences_Comment();
        $authorComment->setFilePath(REVIEW_FILES_PATH . $paper->getDocid() . '/comments/');
        $authorComment->setType(Episciences_CommentsManager::TYPE_AUTHOR_COMMENT);
        $authorComment->setDocid($paper->getDocid());
        $authorComment->setMessage($coverLetter["message"]);

        if ($paper->getUid())

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
        $jump = false;

        // Suggested reviewers
        if (array_key_exists('suggestReviewers', $data) &&
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
    private function buildValuesToPopulatePaper(array $data, $paperId = null, $vid = null, $sid = null): array
    {

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

        if (isset($data['concept_identifier'])) {
            $values['concept_identifier'] = $data['concept_identifier'];
        }

        return $values;

    }

    /**
     * @param array $post
     * @return array
     */
    private function getSuggestedEditorsFromPost(array $post): array
    {

        if (isset($post['suggestEditors']) && (!isset($post['can_replace']) || !$post['can_replace'])) {
            return array_filter((array)$post['suggestEditors'], static function ($value) {
                return !empty($value);
            });
        }

        return [];

    }

    /**
     * Update paper datasets
     * @param int $docId
     * @return int
     */
    public static function datasetsProcessing(int $docId): int
    {
        $cHeaders = [
            'headers' => ['Content-type' => 'application/json']
        ];

        $affectedRows = 0;

        try {
            $paper = Episciences_PapersManager::get($docId, false);

            $client = new Client($cHeaders);

            if (Episciences_Repositories::getLabel($paper->getRepoid()) === 'Hal') {
                $url = Episciences_Repositories::getApiUrl($paper->getRepoid()) . '/search/?indent=true&q=halId_s:' . $paper->getIdentifier() . '&fl=swhidId_s,researchData_s&version_i:' . $paper->getversion();
                $response = $client->get($url);
                $result = json_decode($response->getBody()->getContents(), true);
                $allDatasets = $result['response']['docs'][array_key_first($result['response']['docs'])];

                $data = [];
                $tmpData = [];

                /** @var array $datastes */
                foreach ($allDatasets as $key => $datasets) {
                    $tmpData['doc_id'] = $docId;
                    $tmpData['code'] = $key;
                    $tmpData['name'] = Episciences_Paper_Dataset::$_datasetsLabel[$key];
                    $tmpData['source_id'] = $paper->getRepoid();

                    foreach ($datasets as $value) {

                        $tmpData['value'] = $value;
                        $tmpData['link'] = Episciences_Paper_Dataset::$_datasetsLink[$key] . $value;
                        $data[] = $tmpData;
                    }

                    $tmpData = [];

                }

                $affectedRows = Episciences_Paper_DatasetsManager::insert($data);
                unset($tmpData, $data);
            }


        } catch (Zend_Db_Statement_Exception|GuzzleException  $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);

        }

        return $affectedRows;
    }

    /**
     * @param array $options
     * @return string
     * @throws Zend_Exception
     */
    private static function buildNewVersionDescription(array $options = []): string
    {
        $latestVersion = $options['version'] ?? 1;
        $repoId = $options['repoId'] ?? 0;
        $translator = Zend_Registry::get('Zend_Translate');
        $vCodeMsg = '<code>' . $latestVersion . '</code>';
        $description = $translator->translate('Veuillez vérifier :');
        $description .= '<ol><li>';
        $description .= $translator->translate('La version du document');
        $description .= ' ( ';
        $description .= $translator->translate('nombre uniquement');
        $description .= ', ' . mb_strtoupper($translator->translate('supérieur à ')) . ' ';
        $description .= $vCodeMsg;
        $description .= $translator->translate(' :');
        $description .= ' <mark>';
        $description .= $translator->translate('dernière version soumise à la revue');
        $description .= '</mark>';
        $description .= ' )</li><li>';
        $description .= $translator->translate("Ladite nouvelle version de l' article a bien été déposée dans l'archive ouverte");
        $description .= ' (<mark> ' . mb_strtoupper(Episciences_Repositories::getLabel($repoId)) . ' </mark>)';
        $description .= '</li></ol>';

        return $description;

    }

    /**
     * @param Episciences_Paper $paper
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    private static function fetchNewVersionFormDefaultValues(Episciences_Paper $paper): array
    {
        $repository = $paper->getRepoid();
        $hasHook = $paper->hasHook;

        if ($repository) {
            $defaults = [
                'hasHook' => $hasHook,
                'docId' => !$hasHook ? $paper->getIdentifier() : '', //NB. Pour Zenodo, un identifiant différent par version, d’où l’initialisation de sa valeur par défaut à ''
                'version' => $paper->getVersion(),
                'repoId' => $repository
            ];

        } else { // tmp version

            $latestSubmission = Episciences_PapersManager::getLastPaper($paper->getPaperid());

            if ($latestSubmission) {
                $hasHook = $latestSubmission->hasHook;
            }

            $defaults = [
                'hasHook' => $hasHook,
                'docId' => !$hasHook ? $latestSubmission->getIdentifier() : '',
                'version' => $latestSubmission->getVersion(),
                'repoId' => $latestSubmission->getRepoid()
            ];
        }

        return $defaults;

    }

    /**
     * fetch labels from repositories
     * @param array $settings
     * @return array
     */
    public static function getRepositoriesLabels(array $settings = []): array
    {

        // fetch repositories
        if (array_key_exists('repositories', $settings) && !empty($settings['repositories'])) {
            $repositories = $settings['repositories'];
        } else {
            $repositories = array_keys(Episciences_Repositories::getRepositories());
            unset($repositories[0]);
        }

        $options = [];

        foreach ($repositories as $repoId) {
            $label = Episciences_Repositories::getLabel($repoId);

            if ('' !== $label) {
                $options[$repoId] = Episciences_Repositories::getLabel($repoId);
            }
        }

        return $options;

    }

    /**
     * @param Zend_Form $form
     * @param bool $required
     * @return Zend_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */

    public static function addPaperArxivPwdElement(Zend_Form $form, bool $required): Zend_Form
    {

        $translator = Zend_Registry::get('Zend_Translate');

        $description = !$required ?
            $translator->translate("Si vous le souhaitez et si la revue vous le demande, vous pouvez partager") :
            $translator->translate('La revue vous demande de partager');
        $description .= ' ';
        $description .= $translator->translate("ici le mot de passe papier lui permettant de mettre à jour ce papier dans arXiv, en particulier pour mettre en ligne la version finale mise en page de votre article. Ce mot de passe est chiffré et sera automatiquement supprimé à la publication de l'article. Seuls les gestionnaires de votre article ont accès à ce mot de passe.");
        $description .= ' (';
        $description .= sprintf(ucfirst($translator->translate("le nombre maximum de caractères autorisé est de <strong>%u</strong>")), MAX_PWD_INPUT_SIZE);
        $description .= ')';


        $form->addElement('password', 'paperPassword', ([
            'required' => $required,
            'autocomplete' => 'off',
            'label' => $translator->translate('Mot de passe papier arXiv'),
            'maxlength' => MAX_PWD_INPUT_SIZE,
            'description' => $description,
            'style' => 'width:55%;'

        ]));

        $form->addElement('hidden', 'h_requiredPwd', ([
            'value' => (int)$required
        ]));

        return $form;

    }

    private function getHookParams(){

    }

}
