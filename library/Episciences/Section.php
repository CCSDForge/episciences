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
    const UNLABELED_SECTION = 'Unlabeled section';

    const SETTING_STATUS = 'status';

    public const SECTION_PREFIX_DESCRIPTION = 'description_';
    public const SECTION_PREFIX_TITLE = 'title_';

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
    private $_db;

    /**
     * Section ordering
     * @var int
     */
    private int $_position = 0;
    private ?array $titles;
    private ?array $descriptions;
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
    public function getFormDefaults(Episciences_Section $section): array
    {
        $defaults = array_merge(
            self::sectionTitleToTextArray($section->getTitles()),
            self::sectionDescriptionToTextareaArray($section->getDescriptions())
        );

        foreach ($section->getSettings() as $setting => $value) {
            $defaults[$setting] = $value;
        }

        return $defaults;
    }

    private static function sectionTitleToTextArray(?array $titles): array
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

    private static function sectionDescriptionToTextareaArray(?array $descriptions): array
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
    public function save(): bool
    {


        if (!$this->getRvid()) { // If RVID has not been defined, it is specified here
            $this->setRvid(RVID);
        }

        $sectionData = [
            'RVID' => $this->getRvid(),
            'POSITION' => $this->getPosition(),
            'titles' => $this->getTitles(),
            'descriptions' => $this->getDescriptions()
        ];


        $sectionSettings = [
            'SID' => $this->getSid(),
            'SETTING' => self::SETTING_STATUS,
            'VALUE' => $this->getStatus()
        ];

        if (!$this->getSid()) { // add new section
            Episciences_VolumesAndSectionsManager::dataProcess($sectionData);
            if ($this->_db->insert(T_SECTIONS, $sectionData)) {
                $sid = $this->_db->lastInsertId();
                $this->setSid($sid);
                $sectionSettings['SID'] = $this->getSid();
                Episciences_VolumesAndSectionsManager::sort([], 'SID');
            } else {
                return false;
            }

            $this->_db->insert(T_SECTION_SETTINGS, $sectionSettings);
        } else { // update exiting section
            return $this->update($sectionData, $sectionSettings) >= 0;
        }

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
    public function setPosition(int $position = 0): Episciences_Section
    {
        $this->_position = $position;
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
    public function toArray(): array
    {
        $result = [];

        $result['sid'] = $this->getSid();
        $result['rvid'] = $this->getRvid();
        $result['position'] = $this->getPosition();
        $result['titles'] = $this->getTitles();
        $result['descriptions'] = $this->getDescriptions();

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
        $result['description'] = $this->getDescriptions();
        return $result;
    }


    public function getDescriptionKey(bool $force = false): string
    {
        $descriptions = $this->getDescriptions();

        if (!empty($descriptions)) {

            $locale = Episciences_Tools::getLocale();
            if ($locale && isset($descriptions[$locale])) {
                return $descriptions[$locale];
            }
        }

        return $force ? 'section_' . $this->getSid() . '_description' : '';
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

    public function getName(string $lang = null, bool $forceResult = true): string
    {

        $titles = $this->getTitles();

        if (!$titles) {
            return self::UNLABELED_SECTION;
        }

        if (null === $lang) {
            try {
                $lang = Zend_Registry::get('lang');
            } catch (Zend_Exception $e) {
                trigger_error($e->getMessage());
            }
        }

        return $forceResult ? ($titles[$lang] ?? $titles[array_key_first($titles)]) : self::UNLABELED_SECTION;

    }

    public function getNameKey(string $lang = null, bool $force = true): string
    {
        $titles = $this->getTitles();

        if (!empty($titles)) {

            $locale = !$lang ? Episciences_Tools::getLocale() : $lang;

            if ($locale && isset($titles[$locale])) {
                return $titles[$locale];
            }

        }

        return $force ? 'section_' . $this->getSid() . '_title' : '';

    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return (int)$this->getSetting(self::SETTING_STATUS);
    }

    /**
     * @return array|null
     */

    public function getTitles(): ?array
    {
        return $this->titles;
    }


    public function setTitles(?array $titles): self
    {
        $this->titles = $titles;
        return $this;
    }

    public function getDescriptions(): ?array
    {
        return $this->descriptions;
    }

    public function setDescriptions(?array $descriptions): self
    {
        $this->descriptions = $descriptions;
        return $this;
    }

    private function update(array $data = [], array $settings = []): ?int
    {
        $where = 'SID = ' . $this->getSID();

        Episciences_VolumesAndSectionsManager::dataProcess($data);

        try {
            $result = $this->_db->update(T_SECTIONS, $data, $where);

            if (!empty($settings)) {
                $sql = $this->_db->quoteInto('INSERT INTO ' . T_SECTION_SETTINGS . ' (SID, SETTING, VALUE) VALUES (?) ON DUPLICATE KEY UPDATE VALUE = VALUES(VALUE)', $settings);
                $query = $this->_db->query($sql);

                $result += $query->rowCount();

            }

            return $result;

        } catch (Zend_Db_Adapter_Exception|Zend_Db_Statement_Exception  $exception) {
            trigger_error($exception->getMessage());
            return 0;
        }
    }
}