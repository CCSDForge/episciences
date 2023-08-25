<?php

/**
 * Class Episciences_Section
 */
class Episciences_Section
{
    /**
     * @const int
     */
    const SECTION_CLOSED_STATUS = 0;

    /**
     * @const int
     */
    const SECTION_OPEN_STATUS = 1;

    /**
     * String prefix for translations
     * @const string
     */
    const SECTION_TRANSLATION_PREFIX = 'section_';
    const UNLABELED_SECTION = 'Unlabeled section';

    const SETTING_STATUS = 'status';

    /**
     * Section ID
     * @var int
     */
    private $_sid;

    /**
     * Journal ID
     * @var int
     */
    private $_rvid;

    /**
     * Section ordering
     * @var int
     */
    private $_position;
    private $_title;
    private $_description = [];
    private $_settings = [];
    /**
     * @var array of Episciences_Editor
     */
    private $_editors;
    /**
     * @var int number of papers in a section
     */
    private $_countOfPapers;

    /**
     * Episciences_Section constructor.
     * @param array|null $options
     */
    public function __construct(array $options = null)
    {
        $this->_db = Zend_Db_Table_Abstract::getDefaultAdapter();

        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

    public function setOptions(array $options)
    {
        $methods = get_class_methods($this);
        foreach ($options as $key => $value) {
            $key = strtolower($key);
            $method = 'set' . ucfirst($key);
            if (in_array($method, $methods)) {
                $this->$method($value);
            }
        }
        return $this;
    }

    /**
     * Renvoie les valeurs par défaut du formulaire de modification d'une rubrique
     * @return array
     * @throws Zend_Exception
     */
    public function getFormDefaults()
    {
        $defaults = [];

        $langs = Episciences_Tools::getLanguages();
        $path = Episciences_SectionsManager::TRANSLATION_PATH;
        $file = Episciences_SectionsManager::TRANSLATION_FILE;
        $translator = Zend_Registry::get('Zend_Translate');
        Episciences_Tools::loadTranslations($path, $file);

        $sid = $this->getSid();
        $defaults[self::SETTING_STATUS] = $this->getStatus();

        foreach ($langs as $code => $lang) {

            if ($translator->isTranslated(self::SECTION_TRANSLATION_PREFIX . $sid . '_title', $code)) {
                $defaults['title'][$code] = $translator->translate(self::SECTION_TRANSLATION_PREFIX . $sid . '_title', $code);
            }

            if ($translator->isTranslated(self::SECTION_TRANSLATION_PREFIX . $sid . '_description', $code)) {
                $defaults['description'][$code] = $translator->translate(self::SECTION_TRANSLATION_PREFIX . $sid . '_description', $code);
            }
        }

        return $defaults;
    }

    /**
     * @return int
     */
    public function getSid()
    {
        return $this->_sid;
    }

    /**
     * @param int $sid
     * @return Episciences_Section
     */
    public function setSid(int $sid): Episciences_Section
    {
        $this->_sid = $sid;
        return $this;
    }


    public function getSetting($setting)
    {
        $settings = $this->getSettings();

        if (array_key_exists($setting, $settings)) {
            return $settings[$setting];
        }

        return false;
    }

    public function getSettings()
    {
        return $this->_settings;
    }

    /**
     * Assigne des éditeurs à la rubrique
     * @param $settings
     * @return $this
     */
    public function setSettings($settings)
    {
        $this->_settings = $settings;
        return $this;
    }

    /**
     * Désassigne des éditeurs de la rubrique
     * @return bool|Zend_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public function getEditorsForm()
    {
        return Episciences_SectionsManager::getEditorsForm($this->getEditors());
    }

    /**
     * Return editors
     * @param bool $active
     * @return array
     */
    public function getEditors($active = true)
    {
        if (!isset($this->_editors)) {
            $this->loadEditors($active);
        }
        return $this->_editors;
    }

    /**
     * @param $editors
     * @return $this
     */
    public function setEditors($editors)
    {
        $this->_editors = $editors;
        return $this;
    }

    /**
     * @param bool $active
     */
    public function loadEditors($active = true)
    {
        $subquery = $this->_db->select()
            ->from(T_ASSIGNMENTS, ['UID', 'MAX(`WHEN`) AS WHEN'])
            ->where('ITEM = ?', Episciences_User_Assignment::ITEM_SECTION)
            ->where('ITEMID = ?', $this->getSid())
            ->where('ROLEID = ?', Episciences_User_Assignment::ROLE_EDITOR)
            ->group('UID');

        $select = $this->_db->select()
            ->from(['a' => T_ASSIGNMENTS], ['UID', 'STATUS', 'WHEN'])
            ->joinUsing(T_USERS, 'UID', ['LANGUEID', 'SCREEN_NAME'])
            ->join(['b' => $subquery], 'a.UID = b.UID AND a.`WHEN` = b.`WHEN`')
            ->join(['ur' => T_USER_ROLES], 'ur.UID = a.UID')
            ->where('ur.ROLEID IN (?)', [Episciences_Acl::ROLE_GUEST_EDITOR, Episciences_Acl::ROLE_EDITOR, Episciences_Acl::ROLE_CHIEF_EDITOR])
            ->where('ur.RVID = ?', RVID);

        $result = $this->_db->fetchAssoc($select);
        $editors = [];

        if ($active && !empty($result)) {
            $result = array_filter($result, function ($user) {
                return ($user['STATUS']) == Episciences_User_Assignment::STATUS_ACTIVE;
            });
        }

        if ($result) {

            foreach ($result as $uid => $user) {
                $editor = new Episciences_Editor();
                $editor->findWithCAS($uid);
                $editor->setWhen($user['WHEN']);
                $editor->setStatus($user['STATUS']);
                $editors[$uid] = $editor;
            }
        }

        $this->setEditors($editors);

    }

    /**
     * @param $ids
     * @param array $params
     * @return array|bool
     */
    public function assign($ids, $params = [])
    {
        $params = [
            'rvid' => Ccsd_Tools::ifsetor($params['rvid'], RVID),
            'itemid' => $this->getSid(),
            'item' => Episciences_User_Assignment::ITEM_SECTION,
            'roleid' => Episciences_User_Assignment::ROLE_EDITOR,
            'status' => Ccsd_Tools::ifsetor($params['status'], Episciences_User_Assignment::STATUS_ACTIVE)
        ];

        return Episciences_UsersManager::assign($ids, $params);
    }

    /**
     * @param $ids
     * @param array $params
     * @return array|bool
     */
    public function unassign($ids, $params = [])
    {
        $params = [
            'rvid' => Ccsd_Tools::ifsetor($params['rvid'], RVID),
            'itemid' => $this->getSid(),
            'item' => Episciences_User_Assignment::ITEM_SECTION,
            'roleid' => Episciences_User_Assignment::ROLE_EDITOR
        ];

        return Episciences_UsersManager::unassign($ids, $params);
    }

    /**
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     */
    public function save()
    {
        // Si le RVID n'a pas été défini, on le spécifie ici
        if (!$this->getRvid()) {
            $this->setRvid(RVID);
        }

        // Récupération des paramètres de la rubrique
        $settingsValues['SETTING'] = self::SETTING_STATUS;
        $settingsValues['VALUE'] = $this->getStatus();

        // Si il s'agit d'une nouvelle rubrique
        if (!$this->getSid()) {

            // Récupération de la position de la rubrique
            $select = $this->_db->select()->from(T_SECTIONS, new Zend_Db_Expr('MAX(POSITION)+1'))->where('RVID = ?', $this->getRvid());
            $position = $this->_db->fetchOne($select);
            if (empty($position)) {
                $position = 1;
            }
            $this->setPosition($position);

            // Enregistrement de la rubrique
            if ($this->_db->insert(T_SECTIONS, ['RVID' => $this->getRvid(), 'POSITION' => $this->getPosition()])) {
                $sid = $this->_db->lastInsertId();
                $this->setSid($sid);
            } else {
                return false;
            }

            // Enregistrement des paramètres du volume
            $settingsValues['SID'] = $this->getSid();
            $this->_db->insert(T_SECTION_SETTINGS, $settingsValues);
        } // Modification d'une rubrique
        else {
            // Mise à jour des paramètres du volume
            $settingsValues['SID'] = $this->getSid();
            $sql = $this->_db->quoteInto('INSERT INTO ' . T_SECTION_SETTINGS . ' (SETTING, VALUE, SID) VALUES (?)
                    ON DUPLICATE KEY UPDATE VALUE = VALUES(VALUE)', $settingsValues);
            $this->_db->query($sql);
        }

        // Préparation des données de traduction
        $path = Episciences_SectionsManager::TRANSLATION_PATH;
        $file = Episciences_SectionsManager::TRANSLATION_FILE;
        $translations = Episciences_Tools::getOtherTranslations($path, $file, '#section_' . $this->getSid() . '_#');

        // Nom de la rubrique
        $titles = $this->getTitle();
        foreach ($titles as $lang => $translated) {
            $translations[$lang][self::SECTION_TRANSLATION_PREFIX . $this->getSid() . '_title'] = $translated;
        }

        // Description de la rubrique
        $descriptions = $this->getDescription();
        foreach ($descriptions as $lang => $translated) {
            $translations[$lang][self::SECTION_TRANSLATION_PREFIX . $this->getSid() . '_description'] = $translated;
        }

        // Enregistrement des traductions
        Episciences_Tools::writeTranslations($translations, $path, $file);
        return true;
    }

    /**
     * @return int
     */
    public function getRvid()
    {
        return $this->_rvid;
    }

    /**
     * @param int $rvid
     * @return Episciences_Section
     */
    public function setRvid(int $rvid): Episciences_Section
    {
        $this->_rvid = $rvid;
        return $this;
    }

    /**
     * @return int
     */
    public function getPosition(): int
    {
        return $this->_position;
    }

    /**
     * @param int $position
     * @return Episciences_Section
     */
    public function setPosition(int $position): Episciences_Section
    {
        $this->_position = $position;
        return $this;
    }

    /**
     * @param null $lang
     * @return mixed
     */
    public function getTitle($lang = null)
    {
        if ($lang) {
            return $this->_title[$lang];
        }

        return $this->_title;
    }

    /**
     * @param $titles
     * @return $this
     */
    public function setTitle($titles)
    {
        foreach ($titles as $lang => $title) {
            $this->_title[$lang] = $title;
        }
        return $this;
    }

    /**
     * @param null $lang
     * @return array|mixed
     */
    public function getDescription($lang = null)
    {
        if ($lang) {
            return $this->_description[$lang];
        }

        return $this->_description;
    }

    /**
     * @param $descriptions
     * @return $this
     */
    public function setDescription($descriptions)
    {
        foreach ($descriptions as $lang => $description) {
            $this->_description[$lang] = $description;
        }
        return $this;
    }

    /**
     * @return bool
     */
    public function hasEditors()
    {
        $editors = $this->getEditors();

        if (!empty($editors)) {
            return true;
        }

        return false;

    }

    /**
     * Get number of papers indexed in a section
     * @return int
     */
    public function countIndexedPapers()
    {
        $query = 'q=*%3A*';
        $query .= '&wt=phps&omitHeader=true';
        $query .= '&fq=revue_id_i:' . RVID;
        $query .= '&fq=section_id_i:' . $this->getSid();
        $query .= '&rows=0';

        try {
            $result = Episciences_Tools::solrCurl($query);

            if ($result) {
                $numFound = unserialize($result, ['allowed_classes' => false])['response']['numFound'];
            } else {
                $numFound = 0;
            }
        } catch (Exception $exception) {
            $numFound = 0;
        }

        $this->setCountOfPapers($numFound);
        return $numFound;
    }

    /**
     * Load Papers indexed in Solr Core
     * @throws Exception
     */
    public function loadIndexedPapers()
    {
        $query = 'q=*%3A*';
        $query .= '&sort=publication_date_tdate+desc&wt=phps&omitHeader=true';
        $query .= '&fq=revue_id_i:' . RVID;
        $query .= '&fq=section_id_i:' . $this->getSid();
        $query .= '&rows=1000';

        $result = Episciences_Tools::solrCurl($query);

        if ($result) {

            $positions = [];
            // $positions = $this->getPaperPositions();

            $response = unserialize($result, ['allowed_classes' => false])['response'];
            $unsorted_papers = [];
            $sorted_papers = [];
            foreach ($response['docs'] as $paper) {
                $unsorted_papers[$paper['docid']] = $paper;
            }

            if (is_array($positions) && !empty($positions)) {
                foreach ($positions as $position => $docid) {
                    $sorted_papers[$position] = $unsorted_papers[$docid];
                }
            } else {
                $sorted_papers = $unsorted_papers;
            }

            $this->setIndexedPapers($sorted_papers);
        }
    }

    /**
     * @param $indexedPapers
     */
    public function setIndexedPapers($indexedPapers)
    {
        $this->_indexedPapers = $indexedPapers;
    }

    /**
     * @return mixed
     */
    public function getIndexedPapers()
    {
        return $this->_indexedPapers;
    }


    public function loadSettings()
    {
        $select = $this->_db->select()
            ->from(T_SECTION_SETTINGS, ['SETTING', 'VALUE'])
            ->where('SID = ?', $this->getSid());

        $this->setSettings($this->_db->fetchPairs($select));
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $result = [];

        $result['sid'] = $this->getSid();
        $result['rvid'] = $this->getRvid();
        $result['position'] = $this->getPosition();
        $result['title'] = $this->getTitle();
        $result['description'] = $this->getDescription();

        return $result;
    }

    /**
     * @return array
     * @throws Zend_Exception
     */
    public function toPublicArray()
    {
        $result = [];
        $result['sid'] = $this->getSid();
        $result['title'] = $this->getName('en', true);
        $result['description'] = $this->getDescription();
        return $result;
    }


    /**
     * Définit le titre de la rubrique (dans différentes langues)
     * @return string
     */
    public function getDescriptionKey()
    {
        return self::SECTION_TRANSLATION_PREFIX . $this->getSid() . '_description';
    }

    /**
     * Définit la description de la rubrique (dans différentes langues)
     * @param $setting
     * @param $value
     * @return $this
     */
    public function setSetting($setting, $value)
    {
        $this->_settings[$setting] = $value;
        return $this;
    }

    /**
     * @return int
     */
    public function getCountOfPapers(): int
    {
        return $this->_countOfPapers;
    }

    /**
     * @param int $countOfPapers
     */
    public function setCountOfPapers(int $countOfPapers)
    {
        $this->_countOfPapers = $countOfPapers;
    }

    /**
     * @param null $lang
     * @param bool $forceResult
     * @return string|null
     * @throws Zend_Exception
     */
    public function getName($langSection = null, $forceResult = false)
    {
        $result = null;
        // try to fetch translation for specified language
        if (Zend_Registry::get('Zend_Translate')->isTranslated($this->getNameKey(), $langSection)) {
            $result = Zend_Registry::get('Zend_Translate')->translate($this->getNameKey(), $langSection);
        }
        if (!$result && $forceResult) {
            if (Zend_Registry::get('Zend_Translate')->isTranslated($this->getNameKey(), 'en')) {
                // if it cannot be found, try to fetch english translation
                $result = Zend_Registry::get('Zend_Translate')->translate($this->getNameKey(), 'en');
            } else {
                // else, try to fetch any translation
                foreach (Episciences_Tools::getLanguages() as $locale => $lang) {
                    if (Zend_Registry::get('Zend_Translate')->isTranslated($this->getNameKey(), $locale)) {
                        $result = Zend_Registry::get('Zend_Translate')->translate($this->getNameKey(), $locale);
                        break;
                    }
                }
            }
            if (!$result) {
                $result = self::UNLABELED_SECTION;
            }
        }
        return $result;
    }

    /**
     * @return string
     */
    public function getNameKey()
    {
        return self::SECTION_TRANSLATION_PREFIX . $this->getSid() . '_title';
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return (int) $this->getSetting(self::SETTING_STATUS);
    }

}