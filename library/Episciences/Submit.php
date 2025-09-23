<?php

use Episciences\DataSet;
use Episciences\Files\File;
use Episciences\Files\Uploader;
use Episciences\Paper\DataDescriptor;
use Episciences\Paper\DataDescriptorManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Episciences_Submit
{
    public const SUBMIT_DOCUMENT_LABEL = 'Proposer un document';

    public const COVER_LETTER_FILE_ELEMENT_NAME = 'file_comment_author';
    public const COVER_LETTER_COMMENT_ELEMENT_NAME = 'author_comment';
    public const DD_FILE_ELEMENT_NAME = 'file_data_descriptor';
    public const DD_PREVIOUS_VERSION_STR = 'previous_dataset_version_number';
    protected $_db = null;

    public function __construct()
    {
        $this->_db = Zend_Db_Table_Abstract::getDefaultAdapter();
    }

    /**
     * @param array $settings
     * @param array|null $defaults
     * @param bool $isFromZSubmit
     * @return Ccsd_Form
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */

    public static function getForm(array $settings = [], ?array $defaults = [], bool $isFromZSubmit = false): \Ccsd_Form
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

        $hookVersion = isset($defaults['repoId']) ? Episciences_Repositories::callHook('hookIsRequiredVersion', ['repoId' => $defaults['repoId']]) : [];
        $isRequiredVersionField = empty($hookVersion) || (isset($hookVersion['result']) && $hookVersion['result']);

        // Champ texte : version du document

        if ($isRequiredVersionField) {
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

        $form->addElement('hidden', 'h_enrichment',
            ['decorators' => [
                'ViewHelper',
            ]]);

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


        $form->addElement('textarea', self::COVER_LETTER_COMMENT_ELEMENT_NAME, [
            'label' => 'Commentaire', 'rows' => 5,
            'description' => $descriptionAllowedToSeeCoverLetterTranslated,
            'validators' => [['StringLength', false, ['max' => MAX_INPUT_TEXTAREA]]]
        ]);
        $group[] = self::COVER_LETTER_COMMENT_ELEMENT_NAME;

        // Attached file
        $extensions = ALLOWED_EXTENSIONS;
        $implode_extensions = implode(',', $extensions);
        $description = Episciences_Tools::buildAttachedFilesDescription($extensions, '.&nbsp;' . $descriptionAllowedToSeeCoverLetterTranslated);

        $form->addElement('file', self::COVER_LETTER_FILE_ELEMENT_NAME, [
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

        $group[] = self::COVER_LETTER_FILE_ELEMENT_NAME;

        $form = self::addDdElement($form, $group);


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
                ['HtmlTag', ['tag' => 'div', 'class' => "col-md-4", 'style' => 'margin-top: 20px; margin-bottom: 20px']]
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
     * @throws Zend_Db_Statement_Exception
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
     * @return array
     * @throws Zend_Db_Adapter_Exception
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
     * @throws Zend_Exception
     */
    public static function getNewVersionForm(Episciences_Paper $paper, array $settings = []): ?Ccsd_Form
    {
        $translator = Zend_Registry::get('Zend_Translate');
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
            $placeholder = $translator->translate("Saisir l'identifiant du document");

            $isRequiredIdentifier = $defaults['isRequiredIdentifier'] ?? true; // The identifier field will be empty

            $docIdOptions = [
                'label' => 'Identifiant du document',
                'disabled' => true,
                'value' => $defaults['docId'],
                'placeholder' => $placeholder,
                'style' => 'width:33%;',
            ];

            if (!$isRequiredIdentifier) {
                unset($docIdOptions['disabled']); // make it editable for entering the identifier
            }

            // Champ texte : identifiant du document
            $subform->addElement('text', 'docId', $docIdOptions);

            $subform->addElement('hidden', 'h_docId');

            if (!$hasHook || Episciences_Repositories::callHook('hookIsRequiredVersion', ['repoId' => $defaults['repoId']])['result']) {

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

                if (isset($defaults['hasHook']) && $defaults['hasHook']) {
                    $subform->addElement('hidden', 'h_hasHook', ['value' => $defaults['hasHook']]);
                }

                if ($paper->isTmp()) {

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

            $form->addElement('hidden', 'h_enrichment',
                ['decorators' => [
                    'ViewHelper',
                ]]);

            // Author's comments and Cover Letter [new version]
            // Keep in sync with paper views where these roles have access to the comments and cover letter
            $allowedToSeeCoverLetterTranslated = [];
            foreach ([Episciences_Acl::ROLE_CHIEF_EDITOR_PLURAL, Episciences_Acl::ROLE_EDITOR_PLURAL, Episciences_Acl::ROLE_REVIEWER_PLURAL] as $roleAllowedToSee) {
                $allowedToSeeCoverLetterTranslated[] = Zend_Registry::get('Zend_Translate')->translate($roleAllowedToSee);
            }

            $descriptionAllowedToSeeCoverLetterTranslated = Zend_Registry::get('Zend_Translate')->translate('Visible par : ') . implode(', ', $allowedToSeeCoverLetterTranslated);

            $form->addElement('textarea', self::COVER_LETTER_COMMENT_ELEMENT_NAME, [
                'label' => 'Commentaire', 'rows' => 5,
                'decscription' => $descriptionAllowedToSeeCoverLetterTranslated,
                'validators' => [[
                    'StringLength', false, ['max' => MAX_INPUT_TEXTAREA]
                ]]
            ]);
            $group[] = self::COVER_LETTER_COMMENT_ELEMENT_NAME;


            // Attached file [new version]
            $extensions = ALLOWED_EXTENSIONS;
            $implode_extensions = implode(',', $extensions);
            $description = Episciences_Tools::buildAttachedFilesDescription($extensions, '.&nbsp;' . $descriptionAllowedToSeeCoverLetterTranslated);
            $form->addElement('file', self::COVER_LETTER_FILE_ELEMENT_NAME, [
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

            $group[] = self::COVER_LETTER_FILE_ELEMENT_NAME;

            if (isset($settings['dataType'])) {
                self::addDdElement($form, $group, $settings['dataType']);
            }

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
     * @param bool $isEpiNotify
     * @return array
     * @throws Zend_Exception
     */
    public static function getDoc($repoId, $id, int $version = null, $latestObsoleteDocId = null, $manageNewVersionErrors = true, int $rvId = null, bool $isEpiNotify = false): array
    {

        if (defined('RVID') && !Ccsd_Tools::isFromCli()) {
            $rvId = RVID;
        }

        $isNewVersionOf = !empty($latestObsoleteDocId); // empty $latestObsoleteDocId if submitted  via "Submit an article". (submit/index)
        $result = [];
        $id = trim($id);

        $hookCleanIdentifiers = Episciences_Repositories::callHook('hookCleanIdentifiers', ['id' => $id, 'repoId' => $repoId]);

        if (!empty($hookCleanIdentifiers)) {
            $id = $hookCleanIdentifiers['identifier'];
        }

        $translator = !Ccsd_Tools::isFromCli() ? Zend_Registry::get('Zend_Translate') : null;

        try {

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

            $paper = new Episciences_Paper(['rvid' => $rvId, 'version' => $version, 'repoid' => $repoId, 'identifier' => $id]);
            //The version of the article is not taken into account when checking its existence locally.
            if (!$isNewVersionOf) { // re-submit an article via "Submit an article". (submit/index)
                $paper->setVersion(null);
            }

            // Use enriched record from hookApiRecords if available, otherwise fallback to OAI
            if (!empty($hookApiRecord['record'])) {
                $result['record'] = $hookApiRecord['record'];
            } elseif ($oai) {
                $result['record'] = $oai->getRecord($identifier);
                $type = Episciences_Tools::xpath($result['record'], '//dc:type');

                if (!empty($type)) {
                    $result[Episciences_Repositories_Common::ENRICHMENT][Episciences_Repositories_Common::RESOURCE_TYPE_ENRICHMENT] = $type;
                }
            } else {
                if (isset($hookApiRecord['error'])) {
                    throw new Ccsd_Error(Ccsd_Error::ID_DOES_NOT_EXIST_CODE);
                }
                $result['record'] = null;
            }



            if (isset($hookApiRecord[Episciences_Repositories_Common::ENRICHMENT])) {
                $result[Episciences_Repositories_Common::ENRICHMENT] = $hookApiRecord[Episciences_Repositories_Common::ENRICHMENT];
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
                $paper = Episciences_PapersManager::get($docId, true, $rvId);
                if ($manageNewVersionErrors) {
                    $result['newVerErrors'] = $paper->manageNewVersionErrors(['version' => $version, 'isNewVersionOf' => $isNewVersionOf, 'rvId' => $rvId, 'isEpiNotify' => $isEpiNotify]);
                }
            }

            //Bloquer la soumission dans hal d'une notice vide :git #109
            if (Episciences_Repositories::isFromHalRepository($repoId)) {
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

                if (!in_array($version, $versionHistory, false)) {
                    $error = 'arXivVersionDoesNotExist:';
                    throw new Ccsd_Error($error);
                }

            } else {

                if ($isNewVersionOf) {
                    $oldPaper = Episciences_PapersManager::get($latestObsoleteDocId, false, $rvId);
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
            $parsedError = $e->parseError();

            $mailToStr = sprintf('<a href="mailto:%s">%s</a>', EPISCIENCES_SUPPORT, EPISCIENCES_SUPPORT);

            $error = $translator ? $translator->translate($parsedError) : $parsedError;

            if (
                str_contains($e->getMessage(), Ccsd_Error::ID_DOES_NOT_EXIST_CODE) ||
                str_contains($e->getMessage(), Ccsd_Error::ARXIV_VERSION_DOES_NOT_EXIST_CODE)
            ) {
                $error = sprintf($error, $mailToStr, Episciences_Repositories::getLabel($repoId), Episciences_Repositories::getIdentifierExemple($repoId));
            } elseif (str_contains($parsedError, Ccsd_Error::DEFAULT_PREFIX_CODE)) {
                $error = sprintf($error, $e->getMessage(), $mailToStr, Episciences_Repositories::getLabel($repoId), Episciences_Repositories::getIdentifierExemple($repoId));
            }

            if (!$translator) {
                $result['error'] = $error;

            } else {
                $result['error'] = '<b style="color: red;">' . $translator->translate('Erreur') . '</b> : ' . $error;
            }

            return $result;

        } catch (Exception $e) { // other exceptions: generic message
            $result['status'] = 0;

            if (!$translator) {
                $result['error'] = $e->getMessage();

            } else {
                $result['error'] = '<b style="color: red;">' . $translator->translate('Erreur') . '</b> : ' . $e->getMessage();

            }

            return $result;
        }

        return $result;
    }

    /**
     * @param Episciences_Paper $paper
     * @param array $datasets
     * @return int
     */
    public static function forceAddingDatasets(Episciences_Paper $paper, array $datasets = []): int
    {
        if (empty($datasets)) {
            return 0;
        }

        $data = [];
        $tmpData['doc_id'] = $paper->getDocid();
        $tmpData['source_id'] = $paper->getRepoid();

        foreach ($datasets as $code => $dataset) {

            if (!array_key_exists($code, Episciences_Paper_Dataset::$_datasetsLabel)) {
                $code = Episciences_Paper_Dataset::UNDEFINED_CODE;
            }

            $tmpData['relationship'] = $dataset['relation'] ?? Episciences_Paper_DatasetsManager::RELATION_TYPE_SOFTWARE;
            $tmpData['code'] = $code;
            $tmpData['name'] = Episciences_Paper_Dataset::$_datasetsLabel[$code];
            $tmpData['link'] = Episciences_Paper_Dataset::$_datasetsLink[$code];

            foreach ($dataset as $value) {
                $tmpData['value'] = $value;
                $data[] = $tmpData;
            }
        }
        return Episciences_Paper_DatasetsManager::insert($data);
    }

    /**
     * @param array $data
     * @param int|null $paperId
     * @param int|null $vid
     * @param int|null $sid
     * @return array
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_File_Transfer_Exception
     * @throws Zend_Json_Exception
     * @throws Zend_Mail_Exception
     * @throws Zend_Session_Exception
     */

    public function saveDoc(array $data, ?int $paperId = null, ?int $vid = null, ?int $sid = null): array
    {

        $isCoiEnabled = false;

        $enrichment = [];
        $isEnrichment = isset($data['h_enrichment']) && $data['h_enrichment'] !== '';

        if ($isEnrichment) {
            try {

                $enrichment = json_decode($data['h_enrichment'], true, 512, JSON_THROW_ON_ERROR);
                if (isset($enrichment['type']) && $enrichment['type'] === Episciences_Paper::TEXT_TYPE_TITLE) {
                    $enrichment['type'] = Episciences_Paper::DEFAULT_TYPE_TITLE;
                }

            } catch (Exception $e) {
                trigger_error($e->getMessage());
            }
        }

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

        /**Zend_Translate $translatof */
        $translator = Zend_Registry::get('Zend_Translate');
        /** @var Zend_Controller_Action_Helper_Redirector $redirector */
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        /** @var Zend_Controller_Action_Helper_FlashMessenger $messenger */
        $messenger = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');

        $result = [
            'code' => 0,
            'message' => ''
        ];


        $values = $this->buildValuesToPopulatePaper($data, $paperId, $vid, $sid);

        $paper = !Episciences_Repositories::isDataverse($values['REPOID']) ?
            new Episciences_Paper($values) :
            new DataSet($values);

        if ($paper->alreadyExists()) {
            $message = '<strong>' . $translator->translate("L'article que vous tentez d'envoyer existe déjà.") . '</strong>';
            $messenger->setNamespace('error')->addMessage($message);
            $redirector->gotoUrl((new Episciences_View_Helper_Url())->url(['controller' => 'submit']));
        }

        $paper->setWhen();

        if ($paper->save()) {

            $docId = $paper->getDocid();

            if (!$canReplace && !$paper->getPaperid()) {
                $paper->setPaperid($docId);
                $paper->save();
            }

            /** @var Episciences_User $user */
            $user = Episciences_Auth::getUser();
            $user->addRole(Episciences_Acl::ROLE_AUTHOR);

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

            $hookParams = ['repoId' => $paper->getRepoid(), 'identifier' => $paper->getIdentifier(), 'docId' => $paper->getDocid()];

            Episciences_Repositories::callHook('hookFilesProcessing', ($isEnrichment && isset($enrichment['files'])) ? array_merge($hookParams, ['files' => $enrichment['files']]) : $hookParams);
            Episciences_Repositories::callHook('hookLinkedDataProcessing', array_merge($hookParams));


            if (
                Episciences_Repositories::hasHook($paper->getRepoid()) === '' && Episciences_Repositories::getApiUrl($paper->getRepoid())) {
                self::datasetsProcessing($paper);
            }

            if (!isset($enrichment[Episciences_Repositories_Common::CONTRIB_ENRICHMENT])) {
                // insert author dc:creator to json author in the database
                Episciences_Paper_AuthorsManager::InsertAuthorsFromPapers($paper);
            }

            self::enrichmentProcess($paper, $enrichment);

            try {
                if (Episciences_Repositories::isFromHalRepository($paper->getRepoid())) { // try to enrich with TEI HAL
                    Episciences_Paper_AuthorsManager::enrichAffiOrcidFromTeiHalInDB($paper->getRepoid(), $paper->getPaperid(), $paper->getIdentifier(), (int)$paper->getVersion());
                }
            } catch (JsonException|\Psr\Cache\InvalidArgumentException $e) {
                trigger_error($e->getMessage());
            }


        } else {
            $message = '<strong>' . $translator->translate("Une erreur s'est produite pendant l'enregistrement de votre article.") . '</strong>';
            $messenger->setNamespace('error')->addMessage($message);
            $redirector->gotoUrl((new Episciences_View_Helper_Url())->url(['controller' => 'submit']));

        }

        $this->processCoverLetterAndDataDescriptor($paper, $data);

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

        $paperUrl = SERVER_PROTOCOL . '://' . $_SERVER['SERVER_NAME'] . $paperUrl;

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

        Episciences_Mail_Send::sendMailFromReview($author, $authorTemplateKy, $authorTags, $paper, null, [], false, $paper->getCoAuthors());

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
     * @param null $sid : l'ID de la rubrique; Null par defaut
     * @param null $vid : l'ID du volume; Null par defaut
     * @return array : les Editeurs assignés à l'articles
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
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

        if (!empty($autoAssignation) && in_array(Episciences_Review::SETTING_SYSTEM_CAN_ASSIGN_CHIEF_EDITORS, $autoAssignation, true)) { // Assignation de rédacteurs en chef
            $chiefEditors = $review::getChiefEditors();
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
     * @throws Zend_Db_Statement_Exception
     */

    private function getVolumeEditors($vid, Episciences_Review $review): array
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
     * @throws Zend_Db_Statement_Exception
     */
    private function findSuggestEditors(array $suggestEditors): array
    {

        $editors = [];

        if (!empty($suggestEditors)) {
            foreach ($suggestEditors as $editorId) {
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
            if (is_array($version)) { // exp. ['v1', 'v2', 'v3'..]
                $versions[] = substr($version['version'], 1);
            } else if ($index === 'version') {
                $versions[] = substr($version, 1);
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
    private static function isHalNotice(string $record, string $id, string $format = 'dcterms'): bool
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
    private static function extractEmbargoDate(string $record): string
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
    public static function addIfNotExists(array $input, array &$output, string $tag = ''): void
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
    private static function notifyManagers(Episciences_Paper $paper, array $managers, int $oldDocId, int $oldPaperStatus = 0, array $tags = [], bool $canReplace = false): void
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

        $paperUrl = SERVER_PROTOCOL . '://' . $_SERVER['SERVER_NAME'] . $paperUrl;

        $adminTags[Episciences_Mail_Tags::TAG_PAPER_URL] = $paperUrl; // Lien de gestion de l'article


        if ($canReplace) {
            if ($oldPaperStatus === Episciences_Paper::STATUS_REFUSED) {// Si l'article a été déjà refusé
                $message = 'Cet article a été précédemment refusé dans sa première version, pour le consulter, merci de suivre ce lien : ';
                // Lien vers l'article qui a été déjà refusé
                $refusedPaperUrl = $view->url([
                    'controller' => 'administratepaper',
                    'action' => 'view',
                    'id' => $oldDocId

                ]);

                $refusedPaperUrl = SERVER_PROTOCOL . '://' . $_SERVER['SERVER_NAME'] . $refusedPaperUrl;

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
     * @param array $data
     * @param array $result
     * @throws Zend_Exception
     */
    private function saveAllAuthorSuggestions(array $data, array &$result): void
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
        $values['AUTHOR_COMMENT'] = $data[self::COVER_LETTER_COMMENT_ELEMENT_NAME];
        $values['FILE_AUTHOR'] = $data[self::COVER_LETTER_FILE_ELEMENT_NAME];

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
     * @param Episciences_Paper $paper
     * @return int
     */
    public static function datasetsProcessing(Episciences_Paper $paper): int
    {
        $cHeaders = [
            'headers' => ['Content-type' => 'application/json']
        ];

        try {

            if (Episciences_Repositories::isFromHalRepository($paper->getRepoid())) {
                $client = new Client($cHeaders);
                $url = Episciences_Repositories::getApiUrl($paper->getRepoid()) . '/search/?indent=true&q=halId_s:' . $paper->getIdentifier() . '&fl=swhidId_s,researchData_s&version_i:' . $paper->getversion();
                $response = $client->get($url);
                $result = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
                $allDatasets = $result['response']['docs'][array_key_first($result['response']['docs'])] ?? [];
                return self::processDatasets($paper, $allDatasets);
            }

        } catch (GuzzleException|JsonException  $e) {
            trigger_error($e->getMessage());
        }

        return 0;

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
        $description .= $translator->translate("La nouvelle version a bien été mise en ligne dans l'archive ouverte/l'entrepôt");
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

        $isTmp = $paper->isTmp();

        $hasHook = $paper->hasHook;  // @see Episciences_Paper::setRepoid()
        $repository = $paper->getRepoid();
        $identifier = $paper->getIdentifier();
        $version = (int)$paper->getVersion();

        if ($isTmp) {

            $firstSubmission = Episciences_PapersManager::get($paper->getPaperid());

            if ($firstSubmission) {
                $repository = $firstSubmission->getRepoid();
                $hasHook = $firstSubmission->hasHook;
                $identifier = $firstSubmission->getIdentifier();
            }

        }

        $isRequiredIdentifier = !$hasHook || $repository !== (int)Episciences_Repositories::ZENODO_REPO_ID; //  The identifier field will be empty

        $defaults['hasHook'] = $hasHook;
        $defaults['isRequiredIdentifier'] = $isRequiredIdentifier;
        $defaults['repoId'] = $repository;
        $defaults['docId'] = $isRequiredIdentifier ? $identifier : ''; //NB. Pour Zenodo, un identifiant différent par version, d’où l’initialisation de sa valeur par défaut à ''
        $defaults['version'] = $version;

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

    /**
     * @param Episciences_Paper $paper
     * @param array $enrichment
     * @return int
     */

    public static function enrichmentProcess(Episciences_Paper $paper, array $enrichment = []): int
    {

        if (empty ($enrichment)) {
            return 0;
        }

        $insertedRows = 0;

        $jsonVals = [];

        foreach ($enrichment as $key => $values) {

            if (!in_array($key, Episciences_Repositories_Common::AVAILABLE_ENRICHMENT, true)) {
                continue;
            }

            $paperId = $paper->getPaperId();

            try {
                $jsonVals = json_encode($values, JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            } catch (JsonException $e) {
                trigger_error($e->getMessage());
            }

            if ($key === Episciences_Repositories_Common::CONTRIB_ENRICHMENT) {

                $authors = Episciences_Paper_AuthorsManager::getAuthorByPaperId($paperId);

                if (empty($authors)) { // to prevent manual changes being overwritten.

                    $insertedRows += Episciences_Paper_AuthorsManager::insert([
                        [
                            'authors' => $jsonVals,
                            'paperId' => $paperId
                        ]
                    ]);

                } elseif ($paper->getRepoid() !== (int)Episciences_Repositories::ARXIV_REPO_ID){
                    $authors = new Episciences_Paper_Authors([
                        'authors' => $jsonVals,
                        'paperId' => $paperId
                    ]);

                    $insertedRows += Episciences_Paper_AuthorsManager::update($authors);

                }

            } elseif ($key === Episciences_Repositories_Common::CITATIONS) {


                if (!empty($values)) {

                    $oCitations = new Episciences_Paper_Citations();

                    $oCitations
                        ->setCitation($jsonVals)
                        ->setDocId($paper->getDocid())
                        ->setSourceId($paper->getRepoid());

                    $insertedRows += Episciences_Paper_CitationsManager::insert([$oCitations]);

                }

            } elseif ($key === Episciences_Repositories_Common::REFERENCES_EPI_CITATIONS) {
                // todo to be saved in REFERENCES EPI CITATIONS

            } elseif ($key === Episciences_Repositories_Common::PROJECTS) {

                $data = [
                    'funding' => $jsonVals,
                    'paperId' => $paper->getPaperid(),
                    'source_id' => $paper->getRepoid()
                ];

                $insertedRows += Episciences_Paper_ProjectsManager::insert($data);
            } elseif ($key === Episciences_Repositories_Common::RESOURCE_TYPE_ENRICHMENT) {

                $paper->setType(self::processAndPrepareType($values));
                $paper->forceType();

                try {
                    if ($paper->save()) {
                        ++$insertedRows;
                    }
                } catch (Zend_Db_Adapter_Exception $e) {
                    trigger_error($e->getMessage());
                }
            }
        }
        return $insertedRows;
    }

    /**
     * @param string|array|null $type
     * @return array
     */

    public static function processAndPrepareType(string|array|null $type = null): array
    {

        if (empty($type)) {
            return [];
        }

        $processedType = [];
        $type = !is_array($type) ? [$type] : $type;
        $currentType = strtolower($type[array_key_first($type)]);

        if (str_contains($currentType, 'info:eu-repo/semantics/')) {
            $currentType = str_replace('info:eu-repo/semantics/', '', $currentType);
        }

        if ($currentType === Episciences_Paper::OTHER_TYPE) {
            $currentType = strtolower($type[array_key_last($type)]);
        }

        $currentType = str_replace(
            search: [
                ' ',
                Episciences_Paper::JOURNAL_ARTICLE_TYPE_TITLE,
                ' ',
                Episciences_Paper::REGULAR_ARTICLE_TYPE_TITLE,
                Episciences_Paper::WORKING_PAPER_TYPE_TITLE,
                Episciences_paper::PUBLICATION_TYPE_TITLE,
                Episciences_paper::JOURNAL_TYPE_TITLE,
                Episciences_Paper::CONFERENCE_PAPER_TYPE_TITLE

            ],
            replace: [
                '',
                Episciences_Paper::ARTICLE_TYPE_TITLE,
                '',
                Episciences_Paper::ARTICLE_TYPE_TITLE,
                Episciences_Paper::ARTICLE_TYPE_TITLE,
                Episciences_Paper::ARTICLE_TYPE_TITLE,
                Episciences_Paper::ARTICLE_TYPE_TITLE,
                Episciences_Paper::CONFERENCE_TYPE
            ],
            subject: $currentType
        );


        if (in_array($currentType, Episciences_Paper::PREPRINT_TYPES, true)) {
            $currentType = Episciences_Paper::DEFAULT_TYPE_TITLE;
        }

        $processedType[Episciences_Paper::TITLE_TYPE] = $currentType;


        return $processedType;

    }


    public static function processDatasets(Episciences_Paper|int $paper, ?array $allDatasets = []): int
    {
        $affectedRows = 0;
        if (!$allDatasets) {
            return $affectedRows;
        }

        $current = $paper;

        if (!($paper instanceof Episciences_Paper)) {

            try {
                $current = Episciences_PapersManager::get($paper, false);
            } catch (Zend_Db_Statement_Exception $e) {
                trigger_error($e->getMessage());
                return 0;
            }

        }

        $noProcessed = [];
        $docId = $current->getDocid();
        $repoId = $current->getRepoid();

        $options = ['sourceId' => $repoId];




        foreach ($allDatasets as $datasets) {
/*
            ["identifier"] => string(48) "https://hdl.handle.net/21.11115/0000-0016-7FC9-8"
            ["relation"] => string(7) "HasPart"
            ["resource_type"] => string(7) "dataset"
            ["scheme"] => string(3) "url"
*/
            foreach ($datasets as $key => $value) {


                // Skip existing Dataset
                if (Episciences_Paper_DatasetsManager::findByValue($docId, $value) !== null) {
                    continue;
                }

                if ($repoId === (int)Episciences_Repositories::ZENODO_REPO_ID|| $repoId === (int)Episciences_Repositories::ARCHE_ID) {

                    if ($key !== 'identifier') {
                        continue;
                    }

                    $options['relationship'] = $datasets['relation'] ?? Episciences_Paper_DatasetsManager::RELATION_TYPE_SOFTWARE;
                    $datasets = $value;
                }



                $value = trim($value);
                $typeLd = Episciences_Tools::checkValueType($value);



                if ($typeLd === Episciences_Paper_Dataset::DOI_CODE || Episciences_Tools::isDoiWithUrl($value)) {
                    $result = Episciences_DoiTools::getMetadataFromDoi($value);

                    if (!empty($result)) {

                        try {
                            $aResult = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
                            $typeFromCsl = $aResult['type'] ?? Episciences_Paper_Dataset::UNDEFINED_CODE;

                            if (!array_key_exists($typeFromCsl, Episciences_Paper_Dataset::$_datasetsLabel)) {
                                trigger_error(sprintf('[Paper [#%s]: Add missing code "%s" to %s', $docId, $typeFromCsl, 'Episciences_Paper_Dataset::$_datasetsLabel'));
                                $code = Episciences_Paper_Dataset::UNDEFINED_CODE;
                            } else {
                                $code = Episciences_Paper_Dataset::$_datasetsLabel[$typeFromCsl];
                            }

                        } catch (JsonException $e) {
                            $code = Episciences_Paper_Dataset::UNDEFINED_CODE;
                            trigger_error($e->getMessage());
                        }

                        $epiDM = new Episciences_Paper_DatasetMetadata();
                        $epiDM->setMetatext($result);
                        $idMetaDataLastId = Episciences_Paper_DatasetsMetadataManager::insert([$epiDM]);
                        $affectedRows += Episciences_Paper_DatasetsManager::addDatasetFromSubmission($docId, $typeLd, $value, $code, $idMetaDataLastId, $options);

                    } else {
                        $noProcessed[$typeLd] = $datasets;
                    }
                } elseif ($typeLd === Episciences_Paper_Dataset::SOFTWARE_CODE) {
                    $affectedRows += Episciences_Paper_DatasetsManager::addDatasetFromSubmission($docId, $typeLd, $value, $typeLd, null, $options);
                } elseif ($typeLd === Episciences_Paper_Dataset::HANDLE_CODE) {
                    $value = Episciences_Tools::cleanHandle($value);
                    $affectedRows += Episciences_Paper_DatasetsManager::addDatasetFromSubmission($docId, $typeLd, $value, $typeLd, null, $options);
                }
                if (!empty($noProcessed)) {
                    $affectedRows += $affectedRows = self::forceAddingDatasets($current, $noProcessed);
                }

            }
        }
        return $affectedRows;

    }

    /**
     * @param Episciences_Paper $paper
     * @param array $data
     * @return void
     * @throws Zend_File_Transfer_Exception
     */

    public function processCoverLetterAndDataDescriptor(Episciences_Paper $paper, array $data): void
    {

        $filesPath = [];

        if (isset($data[self::COVER_LETTER_FILE_ELEMENT_NAME])) {
            $filesPath[self::COVER_LETTER_FILE_ELEMENT_NAME] = sprintf('%s/comments/', REVIEW_FILES_PATH . $paper->getDocid());
        }

        if (isset($data[self::DD_FILE_ELEMENT_NAME])) {
            $filesPath[self::DD_FILE_ELEMENT_NAME] = sprintf('%s/dd/', REVIEW_FILES_PATH . $paper->getDocid());
        }


        $uploader = new Uploader($filesPath);

        $uploadsInfo = $uploader->upload()->getInfo();

        self::saveDataDescriptor($uploadsInfo, $paper);

        $coveLetterFile = $data[self::COVER_LETTER_FILE_ELEMENT_NAME] ?? '';

        if (isset($uploadsInfo[Uploader::UPLOADED_FILES_KEY][self::COVER_LETTER_FILE_ELEMENT_NAME])) {
            /** @var File $coveLetterFile */
            $coveLetterFile = $uploadsInfo[Uploader::UPLOADED_FILES_KEY][self::COVER_LETTER_FILE_ELEMENT_NAME];
            $coveLetterFile = $coveLetterFile->getName();
        }

        $coverLetter = [
            "message" => $data[self::COVER_LETTER_COMMENT_ELEMENT_NAME] ?? '',
            "attachedFile" => $coveLetterFile
        ];

        Episciences_CommentsManager::saveCoverLetter($paper, $coverLetter, true);

    }

    /**
     * @param Zend_Form $form
     * @param array $group
     * @param string $type
     * @param bool $withRequiredHiddenElement
     * @return Zend_Form
     * @throws Zend_Form_Exception
     */
    private static function addDdElement(Zend_Form $form, array &$group , string $type = Episciences_Paper::DATASET_TYPE_TITLE, bool $withRequiredHiddenElement = true): Zend_Form
    {

        $availableExtensions = ['pdf'];

        if ($type === (Episciences_Paper::SOFTWARE_TYPE_TITLE)) {
            $label = 'Descripteur de logiciel';
        } elseif ($type === Episciences_Paper::DATASET_TYPE_TITLE) {
            $label = 'Descripteur de données';
        } else {
            $label = '';
        }

        $form->addElement('file', self::DD_FILE_ELEMENT_NAME, [
            'required' => true,
            'id' => self::DD_FILE_ELEMENT_NAME,
            'label' => $label,
            'description' => Episciences_Tools::buildAttachedFilesDescription($availableExtensions),
            'valueDisabled' => true,
            'maxFileSize' => MAX_FILE_SIZE,
            'validators' => [
                'Count' => [false, 1],
                'Extension' => [false, implode(',', $availableExtensions)],
                'Size' => [false, MAX_FILE_SIZE]
            ]
        ]);


        if ($withRequiredHiddenElement) {
            $hiddenElementName = sprintf('%s_is_required', self::DD_FILE_ELEMENT_NAME);
            $form->addElement(new Zend_Form_Element_Hidden($hiddenElementName));
        }

        if ($group) {
            $group[] = self::DD_FILE_ELEMENT_NAME;
            if(isset($hiddenElementName)){
                $group[] = $hiddenElementName;
            }
        }

        return $form;
    }


    /**
     * DD new version form
     * @return Ccsd_Form
     */
    public static function getDDNewVersionForm(): \Ccsd_Form
    {
        $form = new Ccsd_Form();

        $form->setAttrib('enctype', 'multipart/form-data');
        $form->setAttrib('class', 'form-horizontal');
        $form->addElementPrefixPath('Episciences_Form_Decorator', 'Episciences/Form/Decorator/', 'decorator');

        $group = [];

        try {

            $form->addElement('hash', 'no_csrf_foo', array('salt' => 'unique'));
            $form->getElement('no_csrf_foo')->setTimeout(3600);

            self::addDdElement($form, $group, '', false );

        } catch (Zend_Form_Exception $e) {
            trigger_error($e->getMessage());
        }

        // Boutons : Valider et Annuler
        $form->setActions(true)->createSubmitButton('postDdNewVersion', array(
            'label' => 'Enregistrer',
            'class' => 'btn btn-sm btn-primary'
        ));
        $form->setActions(true)->createCancelButton('back', array(
            'label' => 'Annuler',
            'class' => 'btn btn-sm btn-default',
            'onclick' => "cancel()"));


        return $form;
    }


    public static function saveDataDescriptor(array $uploadsInfo, Episciences_Paper $paper): bool
    {

        if (!isset($uploadsInfo[Uploader::UPLOADED_FILES_KEY][self::DD_FILE_ELEMENT_NAME])) {
            return false;
        }

        /** @var File $ddFile */
        $ddFile = $uploadsInfo[Uploader::UPLOADED_FILES_KEY][self::DD_FILE_ELEMENT_NAME];
        $ddFile->setDocId($paper->getDocid());
        $ddFile->setSource();

        try {
            if ($ddFile->save()->getId()) {

                $dd = new DataDescriptor([
                    'uid' => Episciences_Auth::getUid(),
                    'fileid' => $ddFile->getId(),
                    'docid' => $ddFile->getDocid(),
                    'submission_date' => null,
                    'version' => (DataDescriptorManager::getLatestVersion($paper->getDocid()) + 1)
                ]);

                $dd->save();
                $dd->loadFile();
                /** @var Episciences_User $user */
                $user = Episciences_Auth::getUser();

                // paper log
                $logDetails = ['dd' => $dd->toArray(), 'file' => $dd->getFile()->toArray(), 'user' => ['fullname' => $user->getFullName()]];
                $action = $paper->isSoftware() ? Episciences_Paper_Logger::CODE_SWD_UPLOADED : Episciences_Paper_Logger::CODE_DD_UPLOADED;
                $paper->log($action, Episciences_Auth::getUid(), $logDetails);

            }
        } catch (Zend_Db_Adapter_Exception $e) {
            trigger_error($e->getMessage());
            return false;
        }

        return true;

    }

}
