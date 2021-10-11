<?php

class Episciences_PapersManager
{

    const NONE_FILTER = '0';
    const WITH_FILTER = '-1';

    /**
     * @return array
     */
    public static function getFiltersParams()
    {
        $params = Zend_Controller_Front::getInstance()->getRequest()->getParams();
        Episciences_Tools::filter_multiarray($params);
        return $params;
    }

    /**
     * @param array $settings
     * @param bool $cached
     * @param bool $isFilterInfos
     * @param bool $isLimit
     * @return array
     * @throws Zend_Db_Select_Exception
     */
    public static function getList(array $settings = [], bool $cached = false, bool $isFilterInfos = false, bool $isLimit = true)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $select = self::getListQuery($settings, $isFilterInfos, $isLimit);

        $all = $db->fetchAll($select, $cached); // the first column not contains unique values, that's why we use fetchAll
        $list = self::fromSequentialArrayToAssoc($all);

        $result = [];

        foreach ($list as $id => $item) {

            // fetch papers from cache rather than populating them
            if ($cached) {
                $cachename = 'paper-' . $id . '.txt';
                if (Episciences_Cache::exist($cachename)) {
                    $result[$id] = unserialize(Episciences_Cache::get($cachename), ['allowed_classes' => false]);
                } else {
                    $item['withxsl'] = false;
                    $paper = new Episciences_Paper($item);
                    $result[$id] = $paper;
                    Episciences_Cache::save($cachename, serialize($paper));
                }
            } else {
                $item['withxsl'] = false;
                $result[$id] = new Episciences_Paper($item);
            }
        }

