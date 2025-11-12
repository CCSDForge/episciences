<?php

class Episciences_Volume
{
    public const MARKDOWN_TO_HTML = 'markdownToHtml';
    public const HTML_TO_MARKDOWN = 'htmlToMarkdown';
    const VOLUME_PAPER_POSITIONS = 'paper_positions';
    const TRANSLATION_FILE = 'volumes.php';
    const SETTING_STATUS = 'status';
    public const DEFAULT_FETCH_MODE = 'array';

    // Volume settings names
    const SETTING_CURRENT_ISSUE = 'current_issue';
    const SETTING_SPECIAL_ISSUE = 'special_issue';
    const SETTING_ACCESS_CODE = 'access_code';
    const UNLABELED_VOLUME = 'Unlabeled volume';
    const PAPER_POSITION_NEEDS_TO_BE_SAVED = 'needsToBeSaved';

    // volume proceeding (conference act)
    const VOLUME_CONFERENCE_NAME = 'conference_name';
    const VOLUME_CONFERENCE_THEME = 'conference_theme';
    const VOLUME_CONFERENCE_ACRONYM = 'conference_acronym';
    const VOLUME_CONFERENCE_NUMBER = 'conference_number';
    const VOLUME_CONFERENCE_LOCATION = 'conference_location';
    const VOLUME_CONFERENCE_START_DATE = 'conference_start';
    const VOLUME_CONFERENCE_END_DATE = 'conference_end';
    const VOLUME_CONFERENCE_DOI = 'conference_proceedings_doi';

    const VOLUME_IS_PROCEEDING = 'is_proceeding';

    const VOLUME_YEAR = 'year';

    const VOLUME_NUM = 'num';
    public const VOLUME_PREFIX_DESCRIPTION = 'description_';
    public const VOLUME_PREFIX_TITLE = 'title_';

    protected $_db = null;
    private $_vid;
    private $_rvid;
    private $_position;
    private $_settings = [];
    private array $_metadatas = [];
    private $_indexedPapers = null;
    private $_paperPositions = [];
    private $_editors;
    // Copy Editors
    private $_copyEditors = [];
    private $_bib_reference = null;

