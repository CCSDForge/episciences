<?php

class Episciences_SectionsManager
{
    public const TABLE = T_SECTIONS;
    public const SETTINGS_TABLE = T_SECTION_SETTINGS;

    public const MAX_STRING_LENGTH = 255;

    /**
     * Renvoie la liste de toutes les rubriques d'une revue
     * @param array|null $options
     * @param bool $toArray
     * @return array
     */
    public static function getList(array $options = null, $toArray = false)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sections = array();

        $select = $db->select()->from(self::TABLE)->order('POSITION', 'ASC');
        if ($options) {
            foreach ($options as $cmd => $params) {
                $select->$cmd($params);
            }
        } else {
            $select->where('RVID = ?', RVID);
        }
        $data = $db->fetchAll($select);

        if ($data) {
            foreach ($data as $sectionOptions) {
                Episciences_VolumesAndSectionsManager::dataProcess($sectionOptions, 'decode');
                $oSection = new Episciences_Section($sectionOptions);
                $sections[$oSection->getSid()] = ($toArray) ? $oSection->toArray() : $oSection;
            }
        }
        return $sections;
    }

    /**
     * Charge les paramètres pour une liste de rubriques en une seule requête
     * @param Episciences_Section[] $sections
     * @return void
     */
    public static function loadSettingsForSections(array $sections): void
    {
        if (empty($sections)) {
            return;
        }

        $sids = array_map(fn($section) => $section->getSid(), $sections);

        if (empty($sids)) {
            return;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_SECTION_SETTINGS, ['SID', 'SETTING', 'VALUE'])
            ->where('SID IN (?)', $sids);

        try {
            $results = $db->fetchAll($select);

            $settingsBySid = [];
            foreach ($results as $row) {
                $settingsBySid[$row['SID']][$row['SETTING']] = $row['VALUE'];
            }

            foreach ($sections as $section) {
                $sid = $section->getSid();
                if (isset($settingsBySid[$sid])) {
                    $section->setSettings($settingsBySid[$sid]);
                } else {
                    $section->setSettings([]);
                }
            }
        } catch (Exception $e) {
            trigger_error("Error loading section settings: " . $e->getMessage(), E_USER_WARNING);
        }
    }

    public static function find($sid, int $rvid = 0): bool|Episciences_Section
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $select = $db->select()->from(self::TABLE)->where('SID = ?', $sid);
        if ($rvid !== 0) {
            $select->where('RVID = ?', $rvid);
        }
        $options = $db->fetchRow($select);
        if (empty($options)) {
            return false;
        }

        Episciences_VolumesAndSectionsManager::dataProcess($options, 'decode');
        $oSection = new Episciences_Section($options);
        $oSection->loadSettings();

        return $oSection;
    }

    /**
     * Supprime une rubrique
     * @param int $id
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     */
    public static function delete(int $id): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        if (!$db) {
            return false;
        }


        // Si des articles sont rattachés à cette rubrique, on empêche sa suppression
        /*
        $sql = $db->select()->from(T_PAPERS, 'COUNT(DOCID)')->where('SID = ?', $id);
        if ($db->fetchOne($sql)) {
            echo 'Des articles ont déjà été publiés dans cette rubrique.';
            return false;
        }
        */


        // Récupération de l'id de position pour MAJ des autres rubriques
        $select = $db->select()->from(self::TABLE)->where('SID = ?', $id);
        $data = $select->query()->fetch();

        if (!$data) {
            return false;
        }

        $position = $data['POSITION'];
        $rvId = $data['RVID'];

        $db->beginTransaction();

        try {
            // 1. Suppression des assignations liées à la rubrique
            Episciences_User_AssignmentsManager::removeAssignment([
                'ITEM = ?' => Episciences_User_Assignment::ITEM_SECTION,
                'ITEMID = ?' => $id,
                'RVID = ?' => $rvId
            ]);

            // 2. Suppression de la rubrique
            $deleted = $db->delete(self::TABLE, 'SID = ' . $id);
            if (!$deleted) {
                throw new Exception("Unable to delete section " . $id);
            }

            // 3. Mise à jour de l'id de position des autres rubriques
            $db->update(
                self::TABLE,
                ['POSITION' => new Zend_DB_Expr('POSITION-1')],
                ['RVID = ?' => $rvId, 'POSITION > ?' => $position]
            );

            // 4. Suppression des paramètres de la rubrique
            $db->delete(self::SETTINGS_TABLE, 'SID = ' . $id);
            $db->commit();
            return true;

        } catch (Exception $e) {
            $db->rollBack();
            trigger_error("Error deleting section: " . $e->getMessage(), E_USER_WARNING);
            return false;
        }
    }

    /**
     * @Deprecated. Use Episciences_VolumesAndSectionsManager::sort
     * @param $params
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     */
    public static function sort($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $select = $db->select()
            ->from(self::TABLE, 'COUNT(SID) AS results')
            ->where('RVID = ?', RVID);

        if ((int)$db->fetchOne($select) < 1) {
            return false;
        }

        // Si c'est bien le cas, on update les positions
        foreach ($params['sorted'] as $i => $section) {
            preg_match("#section_(.*)#", $section, $matches);
            if (empty($matches)) {
                continue;
            }
            $sid = $matches[1];
            $to = $i + 1;

            // Update position de la rubrique déplacée
            $db->update(self::TABLE, array('POSITION' => $to), array('SID = ?' => $sid));
        }

        return true;

    }

    /**
     * Renvoie le formulaire d'assignation de rédacteurs à une rubrique
     * @param null $currentEditors
     * @return bool|Zend_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getEditorsForm($currentEditors = null)
    {
        // Passer les param par défaut (currentEditors)
        $editors = Episciences_Review::getEditors(false);
        $options = [];
        if ($editors) {

            $form = new Zend_Form();
            $form->setAction((new Episciences_View_Helper_Url())->url(['controller' => 'section', 'action' => 'saveeditors']));

            // Filtrer les résultats
            $form->addElement(new Zend_Form_Element_Text(array(
                'name' => 'filter',
                'class' => 'form-control',
                'style' => 'margin-bottom: 10px',
                'placeholder' => Zend_Registry::get('Zend_Translate')->translate('Rechercher un rédacteur')
            )));

            // Checkbox
            foreach ($editors as $uid => $editor) {
                $options[$uid] = $editor->getFullname();
            }

            $form->addElement(new Zend_Form_Element_MultiCheckbox(array(
                'name' => 'editors',
                'multiOptions' => $options,
                'separator' => '<br/>',
                'decorators' => array('ViewHelper', array('HtmlTag', array('tag' => 'div', 'class' => 'editors-list')))
            )));

            if (is_array($currentEditors)) {
                $form->populate(array('editors' => array_keys($currentEditors)));
            }

            // Bouton de validation
            $form->addElement(new Zend_Form_Element_Button(array(
                'name' => 'submit',
                'type' => 'submit',
                'class' => 'btn btn-default',
                'label' => 'Valider',
                'decorators' => array(array('HtmlTag', array('tag' => 'div', 'openOnly' => true, 'class' => 'control-group')), 'ViewHelper')
            )));

            // Bouton d'annulation
            $form->addElement(new Zend_Form_Element_Button(array(
                'name' => 'cancel',
                'class' => 'btn btn-default',
                'label' => 'Annuler',
                'onclick' => 'closeResult()',
                'decorators' => array('ViewHelper', array('HtmlTag', array('tag' => 'div', 'closeOnly' => true)))
            )));

            return $form;
        }

        return false;
    }

    /**
     * Renvoie le formulaire de création/modification d'une rubrique
     * @param null $defaults
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     * @throws Zend_Validate_Exception
     */
    public static function getForm(array $defaults = null)
    {
        $form = new Ccsd_Form;
        $form->setAttrib('class', 'form-horizontal');

        $languages = Episciences_Tools::getLanguages();
        foreach ($languages as $languageCode => $language) {

            // Nom de la rubrique
            $form->addElement('text', Episciences_Section::SECTION_PREFIX_TITLE . $languageCode,  [
                'label' => 'Nom (' . $language . ')',
                'maxlength' => self::MAX_STRING_LENGTH,
                'required' => true,
            ]);

            // Description de la rubrique
            $form->addElement('textarea', Episciences_Section::SECTION_PREFIX_DESCRIPTION . $languageCode, [
                'label' => 'Description (' . $language . ')',
                'tiny' => true,
                'rows' => 5,
                'maxlength' => self::MAX_STRING_LENGTH,
            ]);
        }

        // Statut (ouvert/fermé)
        $form->addElement('select', 'status', array(
            'label' => 'Statut',
            'multioptions' => array(0 => 'Fermé', 1 => 'Ouvert'),
            'value' => 1,
            'style' => 'width:300px'
        ));

        // Boutons : Valider et Annuler
        $form->setActions(true)->createSubmitButton('submit', array(
            'label' => 'Valider',
            'class' => 'btn btn-primary'
        ));


        $cancelUrl = (new Episciences_View_Helper_Url())->url(array(
            'controller' => 'section',
            'action' => 'index'
        ));
        $form->setActions(true)->createCancelButton('back', array(
            'label' => 'Annuler',
            'class' => 'btn btn-default',
            'onclick' => "window.location='$cancelUrl'"));

        if ($defaults) {
            $form->setDefaults($defaults);
        }

        return $form;
    }

    public static function translateSectionKey(string $sectionKey, string $language = null, bool $force = true): string
    {
        $sId = (int)filter_var($sectionKey, FILTER_SANITIZE_NUMBER_INT);

        if (!$sId) {
            return '';
        }

        $section = self::find($sId);

        if (!$language) {
            $language = Episciences_Tools::getLocale();
        }

        if (!$section) {
            try {
                return $force ? $sectionKey . ' [ ' . Zend_Registry::get('Zend_Translate')->translate("Cette rubrique a été supprimée") . ' ]' : '';
            } catch (Zend_Exception $e) {
                trigger_error($e->getMessage());
                return '';
            }
        }

        return $section->getNameKey($language, $force);
    }

    public static function revertSectionDescriptionToTextareaArray(?array $input): ?array
    {
        $output = [];
        if (empty($input)) {
            return null;
        }
        foreach ($input as $key => $value) {
            if (str_starts_with($key, Episciences_Section::SECTION_PREFIX_DESCRIPTION)) {
                $lang = substr($key, strlen(Episciences_Section::SECTION_PREFIX_DESCRIPTION));
                $output[$lang] = $value;
            }
        }

        return $output;
    }

    public static function revertSectionTitleToTextArray(?array $input): ?array
    {
        $output = [];
        if (empty($input)) {
            return null;
        }
        foreach ($input as $key => $value) {
            if (str_starts_with($key, Episciences_Section::SECTION_PREFIX_TITLE)) {
                $lang = substr($key, strlen(Episciences_Section::SECTION_PREFIX_TITLE));
                $output[$lang] = $value;
            }
        }

        return $output;
    }

}

