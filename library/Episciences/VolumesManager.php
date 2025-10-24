<?php

class Episciences_VolumesManager
{
    public const MAX_STRING_LENGTH = 255;
    public const MAX_STRING_LENGTH_VOL_NUM = 6;

    /**
     * Retourne la liste des volumes
     * @param array|null $options
     * @param bool $toArray
     * @return array
     */
    public static function getList(array $options = null, $toArray = false)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $select = $db->select()->from(T_VOLUMES)->order('POSITION ASC');
        if ($options) {
            foreach ($options as $cmd => $params) {
                if (is_array($params) && $cmd === 'limit' && count($params) === 2) {
                    $select->limit($params[0], $params[1]);
                } else {
                    $select->$cmd($params);
                }
            }
        } else {
            $select->where('RVID = ?', RVID);
        }
        $result = $db->fetchAll($select);

        $volumes = [];

        foreach ($result as $volume) {
            Episciences_VolumesAndSectionsManager::dataProcess($volume, 'decode');
            $oVolume = new Episciences_Volume($volume);
            $volumes[$oVolume->getVid()] = ($toArray) ? $oVolume->toArray() : $oVolume;
        }

        return $volumes;
    }

    /**
     * Renvoie le formulaire d'assignation de rédacteurs à un volume
     * @param null $currentEditors
     * @return bool|array ['form' => Ccsd_Form, 'unavailableEditors' => array]
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getEditorsForm($currentEditors = null)
    {
        $translator = Zend_Registry::get('Zend_Translate');

        // Passer les param par défaut (currentEditors)
        $editors = Episciences_Review::getEditors(false) + Episciences_Review::getGuestEditors();

        if ($editors) {

            $form = new Ccsd_Form();
            $form->setAction((new Episciences_View_Helper_Url())->url(['controller' => 'volume', 'action' => 'saveeditors']));

            // Filtrer les résultats
            $form->addElement(new Zend_Form_Element_Text([
                'name' => 'filter',
                'class' => 'form-control',
                'style' => 'margin-bottom: 10px',
                'placeholder' => Zend_Registry::get('Zend_Translate')->translate('Rechercher un rédacteur')
            ]));

            // Checkbox
            $options = [];
            $unavailableEditors = [];
            foreach ($editors as $uid => $editor) {
                $class = ($editor->isGuestEditor()) ? 'grey glyphicon glyphicon-star' : 'lightergrey glyphicon glyphicon-user';
                $type = ($editor->isGuestEditor()) ? ucfirst($translator->translate(Episciences_Acl::ROLE_GUEST_EDITOR)) : ucfirst($translator->translate(Episciences_Acl::ROLE_EDITOR));
                $icon = '<span class="' . $class . '" style="margin-right:10px"></span>';
                $icon = '<span style="cursor: pointer" data-toggle="tooltip" title="' . $type . '">' . $icon . '</span>';

                // Check availability
                $isAvailable = Episciences_UsersManager::isEditorAvailable($uid, RVID);

                $label = $icon . $editor->getFullname();

                if (!$isAvailable) {
                    $unavailableEditors[] = $uid;
                    // Translate to "unavailable" for English, "Indisponible" for French
                    $unavailableText = $translator->translate('unavailable');
                    $unavailableLabel = ' <span class="unavailable-badge">' . $unavailableText . '</span>';
                    $label .= $unavailableLabel;
                }

                $options[$uid] = $label;
            }

            $form->addElement(new Ccsd_Form_Element_MultiCheckbox([
                'name' => 'editors',
                'escape' => false,
                'multiOptions' => $options,
                'separator' => '<br/>',
                'decorators' => ['ViewHelper', ['HtmlTag', ['tag' => 'div', 'class' => 'editors-list']]]
            ]));

            if (is_array($currentEditors)) {
                $form->populate(['editors' => array_keys($currentEditors)]);
            }

            // Bouton de validation
            $form->addElement(new Zend_Form_Element_Button([
                'name' => 'submit',
                'type' => 'submit',
                'class' => 'btn btn-default',
                'label' => 'Valider',
                'decorators' => [['HtmlTag', ['tag' => 'div', 'openOnly' => true, 'class' => 'control-group']], 'ViewHelper']
            ]));

            // Bouton d'annulation
            $form->addElement(new Zend_Form_Element_Button([
                'name' => 'cancel',
                'class' => 'btn btn-default',
                'label' => 'Annuler',
                'onclick' => 'closeResult()',
                'decorators' => ['ViewHelper', ['HtmlTag', ['tag' => 'div', 'closeOnly' => true]]]
            ]));

            return [
                'form' => $form,
                'unavailableEditors' => $unavailableEditors
            ];
        }

        return false;
    }

    /**
     * @Deprecated. Use Episciences_VolumesAndSectionsManager::sort
     * @param $params
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     */
    public static function sort($params): bool
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_VOLUMES, 'COUNT(VID) AS results')
            ->where('RVID = ?', RVID);


        if ((int)$db->fetchOne($select) < 1) {
            return false;
        }

        // Si c'est bien le cas, on update les positions
        foreach ($params['sorted'] as $i => $volume) {
            preg_match("#volume_(.*)#", $volume, $matches);
            if (empty($matches)) {
                continue;
            }
            $vid = $matches[1];
            $to = $i + 1;

            // Update position du volume déplacé
            $db->update(T_VOLUMES, ['POSITION' => $to], ['VID = ?' => $vid]);
        }

        return true;
    }

    /**
     * Supprime un volume
     * @param int $id
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    public static function delete(int $id): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $docIds = array_keys(self::getAssignedPapers($id));

        if (count($docIds)) { // Si des articles sont rattachés à ce volume, on empêche sa suppression
            $str = '(#' . implode(';#', $docIds) . ')';
            $str = sprintf(Zend_Registry::get('Zend_Translate')->translate('Des articles %s ont déjà été publiés dans ce volume.'), $str);
            echo $str;
            return false;
        }

        // Récupération de l'id de position pour MAJ des autres volumes
        $select = $db->select()->from(T_VOLUMES)->where('VID = ?', $id);
        $data = $select->query()->fetch();
        $position = $data['POSITION'];
        $rvid = $data['RVID'];

        if ($db->delete(T_VOLUMES, 'VID = ' . $id)) {

            // Mise à jour de l'id de position des autres volumes
            $db->update(
                T_VOLUMES,
                ['POSITION' => new Zend_DB_Expr('POSITION-1')],
                ['RVID = ?' => $rvid, 'POSITION > ?' => $position]
            );

            // Suppression des paramètres du volume
            $db->delete(T_VOLUME_SETTINGS, 'VID = ' . $id);

            // Suppression de la grille de notation liée au volume (si elle existe)
            $file = 'grid_' . $id . '.xml';
            if (Episciences_GridsManager::gridExists($file)) {
                Episciences_GridsManager::delete($file);
            }

            // Suppression des metadatas du volume
            if ($db->delete(T_VOLUME_METADATAS, 'VID = ' . $id)) {
                try {
                    self::deleteVolumeMetadataFiles($id);
                } catch (InvalidArgumentException | RuntimeException $e) {
                    trigger_error($e->getMessage(), E_USER_WARNING);
                    return false;
                }

            }


            //suppression de la file pour le volume

            $db->delete(T_DOI_QUEUE_VOLUMES, 'VID = ' . $id);
            Episciences_VolumeProceeding::deleteByVid($id);
            return true;
        }

        return false;
    }

    private static function getAssignedPapers(int $vid): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = self::isPapersInVolumeQuery($vid, ['st.DOCID']);
        return $db->fetchAssoc($select);
    }

    /**
     * @param int $vid
     * @param array $fields
     * @return Zend_Db_Select
     */
    private static function isPapersInVolumeQuery(int $vid, array $fields = ['COUNT(st.DOCID)']): \Zend_Db_Select
    {
        //Prise en compte des volumes secondaires git #169
        return Episciences_PapersManager::getVolumesQuery($fields)
            ->where("st.VID = ? OR vpt.VID = ?", $vid);
    }

    /**
     * Retourne les valeurs par défaut du formulaire de gestion de volume
     * @param $volume Episciences_Volume
     * @return array
     * @throws Zend_Exception
     */
    public static function getFormDefaults(Episciences_Volume $volume): array
    {
        //$defaults = self::volumeTitleToTextArray($volume->preProcess($volume->getTitles(), Episciences_Volume::MARKDOWN_TO_HTML));
        //$defaults['title'] = $volume->preProcess($volume->getTitles(), Episciences_Volume::MARKDOWN_TO_HTML);
        // $defaults['description'] = $volume->preProcess($volume->getDescriptions(), Episciences_Volume::MARKDOWN_TO_HTML);
        //$defaults = self::volumeDescriptionToTextareaArray($volume->preProcess($volume->getDescriptions(), Episciences_Volume::MARKDOWN_TO_HTML));
        $defaults = array_merge(
            self::volumeTitleToTextArray($volume->preProcess($volume->getTitles(), Episciences_Volume::MARKDOWN_TO_HTML)),
            self::volumeDescriptionToTextareaArray($volume->preProcess($volume->getDescriptions(), Episciences_Volume::MARKDOWN_TO_HTML))
        );

        foreach ($volume->getSettings() as $setting => $value) {
            $defaults[$setting] = $value;
        }

        return $defaults;
    }

    private static function volumeTitleToTextArray(?array $titles): array
    {
        $output = [];
        if (empty($titles)) {
            return $output;
        }

        foreach ($titles as $lang => $value) {
            $output["title_$lang"] = $value;
        }

        return $output;
    }

    private static function volumeDescriptionToTextareaArray(?array $descriptions): array
    {
        $output = [];
        if (empty($descriptions)) {
            return $output;
        }

        foreach ($descriptions as $lang => $value) {
            $output["description_$lang"] = $value;
        }

        return $output;
    }





    /**
     * Retourne le formulaire de gestion d'un volume
     * @param string $referer
     * @param Episciences_Volume|null $volume
     * @param bool $hasPublishedPapers Whether the volume contains published papers (STATUS = 16) - disables title editing if true
     * @return Ccsd_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     * @throws Zend_Validate_Exception
     */
    public
    static function getForm(string $referer = '', Episciences_Volume $volume = null, bool $hasPublishedPapers = false): \Ccsd_Form
    {
        if (empty($referer)) {
            $referer = '/volume/list';
        }

        $form = new Ccsd_Form;
        $form->setAttrib('class', 'form-horizontal');
        $form->setDecorators([
            ['ViewScript', [
                'viewScript' => '/volume/form.phtml',
                'referer' => $referer,
                'value' => ''
            ]
            ],
            'FormTinymce',
            'FormCss',
            'FormJavascript'
        ]);
        if ($volume !== null && $volume->getSetting('conference_proceedings_doi') !== null) {
            $form->getDecorator('ViewScript')->setOption('value', substr(strrchr($volume->getSetting('conference_proceedings_doi'), "."), 1));
        }

        $languages = Episciences_Tools::getLanguages();
        foreach ($languages as $languageCode => $language) {

            // Nom du volume
            $titleElementOptions = [
                'label' => 'Nom (' . $language . ')',
                'maxlength' => self::MAX_STRING_LENGTH,
                'required' => true,
            ];

            // Disable title field if volume has any papers
            if ($hasPublishedPapers) {
                $titleElementOptions['readonly'] = 'readonly';
                $titleElementOptions['class'] = 'readonly-field';
                $titleElementOptions['title'] = Zend_Registry::get('Zend_Translate')->translate('Le nom du volume ne peut pas être modifié car des articles sont déjà associés à ce volume');
            }

            $form->addElement('text', Episciences_Volume::VOLUME_PREFIX_TITLE . $languageCode, $titleElementOptions);

            $form->addElement('textarea', Episciences_Volume::VOLUME_PREFIX_DESCRIPTION . $languageCode, [
                'label' => 'Description (' . $language . ')',
                'tiny' => true,
                'rows' => 5,
                'maxlength' => self::MAX_STRING_LENGTH,
            ]);
        }


        // Référence bibliographique du volume
        $form->addElement('text', 'bib_reference', [
            'label' => 'Référence bibliographique du volume',
            'value' => ($volume !== null) ? $volume->getBib_reference() : '',
            'placeholder' => Zend_Registry::get('Zend_Translate')->translate('Référence bibliographique du volume'),
            'style' => 'width:300px; margin-top: 15px;',
            'maxlength' => self::MAX_STRING_LENGTH,
            'validators' => [
                [new Zend_Validate_StringLength(['max' => self::MAX_STRING_LENGTH])]
            ],
        ]);
        $form->addElement('text', 'num', [
            'label' => 'Numéro du volume',
            'placeholder' => Zend_Registry::get('Zend_Translate')->translate('Numéro du volume'),
            'value' => ($volume !== null) ? $volume->getVol_num() : "",
            //'required' => true,
            'style' => 'width:300px;position: static;',
            'validators' => [
                [new Zend_Validate_StringLength(['max' => self::MAX_STRING_LENGTH_VOL_NUM])],
            ],
        ]);

        $form->addElement('text', 'year', [
            'label' => 'Année du volume',
            'value' => ($volume !== null) ? $volume->getVol_year() : '',
            // 'required' => true,
            'style' => 'width:300px;position: static;',
            'validators' => [
                [new Zend_Validate_Int()],
            ],
        ]);
        // Statut du volume
        $form->addElement('select', 'status', [
            'label' => 'Statut',
            'multioptions' => [0 => 'Fermé', 1 => 'Ouvert'],
            'value' => 1,
            'style' => 'width:300px'
        ]);


        // Volume en cours
        $form->addElement('select', 'current_issue', [
            'label' => 'Volume en cours',
            'multioptions' => [0 => 'Non', 1 => 'Oui'],
            'value' => 0,
            'style' => 'width:300px'
        ]);

        // Volume spécial
        $form->addElement('select', 'special_issue', [
            'label' => 'Volume spécial',
            'multioptions' => [0 => 'Non', 1 => 'Oui'],
            'value' => 0,
            'style' => 'width:300px'
        ]);

        self::getProceedingForm($form, $volume);

        return $form;
    }

    public
    static function getProceedingForm(Ccsd_Form $form, Episciences_Volume $volume = null): \Ccsd_Form
    {
        // Acte de conferences
        $checkboxDecorators = [
            ['Label', ['placement' => 'APPEND']],
            'Description',
            'ViewHelper',
            ['HtmlTag', ['tag' => 'div', 'class' => 'col-md-9 col-md-offset-3']],
            ['Errors', ['placement' => 'APPEND']]
        ];
        $form->addElement('checkbox', "is_proceeding", [
            'label' => 'Actes de conférences',
            'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
            'value' => 0,
            'decorators' => $checkboxDecorators]);
        $form->addElement('text', "conference_name", [
            'label' => 'Nom de la conférence',
        ]);
        $form->addElement('text', "conference_theme", [
            'label' => 'Thème de la conférence',
        ]);
        $form->addElement('text', "conference_acronym", [
            'label' => 'Acronyme de la conférence',
        ]);
        $form->addElement('text', "conference_number", [
            'label' => 'Numéro de la conférence',
            'validators' => [
                [new Zend_Validate_Int()]
            ],
        ]);
        $form->addElement('text', "conference_location", [
            'label' => 'Lieu de la conférence',
        ]);

        $form->addElement('date', 'conference_start', [
            'label' => 'Date de début de la conférence',
            'style' => 'position: static;', // avoid too much z-index for the page
            'class' => 'datepicker',
            'format' => 'Y-m-d',
        ]);
        $form->addElement('date', 'conference_end', [
            'label' => 'Date de fin de la conférence',
            'style' => 'position: static;', // avoid too much z-index for the page
            'class' => 'datepicker',
            'format' => 'Y-m-d',
        ]);
        if ($volume !== null) {
            $form->addElement('hidden', 'doi_status', [
                'value' => Episciences_Volume_DoiQueueManager::findByVolumesId($volume->getVid())->getDoi_status(),
                'data-none' => true, // to make the element Display none; because the construtions of the form is particulary check (volume/form.phtml)

            ]);
        } else {
            $form->addElement('hidden', 'doi_status', [
                'value' => Episciences_Volume_DoiQueue::STATUS_NOT_ASSIGNED,
                'data-none' => true, // to make the element Display none; because the construtions of the form is particulary check (volumes/form.phtml)
            ]);

        }

        $form->addElement('hidden', 'translate_text', [
            'value' => Zend_Registry::get('Zend_Translate')->translate("Titre de l'acte de conférence"),
            'data-none' => true, // to make the element Display none; because the construtions of the form is particulary check (volume/form.phtml)
        ]);
        $form->addElement('hidden', 'translate_text_doi_request', [

            'value' => Zend_Registry::get('Zend_Translate')->translate("Le DOI qui va être demandé"),
            'data-none' => true, // to make the element Display none; because the construtions of the form is particulary check (volume/form.phtml)

        ]);
        return $form;
    }

    /**
     * Retourne le formulaire de gestion d'une metadata
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     * @throws Zend_Validate_Exception
     */
    public
    static function getMetadataForm(): \Ccsd_Form
    {
        $form = new Ccsd_Form;
        $form->setAttrib('class', 'form-horizontal');
        $form->setAttrib('data-submission', 'false');

        $lang = ['class' => 'Episciences_Tools', 'method' => 'getLanguages'];
        $reqLang = ['class' => 'Episciences_Tools', 'method' => 'getRequiredLanguages'];

        // Contenu temporaire
        $form->addElement('hidden', 'mTmpData');

        // Nom du volume
        $form->addElement(new Ccsd_Form_Element_MultiTextSimpleLang([
            'name' => 'mTitle',
            'label' => 'Nom',
            'populate' => $lang,
            'validators' => [new Ccsd_Form_Validate_RequiredLang(['populate' => $reqLang])],
            'required' => true,
            'display' => Ccsd_Form_Element_MultiText::DISPLAY_ADVANCED
        ]));

        // Contenu
        $form->addElement('MultiTextAreaLang', 'mContent', [
            'label' => 'Contenu',
            'populate' => $lang,
            'tiny' => true,
            'rows' => 5,
            'display' => Ccsd_Form_Element_MultiText::DISPLAY_ADVANCED
        ]);

        // Fichier
        $form->addElement('file', 'mFile', [
            'label' => 'Fichier',
            'valueDisabled' => true,
            'maxFileSize' => MAX_FILE_SIZE,
            'validators' => [
                'Count' => [false, 1],
                'Size' => [false, MAX_FILE_SIZE]]]);

        $form->addElement('hidden', 'mFileData');

        return $form;

    }

    /**
     * Check if a volume contains any published papers (STATUS = 16)
     * This is used to determine if volume title should be locked for editing
     *
     * @param int $vid Volume ID
     * @return bool True if volume has at least one published paper, false otherwise
     */
    public
    static function isPublishedPapersInVolume(int $vid): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = self::isPapersInVolumeQuery($vid);
        $select->where('st.STATUS = ?', Episciences_Paper::STATUS_PUBLISHED);
        return (int)$db->fetchOne($select) > 0;
    }

    /**
     * Save or update paper positions in a Volume
     * @param $vid
     * @param $paper_positions
     */
    public
    static function savePaperPositionsInVolume($vid, $paper_positions)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sql = 'INSERT INTO ' . $db->quoteIdentifier(T_VOLUME_PAPER_POSITION) . ' (`VID`, `PAPERID`, `POSITION`) VALUES ';

        $values = [];

        foreach ($paper_positions as $position => $paperId) {

            if (!is_numeric($paperId) || !is_numeric($position)) {
                continue;
            }

            $values[] = '(' . $db->quote((int)$vid) . ',' . $db->quote((int)$paperId) . ',' . $db->quote($position) . ')';
        }

        if ($values) {
            try {
                $db->query($sql . implode(', ', $values) . ' ON DUPLICATE KEY UPDATE POSITION=VALUES(POSITION)');
            } catch (Exception $e) {
                trigger_error(sprintf($e->getMessage(), E_USER_WARNING));
            }
        }
    }

    /**
     * load paper positions
     * @param int $vid
     * @return array ['DOCID' => 'POSITION]
     */
    public
    static function loadPositionsInVolume(int $vid = 0): array
    {
        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $select = $db->select()
                ->from(T_VOLUME_PAPER_POSITION, ['PAPERID', 'POSITION']);

            if (!empty($vid)) {
                $select->where('VID = ?', $vid);
            }

            $select->order('POSITION ASC');

            $res = $db->fetchPairs($select);

        } catch (Exception $exception) {
            $res = [];
        }
        return $res;
    }

    public
    static function translateVolumeKey(string $volumeKey, string $language = null, bool $force = true): string
    {
        $vId = (int)filter_var($volumeKey, FILTER_SANITIZE_NUMBER_INT);

        if (!$vId) {
            return '';
        }

        $volume = self::find($vId);

        if (!$language) {
            $language = Episciences_Tools::getLocale();
        }

        if (!$volume) {
            try {
                return $force ? $volumeKey . ' [ ' . Zend_Registry::get('Zend_Translate')->translate("Ce volume a été supprimé") . ' ]' : '';
            } catch (Zend_Exception $e) {
                trigger_error($e->getMessage());
                return '';
            }
        }

        return $volume->getNameKey($language, $force);
    }

    public static function find($vid, int $rvid = 0): Episciences_Volume|bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $select = $db->select()->from(T_VOLUMES)->where('VID = ?', $vid);
        if ($rvid !== 0) {
            $select->where('RVID = ?', $rvid);
        }
        $volume = $db->fetchRow($select);

        if (empty($volume)) {
            return false;
        }

        Episciences_VolumesAndSectionsManager::dataProcess($volume, 'decode');

        $oVolume = new Episciences_Volume($volume);
        $oVolume->loadSettings();
        $oVolume->loadMetadatas();

        return $oVolume;
    }

    public static function revertVolumeDescriptionToTextareaArray(?array $input): ?array
    {
        $output = [];
        if (empty($input)) {
            return null;
        }
        foreach ($input as $key => $value) {
            if (str_starts_with($key, Episciences_Volume::VOLUME_PREFIX_DESCRIPTION)) {
                $lang = substr($key, strlen(Episciences_Volume::VOLUME_PREFIX_DESCRIPTION));
                $output[$lang] = $value;
            }
        }

        return $output;
    }

    public static function revertVolumeTitleToTextArray(?array $input): ?array
    {
        $output = [];
        if (empty($input)) {
            return null;
        }
        foreach ($input as $key => $value) {
            if (str_starts_with($key, Episciences_Volume::VOLUME_PREFIX_TITLE)) {
                $lang = substr($key, strlen(Episciences_Volume::VOLUME_PREFIX_TITLE));
                $output[$lang] = $value;
            }
        }

        return $output;
    }


    /**
     * @param int $vid
     * @return bool
     */
    private static function isPapersInVolume(int $vid): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = self::isPapersInVolumeQuery($vid);
        return (int)$db->fetchOne($select) > 0;
    }

    /**
     * @param int $id
     * @return void
     */
    private static function deleteVolumeMetadataFiles(int $id): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException("Invalid volume ID: must be positive integer");
        }

        // Build and normalize base path
        $baseDir = rtrim(REVIEW_PUBLIC_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'volumes';
        $path = $baseDir . DIRECTORY_SEPARATOR . $id;

        $realBase = realpath($baseDir);
        $realTarget = realpath($path);

        // If the target doesn't exist, nothing to do
        if ($realTarget === false) {
            return;
        }

        // Ensure the target is inside the base directory
        if (!str_starts_with($realTarget, $realBase)) {
            throw new RuntimeException("Deletion path is outside the allowed directory");
        }

        // Use child-first order to delete files before directories
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realTarget, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $filePath = $file->getPathname();

            // Symlink protection
            if ($file->isLink()) {
                continue;
            }

            // File or directory deletion
            if ($file->isDir()) {
                if (!rmdir($filePath)) {
                    throw new RuntimeException("Failed to delete directory: $filePath");
                }
            } else {
                if (!unlink($filePath)) {
                    throw new RuntimeException("Failed to delete file: $filePath");
                }
            }
        }

        // Remove the main directory
        if (!rmdir($realTarget)) {
            throw new RuntimeException("Failed to delete base directory: $realTarget");
        }
    }



}
