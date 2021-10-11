<?php

class Episciences_Volume
{
    const VOLUME_PAPER_POSITIONS = 'paper_positions';
    const TRANSLATION_PATH = REVIEW_LANG_PATH;
    const TRANSLATION_FILE = 'volumes.php';
    const SETTING_STATUS = 'status';

    // Volume settings names
    const SETTING_CURRENT_ISSUE = 'current_issue';
    const SETTING_SPECIAL_ISSUE = 'special_issue';
    const SETTING_ACCESS_CODE = 'access_code';
    const UNLABELED_VOLUME = 'Unlabeled volume';
    const PAPER_POSITION_NEEDS_TO_BE_SAVED = 'needsToBeSaved';
    protected $_db = null;
    private $_vid;
    private $_rvid;
    private $_position;
    private $_settings = [];
    private $_metadatas = [];
    private $_indexedPapers = null;
    private $_paperPositions = [];
    private $_editors;
    // Copy Editors
    private $_copyEditors = [];
    private $_bib_reference = null;

    /**
     * Episciences_Volume constructor.
     * @param array|null $options
     */
    public function __construct(array $options = null)
    {
        $this->_db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

    /**
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options): self
    {
        $methods = get_class_methods($this);
        foreach ($options as $key => $value) {
            $key = strtolower($key);
            $method = 'set' . ucfirst($key);
            if (in_array($method, $methods, true)) {
                $this->$method($value);
            }
        }
        if (!$this->getSetting(self::SETTING_ACCESS_CODE)) {
            $this->createAccessCode();
        }

        return $this;
    }

    /**
     * @param $setting
     * @return bool|mixed
     */
    public function getSetting($setting)
    {
        $settings = $this->getSettings();

        if (array_key_exists($setting, $settings)) {
            return $settings[$setting];
        }

        return false;
    }

    /**
     * @return array
     */
    public function getSettings(): array
    {
        return $this->_settings;
    }

    /**
     * @param $settings
     * @return $this
     */
    public function setSettings($settings): self
    {
        $this->_settings = $settings;
        return $this;
    }

    /**
     * @return string
     */
    private function createAccessCode(): string
    {
        $code = uniqid('', false);
        $this->setSetting('access_code', $code);
        return $code;
    }

    /**
     * @param $setting
     * @param $value
     * @return $this
     */
    public function setSetting($setting, $value): self
    {
        $this->_settings[$setting] = (string)$value;
        return $this;
    }

    /**
     * Find gaps in the ordering of papers in volumes eg [3,[X],5,6,7]
     * @param array $sorted_papers a sorted array of papers
     * @return array array of gaps
     */
    public static function findGapsInPaperOrders(array $sorted_papers): array
    {
        $arrayOfMyDreams = range(0, count($sorted_papers)-1);
        $actualArray = array_keys($sorted_papers);
        return array_diff($arrayOfMyDreams, $actualArray);
    }

    /**
     * Renvoie les rédacteurs assignés au volume
     * @param bool $active
     * @return mixed
     * @throws Zend_Db_Statement_Exception
     */
    public function getEditors($active = true)
    {
        if (!isset($this->_editors)) {
            $this->loadEditors($active);
        }
        return $this->_editors;
    }

    /**
     * Assigne des rédacteurs au volume
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
     * @throws Zend_Db_Statement_Exception
     */
    public function loadEditors(bool $active = true): void
    {
        $select = $this->loadVolumeAssignmentsForRoleQuery(Episciences_User_Assignment::ROLE_EDITOR);

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
     *
     * @param string $role
     * @return Zend_Db_Select
     */
    private function loadVolumeAssignmentsForRoleQuery(string $role)
    {

        $roles = ($role === Episciences_User_Assignment::ROLE_EDITOR) ?
            [Episciences_Acl::ROLE_GUEST_EDITOR, Episciences_Acl::ROLE_EDITOR, Episciences_Acl::ROLE_CHIEF_EDITOR] :
            (array)$role;

        $subquery = $this->_db->select()
            ->from(T_ASSIGNMENTS, ['UID', 'MAX(`WHEN`) AS WHEN'])
            ->where('ITEM = ?', Episciences_User_Assignment::ITEM_VOLUME)
            ->where('ITEMID = ?', $this->getVid())
            ->where('ROLEID = ?', $role)
            ->group('UID');

        return $this->_db->select()
            ->from(['a' => T_ASSIGNMENTS], ['UID', 'STATUS', 'WHEN'])
            ->joinUsing(T_USERS, 'UID', ['LANGUEID', 'SCREEN_NAME'])
            ->join(['b' => $subquery], 'a.UID = b.UID AND a.`WHEN` = b.`WHEN`')
            ->join(['ur' => T_USER_ROLES], 'ur.UID = a.UID')
            ->where('ur.ROLEID IN (?)', $roles)
            ->where('ur.RVID = ?', RVID);
    }

    /**
     * @return mixed
     */
    public function getVid()
    {
        return $this->_vid;
    }

    /**
     * @param $vid
     * @return $this
     */
    public function setVid($vid)
    {
        $this->_vid = (int)$vid;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getReviewers()
    {
        if (!isset($this->_reviewers)) {
            $this->loadReviewers();
        }
        return $this->_reviewers;
    }

    /**
     *
     */
    public function loadReviewers()
    {
        $sql = $this->_db->select()
            ->from(T_REVIEWER_POOL, ['UID'])
            ->where('RVID = ?', RVID)
            ->where('VID = ?', $this->getVid());

        $uids = $this->_db->fetchCol($sql);
        $reviewers = [];
        foreach ($uids as $uid) {
            $oReviewer = new Episciences_Reviewer;
            if ($oReviewer->findWithCAS($uid)) {
                $reviewers[$uid] = $oReviewer;
            }
        }

        $this->setReviewers($reviewers);
    }

    /**
     * @param $reviewers
     * @return $this
     */
    public function setReviewers($reviewers)
    {
        $this->_reviewers = $reviewers;
        return $this;
    }

    /**
     * Assigne des rédacteurs au volume
     * @param $ids
     * @param array $params
     * @return array|bool
     */
    public function assign($ids, $params = [])
    {
        $params = [
            'rvid' => Ccsd_Tools::ifsetor($params['rvid'], RVID),
            'itemid' => $this->getVid(),
            'item' => Episciences_User_Assignment::ITEM_VOLUME,
            'roleid' => Episciences_User_Assignment::ROLE_EDITOR,
            'status' => Ccsd_Tools::ifsetor($params['status'], Episciences_User_Assignment::STATUS_ACTIVE)
        ];

        return Episciences_UsersManager::assign($ids, $params);
    }

    /**
     * Désassigne des rédacteurs du volume
     * @param $ids
     * @param array $params
     * @return array|bool
     */
    public function unassign($ids, $params = [])
    {
        $params = [
            'rvid' => Ccsd_Tools::ifsetor($params['rvid'], RVID),
            'itemid' => $this->getVid(),
            'item' => Episciences_User_Assignment::ITEM_VOLUME,
            'roleid' => Episciences_User_Assignment::ROLE_EDITOR
        ];

        return Episciences_UsersManager::unassign($ids, $params);
    }

    /**
     * @throws Exception
     */
    public function loadIndexedPapers()
    {
        $query = 'q=*%3A*';
        $query .= '&sort=publication_date_tdate+asc&wt=phps&omitHeader=true';
        $query .= '&fq=revue_id_i:' . RVID;
        $query .= '&fq=(volume_id_i:' . $this->getVid() . '+OR+secondary_volume_id_i:' . $this->getVid() . ')';
        $query .= '&rows=1000';

        $result = Episciences_Tools::solrCurl($query, 'episciences', 'select', true);
        $result = unserialize($result, ['allowed_classes' => false]);


        if ($result && array_key_exists('response', $result)) {

            $positions = $this->getPaperPositions();
            $response = $result['response'];
            $papers = $response['docs'];
            $sorted_papers = [];
            $unsorted_papers = [];

            if (is_array($positions) && !empty($positions)) {
                $positions = array_flip($positions);
                foreach ($papers as $paper) {
                    if (array_key_exists($paper['paperid'], $positions)) {
                        $sorted_papers[$positions[$paper['paperid']]] = $paper;
                    } else {
                        $unsorted_papers[] = $paper;
                    }
                }
                ksort($sorted_papers);
                $sorted_papers = array_merge($sorted_papers, $unsorted_papers);
            } else {
                $sorted_papers = $papers;
            }

            $this->setIndexedPapers($sorted_papers);
        }
    }

    /**
     * @return array
     */
    public function getPaperPositions(): array
    {
        if (empty($this->_paperPositions)) {
            $this->loadPaperPositions();
        }
        return $this->_paperPositions;
    }

    /**
     * @param $positions
     */
    public function setPaperPositions($positions)
    {
        $this->_paperPositions = $positions;
    }

    /**
     *
     */
    public function loadPaperPositions()
    {
        $positions = [];
        try {
            $select = $this->_db->select()
                ->from(T_VOLUME_PAPER_POSITION, ['POSITION', 'PAPERID'])
                ->where('VID = ?', $this->getVid())
                ->order('POSITION');

            $tmp = $this->_db->fetchPairs($select);
            reset($tmp); // Remet le pointeur interne de tableau au début
            if (key($tmp) == 1) {
                foreach ($tmp as $position => $paperId) {
                    $i = $position - 1;
                    $positions[$i] = $paperId;
                }
            } else {
                $positions = $tmp;
            }
        } catch (Exception $exception) {
            $positions = [];
        }

        $this->setPaperPositions($positions);
    }

    /**
     * @return null
     */
    public function getIndexedPapers()
    {
        return $this->_indexedPapers;
    }

    /**
     * @param $indexedPapers
     */
    public function setIndexedPapers($indexedPapers)
    {
        $this->_indexedPapers = $indexedPapers;
    }

    /**
     *
     */
    public function loadSettings()
    {
        $select = $this->_db->select()
            ->from(T_VOLUME_SETTINGS, ['SETTING', 'VALUE'])
            ->where('VID = ?', $this->getVid());

        $this->setSettings($this->_db->fetchPairs($select));
    }

    /**
     *
     */
    public function loadMetadatas()
    {
        $metadatas = [];

        $select = $this->_db->select()
            ->from(T_VOLUME_METADATAS)
            ->where('VID = ?', $this->getVid())
            ->order('POSITION');
        $result = $this->_db->fetchAssoc($select);

        foreach ($result as $data) {
            $values = ['ID' => $data['ID'], 'VID' => $data['VID'], 'FILE' => $data['FILE']];
            $metadata = new Episciences_Volume_Metadata($values);
            $metadata->loadTranslations();
            $metadatas[$metadata->getId()] = $metadata;
        }

        $this->setMetadatas($metadatas);
    }

    /**
     * @param $id
     * @return bool|mixed
     */
    public function getMetadata($id)
    {
        if (array_key_exists($id, $this->getMetadatas())) {
            return $this->_metadatas[$id];
        }
        return false;
    }

    /**
     * @return array
     */
    public function getMetadatas(): array
    {
        return $this->_metadatas;
    }

    /**
     * @param $metadatas
     */

    public function setMetadatas($metadatas)
    {
        $this->_metadatas = $metadatas;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $res['vid'] = $this->getVid();
        $res['rvid'] = $this->getRvid();
        $res['position'] = $this->getPosition();
        $res['settings'] = $this->getSettings();
        $res['metadatas'] = $this->getMetadatas();

        return $res;
    }

    /**
     * @return mixed
     */
    public function getRvid()
    {
        return $this->_rvid;
    }

    /**
     * @param $rvId
     * @return $this
     */
    public function setRvid($rvId): self
    {
        $this->_rvid = $rvId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPosition()
    {
        return $this->_position;
    }

    /**
     * @param $position
     * @return $this
     */
    public function setPosition($position): self
    {
        $this->_position = $position;
        return $this;
    }

    /**
     * @param array $data form data volume
     * @param int|null $vid
     * @param array|null $post form data volume metadata
     * @return bool
     */
    public function save($data, $vid = null, $post = null): bool
    {

        // Enregistrement de la position des articles
        if (($post !== null) && (array_key_exists(self::VOLUME_PAPER_POSITIONS, $post)) && ($vid !== null)) {
            $this->savePaperPositionsInVolume($vid, $post[self::VOLUME_PAPER_POSITIONS]);
        }

        $settings = [
            self::SETTING_STATUS => $data['status'],
            self::SETTING_CURRENT_ISSUE => $data['current_issue'],
            self::SETTING_SPECIAL_ISSUE => $data['special_issue'],
            self::SETTING_ACCESS_CODE => $this->getSetting('access_code')
        ];

        if ($settings[self::SETTING_SPECIAL_ISSUE] == 1 && !$settings['access_code']) {
            $settings[self::SETTING_ACCESS_CODE] = $this->createAccessCode();
        }

        // Ajout d'un nouveau volume
        if (empty($vid)) {
            // Récupération de la position du volume
            $position = $this->getNewVolumePosition();

            // Enregistrement du volume
            $vid = $this->addNewVolume($position, $data['bib_reference']);
            if ($vid === 0) {
                return false;
            }

            // Enregistrement des paramètres du volume
            $this->saveVolumeArraySettings($settings, $vid);

        } else {

            // Modification d'un volume
            if ($this->getBib_reference() !== $post['bib_reference']) {
                $this->setBib_reference($post['bib_reference']);
                $this->updateVolume();
            }

            // Mise à jour des paramètres du volume
            $this->saveVolumeArraySettings($settings, $vid, true);

        }


        $this->setVid($vid);

        // Préparation des données de traduction
        $path = self::TRANSLATION_PATH;
        $file = self::TRANSLATION_FILE;
        $translations = Episciences_Tools::getOtherTranslations($path, $file, '#volume_' . $vid . '_#');

        // Nom du volume
        foreach ($data['title'] as $lang => $translated) {
            $translations[$lang]['volume_' . $vid . '_title'] = $translated;
        }

        // Description du volume
        foreach ($data['description'] as $lang => $translated) {
            $translations[$lang]['volume_' . $vid . '_description'] = $translated;
        }


        // Enregistrement des traductions
        $resWriting = Episciences_Tools::writeTranslations($translations, $path, $file);


        if (!$resWriting) {
            return false;
        }

        return true;
    }


    /**
     * Save Paper positions from a formular (with jquery sortable) in a Volume
     * @param $vid
     * @param $paper_positions
     */
    public function savePaperPositionsInVolume($vid, $paper_positions)
    {
        // value="paper-126,paper-38"
        $positionsFromFormular = explode(',', $paper_positions);
        $paper_positions=[];
        foreach ($positionsFromFormular as $position => $paper) {
            $paperid = substr($paper, 6); // paper-126
            if (!is_numeric($paperid) || !is_numeric($position)) {
                continue;
            }
            $paper_positions[(int)$position] = (int)$paperid;
        }

        if (!empty($paper_positions)) {
            Episciences_VolumesManager::savePaperPositionsInVolume($vid, $paper_positions);
        }
    }

    /**
     * @return int
     */
    private function getNewVolumePosition(): int
    {
        $select = $this->_db->select()->from(T_VOLUMES, new Zend_Db_Expr('MAX(POSITION)+1'))->where('RVID = ?', RVID);
        $position = $this->_db->fetchOne($select);
        if (empty($position)) {
            $position = 1;
        }
        return (int)$position;
    }

    /**
     * Add a new volume, return a New volume VID
     * @param int $position
     * @param string|null $bibReference
     * @return int the New volume id OR 0 if we fail
     */
    private function addNewVolume(int $position, string $bibReference = null): int
    {
        $values['RVID'] = RVID;
        $values['POSITION'] = $position;

        if (!empty($bibReference)) {
            $values['BIB_REFERENCE'] = $bibReference;
        }

        try {
            $affectedRows = $this->_db->insert(T_VOLUMES, $values);
        } catch (Zend_Db_Adapter_Exception $exception) {
            return 0;
        }

        if ($affectedRows != 1) {
            $vid = 0;
        } else {
            $vid = $this->_db->lastInsertId();
        }

        return $vid;

    }

    /**
     * @param array $settings
     * @param int $vid
     * @param bool $update
     */
    private function saveVolumeArraySettings(array $settings, int $vid, bool $update = false)
    {
        foreach ($settings as $setting => $value) {
            $this->saveVolumeSetting($setting, $value, $vid, $update);
        }
    }

    /**
     * @param $setting
     * @param $value
     * @param int $vid
     * @param bool $update
     */
    private function saveVolumeSetting($setting, $value, int $vid, bool $update = false)
    {
        try {

            if (!$update) {
                $this->_db->insert(T_VOLUME_SETTINGS, ['SETTING' => $setting, 'VALUE' => $value, 'VID' => $vid]);
            } else {
                $sql = $this->_db->quoteInto('INSERT INTO ' . T_VOLUME_SETTINGS . ' (SETTING, VALUE, VID) VALUES (?) 
                ON DUPLICATE KEY UPDATE VALUE = VALUES(VALUE)', ['SETTING' => $setting, 'VALUE' => $value, 'VID' => $vid]);
                $this->_db->query($sql);
            }
        } catch (Zend_Db_Adapter_Exception $exception) {
            trigger_error(sprintf($exception->getMessage(), E_USER_WARNING));
        }
    }

    /**
     * @param $post
     * @return bool
     */
    public function saveVolumeMetadata($post)
    {

        // Enregistrement des nouvelles metadatas et des metadatas modifiées
        $position = 0;
        $newMetadata_Ids = [];

        foreach ($post as $key => $value) {
            if (strpos($key, 'md_ui-id-') === 0) {
                $position++;
                $values = array_merge(json_decode($value, true), ['vid' => $this->getVid(), 'position' => $position]);
                if (array_key_exists('tmpfile', $values)) {
                    $values['tmpfile'] = json_decode($values['tmpfile'], true);
                }
                $metadata = new Episciences_Volume_Metadata($values);
                $saveResult = $metadata->save();

                if (!$saveResult) {
                    return false;
                }
                $this->setMetadata($metadata);

                $newMetadata_Ids[] = $metadata->getId();
            }
        }

        // Suppression des anciennes metadatas
        foreach ($this->getMetadatas() as $oldMetadata_Ids => $metadata) {
            if (!in_array($oldMetadata_Ids, $newMetadata_Ids)) {
                $this->_db->delete(T_VOLUME_METADATAS, 'ID = ' . $oldMetadata_Ids);
                if ($metadata->hasFile() && file_exists(REVIEW_FILES_PATH . 'volumes/' . $this->getVid() . '/' . $metadata->getFile())) {
                    unlink(REVIEW_FILES_PATH . 'volumes/' . $this->getVid() . '/' . $metadata->getFile());
                }
            }
        }

        return true;

    }

    /**
     * @param Episciences_Volume_Metadata $metadata
     */
    public function setMetadata(Episciences_Volume_Metadata $metadata)
    {
        $this->_metadatas[$metadata->getId()] = $metadata;
    }

    /**
     * @param null $lang
     * @param bool $forceResult
     * @return string|null
     * @throws Zend_Exception
     */
    public function getName($lang = null, $forceResult = false)
    {
        $result = null;
        $translator = Zend_Registry::get('Zend_Translate');
        // try to fetch translation for specified language
        if ($translator->isTranslated($this->getNameKey(), $lang)) {
            $result = $translator->translate($this->getNameKey(), $lang);
        }
        if (!$result && $forceResult) {
            if ($translator->isTranslated($this->getNameKey(), 'en')) {
                // if it cannot be found, try to fetch english translation
                $result = $translator->translate($this->getNameKey(), 'en');
            } else {
                // else, try to fetch any translation
                foreach (Episciences_Tools::getLanguages() as $locale) {
                    if ($translator->isTranslated($this->getNameKey(), $locale)) {
                        $result = $translator->translate($this->getNameKey(), $locale);
                        break;
                    }
                }
            }
            if (!$result) {
                $result = self::UNLABELED_VOLUME;
            }
        }
        return $result;
    }

    /**
     * @return string
     */
    public function getNameKey()
    {
        return 'volume_' . $this->getVid() . '_title';
    }

    /**
     * @return string
     */
    public function getDescriptionKey()
    {
        return 'volume_' . $this->getVid() . '_description';
    }

    /**
     * Renvoie les préparateurs de copie assignés au volume
     * @param bool $active
     * @return array
     */
    public function getCopyEditors($active = true)
    {
        if (!isset($this->_copyEditors)) {
            $this->loadCopyEditors($active);
        }
        return $this->_copyEditors;
    }

    /**
     * Assigne des préparateurs de copie au volume
     * @param array $copyEditors
     * @return $this
     */
    public function setCopyEditors(array $copyEditors)
    {
        $this->_copyEditors = $copyEditors;
        return $this;
    }

    /**
     * Charge les préparateurs de copie assignés à un volume
     * @param bool $active
     */

    public function loadCopyEditors($active = true)
    {

        $select = $this->loadVolumeAssignmentsForRoleQuery(Episciences_User_Assignment::ROLE_COPY_EDITOR);
        $result = $this->_db->fetchAssoc($select);

        $copyEditors = [];

        if ($active && !empty($result)) {
            $result = array_filter($result, function ($user) {
                return ($user['STATUS']) == Episciences_User_Assignment::STATUS_ACTIVE;
            });
        }

        if ($result) {

            foreach ($result as $uid => $user) {
                $ce = new Episciences_CopyEditor();
                $ce->findWithCAS($uid);
                $ce->setWhen($user['WHEN']);
                $ce->setStatus($user['STATUS']);
                $copyEditors[$uid] = $ce;
            }
        }

        $this->setCopyEditors($copyEditors);

    }

    /**
     * Returns a list of sorted papers for current volume
     * @return array
     * @throws Zend_Db_Select_Exception
     */
    public function getSortedPapersFromVolume(): array
    {
        $paperList = [];
        $sorted_papers = [];

        $papers = $this->getPaperListFromVolume([Episciences_Paper::STATUS_OBSOLETE]);

        /* @var $p Episciences_paper */
        foreach ($papers as $p) {
            $docId = $p->getDocid();
            foreach ($p->getAllTitles() as $title) {
                $paperList[$docId]['title'] = $title;
            }
            $paperList[$docId]['docid'] = $docId;
            // RT#129760
            $paperList[$docId]['paperid'] = $p->getPaperid();
            $paperList[$docId]['status'] = $p->getStatus();
        }



        $positions = $this->getPaperPositions();

        if (!empty($positions)) {
            /** @var array $positions [paperId, position] */
            $positions = array_flip($positions);


            $maxPosition = max($positions);

            /**
             * @var  $docId
             * @var  array $paper [title, docid, status, self::PAPER_POSITION_NEEDS_TO_BE_SAVED]
             */
            foreach ($paperList as $docId => $paper) {
                /** @var Episciences_Paper $currentOPaper */
                $currentOPaper = $papers[$docId];
                $paperId = $currentOPaper->getPaperid();
                $paper[self::PAPER_POSITION_NEEDS_TO_BE_SAVED] = false;
                if (array_key_exists($currentOPaper->getPaperId(), $positions)) {
                    $sorted_papers[$positions[$paperId]] = $paper;
                } else if ($currentOPaper->getPosition() === null) {
                    $maxPosition++;
                    $paperPosition = $maxPosition;
                    $paper[self::PAPER_POSITION_NEEDS_TO_BE_SAVED] = true;
                    $sorted_papers[$paperPosition] = $paper;
                 } else {
                    $sorted_papers[$currentOPaper->getPosition()] = $paper;
                }
            }
            ksort($sorted_papers);
        } else {
            /*
             *  When the whole volume (legacy or import) has never been sorted
             *  Automagically sort
             */

            ksort($paperList);
            foreach ($paperList as $paper) {
                $paper[self::PAPER_POSITION_NEEDS_TO_BE_SAVED] = true;
                $sorted_papers[] = $paper;
            }
        }

        return $sorted_papers;
    }

    /**
     * @param array $excludedStatus
     * @return array
     * @throws Zend_Db_Select_Exception
     */
    public function getPaperListFromVolume($excludedStatus = []) : array {

        $options['is']['rvid'] = RVID;
        $options['is']['vid'] = [$this->getVid()];
        $status = empty($excludedStatus) ? Episciences_Paper::DO_NOT_SORT_THIS_KIND_OF_PAPERS : array_merge($excludedStatus, Episciences_Paper::DO_NOT_SORT_THIS_KIND_OF_PAPERS);
        $options['isNot'] = ['status' => $status];

        return Episciences_PapersManager::getList($options);

    }

    /**
     * @return null|string
     */
    public function getBib_reference()
    {
        return $this->_bib_reference;
    }

    /**
     * @param null $bibReference
     * @return Episciences_Volume
     */
    public function setBib_reference($bibReference): \Episciences_Volume
    {
        $this->_bib_reference = $bibReference;
        return $this;
    }

    /**
     * update a volume
     * @return bool
     */
    private function updateVolume(): bool
    {
        $where = 'VID = ' . $this->getVid();

        try {
            return ($this->_db->update(T_VOLUMES, ['BIB_REFERENCE' => $this->getBib_reference()], $where ) > 0);
        } catch (Zend_Db_Adapter_Exception $exception) {
            return false;
        }
    }

    /**
     * @param string $separator
     * @return string
     * @throws Zend_Db_Statement_Exception
     */
    public function formatEditors(string $separator = ', '): string
    {
        $screenNames = '';
        $count = 0;

        $editors = $this->getEditors();

        foreach ($editors as $editor) {
            $screenNames .= $editor->getScreenName();

            if ($count < (count($editors) - 1)) {
                $screenNames .= $separator;
            }

            ++$count;
        }

        return $screenNames;
    }
}