        return $result;
    }

    /**
     * @param array $settings
     * @param bool $isFilterInfos
     * @param bool $isLimit
     * @return Zend_Db_Select
     * @throws Zend_Db_Select_Exception
     */
    public static function getListQuery(array $settings = [], bool $isFilterInfos = false, bool $isLimit = true): \Zend_Db_Select
    {
        $select = self::getFilterQuery($settings, false, $isFilterInfos);

        // limit
        if ($isLimit && array_key_exists('limit', $settings)) {
            if (array_key_exists('offset', $settings)) {
                $select->limit($settings['limit'], $settings['offset']);
            } else {
                $select->limit($settings['limit']);
            }
        }

        // order
        if (array_key_exists('order', $settings)) {
            if (is_array($settings['order'])) {
                foreach ($settings['order'] as $value) {
                    $select->order(strtoupper($value));
                }
            } else {
                $select->order(strtoupper($settings['order']));
            }
        } else {
            $select->order('WHEN DESC');
        }

        return $select;
    }

    /**
     * @param array $settings
     * @param bool $isCount
     * @param bool $isFilterInfos
     * @return Zend_Db_Select
     * @throws Zend_Db_Select_Exception
     */
    private static function getFilterQuery(array $settings = [], bool $isCount = false, bool $isFilterInfos = false): \Zend_Db_Select
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $papersQuery = $db->select()->from(['papers' => T_PAPERS])->joinLeft(['conflicts' => T_PAPER_CONFLICTS], 'papers.PAPERID = conflicts.paper_id' );

        $countQuery = $db->select()->from($papersQuery, [new Zend_Db_Expr("COUNT('DOCID')")]);

        $select = (!$isCount) ? $papersQuery : $countQuery;

        //Filters
        $select = self::applyFilters($select, $settings, $isFilterInfos);

        // DataTable search
        if ($isFilterInfos && array_key_exists('list_search', $settings)) {
            $word = $settings['list_search'];
            $volumes = (array_key_exists('volumes', $settings)) ? $settings['volumes'] : [];
            $sections = (array_key_exists('sections', $settings)) ? $settings['sections'] : [];

            $select = self::dataTableSearchQuery($select, $word, $volumes, $sections);
        }
        return $select;
    }

    /**
     * @param Zend_Db_Select $select
     * @param array $settings
     * @param bool $isFilterInfos
     * @return Zend_Db_Select
     * @throws Zend_Db_Select_Exception
     */
    private static function applyFilters(Zend_Db_Select $select, array $settings, bool $isFilterInfos = false): Zend_Db_Select
    {
        $validFilters = ['rvid', 'repoid', 'uid', 'docid', 'vid', 'sid', 'status'];
        if (array_key_exists('is', $settings)) {
            foreach ($settings['is'] as $setting => $value) {
                if (in_array(strtolower($setting), $validFilters)) {
                    $setting = strtoupper($setting);
                    if ($setting !== 'VID') {
                        if (is_array($value)) {
                            $select->where("$setting IN (?)", $value);
                        } else {
                            $select->where("$setting = ?", $value);
                        }

                    } else {
                        $select = self::volumesFilter($select, $value, $isFilterInfos);
                    }

                }

                if ($setting === 'editors') {
                    $select = self::filterByRole($select, $value, 'editor');
                }

                if ($setting === 'reviewers') {
                    $select = self::filterByRole($select, $value, 'reviewer');
                }

                if ($setting === 'doi') {
                    $select = self::applyDOIFilter($select, $value);
                }
            }
        }

        if (array_key_exists('isNot', $settings)) {
            foreach ($settings['isNot'] as $setting => $value) {
                if (in_array(strtolower($setting), $validFilters)) {
                    $setting = strtoupper($setting);
                    if (is_array($value)) {
                        $select->where("$setting NOT IN (?)", $value);
                    } else {
                        $select->where("$setting != ?", $value);
                    }
                }
            }
        }
        return $select;
    }

    /**
     * @param Zend_Db_Select $select
     * @param array $value
     * @param bool $includeSecondaryVolume
     * @return Zend_Db_Select
     */
    private static function volumesFilter(Zend_Db_Select $select, array $value, bool $includeSecondaryVolume = false)
    {
        // Filtrage par volume secondaire : inclure l'article s'il appartient à un volume primaire(git#72)
        $select1 = self::getVolumesQuery();
        if (is_array($value)) {
            $select1->where(" st.VID IN (?)", $value);

            if ($includeSecondaryVolume) {
                $select1->orWhere("vpt.VID IN (?)", $value);
            }

        } else {
            $select1->where("st.VID = ?", $value);

            if ($includeSecondaryVolume) {
                $select1->orWhere("vpt.VID = ?", $value);
            }

        }

        $select->where("DOCID IN (?)", $select1);
        return $select;
    }

    /**
     * @param array $fields
     * @return Zend_Db_Select
     */
    public static function getVolumesQuery(array $fields = ['DOCID'])
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db
            ->select()
            ->from(['st' => T_PAPERS], $fields)
            ->joinLeft(['vpt' => T_VOLUME_PAPER], 'st.DOCID = vpt.DOCID', [])
            ->where('st.RVID = ?', RVID);
    }

    /**
     * Retourne les article assigné à un rôle
     * @param Zend_Db_Select $select
     * @param array $values
     * @param string $roleId : default : editor
     * @return Zend_Db_Select
     * @throws Zend_Db_Select_Exception
     */
    private static function filterByRole(Zend_Db_Select $select, array $values, string $roleId = 'editor'): Zend_Db_Select
    {

        // fetch last paper assignment for each selected roleId
        $subQuery = self::fetchLastPaperAssignmentForSelectedRoleQuery($values, $roleId);

        $select
            ->where("DOCID IN (?)", $subQuery)
            ->where("DOCID NOT IN (?)", Episciences_UserManager::getSubmittedPapersQuery(Episciences_Auth::getUid())); //git #148 : L'auteur peut deviner les rédcateurs en charge de son article
        return $select;

    }

    /**
     * @param array $uid
     * @param string | array $roleId
     * @return Zend_Db_Select
     * @throws Zend_Db_Select_Exception
     */
    private static function fetchLastPaperAssignmentForSelectedRoleQuery(array $uid = [], $roleId = 'editor'): Zend_Db_Select
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $isNoneRolesFilterActive = false;

        $columns = (!empty($uid)) ? ['DOCID' => 'ITEMID'] : ['DOCID' => 'ITEMID', 'UID_ROLE' => 'UID'];

        $select = $db->select()
            ->from(['a' => T_ASSIGNMENTS], $columns)
            ->join(['b' => self::getAssignedPapersForSelectedRoleQuery($uid, $roleId)], 'a.ITEMID = b.ITEMID AND a.`WHEN` = b.`WHEN`', [])
            ->where('ITEM = ?', 'paper');

        if (is_array($roleId)) {
            $select->where('ROLEID IN (?)', $roleId);
        } else {
            $select->where('ROLEID = ?', $roleId);
        }

        $select->where('RVID = ?', RVID);

        if (!empty($uid)) { //Le filtre "Aucun" est-il selectionné
            foreach ($uid as $key => $val) {
                if ($val === self::NONE_FILTER) {
                    $isNoneRolesFilterActive = true;
                    unset($uid[$key]);
                    break 1;
                }
            }
        }

        if (!empty($uid)) {
            $select->where('UID IN (?)', $uid);
        }

        $select->where('STATUS = ?', 'active');

        if ($isNoneRolesFilterActive) {
            $noneSelect = null;
            if ($roleId === 'editor') {
                $noneSelect = self::getPapersWithoutAssignedEditorsQuery();
            } elseif ($roleId === 'reviewer') {
                $noneSelect = self::getPapersWithoutAssignedReviewersQuery();
            }
            $select = $db
                ->select()
                ->union([$select, $noneSelect]);
        }

        return $select;
    }

    /**
     * get assigned papers docids for each selected editor
     * @param array $uid
     * @param string | string[] $roleId
     * @return Zend_Db_Select
     */
    private static function getAssignedPapersForSelectedRoleQuery(array $uid, $roleId = 'editor'): Zend_Db_Select
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_ASSIGNMENTS, ['ITEMID', 'MAX(`WHEN`) AS WHEN'])
            ->where('ITEM = ?', 'paper');

        if (is_array($roleId)) {
            $select->where("ROLEID IN (?)", $roleId);
        } else {
            $select->where("ROLEID = ?", $roleId);
        }

        $select->where('RVID = ?', RVID);

        if (!empty($uid)) {
            $select->where('UID IN (?)', $uid);
        }

        $select->group('ITEMID');
        return $select;
    }

    /**
     * Retourne les papiers qui n'ont pas encore été assignés à un rédacteur
     * @param bool $excludeChiefEditors = true : un article asigné seulement à un "rédcateur en chef" est considéré comme un article sans rédacteur.
     * @return Zend_Db_Select
     * @throws Zend_Db_Select_Exception
     */
    private static function getPapersWithoutAssignedEditorsQuery(bool $excludeChiefEditors = false): Zend_Db_Select
    {
        $query = self::allPapersAssignedToRole();

        if ($excludeChiefEditors) {
            $subQuery = Episciences_UsersManager::getUsersWithRolesQuery(Episciences_Acl::ROLE_CHIEF_EDITOR);
            //Exclure les rédacteurs en chefs
            $query->where('UID NOT IN (?)', $subQuery);
        }

        return self::allPapers()->where('DOCID NOT IN (?)', $query);
    }

    /**
     * retourne tous les papiers assignés à un rôle
     * @param string $roleId
     * @return Zend_Db_Select
     */
    private static function allPapersAssignedToRole(string $roleId = 'editor'): Zend_Db_Select
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $subQuery = $db->select()
            ->from(T_ASSIGNMENTS, ['ITEMID', 'MAX(`WHEN`) AS WHEN'])
            ->where('ITEM = ?', 'paper')
            ->where('ROLEID = ?', $roleId)
            ->where('RVID = ?', RVID)
            ->group(['ITEMID', 'UID']);

        return $db->select()
            ->distinct()
            ->from(['a' => T_ASSIGNMENTS], ['DOCID' => 'ITEMID'])
            ->join(['b' => $subQuery], 'a.ITEMID = b.ITEMID AND a.`WHEN` = b.`WHEN`', [])
            ->where('ITEM = ?', 'paper')
            ->where('ROLEID = ?', $roleId)
            ->where('RVID = ?', RVID)
            ->where('STATUS = ?', Episciences_User_Assignment::STATUS_ACTIVE);
    }

    /**
     * @param array $excludeStatus
     * @return Zend_Db_Select
     */
    private static function allPapers(array $excludeStatus = [Episciences_Paper::STATUS_OBSOLETE, Episciences_Paper::STATUS_DELETED]): Zend_Db_Select
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db
            ->select()
            ->from(T_PAPERS, 'DOCID')
            ->where('STATUS NOT IN (?)', $excludeStatus)
            ->where('RVID = ?', RVID);
    }

    /**
     * Retourne les articles qui n'ont pas encore de relecteurs
     * @return Zend_Db_Select
     */
    private static function getPapersWithoutAssignedReviewersQuery(): Zend_Db_Select
    {
        $query = self::allPapersAssignedToRole('reviewer');
        return self::allPapers()->where('DOCID NOT IN (?)', $query);
    }

    /**
     * @param Zend_Db_Select $select
     * @param array $values
     * @return Zend_Db_Select
     */
    private static function applyDOIFilter(Zend_Db_Select $select, array $values)
    {

        foreach ($values as $value) {
            if ($value === self::NONE_FILTER) {
                $select = $select->where('DOCID IN (?)', self::getPapersWithoutDOIQuery());

            } elseif ($value === self::WITH_FILTER) {
                $select = $select->where('DOCID IN (?)', self::getPapersWithDOIQuery());
            }

        }

        return $select;
    }

    /**
     * @return Zend_Db_Select
     */
    private static function getPapersWithoutDOIQuery(): Zend_Db_Select
    {
        return
            self::allPapers()
                ->where('DOI IS NULL');
    }

    /**
     * @return Zend_Db_Select
     */
    private static function getPapersWithDOIQuery(): Zend_Db_Select
    {
        return
            self::allPapers()
                ->where('DOI IS NOT NULL');
    }

    /**
     * @param Zend_Db_Select $select
     * @param String $word
     * @param array $volumes
     * @param array $sections
     * @return Zend_Db_Select
     * @throws Zend_Db_Select_Exception
     */
    private static function dataTableSearchQuery(Zend_Db_Select $select, string $word = '', array $volumes = [], array $sections = [])
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        if ((int)$word != 0) {

            // Colonne Id permanent et Id document
            $where = 'CONVERT(PAPERID, CHAR) LIKE ? OR CONVERT(DOCID, CHAR) LIKE ? ';
            // Colonne Date de proposition
            $where .= "OR CONVERT(SUBMISSION_DATE, CHAR) LIKE ? ";
            // Colonne Date de publication
            $where .= "OR  CONVERT(PUBLICATION_DATE, CHAR) LIKE ? ";

        } else {

            // Colonne titre
            $where = "RECORD LIKE '%<dc:title' ? '</dc:title>%' ";

            // Colonne statut
            $paperStatus = [];

            foreach (Episciences_Paper::STATUS_CODES as $code) {
                $paperStatus[$code] = strtolower(
                    Ccsd_Tools::translate(Episciences_PapersManager::getStatusLabel($code)));
            }

            $statusCondition = self::makeCondition($paperStatus, $word);

            if ($statusCondition !== '') {
                $where .= "OR STATUS IN ($statusCondition) ";
            }

            // Colonne Volume
            $volumeCondition = self::makeCondition($volumes, $word, 'getNameKey');
            if ($volumeCondition !== '') {
                //Volume primaire
                $where .= "OR VID IN ($volumeCondition) ";
                //Inclure les documents qui ont un volume secondaire
                $papersWithSecondaryVolume = self::getVolumesQuery()->where("vpt.VID IN ($volumeCondition) ");
                $where .= "OR DOCID IN ($papersWithSecondaryVolume) ";
            }

            // Colonne Section
            $sectionCondition = self::makeCondition($sections, $word, 'getNameKey');
            if ($sectionCondition !== '') {
                $where .= "OR SID IN ($sectionCondition) ";
            }

            //Colonnes Contributeurs, Relecteurs et Rédacteurs
            $query1 = $db
                ->select()
                ->from(['u' => T_USERS], ['USER_ID' => 'UID', 'SCREEN_NAME'])
                ->joinLeft(['p' => T_PAPERS], 'u.UID = p.UID')
                ->joinLeft(
                    ['a' => self::fetchLastPaperAssignmentForSelectedRoleQuery([], ['editor', 'reviewer'])],
                    'u.UID = a.UID_ROLE',
                    ['ITEMID' => 'DOCID']
                );

            $query2 = $db
                ->select()
                ->from(['q2' => $query1], ['DOCID'])
                ->where('SCREEN_NAME LIKE ? ', "%$word%");

            $query3 = $db
                ->select()
                ->from(['q2' => $query1], ['ITEMID'])
                ->where('SCREEN_NAME LIKE ? ', "%$word%")
                ->where('ITEMID NOT IN (?)', Episciences_UserManager::getSubmittedPapersQuery(Episciences_Auth::getUid()));

            $query4 = $db->select()->union([$query2, $query3]);

            $where .= "OR DOCID IN ($query4)";

        }

        $select->where($where, '%' . $word . '%');

        return $select;
    }

    /**
     * @param $status
     * @return mixed
     */
    public static function getStatusLabel($status)
    {
        return array_key_exists($status, Episciences_Paper::$_statusLabel) ? Episciences_Paper::$_statusLabel[$status] : $status;
    }

    /**
     * prépare la condition à ajouter à la requête SQL
     * @param array $array
     * @param string $word
     * @param string $method_name
     * @return string
     */
    private static function makeCondition(array $array, string $word = '', string $method_name = '')
    {
        //Echapper les métacaractères dans les Expressions Régulières
        $metacharacters = '^ \. [ ] $ ( ) * + ? | { } \\';
        $word = addcslashes($word, $metacharacters);
        if (!empty($array)) {

            $valuesName = [];
            $arrayValues = [];

            foreach ($array as $key => $value) {
                if (!is_object($value)) {
                    $valuesName[$key] = $array[$key];

                } else if (method_exists($value, $method_name)) {
                    $valuesName[$key] = Ccsd_Tools::translate($value->$method_name());
                }
                if (preg_match("/$word/i", $valuesName[$key])) {
                    $arrayValues[] = $key;
                }
            }
        }
        return (!empty($arrayValues)) ? implode(',', $arrayValues) : '';

    }

    /**
     * @param array $settings
     * @param bool $isFilterInfos = true : Filtrage additionnel depuis le champ de recherche "rechercher". voir /administratepaper/list
     * @return string
     * @throws Zend_Db_Select_Exception
     */
    public static function getCount(array $settings = [], bool $isFilterInfos = false)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        /** @var Zend_Controller_Front $controller */
        $controller = Zend_Controller_Front::getInstance();

        /** @var Zend_Controller_Request_Http $request */
        $request = $controller->getRequest();
        $params = ($request->isPost()) ? $request->getPost('filters') : $request->getParams();
        unset ($params['controller'], $params['action'], $params['module'], $params['submit']);

        $select = self::getFilterQuery($settings, true, $isFilterInfos);

        return $db->fetchOne($select);
    }

    /**
     * Compte le nombre d'articles d'une liste qui correspondent au(x) statut(s) en paramètre
     * @param $list
     * @param $status
     * @return bool|int
     */
    public static function countByStatus($list, $status)
    {
        if (!is_array($list) || empty($list)) {
            return false;
        }

        $count = 0;

        foreach ($list as $oPaper) {
            if (is_array($status)) {
                if (in_array($oPaper->getStatus(), $status)) {
                    $count++;
                }
            } else {
                if ($oPaper->getStatus() == $status) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Renvoie un tableau de papiers triés par la clé passée en paramètres
     * @param $list
     * @param $key
     * @return bool
     */
    public static function sortBy($list, $key)
    {
        if (empty($list)) {
            return false;
        }

        foreach ($list as $id => $item) {
            $method = 'get' . ucfirst(strtolower($key));
            $itemKey = 0;
            if (method_exists($item, $method)) {
                $itemKey = $item->$method();
            }
            $result[$itemKey][$id] = $item;
        }

        Episciences_Tools::multi_ksort($result);

        if ($key == 'STATUS') {
            uksort($result, 'self::statusCmp');
        }


        return $result;
    }

    /**
     * Regroupe les papiers par statut (pour affichage pour le déposant)
     * @param $list
     * @return bool
     */
    public static function sortByStatus($list)
    {
        if (empty($list)) {
            return false;
        }

        /* @var  Episciences_Paper $item */
        foreach ($list as $id => $item) {
            $itemStatus = $item->getStatus();
            if ($itemStatus == Episciences_Paper::STATUS_SUBMITTED ||
                $itemStatus == Episciences_Paper::STATUS_OK_FOR_REVIEWING ||
                $itemStatus == Episciences_Paper::STATUS_BEING_REVIEWED ||
                $itemStatus == Episciences_Paper::STATUS_REVIEWED
            ) {
                $status = Episciences_Paper::STATUS_SUBMITTED;
            } else {
                $status = $itemStatus;
            }
            $result[$status][$id] = $item;
        }

        Episciences_Tools::multi_ksort($result);
        return $result;
    }

    /**
     * Vérifie l'existence d'un papier
     * @param $docId
     * @return mixed
     * @throws Zend_Db_Statement_Exception
     */
    public static function paperExists($docId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->from(T_PAPERS, '1')->where('DOCID = ?', $docId);
        return $select->query()->fetch();

    }

    /**
     * Retourne l'identifiant de l'article si ce dernier est dèjà publié
     * @param $paperId
     * @return int
     * @throws Zend_Db_Statement_Exception
     */
    public static function getPublishedPaperId($paperId): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db
            ->select()
            ->from(T_PAPERS)
            ->where('PAPERID = ?', $paperId)
            ->where('STATUS = ?', Episciences_Paper::STATUS_PUBLISHED);
        $data = $select->query()->fetch();
        if (!$data) { // Pas de version publiée
            $result = 0;
        } else {
            // l'identifiant de l'article publiée
            $result = (int)$data['DOCID'];
        }
        return $result;
    }

    /**
     * @param $docId
     * @param null $uid
     * @param null $typeId
     * @return array
     */
    public static function getLogs($docId, $uid = null, $typeId = null)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $select = $db->select()
            ->from(T_LOGS)
            ->where('DOCID = ?', $docId)
            ->order('DATE DESC');

        if ($uid) {
            $select->where('UID = ?', $uid);
        }

        if ($typeId) {
            if (is_array($typeId) && !empty($typeId)) {
                $select->where('ACTION IN (?)', $typeId);
            } else if (is_numeric($typeId)) {
                $select->where('ACTION = ?', $typeId);
            }
        }

        return $select->query()->fetchAll();
    }

    /**
     * fetch rating invitations for a given docid
     * @param $docId
     * @param null $status
     * @param bool $sorted
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    public static function getInvitations($docId, $status = null, $sorted = true)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        // fetch assignments (invitations don't have a docid, and are linked to an assignment)
        $select = self::getInvitationQuery($docId);

        $data = $db->fetchAll($select);

        //reviewers array
        $reviewers = ['tmp' => []];

        //prepare array
        $source = [];
        foreach ($data as $row) {
            $source[$row['INVITATION_AID']][$row['ASSIGNMENT_ID']] = $row;
        }

        //sort array
        $invitations = [];
        foreach ($source as $aid => $row) {
            $tmp = [];
            foreach ($row as $id => $invitation) {
                //recuperation du dernier état connu de l'invitation
                if (empty($tmp)) {
                    $tmp = $invitation;
                }
                //recuperation des infos de l'invitation d'orgine, si il y a eu une réponse à l'invitation
                if (!empty($tmp) && $aid == $id) {
                    $tmp['ASSIGNMENT_DATE'] = $invitation['ASSIGNMENT_DATE'];
                }
            }
            //fetch reviewer detail
            if ($invitation['TMP_USER']) {
                if (!array_key_exists($invitation['UID'], $reviewers['tmp'])) {
                    $reviewer = new Episciences_User_Tmp();

                    if (!empty($reviewer->find($invitation['UID']))) {
                        $reviewer->generateScreen_name();
                        $reviewers[$invitation['UID']] = $reviewer;
                    }

                }
            } else if (!array_key_exists($invitation['UID'], $reviewers)) {
                $reviewer = new Episciences_Reviewer;
                $reviewer->findWithCAS($invitation['UID']);
                $reviewers[$invitation['UID']] = $reviewer;
            }
            $reviewer = $reviewers[$invitation['UID']];
            $tmp['reviewer'] = [
                'alias' => (is_a($reviewer, 'Episciences_Reviewer')) ? $reviewer->getAlias($docId) : null,
                'fullname' => $reviewer->getFullName(),
                'screenname' => $reviewer->getScreenName(),
                'username' => $reviewer->getUsername(),
                'email' => $reviewer->getEmail()
            ];

            $key = ($invitation['TMP_USER'] != 1) ? $invitation['UID'] : 'tmp_' . $invitation['UID'];
            $invitations[$key][] = $tmp;
        }

        if ($sorted) {
            $result = [
                Episciences_User_Assignment::STATUS_ACTIVE => [],
                Episciences_User_Assignment::STATUS_PENDING => [],
                Episciences_User_Assignment::STATUS_INACTIVE => [],
                Episciences_User_Assignment::STATUS_EXPIRED => [],
                Episciences_User_Assignment::STATUS_CANCELLED => []];
            foreach ($invitations as $invitation_list) {
                $invitation = array_shift($invitation_list);
                //si l'invitation a expiré, on la place dans une catégorie à part
                if ($invitation['ASSIGNMENT_STATUS'] == Episciences_User_Assignment::STATUS_PENDING && self::compareToCurrentTime($invitation['EXPIRATION_DATE'])) {
                    if ((!is_array($status) && $status != Episciences_User_Assignment::STATUS_EXPIRED) ||
                        (is_array($status) && !in_array(Episciences_User_Assignment::STATUS_EXPIRED, $status))
                    ) {
                        //si on a passé des statuts en paramètre, et que 'expired' n'en fait pas partie, on le saute
                        continue;
                    }
                    $result['expired'][] = $invitation;
                } else {
                    if ((!is_array($status) && $status != $invitation['ASSIGNMENT_STATUS']) ||
                        (is_array($status) && !in_array($invitation['ASSIGNMENT_STATUS'], $status))
                    ) {
                        //si on a passé des statuts en paramètre, et que ce statut n'en fait pas partie, on le saute
                        continue;
                    }
                    $result[$invitation['ASSIGNMENT_STATUS']][] = $invitation;
                }
            }
        } else {
            $result = $invitations;
        }

        return $result;
    }

    /**
     * @param $docId
     * @return Zend_Db_Select
     */
    public static function getInvitationQuery($docId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        // fetch assignments (invitations don't have a docid, and are linked to an assignment)
        return $db->select()
            ->from(['a' => T_ASSIGNMENTS],
                ['ASSIGNMENT_ID' => 'ID', 'INVITATION_ID', 'RVID', 'DOCID' => 'ITEMID', 'TMP_USER', 'UID', 'ASSIGNMENT_STATUS' => 'STATUS', 'ASSIGNMENT_DATE' => 'WHEN', 'ASSIGNMENT_DEADLINE' => 'DEADLINE']
            )
            ->join(['i' => T_USER_INVITATIONS],
                'a.INVITATION_ID = i.ID',
                ['INVITATION_AID' => 'AID', 'INVITATION_STATUS' => 'STATUS', 'SENDER_UID', 'INVITATION_DATE' => 'SENDING_DATE', 'EXPIRATION_DATE']
            )
            ->where('ITEM = ?', Episciences_User_Assignment::ITEM_PAPER)
            ->where('ITEMID = ?', $docId)
            ->where('ROLEID = ?', Episciences_User_Assignment::ROLE_REVIEWER)
            //->where('TMP_USER != 1')
            ->order(['ASSIGNMENT_DATE DESC', 'ASSIGNMENT_STATUS ASC']);
    }

    /**
     * fetch paper reviewers (default: only fetch active reviewers)
     * @param $docId
     * @param null $status
     * @param bool $getCASdata
     * @param bool $vid
     * @return Episciences_Reviewer[]
     * @throws Zend_Db_Statement_Exception
     */
    public static function getReviewers($docId, $status = null, $getCASdata = false, $vid = false): array
    {
        $reviewers = [];
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        //recuperation des relecteurs classiques
        $subquery = $db->select()
            ->from(['z' => T_ASSIGNMENTS], ['z.UID', 'MAX(`WHEN`) AS WHEN'])
            ->where('ITEM = ?', 'paper')
            ->where('ITEMID = ?', $docId)
            ->where('ROLEID = ?', 'reviewer')
            ->where('TMP_USER = ?', 0)
            ->group('z.UID');

        if (is_array($vid)) {
            //$subquery->joinLeft(array('rp'=>T_REVIEWER_POOL), 'a.UID = rp.UID AND rp.VID =  . $vid))
        } elseif (is_numeric($vid)) {
            $subquery->joinLeft(['rp' => T_REVIEWER_POOL], 'z.UID = rp.UID AND rp.VID = ' . $vid, []);
        }

        $select = $db->select()
            ->from(['a' => T_ASSIGNMENTS], ['UID', 'STATUS', 'WHEN'])
            ->joinLeft(['u' => T_USERS], 'a.UID = u.UID', ['LANGUEID', 'SCREEN_NAME'])
            //->joinUsing(T_USERS, 'UID', array('LANGUEID', 'SCREEN_NAME'))
            ->join(['b' => $subquery], 'a.UID = b.UID AND a.`WHEN` = b.`WHEN`')
            ->where('ITEM = ?', 'paper')
            ->where('ITEMID = ?', $docId)
            ->where('TMP_USER = ?', 0)
            ->where('ROLEID = ?', 'reviewer');

        $result = $db->fetchAssoc($select);

        //recuperation des relecteurs qui n'ont pas encore de compte
        if ((!is_array($status) && $status === Episciences_User_Assignment::STATUS_PENDING) || (is_array($status) && in_array(Episciences_User_Assignment::STATUS_PENDING, $status, true))) {
            $subquery2 = $db->select()
                ->from(T_USER_INVITATIONS, ['AID', 'latest' => 'MAX(SENDING_DATE)'])
                ->group('AID');

            $subquery1 = $db->select()
                ->from(['answer' => $subquery2], ['i.AID'])
                ->joinInner(['i' => T_USER_INVITATIONS], 'i.AID = answer.AID AND i.SENDING_DATE = answer.latest', [])
                ->where('STATUS = ?', 'pending');

            $select = $db->select()
                ->from(['a' => T_ASSIGNMENTS], ['a.UID', 'STATUS', 'WHEN'])
                ->where('ID IN ?', $subquery1)
                ->where('ITEMID = ?', $docId)
                ->where('TMP_USER = ?', 1);

            $tmp_reviewers = $db->fetchAssoc($select);
        }

        if ($result) {

            // Filtrage des relecteurs en fonction de leur statut **************************************************
            if (!$status) {
                // Par défaut, on ne renvoie que les relecteurs actifs
                $status = [Episciences_User_Assignment::STATUS_ACTIVE];
            }

            if (!empty($status)) {
                $result = array_filter($result, static function ($user) use ($status) {
                    return in_array($user['STATUS'], $status, true);
                });
            }

        }

        // Récupération d'un tableau d'objets "Reviewer"
        if ($result || (isset($tmp_reviewers) && !empty($tmp_reviewers))) {

            foreach ($result as $uid => $user) {
                $reviewer = new Episciences_Reviewer();
                if ($getCASdata) {
                    $reviewer->findWithCAS($uid);
                } else {
                    $reviewer->find($uid);
                }

                $reviewer->setWhen($user['WHEN']);
                $reviewer->setStatus($user['STATUS']);
                $reviewers[$uid] = $reviewer;
            }

            if (isset($tmp_reviewers) && !empty($tmp_reviewers)) {
                foreach ($tmp_reviewers as $tmp_reviewer) {
                    $reviewer = new Episciences_User_Tmp;
                    if (!empty($reviewer->find($tmp_reviewer['UID']))) {
                        $reviewer->generateScreen_name();
                        $reviewers['tmp_' . $tmp_reviewer['UID']] = $reviewer;
                    }
                }
            }
        }

        return $reviewers;
    }

    /**
     * @return Ccsd_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getTmpReviewerForm()
    {
        $form = new Ccsd_Form();
        $form->setAttrib('id', 'tmp-user-form');
        $form->setAttrib('style', 'width: 500px');
        $form->getDecorator('FormRequired')->setOption('id', 'required_tmp_user');

        $form->addElement('text', 'email', [
            'label' => 'Courriel',
            'class' => 'form-control',
            'placeholder' => Zend_Registry::get('Zend_Translate')->translate("prenom.nom@example.org"),
            'required' => true
        ]);

        $form->addElement('text', 'lastname', [
            'label' => 'Nom',
            'class' => 'form-control',
            'required' => true,
        ]);

        $form->addElement('text', 'firstname', [
            'label' => 'Prénom',
            'class' => 'form-control',
        ]);

        $form->addElement('select', 'user_lang', [
            'label' => 'Langue',
            'class' => 'form-control',
            'style' => 'width:auto;',
            'multiOptions' => Episciences_Tools::getLanguages(),
        ]);

        $form->addElement('button', 'next', [
            'label' => 'Inviter ce relecteur...' . '',
            'class' => 'btn btn-default btn-sm',
        ]);
        /**
         * decorators.0.decorator = "InputIcon"
         * elements.USERNAME.options.decorators.0.options.icon = "glyphicon-user"
         * elements.USERNAME.options.decorators.0.options.class = "form-control"
         */

        return $form;

    }

    /**
     * Renvoie le formulaire d'invitation d'un relecteur
     * @param $docId
     * @param $page
     * @param $referer
     * @param null $params
     * @return Ccsd_Form
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Form_Exception
     */
    public static function getReviewerInvitationForm($docId, $page, $referer, $params = null)
    {
        $action = '/administratepaper/savereviewerinvitation?docid=' . $docId;
        $action .= ($page) ? '&page=' . $page : '';
        $action .= (array_key_exists('vid', $params)) ? '&vid=' . $params['vid'] : '';
        $action .= (array_key_exists('special_issue', $params)) ? '&special_issue=' . $params['special_issue'] : '';

        $form = new Ccsd_Form();
        $form->setAction($action);
        $form->setAttrib('class', 'form-horizontal');
        $form->setAttrib('id', 'invitation-form');


        $form->setDecorators([[
            'ViewScript', [
                'viewScript' => '/administratemail/form.phtml'
            ]],
            'FormActions',
            'Form',
            'FormCss',
            'FormJavascript',
            'FormRequired'
        ]);

        if (!empty($referer)) {
            $form->addElement('hidden', 'referer', [
                'value' => $referer
            ]);
        }

        $form->addElement('hidden', 'reviewer');

        $form->addElement('text', 'sender', [
            'label' => 'Expéditeur',
            'class' => 'form-control',
            'disabled' => 'disabled',
            'value' => Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>'
        ]);


        $form->addElement('text', 'recipient', [
            'label' => 'Destinataire',
            'class' => 'form-control',
            'disabled' => 'disabled'
        ]);

        $form->addElement('text', 'cc', [
            'label' => 'CC',
            'class' => 'form-control',
        ]);

        $form->addElement('text', 'bcc', [
            'label' => 'BCC',
            'value' => Episciences_Review::forYourInformation(),
            'class' => 'form-control'
        ]);

        $form->addElement('date', 'deadline', [
            'label' => 'Date limite de rendu de la relecture',
            'class' => 'form-control',
            'pattern' => '[A-Za-z]{3}'
        ]);
        if (is_array($params) && array_key_exists('rating_deadline_min', $params)) {
            $form->getElement('deadline')->setAttrib('attr-mindate', $params['rating_deadline_min']);
        }

        if (is_array($params) && array_key_exists('rating_deadline_max', $params)) {
            $form->getElement('deadline')->setAttrib('attr-maxdate', $params['rating_deadline_max']);
        }
        // Sujet du message
        $form->addElement('text', 'subject', [
            'label' => 'Sujet',
            'class' => 'form-control'
        ]);

        // Corps du message
        $form->addElement('textarea', 'body', [
            'label' => 'Message',
            'class' => 'form-control',
            'tiny' => true,
            'rows' => 15
        ]);


        return $form;
    }

    /**
     * Renvoie le formulaire d'assignation d'un rédacteur à un papier
     * @param $docId
     * @param $editors
     * @return bool|Ccsd_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getEditorsForm($docId, $editors)
    {
        return self::getAssignUsersForm($editors, (int)$docId, 'editors');
    }

    /**
     * Retourne le formulaire d'assignation des editeurs / préparateurs de copie
     * @param array $users
     * @param int $docId
     * @param string $name
     * @return bool|Ccsd_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    private static function getAssignUsersForm(array $users, int $docId, string $name = '')
    {

        if (empty($users)) {
            return false;
        }
        $currentUsers = [];

        $options = []; // editors options
        $formAttribs['class'] = 'form-horizontal';

        if (!empty($name)) {
            $formAttribs['id'] = 'assign-' . $name;
        }

        $plh = 'Rechercher ';
        if ($name === 'editors') {
            $currentUsers = self::getEditors($docId);
            $plh .= 'des rédacteurs';

        } elseif ($name === 'copyeditors') {
            $currentUsers = self::getCopyEditors($docId);
            $plh .= 'des préparateurs de copie';

        }

        $form = new Ccsd_Form();
        $form->setAttribs($formAttribs);

        // Filtrer les résultats
        $form->addElement('text', 'filter', [
            'class' => 'form-control',
            'style' => 'margin-bottom: 10px',
            'placeholder' => Zend_Registry::get('Zend_Translate')->translate($plh)
        ]);

        // Checkbox
        /** @var Episciences_User $user */
        foreach ($users as $user) {
            $options[$user->getUid()] = $user->getFullname();
        }

        $form->addElement('multiCheckbox', $name, [
            'multiOptions' => $options,
            'separator' => '<br/>',
            'decorators' => ['ViewHelper', ['HtmlTag', ['tag' => 'div', 'class' => $name . '-list', 'style' => 'margin-left: 15px']]]
        ]);

        if (is_array($currentUsers)) {
            $form->populate([$name => array_keys($currentUsers)]);
        }

        $form->addElement('hidden', 'docid', ['id' => $name . '-docid', 'value' => $docId]);
        $form->addElement('hidden', 'type', ['id' => $name . '-type', 'value' => $name]);

        // Bouton de validation
        $form->addElement(new Zend_Form_Element_Button([
            'name' => 'submit_' . $name,
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

        return $form;
    }

    /**
     * fetch an array of paper editors
     * @param $docId
     * @param bool $active
     * @param bool $getCASdata
     * @return array|bool
     * @throws Zend_Db_Statement_Exception
     */
    public static function getEditors($docId, $active = true, $getCASdata = false)
    {
        $editors = [];
        if (!$docId || !is_numeric($docId)) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        /** @var Zend_Db_Select $select */
        $select = self::getAssignmentRoleQuery($docId, Episciences_Acl::ROLE_EDITOR);

        $result = $db->fetchAssoc($select);

        if ($active && !empty($result)) {
            $result = array_filter($result, function ($user) {
                return ($user['STATUS']) == Episciences_User_Assignment::STATUS_ACTIVE;
            });
        }

        if ($result) {

            foreach ($result as $uid => $user) {
                $editor = new Episciences_Editor();
                if ($getCASdata) {
                    $editor->findWithCAS($uid);
                } else {
                    $editor->find($uid);
                }
                $editor->setWhen($user['WHEN']);
                $editor->setStatus($user['STATUS']);
                $editors[$uid] = $editor;
            }
        }

        return $editors;
    }

    /**
     * @param int $docId
     * @param string $role
     * @return Zend_Db_Select
     */
    private static function getAssignmentRoleQuery(int $docId, string $role)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $subQuery = $db->select()
            ->from(T_ASSIGNMENTS, ['UID', 'MAX(`WHEN`) AS WHEN'])
            ->where('ITEM = ?', 'paper')
            ->where('ITEMID = ?', $docId)
            ->where('ROLEID = ?', $role)
            ->group('UID');

        return $db->select()
            ->from(['a' => T_ASSIGNMENTS], ['UID', 'STATUS', 'WHEN'])
            ->joinUsing(T_USERS, 'UID', ['LANGUEID', 'SCREEN_NAME'])
            ->join(['b' => $subQuery], 'a.UID = b.UID AND a.`WHEN` = b.`WHEN`')
            ->where('ITEM = ?', 'paper')
            ->where('ITEMID = ?', $docId)
            ->where('STATUS != ?', Episciences_User_Assignment::STATUS_INACTIVE)
            ->where('ROLEID = ?', $role);
    }

    /**
     * @param int $docId
     * @param bool $active
     * @param bool $getCasData
     * @return Episciences_CopyEditor[]
     * @throws Zend_Db_Statement_Exception
     */
    public static function getCopyEditors(int $docId, bool $active = true, bool $getCasData = false)
    {

        $copyEditors = [];

        if (!$docId || !is_numeric($docId)) {
            return $copyEditors;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        /** @var Zend_Db_Select $select */

        $select = self::getAssignmentRoleQuery($docId, Episciences_Acl::ROLE_COPY_EDITOR);

        $result = $db->fetchAssoc($select);

        if ($active && !empty($result)) {
            $result = array_filter($result, function ($user) {
                return ($user['STATUS']) == Episciences_User_Assignment::STATUS_ACTIVE;
            });
        }

        if ($result) {

            foreach ($result as $uid => $user) {
                $ce = new Episciences_CopyEditor();
                if ($getCasData) {
                    $ce->findWithCAS($uid);
                } else {
                    $ce->find($uid);
                }
                $ce->setWhen($user['WHEN']);
                $ce->setStatus($user['STATUS']);
                $copyEditors[$uid] = $ce;
            }
        }

        return $copyEditors;
    }

    /**
     * @param $docId
     * @param $copyEditors
     * @return bool|Ccsd_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getCopyEditorsForm($docId, $copyEditors)
    {
        return self::getAssignUsersForm($copyEditors, (int)$docId, 'copyeditors');
    }

    /**
     * Renvoie le formulaire de suggestion de changement de statut d'un papier (Rédacteur)
     * @param $docId
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     */
    public static function getSuggestStatusForm($docId)
    {
        $form = new Ccsd_Form();
        $form->setAttrib('class', 'form-horizontal');
        $form->setName('suggeststatus');
        $form->setAction('/administratepaper/suggeststatus?id=' . $docId);

        // Les boutons de choix de suggestion sont dans la vue
        // Seuls les formulaires commentaire + validation sont générés ici


        // Modifier les décorateurs pour masquer les différents champs au chargement
        // Formulaire de suggestion d'acceptation de l'article
        $form->addElement('textarea', 'comment_accept', [
            'label' => "Commentaire",
            'description' => 'Veuillez détailler les raisons de votre recommandation :',
            'rows' => 5]);

        $form->addElement('submit', 'confirm_accept', [
            'label' => "Recommander d'accepter l'article",
            'class' => 'btn btn-primary',
            'decorators' => [['HtmlTag', ['tag' => 'div', 'openOnly' => true, 'class' => 'form-actions text-center']], 'ViewHelper']]);

        $form->addElement('button', 'cancel_accept', [
            'label' => 'Annuler',
            'class' => 'btn btn-default',
            'data-dismiss' => 'modal',
            'onclick' => "cancel()",
            'decorators' => ['ViewHelper', ['HtmlTag', ['tag' => 'div', 'closeOnly' => true]]]]);

        $form->addDisplayGroup(['comment_accept', 'confirm_accept', 'cancel_accept'], 'accept');
        $form->getDisplayGroup('accept')->setDecorators(['FormElements', ['HtmlTag', ['tag' => 'div', 'style' => 'display: none', 'id' => 'accept-form']]]);

        // Formulaire de suggestion du refus de l'article
        $form->addElement('textarea', 'comment_refuse', [
            'label' => "Commentaire",
            'description' => 'Veuillez détailler les raisons de votre recommandation :',
            'rows' => 5]);

        $form->addElement('submit', 'confirm_refuse', [
            'label' => "Recommander de refuser l'article",
            'class' => 'btn btn-primary',
            'decorators' => [['HtmlTag', ['tag' => 'div', 'openOnly' => true, 'class' => 'form-actions text-center']], 'ViewHelper']]);

        $form->addElement('button', 'cancel_refuse', [
            'label' => 'Annuler',
            'class' => 'btn btn-default',
            'data-dismiss' => 'modal',
            'onclick' => "cancel()",
            'decorators' => ['ViewHelper', ['HtmlTag', ['tag' => 'div', 'closeOnly' => true]]]]);

        $form->addDisplayGroup(['comment_refuse', 'confirm_refuse', 'cancel_refuse'], 'refuse');
        $form->getDisplayGroup('refuse')->setDecorators(['FormElements', ['HtmlTag', ['tag' => 'div', 'style' => 'display: none', 'id' => 'refuse-form']]]);

        // Formulaire de suggestion de demande de modifications
        $form->addElement('textarea', 'comment_newversion', [
            'label' => "Commentaire",
            'description' => 'Veuillez détailler les raisons de votre recommandation :',
            'rows' => 5]);

        $form->addElement('submit', 'confirm_newversion', [
            'label' => "Suggérer de demander une nouvelle version",
            'class' => 'btn btn-primary',
            'decorators' => [['HtmlTag', ['tag' => 'div', 'openOnly' => true, 'class' => 'form-actions text-center']], 'ViewHelper']]);

        $form->addElement('button', 'cancel_newversion', [
            'label' => 'Annuler',
            'class' => 'btn btn-default',
            'data-dismiss' => 'modal',
            'onclick' => "cancel()",
            'decorators' => ['ViewHelper', ['HtmlTag', ['tag' => 'div', 'closeOnly' => true]]]]);

        $form->addDisplayGroup(['comment_newversion', 'confirm_newversion', 'cancel_newversion'], 'newversion');
        $form->getDisplayGroup('newversion')->setDecorators(['FormElements', ['HtmlTag', ['tag' => 'div', 'style' => 'display: none', 'id' => 'newversion-form']]]);

        return $form;
    }

    /**
     * Retourne le formulaire de sélection de volume
     * @param $volumes
     * @param null $default
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     */
    public static function getVolumeForm($volumes, $default = null)
    {
        $form = new Ccsd_Form;
        $form->setAttrib('class', 'form-horizontal');
        $form->setDecorators([['ViewScript', ['viewScript' => 'paper/volume_form.phtml']]]);

        // Statut du volume
        $options = ['Hors volume'];
        foreach ($volumes as $volume) {
            $options[$volume->getVid()] = $volume->getNameKey();
        }

        $form->addElement('select', 'vid', [
            'label' => 'Volume',
            'multioptions' => $options,
            'value' => 0
        ]);

        // Boutons : Valider et Annuler
        $form->addElement('submit', 'submit', [
            'label' => 'Valider',
            'class' => 'btn btn-primary',
            'decorators' => ['ViewHelper']
        ]);

        $form->addElement('button', 'cancel', [
            'label' => 'Annuler',
            'class' => 'btn btn-default',
            'decorators' => ['ViewHelper']
        ]);

        if ($default) {
            $form->setDefaults($default);
        }

        return $form;
    }

    /**
     * @param $default
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     */
    public static function getAcceptanceForm($default)
    {
        $form = new Ccsd_Form([
            'class' => 'form-horizontal',
            'action' => '/administratepaper/accept/id/' . $default['id'],
            'id' => 'acceptance-form'
        ]);

        $form->setDecorators([[
            'ViewScript', [
                'viewScript' => '/administratemail/form.phtml'
            ]],
            'FormActions',
            'Form',
            'FormCss',
            'FormJavascript',
            'FormRequired'
        ]);
        // $form->setDefault();

        // to
        $form->addElement('text', 'to', [
            'label' => 'À',
            'disabled' => true,
            'value' => $default['author']->getFullName() . ' <' . $default['author']->getEmail() . '>']);

        // cc
        $form->addElement('text', 'cc', ['label' => 'CC']);

        // bcc
        $form->addElement('text', 'bcc', ['label' => 'BCC']);

        // from
        $form->addElement('text', 'from', [
            'label' => 'De',
            'disabled' => true,
            'placeholder' => RVCODE . '@' . DOMAIN,
            'value' => Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>']);

        // reply-to
        $form->addElement('text', 'reply-to', [
            'label' => 'Répondre à',
            'disabled' => true,
            'placeholder' => RVCODE . '@' . DOMAIN,
            'value' => Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>']);

        $form->addElement(new Ccsd_Form_Element_Text([
            'name' => 'acceptance-subject',
            'label' => 'Sujet',
            'value' => $default['subject']
        ]));

        $form->addElement(new Ccsd_Form_Element_Textarea([
            'name' => 'acceptance-message',
            'class' => 'full_mce',
            'label' => 'Message',
            'value' => $default['body']
        ]));

        return $form;
    }

    /**
     * @param $default
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     */
    public static function getPublicationForm($default)
    {
        $form = new Ccsd_Form([
            'class' => 'form-horizontal',
            'action' => '/administratepaper/publish/id/' . $default['id'],
            'id' => 'publish-form'
        ]);

        $form->setDecorators([[
            'ViewScript', [
                'viewScript' => '/administratemail/form.phtml'
            ]],
            'FormActions',
            'Form',
            'FormCss',
            'FormJavascript',
            'FormRequired'
        ]);

        // to
        $form->addElement('text', 'to', [
            'label' => 'À',
            'disabled' => true,
            'value' => $default['author']->getFullName() . ' <' . $default['author']->getEmail() . '>']);

        // cc
        $form->addElement('text', 'cc', ['label' => 'CC']);

        // bcc
        $form->addElement('text', 'bcc', ['label' => 'BCC']);

        // from
        $form->addElement('text', 'from', [
            'label' => 'De',
            'disabled' => true,
            'placeholder' => RVCODE . '@' . DOMAIN,
            'value' => Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>']);

        // reply-to
        $form->addElement('text', 'reply-to', [
            'label' => 'Répondre à',
            'disabled' => true,
            'placeholder' => RVCODE . '@' . DOMAIN,
            'value' => Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>']);

        $form->addElement(new Ccsd_Form_Element_Text([
            'name' => 'publication-subject',
            'label' => 'Sujet',
            'value' => $default['subject']
        ]));

        $form->addElement(new Ccsd_Form_Element_Textarea([
            'name' => 'publication-message',
            'class' => 'full_mce',
            'label' => 'Message',
            'value' => $default['body']
        ]));

        return $form;
    }

    /**
     * @param $default
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     */
    public static function getRefusalForm($default)
    {
        $form = new Ccsd_Form([
            'class' => 'form-horizontal',
            'action' => '/administratepaper/refuse/id/' . $default['id'],
            'id' => 'refusal-form'
        ]);

        $form->setDecorators([[
            'ViewScript', [
                'viewScript' => '/administratemail/form.phtml'
            ]],
            'FormActions',
            'Form',
            'FormCss',
            'FormJavascript',
            'FormRequired'
        ]);

        // to
        $form->addElement('text', 'to', [
            'label' => 'À',
            'disabled' => true,
            'value' => $default['author']->getFullName() . ' <' . $default['author']->getEmail() . '>']);

        // cc
        $form->addElement('text', 'cc', ['label' => 'CC']);

        // bcc
        $form->addElement('text', 'bcc', ['label' => 'BCC']);

        // from
        $form->addElement('text', 'from', [
            'label' => 'De',
            'disabled' => true,
            'placeholder' => RVCODE . '@' . DOMAIN,
            'value' => Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>']);

        // reply-to
        $form->addElement('text', 'reply-to', [
            'label' => 'Répondre à',
            'disabled' => true,
            'placeholder' => RVCODE . '@' . DOMAIN,
            'value' => Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>'
        ]);

        $form->addElement(new Ccsd_Form_Element_Text([
            'name' => 'refusal-subject',
            'label' => 'Sujet',
            'value' => $default['subject']
        ]));

        $form->addElement(new Ccsd_Form_Element_Textarea([
            'name' => 'refusal-message',
            'label' => "Message",
            'class' => 'full_mce',
            'value' => $default['body']
        ]));

        return $form;
    }

    /**
     * @param $default
     * @param $editors
     * @param $paper
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     */
    public static function getAskOtherEditorsForm($default, $editors, $paper)
    {
        $form = new Ccsd_Form([
            'class' => 'form-horizontal',
            'action' => '/administratepaper/askothereditors/id/' . $default['id']]);

        $askeditors_subform = new Ccsd_Form_SubForm();
        $attachment_subform = new Ccsd_Form_SubForm();

        $attachment_subform->setDecorators([[
            'ViewScript', [
                'viewScript' => '/administratemail/form.phtml',
                'name' => 'attachment',
            ]],
            'FormActions',
            'Form',
            'FormCss',
            'FormJavascript',
            'FormRequired'
        ]);

        $askeditors_subform->setDecorators([
            ['ViewScript', [
                'viewScript' => '/administratepaper/askothereditors_form.phtml',
                'name' => 'askEditors',
                'editors' => $editors,
                'paper' => $paper,
            ]],
            'FormTinymce',
            'FormCss',
            'FormJavascript'
        ]);

        // cc
        $askeditors_subform->addElement('text', 'cc', ['label' => 'CC']);

        // bcc
        $askeditors_subform->addElement('text', 'bcc', ['label' => 'BCC']);

        // from
        $askeditors_subform->addElement('text', 'from', [
            'label' => 'De',
            'disabled' => true,
            'placeholder' => RVCODE . '@' . DOMAIN,
            'value' => Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>']);

        // reply-to
        $askeditors_subform->addElement('text', 'reply-to', [
            'label' => 'Répondre à',
            'disabled' => true,
            'placeholder' => RVCODE . '@' . DOMAIN,
            'value' => Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>']);

        $askeditors_subform->addElement(new Ccsd_Form_Element_Text([
            'name' => 'ask-other-editors-subject',
            'label' => 'Sujet',
            'value' => $default['subject']
        ]));

        $askeditors_subform->addElement(new Ccsd_Form_Element_Textarea([
            'name' => 'ask-other-editors-message',
            'label' => "Message",
            'class' => 'full_mce',
            'value' => $default['body']
        ]));

        $form->addSubForms(['askEditors' => $askeditors_subform, 'attachment' => $attachment_subform]);

        return $form;
    }

    /**
     * @param $default
     * @param string $type
     * @param Episciences_Review|null $review
     * @return Ccsd_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getRevisionForm($default, $type = 'minor', Episciences_Review $review = null)
    {

        $minDate = date('Y-m-d');
        $maxDate = Episciences_Tools::addDateInterval($minDate, Episciences_Review::DEFAULT_REVISION_DEADLINE_MAX);

        $isChecked = ($type === 'major') ? 1 : 0;

        if (null !== $review) { // git #123 : Ne jamais réassigner automatiquement les relecteurs, que ce soit pour des demandes de modif mineures ou majeures
            $automaticallyReassignSameReviewers = $review->getSetting(Episciences_Review::SETTING_AUTOMATICALLY_REASSIGN_SAME_REVIEWERS_WHEN_NEW_VERSION);
            if ($type === 'minor') {
                $isChecked = !empty($automaticallyReassignSameReviewers) && in_array(Episciences_Review::MINOR_REVISION_ASSIGN_REVIEWERS, $automaticallyReassignSameReviewers);
            } elseif ($type === 'major') {
                $isChecked = !empty($automaticallyReassignSameReviewers) && in_array(Episciences_Review::MAJOR_REVISION_ASSIGN_REVIEWERS, $automaticallyReassignSameReviewers);
            }
        }

        $form = new Ccsd_Form([
            'class' => 'form-horizontal',
            'action' => '/administratepaper/revision/id/' . $default['id'] . '/type/' . $type,
            'id' => $type . '_revision-form'
        ]);

        $form->setDecorators([[
            'ViewScript', [
                'viewScript' => '/administratemail/form.phtml'
            ]],
            'FormActions',
            'Form',
            'FormCss',
            'FormJavascript',
            'FormRequired'
        ]);

        // to
        $form->addElement('text', 'to', [
            'label' => 'À',
            'disabled' => true,
            'value' => $default['author']->getFullName() . ' <' . $default['author']->getEmail() . '>']);

        // cc
        $form->addElement('text', 'cc', ['label' => 'CC']);

        // bcc
        $form->addElement('text', 'bcc', [
            'label' => 'BCC',
            'value' => Episciences_Review::forYourInformation()
        ]);

        // from
        $form->addElement('text', 'from', [
            'label' => 'De',
            'placeholder' => RVCODE . '@' . DOMAIN,
            'disabled' => true,
            'value' => Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>']);

        // reply-to
        $form->addElement('text', 'reply-to', [
            'label' => 'Répondre à',
            'placeholder' => RVCODE . '@' . DOMAIN,
            'disabled' => true,
            'value' => Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>']);

        // revision deadline (optional)
        $form->addElement('date', $type . '-revision-deadline', [
            'label' => 'Date limite de réponse',
            'class' => 'form-control',
            'pattern' => '[A-Za-z]{3}',
            'placeholder' => Zend_Registry::get('Zend_Translate')->translate('Optionnelle'),
            'attr-mindate' => $minDate,
            'attr-maxdate' => $maxDate
        ]);

        $form->addElement('text', $type . '-revision-subject', [
            'label' => 'Sujet',
            'value' => $default['subject']]);

        $form->addElement('textarea', $type . '-revision-message', [
            'label' => 'Message',
            'class' => 'full_mce',
            'value' => $default['body']]);

        $checkboxDecorators = [
            'ViewHelper',
            'Description',
            ['Label', ['placement' => 'APPEND', 'class' => $isChecked ? "alert-danger" : "alert-info"]],
            ['HtmlTag', ['tag' => 'div', 'class' => 'col-md-9 col-md-offset-3']],
            ['Errors', ['placement' => 'APPEND']]
        ];
        $form->addElement('checkbox', 'auto_reassign', [
            'id' => 'auto_reassign' . $type,
            'label' => "Réassigner les relecteurs à la nouvelle version de l'article",
            'value' => $isChecked,
            'options' => ['uncheckedValue' => 0, 'checkedValue' => 1],
            'decorators' => $checkboxDecorators]);

        return $form;
    }

    /**
     *  assign users (reviewers or editors) to a paper
     * @param $ids
     * @param array $params
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     */
    public static function assign($ids, array $params)
    {
        if (empty($ids)) {
            return false;
        }

        // prepare assignment
        if (!array_key_exists('status', $params)) {
            $params['status'] = Episciences_User_Assignment::STATUS_ACTIVE;
        }

        // if there is only one id, insert it into an array for processing
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        foreach ($ids as $uid) {
            $params['uid'] = $uid;
            $oAssignment = new Episciences_User_Assignment($params);
            $oAssignment->save();
        }

        return true;
    }

    /**
     * remove users from a paper (editors or reviewers)
     * @param $ids
     * @param array $params
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     */
    public static function unassign($ids, array $params)
    {
        if (empty($ids)) {
            return false;
        }

        // prepare unassignment
        $params['status'] = Episciences_User_Assignment::STATUS_INACTIVE;

        // if there is only one id, insert it into an array for processing
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        foreach ($ids as $uid) {
            $params['uid'] = $uid;
            $oAssignment = new Episciences_User_Assignment($params);
            $oAssignment->save();
        }

        return true;
    }

    /**
     * delete a paper from datbase, and all associated files
     * @param $docid
     * @return bool
     * @throws Zend_Db_Statement_Exception
     */
    public static function delete($docid)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $paper = Episciences_PapersManager::get($docid, false);

        // delete from database
        Episciences_CommentsManager::deleteByDocid($docid);
        Episciences_Mail_LogManager::deleteByDocid($docid);

        $db->delete(T_PAPER_VISITS, ['DOCID = ?' => $docid]);
        $db->delete(VISITS_TEMP, ['DOCID = ?' => $docid]);
        $db->delete(T_LOGS, ['DOCID = ?' => $docid]);
        $db->delete(T_REVIEWER_REPORTS, ['DOCID = ?' => $docid]);
        $db->delete(T_PAPER_SETTINGS, ['DOCID = ?' => $docid]);
        $db->delete(T_ALIAS, ['DOCID = ?' => $docid]);
        $db->delete(T_PAPERS, ['DOCID = ?' => $docid]);
        $db->delete(T_VOLUME_PAPER, ['DOCID = ?' => $docid]);
        $db->delete(T_VOLUME_PAPER_POSITION, ['PAPERID = ?' => $paper->getPaperid()]);

        // delete paper folder and content
        if (defined('RVCODE') && defined('REVIEW_FILES_PATH') && $docid) {
            Episciences_Tools::deleteDir(REVIEW_FILES_PATH . $docid);
        }

        // remove from index
        Ccsd_Search_Solr_Indexer::addToIndexQueue([$docid], 'episciences', 'DELETE', 'episciences');

        // TODO: delete user assignments
        // TODO: delete user invitations
        // TODO: delete user invitation answers
        // TODO: if published paper, update HAL metadata

        return true;

    }

    /**
     * fetch a paper object (or false if not found)
     * @param $docId
     * @param bool $withxsl
     * @return bool|Episciences_Paper
     */
    public static function get($docId, bool $withxsl = true)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $select = $db->select()
            ->from(['papers' => T_PAPERS])
            ->where('DOCID = ?', $docId)
            ->joinLeft(['conflicts' => T_PAPER_CONFLICTS], 'papers.PAPERID = conflicts.paper_id' );

        $data = self::fromSequentialArrayToAssoc($select->query()->fetchAll());

        if (!$data) {
            return false;
        }

        $data = $data[$docId];
        return new Episciences_Paper(array_merge($data, ['withxsl' => $withxsl]));

    }

    /**
     * @param $aid
     * @param array $params
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     */
    public static function getDeadlineForm($aid, $params = [])
    {
        $form = new Ccsd_Form([
            'action' => '/administratepaper/savenewdeadline/aid/' . $aid,
            'class' => 'form-horizontal',
            'id' => 'deadline-form']);

        $form->addElement('text', 'sender', [
            'label' => 'Expéditeur',
            'class' => 'form-control',
            'disabled' => 'disabled',
            'value' => Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>'
        ]);

        $form->addElement('text', 'recipient', [
            'label' => 'Destinataire',
            'class' => 'form-control',
            'disabled' => 'disabled'
        ]);

        // cc
        $form->addElement('text', 'cc', ['label' => 'CC']);

        // bcc
        $form->addElement('text', 'bcc', ['label' => 'BCC']);

        $form->addElement('date', 'deadline', [
            'label' => 'Date limite de rendu de la relecture',
            'class' => 'form-control',
            'pattern' => '[A-Za-z]{3}'
        ]);
        if (is_array($params) && array_key_exists('rating_deadline_min', $params)) {
            $form->getElement('deadline')->setAttrib('attr-mindate', $params['rating_deadline_min']);
        }
        if (is_array($params) && array_key_exists('rating_deadline_max', $params)) {
            $form->getElement('deadline')->setAttrib('attr-maxdate', $params['rating_deadline_max']);
        }

        // Sujet du message
        $form->addElement('text', 'subject', [
            'label' => 'Sujet',
            'class' => 'form-control'
        ]);

        // Corps du message
        $form->addElement('textarea', 'body', [
            'label' => 'Message',
            'class' => 'form-control',
            'tiny' => true,
            'rows' => 15
        ]);

        return $form;
    }

    /**
     * @param $aid
     * @param $docId
     * Une invitation est à l'origine de la relecture ? true : oui, false: non
     * @param bool $isUninvited
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     */
    public static function getReviewerRemovalForm($aid, $docId, bool $isUninvited = false): \Ccsd_Form
    {
        $action = '/administratepaper/savereviewerremoval?aid=' . $aid . '&docid=' . $docId;

        if ($isUninvited) {
            $action .= '&status=' . Episciences_Reviewer::STATUS_UNINVITED;
        }

        $form = new Ccsd_Form([
            'action' => $action,
            'class' => 'form-horizontal',
            'id' => 'reviewer-removal-form'
        ]);

        if (!$isUninvited) {
            $form->addElement('text', 'sender', [
                'label' => 'Expéditeur',
                'class' => 'form-control',
                'disabled' => 'disabled',
                'value' => Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>'
            ]);

            $form->addElement('text', 'recipient', [
                'label' => 'Destinataire',
                'class' => 'form-control',
                'disabled' => 'disabled'
            ]);

            // cc
            $form->addElement('text', 'cc', ['label' => 'CC']);

            // bcc
            $form->addElement('text', 'bcc', ['label' => 'BCC']);

            // Sujet du message
            $form->addElement('text', 'subject', [
                'label' => 'Sujet',
                'class' => 'form-control'
            ]);

            // Corps du message
            $form->addElement('textarea', 'body', [
                'label' => 'Message',
                'class' => 'form-control',
                'tiny' => true,
                'rows' => 15
            ]);

        } else {
            $form->addElement('note', 'note', [
                'value' => ''
            ]);

        }

        return $form;
    }

    /**
     * @param $docid
     * @param $editors
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     */
    public static function getReassignmentForm($docid, $editors)
    {
        $form = new Ccsd_Form([
            'action' => '/administratepaper/savereassignment?docid=' . $docid,
            'class' => 'form-horizontal',
            'id' => 'paper-reassignment-form'
        ]);

        $form->addElement('select', 'editor', [
            'label' => 'Rédacteur',
            'multiOptions' => $editors
        ]);

        $form->addElement('text', 'sender', [
            'label' => 'Expéditeur',
            'class' => 'form-control',
            'disabled' => 'disabled',
            'value' => Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>'
        ]);

        $form->addElement('text', 'recipient', [
            'label' => 'Destinataire',
            'class' => 'form-control',
            'disabled' => 'disabled'
        ]);

        // Sujet du message
        $form->addElement('text', 'subject', [
            'label' => 'Sujet',
            'class' => 'form-control'
        ]);

        // Corps du message
        $form->addElement('textarea', 'body', [
            'label' => 'Message',
            'class' => 'form-control',
            'tiny' => true,
            'rows' => 15
        ]);

        return $form;

    }

    /**
     * fetch an array of mail templates for paper status forms
     * (
     * accept, publish, decline, revision request, ask other editors,
     * waiting for author sources, waitingAuthorFormatting, reviewFormattingDeposed, ceAcceptFinalVersion
     * )
     * @param Episciences_Paper $paper
     * @param Episciences_User $contributor
     * @param $other_editors
     * @return array
     * @throws Zend_Date_Exception
     * @throws Zend_Exception
     */
    public static function getStatusFormsTemplates(Episciences_Paper $paper, Episciences_User $contributor, $other_editors)
    {
        $templates = [];

        $languages = Episciences_Tools::getLanguages();

        $locale = $contributor->getLangueid(true);

        if (!array_key_exists($locale, $languages)) {
            $locale = key($languages);
        }

        $mail = new Episciences_Mail('UTF-8');
        $mail->setDocid($paper->getDocid());
        $translator = Zend_Registry::get('Zend_Translate');

        // mail is going to be sent to contributor:
        // only show completed ratings, and remove criteria contributor is not allowed to see
        $ratings = $paper->getRatings(null, Episciences_Rating_Report::STATUS_COMPLETED, $contributor);
        $ratings_string = '';

        // prepare ratings string tag for template inclusion
        if ($ratings) {
            foreach ($ratings as $rating) {
                $reviewer = $paper->getReviewer($rating->getUid());
                if (!$reviewer) {
                    continue;
                }
                $ratings_string .= '<p style="border-bottom: 1px solid #999">';
                $ratings_string .= ucfirst($translator->translate('reviewer', $locale));
                $ratings_string .=  ' ' . $reviewer->getAlias($paper->getDocid());
                $ratings_string .=  '</p>';

                $partial = new Zend_View();
                $partial->locale = $locale;
                $partial->report = $rating;
                $partial->docid = $paper->getDocid();
                $partial->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts');
                $ratings_string .= $partial->render('partials/paper_report_mail_version.phtml');
                $ratings_string = str_replace(array(chr(13), chr(10)), '', $ratings_string);
            }
        }

        // templates keys
        $template_keys = [
            'publish' => Episciences_Mail_TemplatesManager::TYPE_PAPER_PUBLISHED_AUTHOR_COPY,
            'refuse' => Episciences_Mail_TemplatesManager::TYPE_PAPER_REFUSED,
            'minorRevision' => Episciences_Mail_TemplatesManager::TYPE_PAPER_MINOR_REVISION_REQUEST,
            'majorRevision' => Episciences_Mail_TemplatesManager::TYPE_PAPER_MAJOR_REVISION_REQUEST
        ];

        // accept paper (or request final version)
        $template_keys['accept'] = (!$paper->isTmp())
            ? Episciences_Mail_TemplatesManager::TYPE_PAPER_ACCEPTED
            : Episciences_Mail_TemplatesManager::TYPE_PAPER_ACCEPTED_TMP_VERSION;

        // ask other editors
        if (!empty($other_editors)) {
            $template_keys['askOtherEditors'] = Episciences_Mail_TemplatesManager::TYPE_PAPER_ASK_OTHER_EDITORS;
        }

        // waiting for sources author template key
        $template_keys['waitingAuthorSources'] = Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_WAITING_FOR_AUTHOR_SOURCES_AUTHOR_COPY;
        // waiting for author formatting template key
        $template_keys['waitingAuthorFormatting'] = Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_WAITING_FOR_AUTHOR_FORMATTING_AUTHOR_COPY;
        // review formatting deposed key
        $template_keys['reviewFormattingDeposed'] = Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_REVIEW_FORMATTING_DEPOSED_AUTHOR_COPY;
        // ready to publish
        $template_keys['ceAcceptFinalVersion'] = Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_ACCEPTED_FINAL_VERSION_AUTHOR_COPY;

        foreach ($template_keys as $template_name => $template_key) {
            $oTemplate = new Episciences_Mail_Template();
            $oTemplate->setLocale($locale);
            $oTemplate->findByKey($template_key);
            $oTemplate->loadTranslations();

            $templates[$template_name] = [
                'id' => $paper->getDocid(),
                'subject' => $oTemplate->getSubject(),
                'body' => $oTemplate->getBody(),
                'author' => $contributor
            ];
        }

        $urlHelper = new Zend_View_Helper_Url();
        $dateHelper = new Episciences_View_Helper_Date();

        $site = HTTP . '://' . $_SERVER['SERVER_NAME'];
        $url = $site . $urlHelper->url([
                'controller' => 'paper',
                'action' => 'view',
                'id' => $paper->getDocid()
            ]);

        $defaultTags = [
            Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME => $contributor->getScreenName(),
            Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME => $contributor->getUsername(),
            Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME => $contributor->getFullName(),
        ];

        $tags = array_merge($mail->getTags(), [
            Episciences_Mail_Tags::TAG_ARTICLE_ID => $paper->getDocid(),
            Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($locale, true),
            Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $paper->formatAuthorsMetadata($locale),
            Episciences_Mail_Tags::TAG_SUBMISSION_DATE => $dateHelper::Date($paper->getSubmission_date(), $locale),
            Episciences_Mail_Tags::TAG_PAPER_URL => $url,
            Episciences_Mail_Tags::TAG_PAPER_RATINGS => $ratings_string,
            Episciences_Mail_Tags::TAG_PAPER_REPO_URL => $paper->getDocUrl(),
            Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN => $site . '/user/lostlogin'
        ]);


        foreach ($templates as $name => &$template) {

            if ($name === 'waitingAuthorFormatting') {
                $paperSubmissionDate = date('Y-m-d', strtotime($paper->getSubmission_date())); // Current version
                $doi = $paper->getDoi();
                $volumeId = $paper->getVid();
                $volume = Episciences_VolumesManager::find($volumeId);
                $lastRevisionDateIso = date('Y-m-d', strtotime($paper->getWhen())); // latest version
                $revisionsDate = $paper->buildRevisionDates($locale); // all versions
                $revisionsDateIso = $paper->buildRevisionDates(); // all versions in ISO format
                $paperPosition = $paper->getPaperPositionInVolume(); // position of paper in volume
                $acceptanceDate = $paper->getAcceptanceDate();

                $addTags = array_merge($defaultTags, [
                    Episciences_Mail_Tags::TAG_PAPER_SUBMISSION_DATE => $dateHelper::Date($paperSubmissionDate, $locale),
                    Episciences_Mail_Tags::TAG_PAPER_SUBMISSION_DATE_ISO => $paperSubmissionDate,
                    Episciences_Mail_Tags::TAG_LAST_REVISION_DATE_ISO => $lastRevisionDateIso,
                    Episciences_Mail_Tags::TAG_LAST_REVISION_DATE => $dateHelper::Date($lastRevisionDateIso, $locale),
                    Episciences_Mail_Tags::TAG_REVISION_DATES => !empty($revisionsDate) ? $revisionsDate : $translator->translate('Aucune', $locale),
                    Episciences_Mail_Tags::TAG_REVISION_DATES_ISO => !empty($revisionsDate) ? $revisionsDateIso : $translator->translate('Aucune', $locale),
                    Episciences_Mail_Tags::TAG_ACCEPTANCE_DATE => $acceptanceDate ? $dateHelper::Date($acceptanceDate, $locale) : $translator->translate('Aucune', $locale),
                    Episciences_Mail_Tags::TAG_ACCEPTANCE_DATE_ISO => $acceptanceDate ? date('Y-m-d', strtotime($acceptanceDate)) : $translator->translate('Aucune', $locale),
                    Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $paper->getPaperid(),
                    Episciences_Mail_Tags::TAG_DOI => $doi ?: $translator->translate('Aucun', $locale),
                    Episciences_Mail_Tags::TAG_VOLUME_ID => $volumeId,
                    Episciences_Mail_Tags::TAG_VOLUME_NAME => $paper->buildVolumeName($locale),
                    Episciences_Mail_Tags::TAG_VOL_BIBLIOG_REF => ($volume && $volume->getBib_reference()) ?: $translator->translate('Aucune', $locale),
                    Episciences_Mail_Tags::TAG_SECTION_ID => $paper->getSid(),
                    Episciences_Mail_Tags::TAG_SECTION_NAME => $paper->buildSectionName($locale),
                    Episciences_Mail_Tags::TAG_PAPER_POSITION_IN_VOLUME => !empty($paperPosition) ? $paperPosition : $translator->translate('Aucun', $locale),
                    Episciences_Mail_Tags::TAG_CURRENT_YEAR => date('Y'),
                    Episciences_Mail_Tags::TAG_REVIEW_CE_RESOURCES_URL => $site . '/public/' . RVCODE . '_episciences.zip',
                    Episciences_Mail_Tags::TAG_VOLUME_EDITORS => ($volume && $volume->formatEditors()) ? $volume->formatEditors() : $translator->translate('Aucun', $locale)
                ]);

            } elseif ($name === 'askOtherEditors') { // SCREEN_NAME and FULL_NAME tags can't have a default value for Ask Other Editors template, since there are multiple recipients
                $addTags = [
                    Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME => Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME,
                    Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME => Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME,
                    Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME => Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME
                ];

            } else {
                $addTags = $defaultTags;
            }

            $tags = array_merge($tags, $addTags);

            $template['subject'] = str_replace(array_keys($tags), array_values($tags), $template['subject']);
            $template['subject'] = Ccsd_Tools::clear_nl($template['subject']);
            $template['body'] = str_replace(array_keys($tags), array_values($tags), $template['body']);
            $template['body'] = nl2br($template['body']);
            $template['body'] = Ccsd_Tools::clear_nl($template['body']);
        }

        return $templates;
    }

    /**
     * Met à jour l'UID de l'utilisateur
     * @param int $oldUid : l'UID à supprimer
     * @param int $newUid : Nouvel UID
     * @return int : le nombre de lignes affectées
     * @throws Zend_Db_Adapter_Exception
     */
    public static function updateUid(int $oldUid = 0, int $newUid = 0)
    {

        if ($oldUid == 0 || $newUid == 0) {
            return 0;
        }
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $data['UID'] = (int)$newUid;
        $where['UID = ?'] = (int)$oldUid;
        return $db->update(T_PAPERS, $data, $where);

    }

    /**
     * renome l'identifiant d'un article
     * @param $old
     * @param $new
     * @return bool|int
     */

    public static function renameIdentifier($old, $new)
    {
        try {
            if (!is_string($old) || !is_string($new)) {
                return false;
            }
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $data['IDENTIFIER'] = $new;
            $where['IDENTIFIER = ?'] = (string)$old;
            return $db->update(T_PAPERS, $data, $where);
        } catch (Exception $e) {
            return false;
        }

    }

    /**
     * met à jour les métadonnée
     * @param int $docId
     * @return bool|int
     * @throws Exception
     * @throws Zend_Db_Adapter_Exception
     */
    public static function updateRecordData(int $docId)
    {

        if ($docId <= 0) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $result = self::getPaperParams($docId);

        $identifier = $result['IDENTIFIER'];
        $repoId = (int)$result['REPOID'];
        $version = (int)$result['VERSION'];

        $repoIdentifier = Episciences_Repositories::getIdentifier($repoId, $identifier, $version);
        $baseUrl = Episciences_Repositories::getBaseUrl($repoId);
        $oai = new Ccsd_Oai_Client($baseUrl, 'xml');

        $record = $oai->getRecord($repoIdentifier);
        $record = preg_replace('#xmlns="(.*)"#', '', $record);

        $result = Episciences_Repositories::callHook('hookCleanXMLRecordInput', ['record' => $record, 'repoId' => $repoId]);

        if (array_key_exists('record', $result)) {
            $record = $result['record'];
            // delete all paper files
            Episciences_Paper_FilesManager::deleteByDocId($docId);
            // add all files
            Episciences_Repositories::callHook('hookFilesProcessing', ['repoId' => $repoId, 'identifier' => $identifier, 'docId' => $docId]);
        }

        // delete all paper datasets
        Episciences_Paper_DatasetsManager::deleteByDocId($docId);

        if (Episciences_Repositories::hasHook($repoId)) {
            // add all linked data if has hook
            Episciences_Repositories::callHook('hookLinkedDataProcessing', ['repoId' => $repoId, 'identifier' => $identifier, 'docId' => $docId]);

        } else {
            // add all datasets for Hal repository
            Episciences_Submit::datasetsProcessing($docId);

        }

        // Mise à jour des données
        $data['RECORD'] = $record;
        $where['DOCID = ?'] = $docId;

        return $db->update(T_PAPERS, $data, $where);
    }

    /**
     *
     * @param $docId
     * @return bool|mixed
     */
    private static function getPaperParams($docId)
    {
        if ((int)$docId <= 0) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_PAPERS, ['IDENTIFIER', 'REPOID', 'VERSION'])
            ->where('DOCID = ?', $docId);
        return $db->fetchRow($select);
    }

    /**
     * met à jour la version d'un article
     * @param Episciences_Paper $paper
     * @param int $newVersion
     * @return int|string
     */
    public static function updateVersion(Episciences_Paper $paper, int $newVersion)
    {
        try {
            if ((int)$newVersion <= 0) {
                return false;
            }
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $data['VERSION'] = (int)$newVersion;
            $where['IDENTIFIER = ?'] = $paper->getIdentifier();
            $where['VERSION = ?'] = (int)$paper->getVersion();
            $where['REPOID = ?'] = $paper->getRepoid();
            return $db->update(T_PAPERS, $data, $where);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Retourne l'article dans sa dernière version(non prise en compte des versions temporaires)
     * @param $paperId
     * @return bool|Episciences_Paper
     * @throws Zend_Db_Statement_Exception
     */
    public static function getLastPaper($paperId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_PAPERS)
            ->where('PAPERID = ?', $paperId)
            ->where('REPOID != ?', 0)
            ->order('WHEN DESC');
        $result = $select->query()->fetch();

        if (!$result) {
            return false;
        }

        return new Episciences_Paper($result);
    }

    /**
     * @param array $default
     * @return Zend_Form
     * @throws Zend_Form_Exception
     */
    public static function getReviewFormattingDeposedForm(array $default)
    {
        $form = self::getModalPaperStatusCommonForm($default, 'reviewFormattingDeposed');
        $form->setAttrib('id', 'review-formatting-deposed-form');
        $form->setAction('/administratepaper/reviewformattingdeposed/id/' . $default['id']);
        return $form;
    }

    /**
     * @param array $default
     * @param string $prefix
     * @return Zend_Form
     * @throws Zend_Form_Exception
     */
    private static function getModalPaperStatusCommonForm(array $default, string $prefix)
    {
        $form = new Ccsd_Form(['class' => 'form-horizontal']);
        $subForm = new Ccsd_Form_SubForm();
        $subForm->setDecorators([[
            'ViewScript', [
                'viewScript' => '/administratemail/form.phtml'
            ]],
            'FormActions',
            'Form',
            'FormCss',
            'FormJavascript',
            'FormRequired'
        ]);

        // to
        $subForm->addElement('text', 'to', [
            'label' => 'À',
            'disabled' => true,
            'value' => $default['author']->getFullName() . ' <' . $default['author']->getEmail() . '>']);

        // cc
        $subForm->addElement('text', 'cc', ['label' => 'CC']);

        // bcc
        $subForm->addElement('text', 'bcc', ['label' => 'BCC']);

        // from
        $subForm->addElement('text', 'from', [
            'label' => 'De',
            'disabled' => true,
            'placeholder' => RVCODE . '@' . DOMAIN,
            'value' => Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>']);

        // reply-to
        $subForm->addElement('text', 'reply-to', [
            'label' => 'Répondre à',
            'disabled' => true,
            'placeholder' => RVCODE . '@' . DOMAIN,
            'value' => Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>']);

        $subForm->addElement(new Ccsd_Form_Element_Text([
            'name' => $prefix . 'Subject',
            'label' => 'Sujet',
            'value' => $default['subject']
        ]));

        $subForm->addElement(new Ccsd_Form_Element_Textarea([
            'name' => $prefix . 'Message',
            'class' => 'full_mce',
            'label' => 'Message',
            'value' => $default['body']
        ]));

        return $form->addSubForm($subForm, $prefix);
    }

    /**
     * @param array $default
     * @return Zend_Form
     * @throws Zend_Form_Exception
     */
    public static function getCeAcceptFinalVersionForm(array $default)
    {
        $form = self::getModalPaperStatusCommonForm($default, 'ceAcceptFinalVersionRequest');
        $form->setAttrib('id', 'ready-to-publish-form');
        $form->setAction('/administratepaper/copyeditingacceptfinalversion/id/' . $default['id']);
        return $form;
    }

    /**
     * retourne le formulaire de demande des sources auteur
     * @param array $default
     * @return Zend_Form
     * @throws Zend_Form_Exception
     */
    public static function getWaitingForAuthorSourcesForm(array $default)
    {

        $form = self::getModalPaperStatusCommonForm($default, 'authorSourcesRequest');
        $form->setAttrib('id', 'waiting-for-author-sources-form');
        $form->setAction('/administratepaper/waitingforauthorsources/id/' . $default['id']);

        /*   // revision deadline (optional)
           $form->addElement('date', 'author-sources-deadline', [
               'label' => 'Date limite de réponse',
               'class' => 'form-control',
               'pattern' => '[A-Za-z]{3}',
               'placeholder' => Zend_Registry::get('Zend_Translate')->translate('Optionnelle')
           ]);*/

        return $form;
    }

    /**
     * retourne le formulaire de demande de la mise en forme de l'auteur (version finale)
     * @param array $default
     * @return Zend_Form
     * @throws Zend_Form_Exception
     */
    public static function getWaitingForAuthorFormatting(array $default)
    {

        $form = self::getModalPaperStatusCommonForm($default, 'authorFormattingRequest');
        $form->setAttrib('id', 'waiting-for-author-formatting-form');
        $form->setAction('/administratepaper/waitingforauthorformatting/id/' . $default['id']);
        return $form;
    }

    /**
     * @param string $doi
     * @param int $paperId
     * @return int
     */
    public static function updateDoi(string $doi, int $paperId): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $where['PAPERID = ?'] = $paperId;
        $values = ['DOI' => $doi];

        try {
            $resUpdate = $db->update(T_PAPERS, $values, $where);
        } catch (Zend_Db_Adapter_Exception $exception) {
            error_log($exception->getMessage());
            error_log('Error updating DOI ' . $doi . ' for paperId ' . $paperId);
            $resUpdate = 0;
        }
        return $resUpdate;
    }

    /** OpenAire Metrics
     * @param string $limitDateTime empty for current year
     * @param string $registrationDateTime empty for current year
     * @return false|int
     * @throws Zend_Db_Statement_Exception
     */
    public static function getSubmittedPapersCountAfterDate(string $limitDateTime = '', string $registrationDateTime = '')
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if ($limitDateTime === '') {
            $limitDateTime = date('Y') . '-01-01 00:00:00';
        }

        if ($registrationDateTime === '') {
            $registrationDateTime = date('Y') . '-01-01 00:00:00';
        }

        $select = $db->select()
            ->from(T_PAPERS, [new Zend_Db_Expr("COUNT('DOCID') AS NbSubmissions")])
            ->from(T_USERS, null)
            ->where(T_PAPERS . '.SUBMISSION_DATE >= ?', $limitDateTime)
            ->where(T_PAPERS . '.UID = ' . T_USERS . '.UID');

        $select->where('REGISTRATION_DATE >= ?', $registrationDateTime);

        $result = $select->query()->fetch();

        if (!$result) {
            return false;
        }

        return (int)$result['NbSubmissions'];
    }

    /**
     * @param $a
     * @param $b
     * @return int
     */
    private static function statusCmp($a, $b)
    {
        $a = Episciences_Paper::$_statusOrder[$a];
        $b = Episciences_Paper::$_statusOrder[$b];

        if ($a == $b) {
            $r = 0;
        } else {
            $r = ($a > $b) ? 1 : -1;
        }

        return $r;
    }

    /**
     * @param string $date
     * @return bool
     */
    private static function compareToCurrentTime(string $date): bool
    {
        return strtotime($date) < time();
    }

    /**
     * @return string
     */
    public static function getEarliestPublicationDate()
    {
        define ('EPD', 'earliestPublicationDate');

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $select = $db->select()
            ->from(T_PAPERS, [new Zend_Db_Expr("MIN(PUBLICATION_DATE) AS " . EPD)])
            ->where('STATUS = ?', Episciences_Paper::STATUS_PUBLISHED);

        try {
            $result = $select->query()->fetch();
        } catch (Zend_Db_Statement_Exception $exception) {
            $result = '';
        }

        if (!$result) {
            $result[EPD] = '';
        }

        return $result[EPD];
    }

    /**
     * @param array $array
     * @return array
     */
    private static function fromSequentialArrayToAssoc(array $array): array
    {

        $list = [];
        $currentDocId = null;
        $allConflicts = [];

        foreach ($array as $arrayVals){

            if($currentDocId !== $arrayVals['DOCID'] ) {
                $currentDocId = $arrayVals['DOCID'];
                $allConflicts = []; // Collect all conflicts by docId
            }

            $currentOtherVals = [];
            $currentConflictVals = [];
            foreach ($arrayVals as $key => $val){

                if (in_array($key, Episciences_Paper_Conflict::TABLE_COLONES, true)) {
                    $currentConflictVals[$key] = $val;
                } else {
                    $currentOtherVals[$key] = $val;
                }

            }

            $allConflicts[] = $currentConflictVals;

            $list[$currentDocId] = $currentOtherVals;
            $list[$currentDocId]['conflicts'] = $allConflicts;

        }

        return $list;
    }

}