    private ?string $_vol_type = null;
    //private ?int $_vol_year = null;
    private $_vol_year = null;
    //private ?string $_vol_num = null;
    private $_vol_num = null;
    private int $nbOfPapersInVolume = 0;
    private ?array $titles;
    private ?array $descriptions;

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
        $gaps = [];
        $actualArray = array_keys($sorted_papers);
        for ($i = 0; $i < max($actualArray); $i++) {
            if (!in_array($i, $actualArray, true)) {
                $gaps[] = $i;
            }
        }
        return $gaps;
    }

    /**
     * @param array $volOrSectArray
     * @param string $type
     * @return array
     * @throws Zend_Exception
     */
    public static function volumesOrSectionsToPublicArray(array $volOrSectArray, string $type): array
    {
        $arrayOfVolOrSect = [];
        $paperArray = [];
        $formatAsArrayMappings['docid'] = 'docid';
        $formatAsArrayMappings['paperid'] = 'paperid';
        $formatAsArrayMappings['url'] = 'es_doc_url_s';
        $formatAsArrayMappings['identifier'] = 'identifier_s';
        $formatAsArrayMappings['version'] = 'version_td';


        if ($type === Episciences_Volume::class) {
            /**
             * @var $volOrSectObj Episciences_Volume
             */
            $getId = 'getVid';
            $status = Episciences_Volume::SETTING_STATUS;

        } elseif ($type === Episciences_Section::class) {
            /**
             * @var $volOrSectObj Episciences_Section
             */
            $getId = 'getSid';
            $status = Episciences_Section::SETTING_STATUS;
        } else {
            trigger_error(sprintf('Unexpected type %s at %s', $type, __FUNCTION__), E_USER_WARNING);
            return [];
        }

        foreach ($volOrSectArray as $kVolOrSect => $volOrSectObj) {

            try {
                $volOrSectObj->loadIndexedPapers();
            } catch (Exception $exception) {
                $arrayOfVolOrSect[$kVolOrSect]['papers'] = $paperArray;
                continue;
            }

            $arrayOfVolOrSect[$kVolOrSect]['id'] = $volOrSectObj->$getId();
            $arrayOfVolOrSect[$kVolOrSect]['position'] = $volOrSectObj->getPosition();
            $arrayOfVolOrSect[$kVolOrSect]['name'] = $volOrSectObj->getName();
            $arrayOfVolOrSect[$kVolOrSect][$status] = $volOrSectObj->getStatus();
            foreach ($volOrSectObj->getIndexedPapers() as $kPaper => $paper) {
                foreach ($formatAsArrayMappings as $volKeyName => $volValue) {
                    if (isset($paper[$volValue])) {
                        $paperArray[$kPaper][$volKeyName] = $paper[$volValue];
                    }
                }
            }
            $arrayOfVolOrSect[$kVolOrSect]['papers'] = $paperArray;
        }
        return $arrayOfVolOrSect;
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
    public function loadReviewers(): void
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
    public function assign($ids, array $params = [])
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
    public function unassign($ids, array $params = [])
    {
        $params = [
            'rvid' => Ccsd_Tools::ifsetor($params['rvid'], RVID),
            'itemid' => $this->getVid(),
            'item' => Episciences_User_Assignment::ITEM_VOLUME,
            'roleid' => Episciences_User_Assignment::ROLE_EDITOR
        ];

        return Episciences_UsersManager::unassign($ids, $params);
    }

    public function getSolrCountOfVolumePapers(): int
    {

        $numFound = 0;
        $query = 'q=*%3A*';
        $query .= '&wt=phps&omitHeader=true';
        $query .= '&fq=revue_id_i:' . RVID;
        $query .= '&fq=(volume_id_i:' . $this->getVid() . '+OR+secondary_volume_id_i:' . $this->getVid() . ')';
        $query .= '&rows=0';

        $result = Episciences_Tools::solrCurl($query);
        $result = unserialize($result, ['allowed_classes' => false]);

        if ($result && array_key_exists('response', $result)) {
            $response = $result['response'];
            $numFound = (int)$response['numFound'];
        }

        $this->setNbOfPapersInVolume($numFound);
        return $numFound;

    }

    /**
     * @throws Exception
     */
    public function loadIndexedPapers(): void
    {
        $query = 'q=*%3A*';
        $query .= '&sort=publication_date_tdate+asc&wt=phps&omitHeader=true';
        $query .= '&fq=revue_id_i:' . RVID;
        $query .= '&fq=(volume_id_i:' . $this->getVid() . '+OR+secondary_volume_id_i:' . $this->getVid() . ')';
        $query .= '&rows=1000';

        $result = Episciences_Tools::solrCurl($query);
        $result = unserialize($result, ['allowed_classes' => false]);


        if ($result && array_key_exists('response', $result)) {

            $positions = $this->getPaperPositions();
            $response = $result['response'];
            $papers = $response['docs'];
            $sorted_papers = [];
            $unsorted_papers = [];

            if (!empty($positions)) {
                $positions = array_flip($positions);
                foreach ($papers as $paper) {
                    if (array_key_exists($paper['paperid'], $positions)) {
                        $sorted_papers[$positions[$paper['paperid']]] = $paper;
                    } else {
                        $unsorted_papers[] = $paper;
                    }
                }
                ksort($sorted_papers);

                // Here we must use array_merge to avoid overwriting keys and thus loosing papers
                // The arrays contain numeric keys, so the later value will not overwrite the original value, but will be appended.
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


    public function loadMetadatas(): void
    {
        $allMetadata = [];

        $select = $this->_db->select()
            ->from(T_VOLUME_METADATAS)
            ->where('VID = ?', $this->getVid())
            ->order('POSITION');
        $result = $this->_db->fetchAssoc($select);

        foreach ($result as $data) {
            $values = [
                'ID' => $data['ID'],
                'title' => $data['titles'],
                'content' => $data['CONTENT'],
                'VID' => $data['VID'],
                'FILE' => $data['FILE'],
                'date_creation' => $data['date_creation'],
                'date_updated' => $data['date_updated']
            ];

            Episciences_VolumesAndSectionsManager::dataProcess($values, 'decode', ['title', 'content']);

            $metadata = new Episciences_Volume_Metadata($values);
            $allMetadata[$metadata->getId()] = $metadata;
        }

        $this->setMetadatas($allMetadata);
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

    public function setMetadatas(array $metadatas = [])
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
        $res['bib_reference'] = $this->getBib_reference();
        $res['titles'] = $this->getTitles();
        $res['descriptions'] = $this->getDescriptions();
        $res['settings'] = $this->getSettings();
        $res['metadatas'] = $this->getMetadatas();

        return $res;
    }

    public function toPublicArray(): array
    {
        $res['vid'] = $this->getVid();
        $res['name'] = $this->getName();
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
     * @param array $post form data volume metadata
     * @return bool
     */
    public function save(array $data, int $vid = null, array $post = []): bool
    {
        $post = array_merge($post, $data);
        $post['title'] = Episciences_VolumesManager::revertVolumeTitleToTextArray($post) ?? null;
        $post['description'] = Episciences_VolumesManager::revertVolumeDescriptionToTextareaArray($post) ?? null;
        $post['bib_reference'] = $post['bib_reference'] ?? null;

        // Enregistrement de la position des articles
        if (
            isset($post[self::VOLUME_PAPER_POSITIONS]) && ($vid !== null)
        ) {
            $this->savePaperPositionsInVolume($vid, $post[self::VOLUME_PAPER_POSITIONS]);
        }

        $settings = [
            self::SETTING_STATUS => $data['status'] ?? 0,
            self::SETTING_CURRENT_ISSUE => $data['current_issue'] ?? 0,
            self::SETTING_SPECIAL_ISSUE => $data['special_issue'] ?? 0,
            self::SETTING_ACCESS_CODE => $this->getSetting('access_code'),
            self::VOLUME_IS_PROCEEDING => $data['is_proceeding'] ?? 0,
            self::VOLUME_CONFERENCE_NAME => $data['conference_name'] ?? '',
            self::VOLUME_CONFERENCE_THEME => $data['conference_theme'] ?? '',
            self::VOLUME_CONFERENCE_ACRONYM => $data['conference_acronym'] ?? '',
            self::VOLUME_CONFERENCE_NUMBER => $data['conference_number'] ?? '',
            self::VOLUME_CONFERENCE_LOCATION => $data['conference_location'] ?? '',
            self::VOLUME_CONFERENCE_START_DATE => $data['conference_start'] ?? '',
            self::VOLUME_CONFERENCE_END_DATE => $data['conference_end'] ?? '',
        ];

        $settingsProceeding = [
            self::VOLUME_IS_PROCEEDING => $data['is_proceeding'] ?? 0,
            self::VOLUME_CONFERENCE_NAME => $data['conference_name'] ?? '',
            self::VOLUME_CONFERENCE_THEME => $data['conference_theme'] ?? '',
            self::VOLUME_CONFERENCE_ACRONYM => $data['conference_acronym'] ?? '',
            self::VOLUME_CONFERENCE_NUMBER => $data['conference_number'] ?? '',
            self::VOLUME_CONFERENCE_LOCATION => $data['conference_location'] ?? '',
            self::VOLUME_CONFERENCE_START_DATE => $data['conference_start'] ?? '',
            self::VOLUME_CONFERENCE_END_DATE => $data['conference_end'] ?? '',
            self::VOLUME_CONFERENCE_DOI => ''
        ];

        if ((int)$settings[self::SETTING_SPECIAL_ISSUE] === 1 && !$settings['access_code']) {
            $settings[self::SETTING_ACCESS_CODE] = $this->createAccessCode();
        }


        try {
            $doiPrefix = Zend_Registry::get('reviewSettingsDoi')->getDoiPrefix();
        } catch (Zend_Exception $e) {
            $doiPrefix = false;
            trigger_error($e->getMessage());
        }

        if (
            isset($post['conference_proceedings_doi']) && $post['conference_proceedings_doi'] !== '' &&
            (
                $data['doi_status'] === Episciences_Volume_DoiQueue::STATUS_ASSIGNED ||
                $data['doi_status'] === Episciences_Volume_DoiQueue::STATUS_NOT_ASSIGNED
            ) &&

            $doiPrefix) {
            $doiPrefixSetting = $doiPrefix;
            $doiPrefixSetting .= '/';
            $doiPrefixSetting .= RVCODE;
            $doiPrefixSetting .= '.proceedings.';
            $doiPrefixSetting .= $post['conference_proceedings_doi'];
            $settings[self::VOLUME_CONFERENCE_DOI] = $doiPrefixSetting;
            $settingsProceeding[self::VOLUME_CONFERENCE_DOI] = $doiPrefixSetting;
        }

        $this->setVol_year(!empty($post['year']) ? $post['year'] : null);
        $this->setVol_num(!empty($post['num']) ? $post['num'] : null);
        $this->setBib_reference($post['bib_reference']);

        if ($data['special_issue'] === "1" && $data['is_proceeding'] === "1") {
            $this->setVol_type('special_issue,proceedings');
        } elseif ($data['special_issue'] === "1") {
            $this->setVol_type('special_issue');
        } elseif (isset($data['is_proceeding']) && $data['is_proceeding'] === "1") {
            $this->setVol_type('proceedings');
        } else {
            $this->setVol_type(null);
        }
        $this->setTitles($post['title']);
        $this->setDescriptions($post['description']);

        // Ajout d'un nouveau volume
        if (!$vid) {
            // Récupération de la position du volume
            //$position = $this->getNewVolumePosition();

            // Enregistrement du volume
            $vid = $this->addNewVolume();

            if ($vid === 0) {
                return false;
            }

            $this->setVid($vid);

            // Enregistrement des paramètres du volume
            $this->saveVolumeArraySettings($settings, $vid);
            if (isset($data['is_proceeding']) && $data['is_proceeding'] === '1') {
                $volumeProceeding = new Episciences_VolumeProceeding();
                $volumeProceeding->saveVolumeArrayProceeding($settingsProceeding, $vid);
            }


        } else {
            // Modification d'un volume
            $this->updateVolume();

            // Mise à jour des paramètres du volume
            $this->saveVolumeArraySettings($settings, $vid, true);

            if ($data['is_proceeding'] === '1') {
                $volumeProceeding = new Episciences_VolumeProceeding();
                $volumeProceeding->saveVolumeArrayProceeding($settingsProceeding, $vid, true);
            }


        }

        return true;
    }


    /**
     * Save Paper positions from a form (with jquery sortable) in a Volume
     * @param $vid
     * @param $paper_positions
     */
    public function savePaperPositionsInVolume($vid, $paper_positions): void
    {
        // value="paper-126,paper-38"
        $positionsFromFormular = explode(',', $paper_positions);
        $paper_positions = [];
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
     * @return int the New volume id OR 0 if we fail
     */
    private function addNewVolume(): int
    {
        $values['RVID'] = RVID;
        $values['POSITION'] = 0;
        $values['BIB_REFERENCE'] = $this->getBib_reference();
        $values['titles'] = $this->preProcess($this->getTitles());
        $values['descriptions'] = $this->preProcess($this->getDescriptions());
        $values['vol_type'] = $this->getVol_type();
        $values['vol_year'] = $this->getVol_year();
        $values['vol_num'] = $this->getVol_num();
        Episciences_VolumesAndSectionsManager::dataProcess($values);

        try {
            $affectedRows = $this->_db->insert(T_VOLUMES, $values);
        } catch (Zend_Db_Adapter_Exception $exception) {
            return 0;
        }

        if ($affectedRows != 1) {
            $vid = 0;
        } else {
            $vid = $this->_db->lastInsertId();
            $result = Episciences_VolumesAndSectionsManager::sort();
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
     * Save volume metadata from POST data with XSS prevention
     * @param array $post POST data containing metadata
     * @return bool Success status
     * @throws JsonException
     */
    public function saveVolumeMetadata($post): bool
    {
        if (empty($post) || !is_array($post)) {
            return false;
        }

        $position = 0;
        $newMetadataIds = [];
        $errors = [];

        foreach ($post as $key => $value) {
            if (!str_starts_with($key, 'md_ui-id-')) {
                continue;
            }

            $position++;

            try {
                $decodedValues = $this->decodeAndValidateMetadata($value);
            } catch (JsonException $e) {
                $errors[] = "Failed to decode metadata for key {$key}: " . $e->getMessage();
                continue;
            }

            // Sanitize user input to prevent XSS
            $sanitizedValues = $this->sanitizeMetadataValues($decodedValues);

            // Add required fields
            $sanitizedValues['vid'] = $this->getVid();
            $sanitizedValues['position'] = $position;

            $metadata = new Episciences_Volume_Metadata($sanitizedValues);

            if (!$metadata->save()) {
                $errors[] = "Failed to save metadata at position {$position}";
                continue;
            }

            $this->setMetadata($metadata);
            $newMetadataIds[] = $metadata->getId();
        }

        // Clean up old metadata
        $this->deleteOldMetadata($newMetadataIds);

        // Log errors if any occurred
        if (!empty($errors)) {
            error_log('Volume metadata save errors: ' . implode('; ', $errors));
        }

        return empty($errors);
    }

    /**
     * Decode and validate JSON metadata from form input
     * @param string $value JSON string from form
     * @return array Decoded metadata
     * @throws JsonException
     */
    private function decodeAndValidateMetadata(string $value): array
    {
        $decodedValues = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decodedValues)) {
            throw new JsonException('Decoded metadata must be an array');
        }

        // Handle nested tmpfile JSON
        if (array_key_exists('tmpfile', $decodedValues) && !empty($decodedValues['tmpfile'])) {
            $decodedValues['tmpfile'] = json_decode(
                $decodedValues['tmpfile'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }

        return $decodedValues;
    }

    /**
     * Sanitize metadata values to prevent XSS attacks
     * @param array $values Raw metadata values
     * @return array Sanitized metadata values
     */
    private function sanitizeMetadataValues(array $values): array
    {
        $sanitized = [];

        // Sanitize title array - HTML escape each language version
        if (isset($values['title']) && is_array($values['title'])) {
            $sanitized['title'] = [];
            foreach ($values['title'] as $lang => $title) {
                if (is_string($title)) {
                    $sanitized['title'][$lang] = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
                }
            }
        }

        // Sanitize content array - HTML escape each language version
        if (isset($values['content']) && is_array($values['content'])) {
            $sanitized['content'] = [];
            foreach ($values['content'] as $lang => $content) {
                if (is_string($content)) {
                    $sanitized['content'][$lang] = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
                }
            }
        }

        // Pass through safe values without modification
        $safeFields = ['id', 'tmpfile', 'file', 'deletelist'];
        foreach ($safeFields as $field) {
            if (isset($values[$field])) {
                $sanitized[$field] = $values[$field];
            }
        }

        return $sanitized;
    }

    /**
     * @param array $newMetadataIds
     * @return void
     */
    private function deleteOldMetadata(array $newMetadataIds = []): void
    {

        foreach ($this->getMetadatas() as $oldMetadataIds => $metadata) {
            if (!in_array($oldMetadataIds, $newMetadataIds, false)) {
                $this->_db->delete(T_VOLUME_METADATAS, 'ID = ' . $oldMetadataIds);
                if ($metadata->hasFile() && file_exists(REVIEW_FILES_PATH . 'volumes/' . $this->getVid() . '/' . $metadata->getFile())) {
                    unlink(REVIEW_FILES_PATH . 'volumes/' . $this->getVid() . '/' . $metadata->getFile());
                }
            }
        }

    }

    /**
     * @param Episciences_Volume_Metadata $metadata
     */
    public function setMetadata(Episciences_Volume_Metadata $metadata)
    {
        $this->_metadatas[$metadata->getId()] = $metadata;
    }

    /**
     * @param string|null $lang
     * @param bool $forceResult
     * @return string
     */
    public function getName(string $lang = null, bool $forceResult = true): string
    {

        $titles = $this->getTitles();

        if (!$titles) {
            return self::UNLABELED_VOLUME;
        }

        if (null === $lang) {
            try {
                $lang = Zend_Registry::get('lang');
            } catch (Zend_Exception $e) {
                trigger_error($e->getMessage());
            }
        }

        return $forceResult ? ($titles[$lang] ?? $titles[array_key_first($titles)]) : self::UNLABELED_VOLUME;

    }

    /**
     * @param string|null $lang
     * @param bool $force
     * @return string
     */
    public function getNameKey(string $lang = null, bool $force = true): string
    {
        $titles = $this->getTitles();

        if (!empty($titles)) {

            $locale = !$lang ? Episciences_Tools::getLocale() : $lang;

            if ($locale && isset($titles[$locale])) {
                return $titles[$locale];
            }

        }

        return $force ? 'volume_' . $this->getVid() . '_title' : '';

    }

    /**
     * @return string
     */
    public function getDescriptionKey(bool $force = false): string
    {
        $descriptions = $this->getDescriptions();

        if (!empty($descriptions)) {

            $locale = Episciences_Tools::getLocale();
            if ($locale && isset($descriptions[$locale])) {
                return $descriptions[$locale];
            }
        }

        return $force ? 'volume_' . $this->getVid() . '_description' : '';
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
     * @param string $fetchMode
     * @return array
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Exception
     */
    public function getSortedPapersFromVolume(string $fetchMode = self::DEFAULT_FETCH_MODE): array
    {
        $paperList = [];
        $sorted_papers = [];

        $locale = Episciences_Tools::getLocale() ?: Episciences_Review::getDefaultLanguage();

        $papers = $this->getPaperListFromVolume([Episciences_Paper::STATUS_OBSOLETE]);

        $positions = $this->getPaperPositions();

        $isDefaultFetch = $fetchMode === self::DEFAULT_FETCH_MODE;

        if ($isDefaultFetch) {

            /* @var $p Episciences_paper */
            foreach ($papers as $p) {
                $docId = $p->getDocid();
                $titles = $p->getAllTitles();
                $vid = $p->getVid(); // primary volume

                if (array_key_exists($locale, $titles)) {
                    $pTitle = $titles[$locale];
                } elseif (array_key_exists(Episciences_Review::DEFAULT_LANG, $titles)) {
                    $pTitle = $titles[Episciences_Review::DEFAULT_LANG];
                } else {
                    $pTitle = $titles[array_key_first($titles)];
                }

                $paperList[$docId]['title'] = $pTitle;
                $paperList[$docId]['docid'] = $docId;
                $paperList[$docId]['vid'] = $vid;
                // RT#129760
                $paperList[$docId]['paperid'] = $p->getPaperid();
                $paperList[$docId]['status'] = $p->getStatus();
                $paperList[$docId]['identifier'] = $p->getIdentifier();
                $paperList[$docId]['version'] = $p->getVersion();
            }


        } else {
            $paperList = $papers;
        }


        if (!empty($positions)) {
            /** @var array $positions [paperId, position] */
            $positions = array_flip($positions);


            $maxPosition = max($positions);

            /**
             * @var  $docId
             * @var  array | object $paper
             */
            foreach ($paperList as $docId => $paper) {

                /** @var Episciences_Paper $currentOPaper */
                $currentOPaper = $papers[$docId];
                $paperId = $currentOPaper->getPaperid();

                if ($isDefaultFetch) {
                    $paper[self::PAPER_POSITION_NEEDS_TO_BE_SAVED] = false;
                }


                if (array_key_exists($currentOPaper->getPaperId(), $positions)) {
                    $sorted_papers[$positions[$paperId]] = $paper;
                } else if ($currentOPaper->getPosition() === null) {
                    $maxPosition++;
                    $paperPosition = $maxPosition;

                    if ($isDefaultFetch) {
                        $paper[self::PAPER_POSITION_NEEDS_TO_BE_SAVED] = true;

                    }

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

                if ($isDefaultFetch) {
                    $paper[self::PAPER_POSITION_NEEDS_TO_BE_SAVED] = true;
                }

                $sorted_papers[] = $paper;
            }
        }

        return $sorted_papers;
    }

    /**
     * @param array $excludedStatus
     * @param bool $includeSecondaryVolume
     * @return array
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Db_Statement_Exception
     */
    public function getPaperListFromVolume(array $excludedStatus = [], bool $includeSecondaryVolume = true): array
    {

        $options['is']['rvid'] = RVID;
        $options['is']['vid'] = [$this->getVid()];
        $status = empty($excludedStatus) ? Episciences_Paper::DO_NOT_SORT_THIS_KIND_OF_PAPERS : array_merge($excludedStatus, Episciences_Paper::DO_NOT_SORT_THIS_KIND_OF_PAPERS);
        $options['isNot'] = ['status' => $status];

        return Episciences_PapersManager::getList($options, false, $includeSecondaryVolume);

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

    public function getVol_year(): ?int
    {
        if (!is_int($this->_vol_year)) {
            return null;
        }
        return $this->_vol_year;
    }

    public function setVol_year($volYear): \Episciences_Volume
    {
        $this->_vol_year = (int)$volYear ?: null;
        return $this;
    }

    public function getVol_type()
    {
        return $this->_vol_type;
    }

    public function setVol_type(?string $volType): \Episciences_Volume
    {
        if (!is_null($volType)) {
            $this->_vol_type = trim(strip_tags($volType));
        } else {
            $this->_vol_type = null;
        }

        return $this;
    }

    public function getVol_num()
    {
        return $this->_vol_num;
    }

    public function setVol_num($volNum): \Episciences_Volume
    {
        $this->_vol_num = $volNum ? (int)trim(strip_tags($volNum)) : null;
        return $this;
    }

    /**
     * update a volume
     * @return int|null
     */
    private function updateVolume(): ?int
    {
        $where = 'VID = ' . $this->getVid();

        $data['BIB_REFERENCE'] = $this->getBib_reference();
        $data['titles'] = $this->preProcess($this->getTitles());
        $data['descriptions'] = $this->preProcess($this->getDescriptions());
        $data['vol_type'] = $this->getVol_type();
        $data['vol_year'] = $this->getVol_year();
        $data['vol_num'] = $this->getVol_num();
        Episciences_VolumesAndSectionsManager::dataProcess($data);

        try {
            return $this->_db->update(T_VOLUMES, $data, $where);

        } catch (Zend_Db_Adapter_Exception $exception) {
            trigger_error($exception->getMessage());
            return 0;
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

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return (int)$this->getSetting(self::SETTING_STATUS);
    }

    public function isProceeding(): int
    {
        return (int)$this->getSetting(self::VOLUME_IS_PROCEEDING);
    }

    /**
     * @return array
     */
    public function getProceedingInfo(): array
    {
        $this->loadSettings();
        return [
            self::VOLUME_IS_PROCEEDING => $this->getSetting(self::VOLUME_IS_PROCEEDING),
            self::VOLUME_CONFERENCE_NAME => $this->getSetting(self::VOLUME_CONFERENCE_NAME),
            self::VOLUME_CONFERENCE_THEME => $this->getSetting(self::VOLUME_CONFERENCE_THEME),
            self::VOLUME_CONFERENCE_ACRONYM => $this->getSetting(self::VOLUME_CONFERENCE_ACRONYM),
            self::VOLUME_CONFERENCE_NUMBER => $this->getSetting(self::VOLUME_CONFERENCE_NUMBER),
            self::VOLUME_CONFERENCE_LOCATION => $this->getSetting(self::VOLUME_CONFERENCE_LOCATION),
            self::VOLUME_CONFERENCE_START_DATE => $this->getSetting(self::VOLUME_CONFERENCE_START_DATE),
            self::VOLUME_CONFERENCE_END_DATE => $this->getSetting(self::VOLUME_CONFERENCE_END_DATE),
            self::VOLUME_CONFERENCE_DOI => $this->getSetting(self::VOLUME_CONFERENCE_DOI),
        ];
    }

    /**
     * @return array|null
     */
    public function getTitles(): ?array
    {
        return $this->titles;
    }

    public function getNbOfPapersInVolume(): int
    {
        return $this->nbOfPapersInVolume;
    }

    public function setNbOfPapersInVolume(int $nbOfPapersInVolume): void
    {
        $this->nbOfPapersInVolume = $nbOfPapersInVolume;
    }

    /**
     * @param array|null $titles
     * @return Episciences_Volume
     */
    public function setTitles(?array $titles): self
    {
        $this->titles = $titles;
        return $this;
    }

    /**
     * @return array|null
     */
    public function getDescriptions(): ?array
    {
        return $this->descriptions;
    }

    /**
     * @param array|null $descriptions
     * @return Episciences_Volume
     */
    public function setDescriptions(?array $descriptions): self
    {
        $this->descriptions = $descriptions;
        return $this;
    }

    /**
     * @param array|null $assoc
     * @param string $type
     * @return array|null
     */
    public function preProcess(?array $assoc, string $type = self::HTML_TO_MARKDOWN): ?array
    {

        // todo Edition d'un volume : voir pourquoi les balises <p></p> sont ajoutées automatiquement.

//        if (!empty($assoc)) {
//
//            foreach ($assoc as $lang => $val) {
//
//                if ($type === self::MARKDOWN_TO_HTML) {
//                    $assoc[$lang] = Episciences_Tools::convertMarkdownToHtml($val);
//                } elseif ($type === self::HTML_TO_MARKDOWN) {
//                    $assoc[$lang] = Episciences_Tools::convertHtmlToMarkdown($val);
//                }
//
//            }
//        }

        return $assoc;

    }

    public function getEarliestPublicationDateFromVolume()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_PAPERS, new Zend_Db_Expr('MIN(PUBLICATION_DATE) AS FIRST_PUB_DATE'))
            ->where('VID = ?', $this->getVid())
            ->where('STATUS = ?', Episciences_Paper::STATUS_PUBLISHED);
        return $db->fetchOne($select);
    }

    /**
     *
     * @param int $paperId
     * @param int|null $vid [ default ($vid = null]) : returns position in primary volume
     * @return int|null
     */

    public function getPositionByPaperId(int $paperId, int $vid = null): ?int
    {

        try {
            $sortedPapers = $this->getSortedPapersFromVolume('object');
        } catch (Zend_Db_Select_Exception|Zend_Exception $e) {
            $sortedPapers = [];
        }


        /**
         * @var int $position
         * @var  Episciences_Paper $paper
         */

        foreach ( $sortedPapers as $position => $paper) {

            if ($paper->getPaperid() === $paperId && $paper->getVid() === $vid) {
                return $position;
            }
        }

        return $position + 1;
    }


}
