<?php

use GuzzleHttp\Exception\GuzzleException;
use Episciences\Trait\UrlBuilder;

class Episciences_PapersManager
{
    use UrlBuilder;

    public const NONE_FILTER = '0';
    public const WITH_FILTER = '-1';

    /**
     * @return array
     */
    public static function getFiltersParams(): array
    {
        $params = Zend_Controller_Front::getInstance()->getRequest()->getParams();
        Episciences_Tools::filter_multiarray($params);
        return $params;
    }

    /**
     * @param array $settings
     * @param bool $isFilterInfos
     * @param bool $isLimit
     * @param string|array|Zend_Db_Expr $cols // The columns to select
     * @return array
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Db_Statement_Exception
     */
    public static function getList(array $settings = [], bool $isFilterInfos = false, bool $isLimit = true, string|array|Zend_Db_Expr $cols = '*'): array
    {
        $rvId = $settings['is']['RVID'] ?? RVID;

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $select = self::getListQuery($settings, $isFilterInfos, $isLimit, $cols);

        $list = $db->fetchAssoc($select);

        $result = [];

        $allConflicts = Episciences_Paper_ConflictsManager::all($rvId);

        foreach ($list as $id => $item) {
            $item['withxsl'] = false;
            $paper = new Episciences_Paper($item);
            if (array_key_exists($paper->getPaperid(), $allConflicts)) {
                $paper->setConflicts($allConflicts[$paper->getPaperid()]);
            }
            $result[$id] = $paper;
        }

        return $result;
    }

    /**
     * @param array $settings
     * @param bool $isFilterInfos
     * @param bool $isLimit
     * @param string|array|Zend_Db_Expr $cols // The columns to select
     * @return Zend_Db_Select
     * @throws Zend_Db_Select_Exception
     */
    public static function getListQuery(array $settings = [], bool $isFilterInfos = false, bool $isLimit = true, string|array|Zend_Db_Expr $cols = '*'): \Zend_Db_Select
    {
        $select = self::getFilterQuery($settings, false, $isFilterInfos, $cols);

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
     * @param string|array|Zend_Db_Expr $cols // The columns to select
     * @return Zend_Db_Select
     * @throws Zend_Db_Select_Exception
     */
    private static function getFilterQuery(array $settings = [], bool $isCount = false, bool $isFilterInfos = false, string|array|Zend_Db_Expr $cols = '*'): \Zend_Db_Select
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $papersQuery = $db->select()->from(['papers' => T_PAPERS], $cols);

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

                if ($setting === 'repositories') {
                    $select = self::applyRepositoriesFilter($select, $value);
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
    private static function volumesFilter(Zend_Db_Select $select, array $value, bool $includeSecondaryVolume = false): \Zend_Db_Select
    {
        // Filtrage par volume secondaire : inclure l'article s'il appartient à un volume primaire(git#72)
        $select1 = self::getVolumesQuery();

        $select1->where(" st.VID IN (?)", $value);

        if ($includeSecondaryVolume) {
            $select1->orWhere("vpt.VID IN (?)", $value);
        }

        $select->where("DOCID IN (?)", $select1);

        return $select;
    }

    /**
     * @param array $fields
     * @return Zend_Db_Select
     */
    public static function getVolumesQuery(array $fields = ['DOCID']): \Zend_Db_Select
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db
            ->select()
            ->from(['st' => T_PAPERS], $fields)
            ->joinLeft(['vpt' => T_VOLUME_PAPER], 'st.DOCID = vpt.DOCID', [])
            ->where('st.RVID = ?', RVID);
    }

    /**
     * Retourne les articles assignés à un rôle
     * @param Zend_Db_Select $select
     * @param array $values
     * @param string $roleId : default : editor
     * @return Zend_Db_Select
     * @throws Zend_Db_Select_Exception
     */
    private static function
    filterByRole(Zend_Db_Select $select, array $values, string $roleId = Episciences_User_Assignment::ROLE_EDITOR): Zend_Db_Select
    {

        // fetch last paper assignment for each selected roleId
        $subQuery = self::fetchLastPaperAssignmentForSelectedRoleQuery($values, $roleId);

        $select
            ->where("DOCID IN (?)", $subQuery)
            ->where("DOCID NOT IN (?)", Episciences_UserManager::getSubmittedPapersQuery(Episciences_Auth::getUid())); //git #148 : L'auteur peut deviner les rédcateurs en charge de son article


        if ($roleId === Episciences_User_Assignment::ROLE_REVIEWER) {

            $result = self::fetchPapersWithNoConflictsConfirmation();

            if (!empty($result)) {
                $select->where('DOCID IN (?)', $result);
            }
        }


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
            if ($noneSelect !== null) {
                $select = $db
                    ->select()
                    ->union([$select, $noneSelect]);
            }
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
    private static function allPapers(array $excludeStatus = Episciences_Paper::NOT_LISTED_STATUS): Zend_Db_Select
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
    private static function applyDOIFilter(Zend_Db_Select $select, array $values): \Zend_Db_Select
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
                ->where("DOI IS NULL OR DOI LIKE '' ");
    }

    /**
     * @return Zend_Db_Select
     */
    private static function getPapersWithDOIQuery(): Zend_Db_Select
    {
        return
            self::allPapers()
                ->where("DOI IS NOT NULL AND DOI NOT LIKE '' ");
    }

    /**
     * @param Zend_Db_Select $select
     * @param String $word
     * @param array $volumes
     * @param array $sections
     * @return Zend_Db_Select
     * @throws Zend_Db_Select_Exception
     */
    private static function dataTableSearchQuery(Zend_Db_Select $select, string $word = '', array $volumes = [], array $sections = []): \Zend_Db_Select
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        if ((int)$word !== 0) {

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

            $assignedTo = ['editor'];

            if (!Zend_Registry::get('isCoiEnabled')) {
                $assignedTo[] = 'reviewer';
            }


            $query1 = $db
                ->select()
                ->from(['u' => T_USERS], ['USER_ID' => 'UID', 'SCREEN_NAME'])
                ->joinLeft(['p' => T_PAPERS], 'u.UID = p.UID')
                ->joinLeft(
                    ['a' => self::fetchLastPaperAssignmentForSelectedRoleQuery([], $assignedTo)],
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
    private static function makeCondition(array $array, string $word = '', string $method_name = ''): string
    {
        //Echapper les métacaractères dans les Expressions Régulières
        $metacharacters = '^ \. [ ] $ ( ) * + ? | { } \\';
        $word = addcslashes($word, $metacharacters);
        if (!empty($array)) {

            $valuesName = [];
            $arrayValues = [];

            foreach ($array as $key => $value) {
                if (!is_object($value)) {
                    $valuesName[$key] = $value;

                } else if (method_exists($value, $method_name)) {
                    $valuesName[$key] = $value instanceof Episciences_Volume ? $value->$method_name() : Ccsd_Tools::translate($value->$method_name());
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
     * @return int
     * @throws Zend_Db_Select_Exception
     */
    public static function getCount(array $settings = [], bool $isFilterInfos = false): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        /** @var Zend_Controller_Front $controller */
        $controller = Zend_Controller_Front::getInstance();

        /** @var Zend_Controller_Request_Http $request */
        $request = $controller->getRequest();
        $params = ($request->isPost()) ? $request->getPost('filters') : $request->getParams();
        unset ($params['controller'], $params['action'], $params['module'], $params['submit']);

        $select = self::getFilterQuery($settings, true, $isFilterInfos);

        return (int)$db->fetchOne($select);
    }

    /**
     * Compte le nombre d'articles d'une liste qui correspondent au(x) statut(s) en paramètre
     * @param $list
     * @param $status
     * @return int
     */
    public static function countByStatus($list, $status): int
    {
        $count = 0;

        if (!is_array($list) || empty($list)) {
            return $count;
        }

        foreach ($list as $oPaper) {
            if (is_array($status)) {
                if (in_array($oPaper->getStatus(), $status, false)) {
                    $count++;
                }
            } else if ($oPaper->getStatus() === (int)$status) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Renvoie un tableau de papiers triés par la clé passée en paramètres
     * @param $list
     * @param $key
     * @return false|mixed
     */
    public static function sortBy($list, $key)
    {
        if (empty($list)) {
            return false;
        }

        $result = [];
        foreach ($list as $id => $item) {
            $method = 'get' . ucfirst(strtolower($key));
            $itemKey = 0;
            if (method_exists($item, $method)) {
                $itemKey = $item->$method();
            }
            $result[$itemKey][$id] = $item;
        }

        Episciences_Tools::multi_ksort($result);

        if ($key === 'STATUS') {
            uksort($result, 'self::statusCmp');
        }


        return $result;
    }

    /**
     * Regroupe les papiers par statut (pour affichage pour le déposant)
     * @param $list
     * @return array
     */
    public static function sortByStatus($list): array
    {
        $result = [];

        if (empty($list)) {
            return $result;
        }

        /* @var  Episciences_Paper $item */
        foreach ($list as $id => $item) {
            $itemStatus = $item->getStatus();
            if ($itemStatus === Episciences_Paper::STATUS_SUBMITTED ||
                $itemStatus === Episciences_Paper::STATUS_OK_FOR_REVIEWING ||
                $itemStatus === Episciences_Paper::STATUS_BEING_REVIEWED ||
                $itemStatus === Episciences_Paper::STATUS_REVIEWED
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
     * @param int $docId
     * @param int $rvid
     * @return bool
     */
    public static function paperExists(int $docId, int $rvid = 0): bool
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->from(T_PAPERS, [new Zend_Db_Expr("COUNT('DOCID')")])
            ->where('DOCID = ?', $docId);

        if ($rvid !== 0) {
            $select->where('RVID = ?', $rvid);
        }
        return ((int)$select->query()->fetchColumn() > 0);

    }

    /**
     * Retourne l'identifiant de l'article si ce dernier est dèjà publié
     * @param int $paperId
     * @return int
     * @throws Zend_Db_Statement_Exception
     */
    public static function getPublishedPaperId(int $paperId): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db
            ->select()
            ->from(T_PAPERS)
            ->where('PAPERID = ?', $paperId)
            ->where('STATUS = ?', Episciences_Paper::STATUS_PUBLISHED);
        $data = $select->query()->fetch();

        if (!$data) {
            return 0;
        }

        return (int)$data['DOCID'];
    }

    /**
     * @param $docId
     * @param null $uid
     * @param null $typeId
     * @return array
     */
    public static function getLogs($docId, $uid = null, $typeId = null): array
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
            } elseif (is_numeric($typeId)) {
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
     * @param int $rvId
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    public static function getInvitations($docId, $status = null, bool $sorted = true, int $rvId = RVID): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        // fetch assignments (invitations don't have a docid, and are linked to an assignment)
        $select = self::getLatestInvitationByDocIdQuery($docId);

        //$debugQuery = $select->__toString();

        $data = $db?->fetchAll($select);

        //reviewers array
        $reviewers = ['tmp' => []];

        //prepare array
        $source = [];
        foreach ($data as $row) {

            if (array_key_exists($row['ASSIGNMENT_ID'], $source)) { // remove duplicated invitations
                continue;
            }
            self::addAnswersDate($row);
            $source[$row['INVITATION_AID']][$row['ASSIGNMENT_ID']] = $row;
        }

        //sort array
        $invitations = [];
        foreach ($source as $aid => $row) {
            $reviewer = null;
            $tmp = [];
            foreach ($row as $id => $invitation) {
                $isTmpUser = false;
                //recuperation du dernier état connu de l'invitation
                if (empty($tmp)) {
                    $tmp = $invitation;
                }
                //recuperation des infos de l'invitation d'origine, s'il y a eu une réponse à l'invitation
                if (!empty($tmp) && $aid === $id) {
                    $tmp['ASSIGNMENT_DATE'] = $invitation['ASSIGNMENT_DATE'];
                }

                //fetch reviewer detail
                if ($invitation['TMP_USER']) {
                    $isTmpUser = true;
                    if (!array_key_exists($invitation['UID'], $reviewers['tmp'])) {
                        $reviewer = new Episciences_User_Tmp();

                        if (!empty($reviewer->find($invitation['UID']))) {
                            $reviewer->generateScreen_name();
                            $reviewers['tmp'][$invitation['UID']] = $reviewer;
                        }

                    }
                } elseif (!array_key_exists($invitation['UID'], $reviewers)) {
                    $reviewer = new Episciences_Reviewer();
                    if ($reviewer->findWithCAS($invitation['UID'])) {
                        $reviewers[$invitation['UID']] = $reviewer;
                    } else {
                        trigger_error('CAS USER UID = ' . $invitation['UID'] . ' NOT FOUND', E_USER_WARNING);
                        continue;
                    }
                }


                if ($reviewer) {
                    $tmp['reviewer'] = self::reviewerProcess($reviewer, $docId, $rvId, $isTmpUser);
                }

                $key = !$isTmpUser ? $invitation['UID'] : 'tmp_' . $invitation['UID'];

                if (!array_key_exists('reviewer', $tmp) && array_key_exists($key, $reviewers)) {

                    $tmp['reviewer'] = self::reviewerProcess($reviewers[$key], $docId, $rvId, $isTmpUser);
                }
                $invitations[$key][] = $tmp;
            }

        }

        if ($sorted) {
            $result = self::sortInvitations($status, $invitations);
        } else {
            $result = $invitations;
        }

        return $result;
    }

    /**
     * @deprecated to use getLatestInvitationByDocIdQuery(), see https://github.com/CCSDForge/episciences/issues/886
     * @param $docId
     * @return Zend_Db_Select
     */
    public static function getInvitationQuery($docId): Zend_Db_Select
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
     * Generates a query to retrieve the latest invitation associated with a specific docId
     * @param $docId
     * @return Zend_Db_Select
     */
    public static function getLatestInvitationByDocIdQuery($docId): Zend_Db_Select
    {
        $db = Zend_Db_Table::getDefaultAdapter();

        $subMaxDate = $db?->select()
            ->from(
                T_USER_INVITATIONS,
                [
                    'AID',
                    'max_date' => 'MAX(SENDING_DATE)'
                ]
            )
            ->group('AID');

        $subInvitations = $db?->select()
            ->from(
                ['t1' => 'USER_INVITATION'],
                ['ID', 'AID', 'STATUS', 'SENDING_DATE', 'SENDER_UID', 'EXPIRATION_DATE']
            )
            ->join(
                ['t2' => $subMaxDate],
                't1.AID = t2.AID AND t1.SENDING_DATE = t2.max_date',
                []
            );

        return $db?->select()
            ->from(
                ['a' => 'USER_ASSIGNMENT'],
                [
                    'ASSIGNMENT_ID' => 'a.ID',
                    'INVITATION_ID' => 'a.INVITATION_ID',
                    'RVID' => 'a.RVID',
                    'DOCID' => 'a.ITEMID',
                    'TMP_USER' => 'a.TMP_USER',
                    'UID' => 'a.UID',
                    'ASSIGNMENT_STATUS' => 'a.STATUS',
                    'ASSIGNMENT_DATE' => 'a.WHEN',
                    'ASSIGNMENT_DEADLINE' => 'a.DEADLINE'
                ]
            )
            ->join(
                ['i' => $subInvitations],
                'a.INVITATION_ID = i.ID',
                [
                    'INVITATION_AID' => 'i.AID',
                    'INVITATION_STATUS' => 'i.STATUS',
                    'SENDER_UID' => 'i.SENDER_UID',
                    'INVITATION_DATE' => 'i.SENDING_DATE',
                    'EXPIRATION_DATE' => 'i.EXPIRATION_DATE'
                ]
            )
            ->where('a.ITEM = ?', 'paper')
            ->where('a.ITEMID = ?', $docId)
            ->where('a.ROLEID = ?', 'reviewer')
            ->order('ASSIGNMENT_DATE DESC')
        ;
    }

    /**
     * fetch paper reviewers (default: only fetch active reviewers)
     * @param $docId
     * @param null $status
     * @param bool $getCASdata
     * @param int|null $vid
     * @return Episciences_Reviewer[]
     * @throws JsonException
     * @throws Zend_Db_Statement_Exception
     */
    public static function getReviewers($docId, $status = null, bool $getCASdata = false, int $vid = null): array
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

        if (is_numeric($vid)) {
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
        if ($status === Episciences_User_Assignment::STATUS_PENDING || (is_array($status) && in_array(Episciences_User_Assignment::STATUS_PENDING, $status, true))) {
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
    public static function getTmpReviewerForm(): \Ccsd_Form
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

        /*  $form->addElement('text', 'firstname', [
              'label' => 'Prénom',
              'class' => 'form-control',
          ]);*/

        $form->addElement('select', 'user_lang', [
            'label' => 'Langue par défaut',
            'class' => 'form-control',
            'style' => 'width:auto;',
            'multiOptions' => ['en' => 'Anglais', 'fr' => 'Français'],
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
    public static function getReviewerInvitationForm($docId, $page, $referer, $params = null): \Ccsd_Form
    {
        $action = (new Episciences_View_Helper_Url())->url(['controller' => 'administratepaper', 'action' => 'savereviewerinvitation', 'docid' => $docId]);
        $action .= ($page) ? '&page=' . $page : '';
        $action .= (array_key_exists('vid', $params)) ? '&vid=' . $params['vid'] : '';
        $action .= (array_key_exists('special_issue', $params)) ? '&special_issue=' . $params['special_issue'] : '';

        $form = new Ccsd_Form();
        $form->addElement('hash', 'no_csrf_foo', array('salt' => 'unique'));
        $form->getElement('no_csrf_foo')->setTimeout(3600);
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
            'value' => Episciences_Review::forYourInformation($docId, null, true),
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

        self::addHiddenDocIdElement($form, 'invite-reviewer', $docId);

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

        $translator = Zend_Registry::get('Zend_Translate');
        $unavailableEditors = [];
        foreach ($users as $user) {
            $userName = '';

            // Add icon for editors (guest editor = star, editor = user icon)
            if ($name === 'editors') {
                $class = ($user->isGuestEditor()) ? 'grey glyphicon glyphicon-star' : 'lightergrey glyphicon glyphicon-user';
                $type = ($user->isGuestEditor()) ? ucfirst($translator->translate(Episciences_Acl::ROLE_GUEST_EDITOR)) : ucfirst($translator->translate(Episciences_Acl::ROLE_EDITOR));
                $icon = '<span class="' . $class . '" style="margin-right:10px"></span>';
                $icon = '<span style="cursor: pointer" data-toggle="tooltip" title="' . $type . '">' . $icon . '</span>';
                $userName .= $icon;
            }

            $userName .= $user->getFullname();

            // Track unavailable editors for JavaScript handling
            if ($name === 'editors' && !Episciences_UsersManager::isEditorAvailable($user->getUid(), RVID)) {
                $unavailableEditors[] = $user->getUid();
                $userName .= ' <span class="unavailable-badge">' . $translator->translate('unavailable') . '</span>';
            }

            $options[$user->getUid()] = $userName;
        }

        $form->addElement('multiCheckbox', $name, [
            'multiOptions' => $options,
            'separator' => '<br/>',
            'escape' => false,
            'decorators' => ['ViewHelper', ['HtmlTag', ['tag' => 'div', 'class' => $name . '-list', 'style' => 'margin-left: 15px', 'data-unavailable-editors' => json_encode($unavailableEditors)]]]
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
     * @throws JsonException
     * @throws Zend_Db_Statement_Exception
     */
    public static function getEditors($docId, $active = true, $getCASdata = false)
    {
        $editors = [];
        if (!$docId || !is_numeric($docId)) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $select = self::getAssignmentRoleQuery($docId, Episciences_Acl::ROLE_EDITOR);

        $result = $db->fetchAssoc($select);

        if ($active && !empty($result)) {
            $result = array_filter($result, static function ($user) {
                return ($user['STATUS']) === Episciences_User_Assignment::STATUS_ACTIVE;
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
    private static function getAssignmentRoleQuery(int $docId, string $role): \Zend_Db_Select
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
     * @throws JsonException
     * @throws Zend_Db_Statement_Exception
     */
    public static function getCopyEditors(int $docId, bool $active = true, bool $getCasData = false): array
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
    public static function getSuggestStatusForm($docId): \Ccsd_Form
    {
        $form = new Ccsd_Form();
        $form->setAttrib('class', 'form-horizontal');
        $form->setName('suggeststatus');
        $form->setMethod(Zend_Form::METHOD_POST);
        $form->setAction((new Episciences_View_Helper_Url())->url(['controller' => 'administratepaper', 'action' => 'suggeststatus', 'id' => $docId]));
        $csrfHashName = 'csrf_suggeststatus';
        $form->addElement('hash', $csrfHashName, array('salt' => 'unique'));
        $form->getElement($csrfHashName)->setTimeout(3600);

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
    public static function getVolumeForm($volumes, $default = null): \Ccsd_Form
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
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getAcceptanceForm($default): \Ccsd_Form
    {
        $formId = 'acceptance-form';
        $form = new Ccsd_Form([
            'class' => 'form-horizontal',
            'action' => (new Episciences_View_Helper_Url())->url(['controller' => 'administratepaper', 'action' => 'accept', 'id' => $default['id']]),
            'id' => $formId
        ]);

        $csrfName = 'csrf_accept_' . (int)$default['id'];
        $form->addElement('hash', $csrfName, ['salt' => 'unique']);
        $form->getElement($csrfName)->setTimeout(3600);

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
            'id' => $formId . '-to',
            'label' => 'À',
            'disabled' => true,
            'value' => $default['author']->getFullName() . ' <' . $default['author']->getEmail() . '>']);

        // cc
        $existingMails = '';
        if (!empty($default['coAuthor'])) {
            $existingMails = self::getCoAuthorsMails($default['coAuthor']);
        }
        $translator = Zend_Registry::get('Zend_Translate');
        $title = $translator->translate('Ajouter des destinataires');
        $form->addElement('text', 'cc', [
            'label' => '<a class="show_contacts_button" title="' . $title . '" href="/administratemail/getcontacts?target=cc">' . $translator->translate('Cc') . '</a>',
            'id' => $formId . '-cc',
            'value' => $existingMails,
            'class' => 'autocomplete'
        ]);

        // bcc
        $form->addElement('text', 'bcc', [
            'label' => '<a class="show_contacts_button" title="' . $title . '" href="/administratemail/getcontacts?target=bcc">' . $translator->translate('Bcc') . '</a>',
            'id' => $formId . '-bcc',
            'class' => 'autocomplete'
        ]);

        // from
        $form->addElement('text', 'from', [
            'id' => $formId . '-from',
            'label' => 'De',
            'disabled' => true,
            'placeholder' => RVCODE . '@' . DOMAIN,
            'value' => Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>']);

        // reply-to
        $form->addElement('text', 'reply-to', [
            'id' => $formId . '-reply-to',
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


        self::addHiddenDocIdElement($form, $formId, $default['id']);

        if (!empty($default['coAuthor'])) {
            self::getCoAuthorsForm($default['coAuthor'], $form);
        }

        return $form;

    }

    /**
     * @param $default
     * @return Ccsd_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getPublicationForm($default): \Ccsd_Form
    {
        $formId = 'publish-form';
        $form = new Ccsd_Form([
            'class' => 'form-horizontal',
            'action' => (new Episciences_View_Helper_Url())->url(['controller' => 'administratepaper', 'action' => 'publish', 'id' => $default['id']]),
            'id' => $formId
        ]);

        $csrfName = 'csrf_publish_' . (int)$default['id'];
        $form->addElement('hash', $csrfName, ['salt' => 'unique']);
        $form->getElement($csrfName)->setTimeout(3600);

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
            'id' => $formId . '-to',
            'label' => 'À',
            'disabled' => true,
            'value' => $default['author']->getFullName() . ' <' . $default['author']->getEmail() . '>']);

        // cc
        $existingMails = '';
        if (!empty($default['coAuthor'])) {
            $existingMails = self::getCoAuthorsMails($default['coAuthor']);
        }
        $translator = Zend_Registry::get('Zend_Translate');
        $title = $translator->translate('Ajouter des destinataires');
        $form->addElement('text', 'cc', [
            'label' => '<a class="show_contacts_button" title="' . $title . '" href="/administratemail/getcontacts?target=cc">' . $translator->translate('Cc') . '</a>',
            'id' => $formId . '-cc',
            'value' => $existingMails,
            'class' => 'autocomplete'
        ]);

        // bcc
        $form->addElement('text', 'bcc', [
            'label' => '<a class="show_contacts_button" title="' . $title . '" href="/administratemail/getcontacts?target=bcc">' . $translator->translate('Bcc') . '</a>',
            'id' => $formId . '-bcc',
            'class' => 'autocomplete'
        ]);

        // from
        $form->addElement('text', 'from', [
            'id' => $formId . '-from',
            'label' => 'De',
            'disabled' => true,
            'placeholder' => RVCODE . '@' . DOMAIN,
            'value' => Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>']);

        // reply-to
        $form->addElement('text', 'reply-to', [
            'id' => $formId . 'reply-to',
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


        self::addHiddenDocIdElement($form, $formId, $default['id']);

        if (!empty($default['coAuthor'])) {
            self::getCoAuthorsForm($default['coAuthor'], $form);
        }


        return $form;
    }

    /**
     * @param $default
     * @return Ccsd_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getRefusalForm($default): \Ccsd_Form
    {
        $formId = 'refusal-form';
        $form = new Ccsd_Form([
            'class' => 'form-horizontal',
            'action' => (new Episciences_View_Helper_Url())->url(['controller' => 'administratepaper', 'action' => 'refuse', 'id' => $default['id']]),
            'id' => $formId
        ]);

        $csrfName = 'csrf_refuse_' . (int)$default['id'];
        $form->addElement('hash', $csrfName, ['salt' => 'unique']);
        $form->getElement($csrfName)->setTimeout(3600);

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
            'id' => $formId . '-to',
            'label' => 'À',
            'disabled' => true,
            'value' => $default['author']->getFullName() . ' <' . $default['author']->getEmail() . '>']);

        // cc
        $existingMails = '';
        if (!empty($default['coAuthor'])) {
            $existingMails = self::getCoAuthorsMails($default['coAuthor']);
        }
        $translator = Zend_Registry::get('Zend_Translate');
        $title = $translator->translate('Ajouter des destinataires');
        $form->addElement('text', 'cc', [
            'label' => '<a class="show_contacts_button" title="' . $title . '" href="/administratemail/getcontacts?target=cc">' . $translator->translate('Cc') . '</a>',
            'id' => $formId . '-cc',
            'value' => $existingMails,
            'class' => 'autocomplete'
        ]);

        $bccVal = '';

        if (
            isset($default[Episciences_Review::SETTING_REFUSED_ARTICLE_AUTHORS_MESSAGE_AUTOMATICALLY_SENT_TO_REVIEWERS]) &&
            ((int)$default[Episciences_Review::SETTING_REFUSED_ARTICLE_AUTHORS_MESSAGE_AUTOMATICALLY_SENT_TO_REVIEWERS] === 1)
        ) {
            $bccVal = Episciences_Review::forYourInformation($default['id'], Episciences_Acl::ROLE_REVIEWER);
        }

        // bcc
        $form->addElement('text', 'bcc', [
            'label' => '<a class="show_contacts_button" title="' . $title . '" href="/administratemail/getcontacts?target=bcc">' . $translator->translate('Bcc') . '</a>',
            'id' => $formId . '-bcc',
            'value' => $bccVal,
            'class' => 'autocomplete'
        ]);

        // from
        $form->addElement('text', 'from', [
            'id' => $formId . '-from',
            'label' => 'De',
            'disabled' => true,
            'placeholder' => RVCODE . '@' . DOMAIN,
            'value' => Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>']);

        // reply-to
        $form->addElement('text', 'reply-to', [
            'id' => $formId . '-reply-to',
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


        self::addHiddenDocIdElement($form, $formId, $default['id']);

        if (!empty($default['coAuthor'])) {
            self::getCoAuthorsForm($default['coAuthor'], $form);
        }


        return $form;
    }

    /**
     * @param $default
     * @param $editors
     * @param $paper
     * @return Ccsd_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getAskOtherEditorsForm($default, $editors, $paper): \Ccsd_Form
    {
        $formId = 'ask-other-editors-form';
        $form = new Ccsd_Form([
            'id' => $formId,
            'class' => 'form-horizontal',
            'action' => (new Episciences_View_Helper_Url())->url(['controller' => 'administratepaper', 'action' => 'askothereditors', 'id' => $default['id']])
        ]);

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
        $translator = Zend_Registry::get('Zend_Translate');
        $title = $translator->translate('Ajouter des destinataires');
        $askeditors_subform->addElement('text', 'cc', [
            'label' => '<a class="show_contacts_button" title="' . $title . '" href="/administratemail/getcontacts?target=cc">' . $translator->translate('Cc') . '</a>',
            'id' => $formId . '-cc',
            'class' => 'autocomplete'
        ]);

        // bcc
        $askeditors_subform->addElement('text', 'bcc', [
            'label' => '<a class="show_contacts_button" title="' . $title . '" href="/administratemail/getcontacts?target=bcc">' . $translator->translate('Bcc') . '</a>',
            'id' => $formId . '-bcc',
            'class' => 'autocomplete'
        ]);

        // from
        $askeditors_subform->addElement('text', 'from', [
            'id' => $formId . '-from',
            'label' => 'De',
            'disabled' => true,
            'placeholder' => RVCODE . '@' . DOMAIN,
            'value' => Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>']);

        // reply-to
        $askeditors_subform->addElement('text', 'reply-to', [
            'id' => $formId . 'reply-to',
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

        self::addHiddenDocIdElement($form, $formId, $default['id']);

        return $form;
    }

    /**
     * @param $default
     * @param string $type
     * @param Episciences_Review|null $review
     * @param bool $withAutoReassignment
     * @param int|null $docId
     * @return Ccsd_Form
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getRevisionForm($default, string $type = 'minor', Episciences_Review $review = null, bool $withAutoReassignment = true, int $docId = null): \Ccsd_Form
    {
        $formId = $withAutoReassignment ? $type . '_revision-form' : 'accepted-ask-final-version-form';
        $isRequiredRevisionDeadline = false;

        $minDate = date('Y-m-d');
        $maxDate = Episciences_Tools::addDateInterval($minDate, Episciences_Review::DEFAULT_REVISION_DEADLINE_MAX);

        $isChecked = ($type === 'major') ? 1 : 0;

        if (null !== $review) { // git #123 : Ne jamais réassigner automatiquement les relecteurs, que ce soit pour des demandes de modif mineures ou majeures
            $automaticallyReassignSameReviewers = $review->getSetting(Episciences_Review::SETTING_AUTOMATICALLY_REASSIGN_SAME_REVIEWERS_WHEN_NEW_VERSION);
            $isRequiredRevisionDeadline = (bool)$review->getSetting(Episciences_Review::SETTING_TO_REQUIRE_REVISION_DEADLINE);
            if ($type === 'minor') {
                $isChecked = !empty($automaticallyReassignSameReviewers) && in_array(Episciences_Review::MINOR_REVISION_ASSIGN_REVIEWERS, $automaticallyReassignSameReviewers, true);
            } elseif ($type === 'major') {
                $isChecked = !empty($automaticallyReassignSameReviewers) && in_array(Episciences_Review::MAJOR_REVISION_ASSIGN_REVIEWERS, $automaticallyReassignSameReviewers, true);
            }
        }

        $form = new Ccsd_Form([
            'class' => 'form-horizontal',
            'action' => (new Episciences_View_Helper_Url())->url(['controller' => 'administratepaper', 'action' => 'revision', 'id' => $default['id'], 'type' => $type]),
            'id' => $formId
        ]);

        $csrfName = 'csrf_revision_' . $type . '_' . (int)$default['id'];
        $form->addElement('hash', $csrfName, ['salt' => 'unique']);
        $form->getElement($csrfName)->setTimeout(3600);

        $form->setDecorators([[
            'ViewScript', [
                'id' => $formId,
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
            'id' => $formId . '-to',
            'label' => 'À',
            'disabled' => true,
            'value' => $default['author']->getFullName() . ' <' . $default['author']->getEmail() . '>']);

        // cc
        $existingMails = '';
        if (!empty($default['coAuthor'])) {
            $existingMails = self::getCoAuthorsMails($default['coAuthor']);
        }
        $translator = Zend_Registry::get('Zend_Translate');
        $title = $translator->translate('Ajouter des destinataires');
        $form->addElement('text', 'cc', [
            'label' => '<a class="show_contacts_button" title="' . $title . '" href="/administratemail/getcontacts?target=cc">' . $translator->translate('Cc') . '</a>',
            'id' => $formId . '-cc',
            'value' => $existingMails,
            'class' => 'autocomplete'
        ]);

        // bcc
        $form->addElement('text', 'bcc', [
            'label' => '<a class="show_contacts_button" title="' . $title . '" href="/administratemail/getcontacts?target=bcc">' . $translator->translate('Bcc') . '</a>',
            'id' => $formId . '-bcc',
            'value' => Episciences_Review::forYourInformation($docId),
            'class' => 'autocomplete'
        ]);

        // from
        $form->addElement('text', 'from', [
            'id' => $formId . '-from',
            'label' => 'De',
            'placeholder' => RVCODE . '@' . DOMAIN,
            'disabled' => true,
            'value' => Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>']);

        // reply-to
        $form->addElement('text', 'reply-to', [
            'id' => $formId . '-reply-to',
            'label' => 'Répondre à',
            'placeholder' => RVCODE . '@' . DOMAIN,
            'disabled' => true,
            'value' => Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>']);

        // revision deadline (optional ?)
        $deadlineOptions = [
            'id' => $formId . '-revision-deadline',
            'label' => 'Date limite de réponse',
            'class' => 'form-control',
            'pattern' => '[A-Za-z]{3}',
            'placeholder' => !$isRequiredRevisionDeadline ? Zend_Registry::get('Zend_Translate')->translate('Optionnelle') : Zend_Registry::get('Zend_Translate')->translate('Veuillez préciser une date limite'),
            'attr-mindate' => $minDate,
            'attr-maxdate' => $maxDate
        ];

        if ($isRequiredRevisionDeadline) {
            $deadlineOptions['required'] = true;
        }


        $form->addElement('date', $type . '-revision-deadline', $deadlineOptions);

        $form->addElement('text', $type . '-revision-subject', [
            'id' => $formId . '-revision-subject',
            'label' => 'Sujet',
            'value' => $default['subject']]);

        $form->addElement('textarea', $type . '-revision-message', [
            'id' => $formId . '-revision-message',
            'label' => 'Message',
            'class' => 'full_mce',
            'value' => $default['body']]);

        if ($withAutoReassignment) {
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

        }

        self::addHiddenDocIdElement($form, $formId, $default['id']);

        if (!empty($default['coAuthor'])) {
            self::getCoAuthorsForm($default['coAuthor'], $form);
        }

        return $form;
    }

    /**
     *  assign users (reviewers or editors) to a paper
     * @param $ids
     * @param array $params
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     */
    public static function assign($ids, array $params): bool
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
    public static function unassign($ids, array $params): bool
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
     */
    public static function delete($docid): bool
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
        $db->delete(T_PAPER_LICENCES, ['docid = ?' => $docid]);
        $db->delete(T_VOLUME_PAPER_POSITION, ['PAPERID = ?' => $paper->getPaperid()]);

        // delete paper folder and content
        if (defined('RVCODE') && defined('REVIEW_FILES_PATH') && $docid) {
            Episciences_Tools::deleteDir(self::buildDocumentPath($docid));
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
     * @param int | null $rvId
     * @return bool|Episciences_Paper
     * @throws Zend_Db_Statement_Exception
     */
    public static function get($docId, bool $withxsl = true, int $rvId = null): Episciences_Paper|bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $select = $db->select()
            ->from(['papers' => T_PAPERS])
            ->where('DOCID = ?', $docId);


        if (defined('RVID') && !Ccsd_Tools::isFromCli()) {
            $rvId = RVID;
        }

        if ($rvId) {
            $select->where('RVID = ?', $rvId);
        }

        $data = $select->query()->fetch();

        if (!$data) {
            return false;
        }

        $paper = new Episciences_Paper(array_merge($data, ['withxsl' => $withxsl]));
        $paper->loadDataDescriptors();
        $paper->setRevisionDeadline();
        $paper->setConflicts(Episciences_Paper_ConflictsManager::findByPaperId($paper->getPaperid(), $rvId));
        return $paper;
    }

    /**
     * @param $aid
     * @param array $params
     * @return Ccsd_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getDeadlineForm($aid, $params = []): \Ccsd_Form
    {
        $form = new Ccsd_Form([
            'action' => (new Episciences_View_Helper_Url())->url(['controller' => 'administratepaper', 'action' => 'savenewdeadline', 'aid' => $aid]),
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
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getReviewerRemovalForm($aid, $docId, bool $isUninvited = false): \Ccsd_Form
    {
        $action = (new Episciences_View_Helper_Url())->url(['controller' => 'administratepaper', 'action' => 'savereviewerremoval', 'aid' => $aid, 'docid' => $docId]);

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
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getReassignmentForm($docid, $editors): \Ccsd_Form
    {
        $form = new Ccsd_Form([
            'action' => (new Episciences_View_Helper_Url())->url(['controller' => 'administratepaper', 'action' => 'savereassignment', 'docid' => $docid]),
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
     * @param array $options
     * @return array
     * @throws Zend_Date_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    public static function getStatusFormsTemplates(Episciences_Paper $paper, Episciences_User $contributor, $other_editors, array $options = []): array
    {
        $templates = [];

        $languages = Episciences_Tools::getLanguages();

        $contributorLocale = $contributor->getLangueid(true);

        // see gitlab #402
        $locale = (Episciences_Tools::getLocale() !== $contributorLocale) ? Episciences_Review::getDefaultLanguage() : $contributorLocale;

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
                $ratings_string .= ' ' . $reviewer->getAlias($paper->getDocid());
                $ratings_string .= '</p>';

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
            'majorRevision' => Episciences_Mail_TemplatesManager::TYPE_PAPER_MAJOR_REVISION_REQUEST,
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
        $template_keys['acceptedAskAuthorFinalVersion'] = Episciences_Mail_TemplatesManager::TYPE_PAPER_ACCEPTED_ASK_FINAL_AUTHORS_VERSION;
        // accepted - waiting for authors validation
        $template_keys['acceptedAskAuthorValidation'] = Episciences_Mail_TemplatesManager::TYPE_PAPER_FORMATTED_BY_JOURNAL_WAITING_AUTHOR_VALIDATION;

        foreach ($template_keys as $template_name => $template_key) {
            $oTemplate = new Episciences_Mail_Template();
            $oTemplate->setLocale($locale);
            $oTemplate->findByKey($template_key);
            $oTemplate->loadTranslations();

            $templates[$template_name] = [
                'id' => $paper->getDocid(),
                'subject' => $oTemplate->getSubject(),
                'body' => $oTemplate->getBody(),
                'author' => $contributor,
                'coAuthor' => $paper->getCoAuthors()
            ];

            if (
                $template_name === 'refuse' &&
                isset($options[Episciences_Review::SETTING_REFUSED_ARTICLE_AUTHORS_MESSAGE_AUTOMATICALLY_SENT_TO_REVIEWERS])
            ) {
                $templates[$template_name][Episciences_Review::SETTING_REFUSED_ARTICLE_AUTHORS_MESSAGE_AUTOMATICALLY_SENT_TO_REVIEWERS] = $options[Episciences_Review::SETTING_REFUSED_ARTICLE_AUTHORS_MESSAGE_AUTOMATICALLY_SENT_TO_REVIEWERS];
            }
        }

        $lostLoginLink = self::buildLostLoginUrl();

        $lostLoginTags = [
            Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME_LOST_LOGIN =>  $lostLoginLink,
            Episciences_Mail_Tags::TAG_OBSOLETE_RECIPIENT_USERNAME_LOST_LOGIN => $lostLoginLink // present in custom templates
        ];

        $defaultTags = [
            Episciences_Mail_Tags::TAG_RECIPIENT_SCREEN_NAME => $contributor->getScreenName(),
            Episciences_Mail_Tags::TAG_RECIPIENT_USERNAME => $contributor->getUsername(),
            Episciences_Mail_Tags::TAG_RECIPIENT_FULL_NAME => $contributor->getFullName()
        ];

        $tags = array_merge($mail->getTags(), [
            Episciences_Mail_Tags::TAG_ARTICLE_ID => $paper->getDocid(),
            Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $paper->getPaperid(),
            Episciences_Mail_Tags::TAG_ARTICLE_TITLE => $paper->getTitle($locale, true),
            Episciences_Mail_Tags::TAG_AUTHORS_NAMES => $paper->formatAuthorsMetadata($locale),
            Episciences_Mail_Tags::TAG_SUBMISSION_DATE => Episciences_View_Helper_Date::Date($paper->getSubmission_date(), $locale),
            Episciences_Mail_Tags::TAG_PAPER_URL => self::buildPublicPaperUrl($paper->getDocid()),
            Episciences_Mail_Tags::TAG_PAPER_RATINGS => $ratings_string,
            Episciences_Mail_Tags::TAG_PAPER_REPO_URL => $paper->getDocUrl()
        ]);


        foreach ($templates as $name => &$template) {

            if ($name === 'waitingAuthorFormatting') {

                $site = self::buildBaseUrl();
                $ceRessourcesUrl = sprintf('%s%spublic/', $site, PREFIX_URL);
                $ceRessourcesUrl .= sprintf('%s_episciences.zip',RVCODE);

                $paperSubmissionDate = date('Y-m-d', strtotime($paper->getSubmission_date())); // Current version
                $doi = $paper->getDoi();

                $volumeId = $paper->getVid();
                $sectionId = $paper->getSid();
                $volume = null;
                $section = null;
                $sectionName = '';
                $volumeName = '';
                $volBiblioRef = '';

                if ($volumeId) {
                    $volume = Episciences_VolumesManager::find($volumeId);
                    if ($volume) {
                        $volBiblioRef = !$volume->getBib_reference() ? $translator->translate('Aucune', $locale) : $volume->getBib_reference();
                        $volumeName = $volume->getName($locale);
                    }
                } else {
                    $volumeName = $translator->translate('Hors volume', $locale);
                    $volBiblioRef = $translator->translate('Aucune', $locale);
                }



                if ($sectionId) {
                    $section = Episciences_SectionsManager::find($sectionId);
                    if ($section) {
                        $sectionName = $section->getNameKey($locale);
                    }
                } else {
                    $sectionName = $translator->translate('Hors rubrique', $locale);
                }

                $lastRevisionDateIso = date('Y-m-d', strtotime($paper->getWhen())); // latest version
                $revisionsDate = $paper->buildRevisionDates($locale); // all versions
                $revisionsDateIso = $paper->buildRevisionDates(); // all versions in ISO format
                $paperPosition = $paper->getPaperPositionInVolume(); // position of paper in volume
                $acceptanceDate = $paper->getAcceptanceDate();

                $addTags = array_merge($defaultTags, [
                    Episciences_Mail_Tags::TAG_PAPER_SUBMISSION_DATE => Episciences_View_Helper_Date::Date($paperSubmissionDate, $locale),
                    Episciences_Mail_Tags::TAG_PAPER_SUBMISSION_DATE_ISO => $paperSubmissionDate,
                    Episciences_Mail_Tags::TAG_LAST_REVISION_DATE_ISO => $lastRevisionDateIso,
                    Episciences_Mail_Tags::TAG_LAST_REVISION_DATE => Episciences_View_Helper_Date::Date($lastRevisionDateIso, $locale),
                    Episciences_Mail_Tags::TAG_REVISION_DATES => !empty($revisionsDate) ? $revisionsDate : $translator->translate('Aucune', $locale),
                    Episciences_Mail_Tags::TAG_REVISION_DATES_ISO => !empty($revisionsDate) ? $revisionsDateIso : $translator->translate('Aucune', $locale),
                    Episciences_Mail_Tags::TAG_ACCEPTANCE_DATE => $acceptanceDate ? Episciences_View_Helper_Date::Date($acceptanceDate, $locale) : $translator->translate('Aucune', $locale),
                    Episciences_Mail_Tags::TAG_ACCEPTANCE_DATE_ISO => $acceptanceDate ? date('Y-m-d', strtotime($acceptanceDate)) : $translator->translate('Aucune', $locale),
                    Episciences_Mail_Tags::TAG_PERMANENT_ARTICLE_ID => $paper->getPaperid(),
                    Episciences_Mail_Tags::TAG_DOI => $doi ?: $translator->translate('Aucun', $locale),
                    Episciences_Mail_Tags::TAG_VOLUME_ID => $volumeId,
                    Episciences_Mail_Tags::TAG_VOLUME_NAME => $volumeName,
                    Episciences_Mail_Tags::TAG_VOL_BIBLIOG_REF => $volBiblioRef,
                    Episciences_Mail_Tags::TAG_SECTION_ID => $paper->getSid(),
                    Episciences_Mail_Tags::TAG_SECTION_NAME => $sectionName,
                    Episciences_Mail_Tags::TAG_PAPER_POSITION_IN_VOLUME => !empty($paperPosition) ? $paperPosition : $translator->translate('Aucun', $locale),
                    Episciences_Mail_Tags::TAG_CURRENT_YEAR => date('Y'),
                    Episciences_Mail_Tags::TAG_REVIEW_CE_RESOURCES_URL =>$ceRessourcesUrl,
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

            $tags = [...$tags, ...$addTags, ...$lostLoginTags];


            if ($template['subject']){
                $template['subject'] = str_replace(array_keys($tags), array_values($tags), $template['subject']);
                $template['subject'] = Ccsd_Tools::clear_nl($template['subject']);
            }

            if($template['body']) {
                $template['body'] = str_replace(array_keys($tags), array_values($tags), $template['body']);
                $template['body'] = nl2br($template['body']);
                $template['body'] = Ccsd_Tools::clear_nl($template['body']);
            }

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
    public static function updateUid(int $oldUid = 0, int $newUid = 0): int
    {

        if ($oldUid === 0 || $newUid === 0) {
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
     * @param Episciences_Paper $paper
     * @return int
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Exception
     */

    public static function updateRecordData(Episciences_Paper $paper): int
    {
        $docId = $paper->getDocId();

        if (!$docId) {
            return 0;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $params = self::getPaperParams($docId);

        if(!$params){
            return 0;
        }

        $affectedRows = 0;

        $context = self::initializeContext($params);
        $recordData = self::fetchRecordData($context);
        [$record, $enrichment] = [$recordData['record'], $recordData['enrichment']];

        $record = self::cleanRecord($record, $context['repoId']);
        [$record, $enrichment, $affectedRows] = self::processFilesHook($record, $context, $enrichment, $affectedRows);

        $affectedRows += self::processLinkedDataOrDatasets($paper, $context, $affectedRows);
        $affectedRows += Episciences_Submit::enrichmentProcess($paper, $enrichment);
        Episciences_Paper_AuthorsManager::verifyExistOrInsert($context['docId'], $context['paperId']);

        $affectedRows = self::processLicence($context, $affectedRows);
        $affectedRows = self::processHalOpenAireData($context, $affectedRows);

        $affectedRows += $db?->update(T_PAPERS, ['RECORD' => $record], ['DOCID = ?' => $context['docId']]);
        return $affectedRows;
    }

    private static function initializeContext(array $params): array
    {
        return [
            'docId' => $params['DOCID'],
            'identifier' => str_replace('-REFUSED', '', $params['IDENTIFIER']),
            'repoId' => (int)$params['REPOID'],
            'version' => (float)$params['VERSION'],
            'paperId' => (int)$params['PAPERID'],
            'doi' => trim($params['DOI'] ?? ''),
            'status' => (int)$params['STATUS'],
        ];
    }

    /**
     * @param array $context
     * @return array
     * @throws Exception
     */

    private static function fetchRecordData(array $context): array
    {
        $repoIdentifier = Episciences_Repositories::getIdentifier(
            $context['repoId'], $context['identifier'], $context['version']
        );

        $response = Episciences_Repositories::callHook('hookApiRecords', [
            'identifier' => $context['identifier'],
            'repoId' => $context['repoId'],
            'version' => $context['version']
        ]);

        if (!empty($response['record'])) {
            return ['record' => $response['record'], 'enrichment' => $response['enrichment'] ?? []];
        }

        $baseUrl = Episciences_Repositories::getBaseUrl($context['repoId']);
        if ($baseUrl) {
            $oai = new Ccsd_Oai_Client($baseUrl, 'xml');
            $record = $oai->getRecord($repoIdentifier);
            $type = Episciences_Tools::xpath($record, '//dc:type');

            $enrichment = [];
            if (!empty($type)) {
                $enrichment[Episciences_Repositories_Common::RESOURCE_TYPE_ENRICHMENT] = $type;
            }

            return ['record' => $record, 'enrichment' => $enrichment];
        }

        return ['record' => '', 'enrichment' => []];
    }

    /**
     * @param string $record
     * @param int $repoId
     * @return string
     */

    private static function cleanRecord(string $record, int $repoId): string
    {
        if ($record === '') {
            return $record;
        }

        $record = preg_replace('#xmlns="(.*)"#', '', $record);

        if ($repoId === (int)Episciences_Repositories::CWI_REPO_ID) {
            $record = Episciences_Repositories_Common::checkAndCleanRecord($record);
        }

        $result = Episciences_Repositories::callHook('hookCleanXMLRecordInput', [
            'record' => $record,
            'repoId' => $repoId
        ]);

        return $result['record'] ?? $record;
    }

    /**
     * @param string $record
     * @param array $context
     * @param array $enrichment
     * @param int $affectedRows
     * @return array
     */

    private static function processFilesHook(
        string $record, array $context, array $enrichment, int $affectedRows
    ): array {
        return self::updateRecordDataProcessFilesHook(
            $record, $context['docId'], $context['repoId'], $context['identifier'], $enrichment, $affectedRows
        );
    }

    /**
     * @param Episciences_Paper $paper
     * @param array $context
     * @param int $affectedRows
     * @return int
     */

    private static function processLinkedDataOrDatasets(
        Episciences_Paper $paper, array $context, int $affectedRows
    ): int {
        if (Episciences_Repositories::hasHook($context['repoId'])) {
            $hookData = Episciences_Repositories::callHook('hookLinkedDataProcessing', [
                'repoId' => $context['repoId'],
                'identifier' => $context['identifier'],
                'docId' => $context['docId']
            ]);
            return $affectedRows + ($hookData['affectedRows'] ?? 0);
        }

        return $affectedRows + Episciences_Submit::datasetsProcessing($paper);
    }

    /**
     * @param array $context
     * @param int $affectedRows
     * @return int
     */

    private static function processLicence(array $context, int $affectedRows): int
    {
        return self::updateRecordDataProcessLicence(
            $context['repoId'], $context['identifier'], $context['version'], $context['docId'], $affectedRows
        );
    }

    /**
     * @param array $context
     * @param int $affectedRows
     * @return int
     */

    private static function processHalOpenAireData(array $context, int $affectedRows): int
    {
        $strRepoId = (string)$context['repoId'];

        if ($strRepoId === Episciences_Repositories::HAL_REPO_ID) {
            try {
                $affectedRows = self::updateRecordDataHal(
                    $context['paperId'], $context['identifier'], $context['version'], $affectedRows
                );
            } catch (JsonException | \Psr\Cache\InvalidArgumentException $e) {
                trigger_error($e->getMessage(), E_USER_WARNING);
            }
        }

        $isEligibleRepo = in_array($strRepoId, [
            Episciences_Repositories::ARXIV_REPO_ID,
            Episciences_Repositories::ZENODO_REPO_ID,
            Episciences_Repositories::HAL_REPO_ID,
            Episciences_Repositories::BIO_RXIV_ID,
            Episciences_Repositories::MED_RXIV_ID,
        ], true);

        if (!empty($context['doi']) &&
            $context['status'] === Episciences_Paper::STATUS_PUBLISHED &&
            $isEligibleRepo
        ) {
            try {
                $affectedRows = self::updateRecordDataCallOpenAireTools(
                    $context['doi'], $context['paperId'], $affectedRows
                );
            } catch (JsonException | \Psr\Cache\InvalidArgumentException $e) {
                trigger_error($e->getMessage(), E_USER_WARNING);
            }
        }

        return $affectedRows;
    }

    /**
     * @param int $docId
     * @return mixed
     */
    private static function getPaperParams(int $docId): mixed
    {
        if (!$docId) {
            return null;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_PAPERS, ['DOCID','IDENTIFIER', 'REPOID', 'VERSION', 'PAPERID', 'STATUS', 'DOI'])
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
     * Return latest article version
     * @param $paperId
     * @param bool $withTmpVersions
     * @return Episciences_Paper | null
     * @throws Zend_Db_Statement_Exception
     */
    public static function getLastPaper($paperId, bool $withTmpVersions = false): ?Episciences_Paper
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_PAPERS)
            ->where('PAPERID = ?', $paperId);

        if (!$withTmpVersions) {
            $select
                ->where('REPOID != ?', 0);
        }

        $select
            ->order('WHEN DESC');
        $result = $select->query()->fetch();

        if (!$result) {
            return null;
        }

        return new Episciences_Paper($result);
    }

    /**
     * @param array $default
     * @return Zend_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getReviewFormattingDeposedForm(array $default): \Zend_Form
    {
        $form = self::getModalPaperStatusCommonForm($default, 'reviewFormattingDeposed');
        $form->setAttrib('id', 'review-formatting-deposed-form');
        $form->setAction((new Episciences_View_Helper_Url())->url(['controller' => 'administratepaper', 'action' => 'reviewformattingdeposed', 'id' => $default['id']]));
        return $form;
    }

    /**
     * @param array $default
     * @param string $prefix
     * @param bool $displayDeadlineElement
     * @return Zend_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    private static function getModalPaperStatusCommonForm(array $default, string $prefix, bool $displayDeadlineElement = false): \Zend_Form
    {
        $form = new Ccsd_Form(['class' => 'form-horizontal']);
        $subjectStr = 'Subject';
        $messageStr = 'Message';

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
            'id' => $prefix . '-to',
            'label' => 'À',
            'disabled' => true,
            'value' => $default['author']->getFullName() . ' <' . $default['author']->getEmail() . '>']);

        // cc
        $existingMails = '';
        if (!empty($default['coAuthor'])) {
            $existingMails = self::getCoAuthorsMails($default['coAuthor']);
        }
        $form->addElement('text', 'cc', ['label' => 'CC', 'id' => $prefix . '-cc', 'value' => $existingMails]);

        // bcc
        $form->addElement('text', 'bcc', ['label' => 'BCC', 'id' => $prefix . '-bcc']);

        // from
        $form->addElement('text', 'from', [
            'id' => $prefix . '-from',
            'label' => 'De',
            'disabled' => true,
            'placeholder' => RVCODE . '@' . DOMAIN,
            'value' => Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>']);

        // reply-to
        $form->addElement('text', 'reply-to', [
            'id' => $prefix . '-reply-to',
            'label' => 'Répondre à',
            'disabled' => true,
            'placeholder' => RVCODE . '@' . DOMAIN,
            'value' => Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>']);

        if ($displayDeadlineElement) {
            $subjectStr = '-revision-subject'; // see /public/js/administratepaper/view.js
            $messageStr = '-revision-message';
            $minDate = date('Y-m-d');
            $maxDate = Episciences_Tools::addDateInterval($minDate, Episciences_Review::DEFAULT_REVISION_DEADLINE_MAX);

            $form->addElement('date', $prefix . '-revision-deadline', [
                'id' => $prefix . '-revision-deadline',
                'label' => 'Date limite de réponse',
                'class' => 'form-control',
                'pattern' => '[A-Za-z]{3}',
                'placeholder' => Zend_Registry::get('Zend_Translate')->translate('Optionnelle'),
                'attr-mindate' => $minDate,
                'attr-maxdate' => $maxDate
            ]);

        }

        $form->addElement(new Ccsd_Form_Element_Text([
            'name' => $prefix . $subjectStr,
            'id' => $prefix . $subjectStr,
            'label' => 'Sujet',
            'value' => $default['subject']
        ]));

        $form->addElement(new Ccsd_Form_Element_Textarea([
            'name' => $prefix . $messageStr,
            'id' => $prefix . $messageStr,
            'class' => 'full_mce',
            'label' => 'Message',
            'value' => $default['body']
        ]));

        return self::addHiddenDocIdElement($form, $prefix, $default['id']);
    }

    /**
     * @param array $default
     * @return Zend_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getCeAcceptFinalVersionForm(array $default): \Zend_Form
    {
        $form = self::getModalPaperStatusCommonForm($default, 'ceAcceptFinalVersionRequest');
        $form->setAttrib('id', 'ready-to-publish-form');
        $form->setAction((new Episciences_View_Helper_Url())->url(['controller' => 'administratepaper', 'action' => 'copyeditingacceptfinalversion', 'id' => $default['id']]));
        return $form;
    }

    /**
     * retourne le formulaire de demande des sources auteur
     * @param array $default
     * @return Zend_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getWaitingForAuthorSourcesForm(array $default): \Zend_Form
    {

        $form = self::getModalPaperStatusCommonForm($default, 'authorSourcesRequest');
        $form->setAttrib('id', 'waiting-for-author-sources-form');
        $form->setAction((new Episciences_View_Helper_Url())->url(['controller' => 'administratepaper', 'action' => 'waitingforauthorsources', 'id' => $default['id']]));

        return $form;
    }

    /**
     * retourne le formulaire de demande de la mise en forme de l'auteur (version finale)
     * @param array $default
     * @return Zend_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getWaitingForAuthorFormatting(array $default): \Zend_Form
    {

        $form = self::getModalPaperStatusCommonForm($default, 'authorFormattingRequest');
        $form->setAttrib('id', 'waiting-for-author-formatting-form');
        $form->setAction((new Episciences_View_Helper_Url())->url(['controller' => 'administratepaper', 'action' => 'waitingforauthorformatting', 'id' => $default['id']]));
        return $form;
    }

    /**
     * @param array $default
     * @return Zend_Form
     * @throws Zend_Form_Exception
     * @throws Zend_Exception
     */
    public static function getAcceptedAskAuthorFinalVersionForm(array $default): \Zend_Form
    {
        $type = 'acceptedAskAuthorsFinalVersion';
        $formId = $type . '-form';
        $formAction = (new Episciences_View_Helper_Url())->url(['controller' => 'administratepaper' , 'action' => 'acceptedaskauhorfinalversion', 'id' => $default['id'], 'type' => $type ]);
        $form = self::getModalPaperStatusCommonForm($default, $type, true);
        $form->setAttrib('id', $formId);
        $form->setAction($formAction);
        return $form;
    }

    /**
     * @param array $default
     * @return Zend_Form
     * @throws Zend_Form_Exception
     * @throws Zend_Exception
     */
    public static function getAcceptedAskAuthorValidationForm(array $default): \Zend_Form
    {
        $formId = 'accepted-ask-author-validation-form';
        $formAction = (new Episciences_View_Helper_Url())->url(['controller' => 'administratepaper', 'action' => 'acceptedaskauthorvalidation', 'id' => $default['id']]);
        $form = self::getModalPaperStatusCommonForm($default, 'acceptedAskAuthorValidation');
        $form->setAttrib('id', $formId);
        $form->setAction($formAction);
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
     * @return false|int
     * @throws Zend_Db_Statement_Exception
     */
    public static function getPublishedPapersCount()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_PAPERS, [new Zend_Db_Expr("COUNT('DOCID') AS NbPublished")])
            ->where(T_PAPERS . '.STATUS = ' . Episciences_Paper::STATUS_PUBLISHED);

        $result = $select->query()->fetch();

        if (!$result) {
            return false;
        }

        return (int)$result['NbPublished'];
    }

    /**
     * @param array $coAuthors
     * @param Ccsd_Form $form
     * @return void
     * @throws Zend_Form_Exception
     */
    public static function getCoAuthorsForm(array $coAuthors, Ccsd_Form $form): void
    {
// get a copy
        $strMail = self::getCoAuthorsMails($coAuthors);
        $strMail = substr($strMail, 0, -1);
        $form->addElement('hidden', 'co-author-mail', ['value' => $strMail]);
        $form->addElement('checkbox', 'copy-co-author', array(
            'label' => "Envoyer une copie de ce message aux co-auteur",
            'decorators' => [
                'ViewHelper',
                ['Label', array('placement' => 'APPEND')],
                ['HtmlTag', array('tag' => 'div', 'class' => 'col-md-9 col-md-offset-3')]
            ],
            'value' => '1'
        ));
    }

    /**
     * @param array $coAuthors
     * @return string
     */
    public static function getCoAuthorsMails(array $coAuthors): string
    {
        $strMail = '';
        foreach ($coAuthors as $coAuthor) {
            /** @var Episciences_User $coAuthor */
            $strMail .= "<" . $coAuthor->getEmail() . '>';
            $strMail .= ";";
        }
        return $strMail;
    }

    /**
     * @param $a
     * @param $b
     * @return int
     */
    private static function statusCmp($a, $b): int
    {
        $a = Episciences_Paper::$_statusOrder[$a];
        $b = Episciences_Paper::$_statusOrder[$b];

        if ($a === $b) {
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
    public static function getEarliestPublicationDate(): string
    {
        define('EPD', 'earliestPublicationDate');

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
     * @deprecated
     */
    private static function fromSequentialArrayToAssoc(array $array): array
    {

        $list = [];
        $currentDocId = null;
        $allConflicts = [];

        foreach ($array as $arrayVals) {

            if ($currentDocId !== $arrayVals['DOCID']) {
                $currentDocId = $arrayVals['DOCID'];
                $allConflicts = []; // Collect all conflicts by docId
            }

            $currentOtherVals = [];
            $currentConflictVals = [];
            foreach ($arrayVals as $key => $val) {

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


    public static function getAllStatus(int $byRvId = null, string $order = null, array $without = Episciences_Paper::OTHER_STATUS_CODE): array
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $statusQuery = $db
            ->select()
            ->distinct()
            ->from(T_PAPERS, ['STATUS'])
            ->where('STATUS NOT IN (?)', $without);

        if ($byRvId) {
            $statusQuery->where('RVID = ? ', $byRvId);
        }

        if ($order) {
            $statusQuery->order('STATUS', $order);
        }

        return $db->fetchCol($statusQuery);

    }

    /**
     *
     * @param $docId
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     */
    public static function getApprovedForm($docId): \Ccsd_Form
    {
        $action = 'approvedwaitingforfinalpublication';
        $id = 'approved';
        $form = new Ccsd_Form();
        $form->setAttrib('class', 'form-horizontal');
        $form->setName($id);
        $form->setAction((new Episciences_View_Helper_Url())->url(['controller' => 'administratepaper', 'action' => $action, 'id' => $docId]));


        $form->addElement('submit', 'submit', [
            'label' => 'Envoyer',
            'class' => 'btn btn-primary',
            'decorators' => [['HtmlTag', ['tag' => 'div', 'openOnly' => true, 'class' => 'form-actions text-center']], 'ViewHelper']]);

        $form->addElement('button', 'cancel', [
            'label' => 'Annuler',
            'class' => 'btn btn-default',
            'data-dismiss' => 'modal',
            'onclick' => "cancel()",
            'decorators' => ['ViewHelper', ['HtmlTag', ['tag' => 'div', 'closeOnly' => true]]]]);

        return $form;
    }


    /**
     * @param array $option
     * @return Ccsd_Form
     * @throws Zend_Form_Exception
     */
    public static function getAffiliationsForm(array $option = []): \Ccsd_Form
    {

        $form = new Ccsd_Form;

        $form->setAttrib('id', 'form-affi-authors');
        $form->setAction((new Episciences_View_Helper_Url())->url(['controller' => 'paper', 'action' => 'addaffiliationsauthor']));
        $affiliationInfo = [
            'id' => 'affiliations',
            'label' => 'Affiliation(s)',
            'description' => Ccsd_Tools::translate('Affiliation en texte libre ou issue du ') . "<a target='_blank' rel='noopener' href='https://ror.org/'>ROR</a>",
            'display' => 'advanced',
        ];
        $affiliationInfo['value'] = [];
        if (isset($option['affiliations'])) {
            foreach ($option['affiliations'] as $value) {
                $affiliationInfo['value'][] = $value;
            }
        }
        $form->addElement('multiTextSimple', 'affiliations', $affiliationInfo);
        if (isset($option['acronymList'])) {
            $form->addElement('hidden', 'affiliationAcronym', ['value' => $option['acronymList']]);
        } else {
            $form->addElement('hidden', 'affiliationAcronym');
        }
        // Button : validate
        $form->addElement('submit', 'submit-affiliation-author', [
            'label' => 'Valider',
            'class' => 'btn btn-primary',
            'decorators' => ['ViewHelper']
        ]);

        // index of the author in the json author from db -> they're sorted same way
        if (isset($option['idAuthor'])) {
            $form->addElement('hidden', 'id-author-in-json', ['id' => 'id-edited-affi-author', 'name' => 'id-edited-affi-author', 'value' => $option['idAuthor']]);
        } else {
            $form->addElement('hidden', 'id-author-in-json', ['id' => 'id-edited-affi-author', 'name' => 'id-edited-affi-author']);
        }


        $form->addElement('hidden', 'paperid', ['id' => 'paper-id-authors', 'name' => 'paper-id-authors', 'value' => $option['paperid']]);


        foreach ($form->getElements() as $element) {

            if ($element->getDecorator('HtmlTag')) {
                $element->getDecorator('HtmlTag')->setOption("class", "col-md-12");
                break; // found
            }

        }

        return $form;

    }

    /**
     * fetch a paper
     * @param $identifier
     * @return Episciences_Paper|null
     * @throws Zend_Db_Statement_Exception
     */
    public static function findByIdentifier($identifier): ?Episciences_Paper
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $select = $db->select()
            ->distinct()
            ->from(['papers' => T_PAPERS])
            ->where('identifier = ?', $identifier)
            ->order('DOCID DESC')
            ->order('WHEN DESC');

        $data = $select->query()->fetch();

        return !$data ? null : new Episciences_Paper($data);

    }

    public static function getDocIdsInConflitByUid($uid): array
    {

        $docIds = [];

        $oConflicts = Episciences_Paper_ConflictsManager::findByUidAndAnswer($uid, Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes']);

        foreach ($oConflicts as $oConflict) {

            $pId = $oConflict->getPaperId();

            try {
                $oPaper = self::get($pId, false);

                $pVersionIds = $oPaper->getVersionsIds();

                foreach ($pVersionIds as $id) {
                    $docIds[] = $id;
                }

            } catch (Zend_Db_Statement_Exception $e) {
                trigger_error($e->getMessage());
            }

        }

        return $docIds;

    }

    /**
     * @param Episciences_Paper $paper
     * @return array
     */
    public static function getAuthorsData(Episciences_Paper $paper): array
    {
        $enrichedAuthor = Episciences_Paper_AuthorsManager::getArrayAuthorsAffi($paper->getPaperid());
        $language = $paper->getMetadata('language') ?? 'en';
        $abstract = $paper->getAbstract($language, true);
        $googleScholarData = [];
        if (!empty($enrichedAuthor)) {
            foreach ($enrichedAuthor as $author) {
                $googleScholarData[]['citation_author'] = $author['fullname'];
                if (array_key_exists('orcid', $author)) {
                    $googleScholarData[array_key_last($googleScholarData)]['citation_author_orcid'] = $author['orcid'];
                }
                if (array_key_exists('affiliation', $author)) {
                    foreach ($author['affiliation'] as $affiliation) {
                        $googleScholarData[array_key_last($googleScholarData)]['citation_author_institution'][] = $affiliation['name'];
                    }
                }
            }
        } else {
            $authors = $paper->getMetadata('authors');
            if ($authors) {
                if (is_array($authors)) {
                    foreach ($authors as $author) {
                        $googleScholarData[]['citation_author'] = $author;
                    }
                } else {
                    $googleScholarData[]['citation_author'] = $authors;
                }
            }
        }

        return $googleScholarData;
    }

    public static function headMetaData(Episciences_Paper $paper): array
    {
        $language = $paper->getMetadata('language') ?? 'en';
        $title = $paper->getTitle($language, true);
        $id = $paper->getDocid();
        $url = APPLICATION_URL . '/' . $id;
        $pdf = $url . '/pdf';
        $abstract = $paper->getAbstract($language, true);
        $allTitles = $paper->getAllTitles();
        $listLang = [];
        foreach ($allTitles as $lang => $title) {
            if (Zend_Locale::isLocale($lang)) {
                if ($lang === $language) {
                    (!empty($listLang)) ? array_unshift($listLang, Episciences_Tools::translateToICU($lang)) : $listLang[] = Episciences_Tools::translateToICU($lang);
                } else {
                    $listLang[] = Episciences_Tools::translateToICU($lang);
                }
            }
        }
        $keywords = $paper->getMetadata('subjects');
        $allKeywords = [];
        $journal = RVNAME;
        $doi = $paper->getDoi();
        if (is_array($keywords) && !empty($keywords)) {
            foreach ($keywords as $word) {
                if (is_array($word)) {
                    foreach ($word as $wordLang => $itemWord) {
                        $allKeywords[] = $itemWord;
                    }
                } else {
                    $allKeywords[] = $word;
                }

            }
        } elseif ($keywords) {
            $allKeywords[] = $keywords;
        }

        $volume = '';

        if ($paper->getVid()) {
            $key = 'volume_' . $paper->getVid() . '_title';
            $volume = Episciences_VolumesManager::translateVolumeKey($key, $language);
        }

        $section = "";
        if ($paper->getSid()) {
            /* @var $oSection Episciences_Section */
            $oSection = Episciences_SectionsManager::find($paper->getSid());
            if ($oSection) {
                $section = $oSection->getName('en', true);
            }
        }
        $journalSettings = Zend_Registry::get('reviewSettings');
        $eissn = "";
        $issn = "";
        if (isset($journalSettings[Episciences_Review::SETTING_ISSN]) && $journalSettings[Episciences_Review::SETTING_ISSN] !== '') {
            $eissn = $journalSettings[Episciences_Review::SETTING_ISSN];
        }
        if (isset($journalSettings[Episciences_Review::SETTING_ISSN_PRINT]) && $journalSettings[Episciences_Review::SETTING_ISSN_PRINT] !== '') {
            $issn = $journalSettings[Episciences_Review::SETTING_ISSN_PRINT];
        }
        $arxivId = '';
        if ($paper->getRepoid() === (int)Episciences_Repositories::ARXIV_REPO_ID) {
            $arxivId = $paper->getIdentifier();
        }
        $authors = self::getAuthorsData($paper);

        $contributor = new Episciences_User();
        $contributor->findWithCAS($paper->getUid());
        return [
            'dc' => [
                'creator' => $authors,
                'language' => $language,
                'title' => $title,
                'type' => 'journal',
                'identifier' => ['id' => $id, 'url' => $url, 'pdf' => $pdf, 'doi' => $doi],
                'abstract' => $abstract,
                'keywords' => $allKeywords,
                'date' => $paper->getPublication_date(),
                'relation' => $journal,
                'volume' => $volume,
                'publisher' => 'Episciences.org'
            ],
            'og' => [
                'title' => $title,
                'type' => "article",
                'article' => [
                    "published_time" => $paper->getPublication_date(),
                    "modified_time" => $paper->getModification_date(),
                    "author" => $authors,
                    "tag" => $allKeywords
                ],
                'locale' => $listLang,
                'url' => $url,
                'image' => APPLICATION_URL . '/img/episciences_logo_1081x1081.jpg',
                'description' => $abstract,
                'site_name' => "Episciences"

            ],
            'header' => [
                'description' => $abstract,
                'keywords' => $allKeywords,
            ],
            'citation' => [
                'journal_title' => $journal,
                'author' => $authors,
                'title' => $title,
                'publication_date' => $paper->getPublication_date(),
                'volume' => $volume,
                'issue' => $section,
                'doi' => $doi,
                'fulltext_world_readable' => "",
                'pdf_url' => $pdf,
                'issn' => ["eissn" => $eissn, 'issn' => $issn],
                'arxiv_id' => $arxivId,
                'language' => $language,
                'article_type' => "Research Article",
                'keywords' => $allKeywords,
                'fundings' => Episciences_Paper_ProjectsManager::getProjectWithDuplicateRemoved($paper->getPaperid()),
//                'abstract' => $abstract,

            ],
            "socialMedia" =>
                [
                    "twitter" =>
                        [
                            "card" => "summary_large_image",
                            "site" => "@episciences",
                            "creator" => [
                                $contributor->getSocialMedias()
                            ],
                            "title" => $title,
                            "description" => $abstract,
                            "image" => APPLICATION_URL . '/img/episciences_logo_1081x1081.jpg',
                            "image:alt" => 'Episciences Logo'
                        ]
                ]
        ];
    }

    public static function getOnlyActivatedRepositoriesLabels(int $byRvId = RVID): array
    {

        $without = [Episciences_Paper::STATUS_DELETED, Episciences_Paper::STATUS_REMOVED];

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $result = [];

        $statusQuery = $db
            ->select()
            ->distinct()
            ->where('STATUS NOT IN (?)', $without)
            ->from(T_PAPERS, ['REPOID']);

        if ($byRvId) {
            $statusQuery->where('RVID = ? ', $byRvId);
        }


        foreach ($db->fetchCol($statusQuery) as $repoId) {

            $label = Episciences_Repositories::getLabel($repoId);

            $result['repo-' . $repoId] = $label;
        }

        natcasesort($result);

        return $result;

    }

    /**
     * @param Zend_Db_Select $select
     * @param array $values
     * @return Zend_Db_Select
     */
    private static function applyRepositoriesFilter(Zend_Db_Select $select, array $values = []): Zend_Db_Select
    {

        $repoIds = [];

        foreach ($values as $value) {
            $repoIds[] = str_replace('repo-', '', $value);
        }

        $select->where('REPOID in (?)', $repoIds);

        return $select;

    }

    /**
     * @param int $rvId
     * @param int $limit
     * @return array
     */
    public static function getAcceptedPapersByRvid(int $rvId, int $limit = 200): array
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $select = $db->select()
            ->from(T_PAPERS)->where('STATUS IN (?)', Episciences_Paper::ACCEPTED_SUBMISSIONS)
            ->where('RVID = ?', $rvId)
            ->order('MODIFICATION_DATE DESC')
            ->limit($limit);

        return $db->fetchAssoc($select);
    }

    /**
     * @param array $row
     * @return void
     */
    private static function addAnswersDate(array &$row): void
    {
        $invitationAid = Episciences_User_AssignmentsManager::findById($row['INVITATION_AID']);
        $answer = Episciences_User_InvitationAnswersManager::findById($invitationAid->getInvitation_id());
        // this date is overwritten by the last action
        $row['INVITATION_DATE'] = $invitationAid->getWhen();
        $row['ANSWER_DATE'] = $answer ? $answer->getAnswer_date() : null;
    }

    /**
     * @param $docId
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    public static function getCoAuthors($docId): array
    {
        //get coauthors
        $coAuthors = Episciences_User_AssignmentsManager::findAll(['ITEMID' => $docId, 'ROLEID' => Episciences_Acl::ROLE_CO_AUTHOR]);
        $coAuthorsList = [];
        foreach ($coAuthors as $coAuthor) {
            $coAuthorUser = new Episciences_User();
            $coAuthorUser->findWithCAS($coAuthor->getUid());
            $coAuthorsList[$coAuthorUser->getUid()] = $coAuthorUser;
        }
        return $coAuthorsList;
    }

    private static function sortInvitations($status, array $invitations = []): array
    {

        $result = [
            Episciences_User_Assignment::STATUS_ACTIVE => [],
            Episciences_User_Assignment::STATUS_PENDING => [],
            Episciences_User_Assignment::STATUS_INACTIVE => [],
            Episciences_User_Assignment::STATUS_EXPIRED => [],
            Episciences_User_Assignment::STATUS_CANCELLED => []
        ];

        foreach ($invitations as $invitation_list) {
            $invitation = array_shift($invitation_list);

            //si l'invitation a expiré, on la place dans une catégorie à part
            if (
                $invitation['ASSIGNMENT_STATUS'] === Episciences_User_Assignment::STATUS_PENDING &&
                self::compareToCurrentTime($invitation['EXPIRATION_DATE'])
            ) {
                if (
                    (!is_array($status) && $status !== Episciences_User_Assignment::STATUS_EXPIRED) ||
                    (is_array($status) && !in_array(Episciences_User_Assignment::STATUS_EXPIRED, $status, true))
                ) {
                    //si on a passé des statuts en paramètre, et que 'expired' n'en fait pas partie, on le saute
                    continue;
                }
                $result['expired'][] = $invitation;
            } else {
                if (
                    (!is_array($status) && $status !== $invitation['ASSIGNMENT_STATUS']) ||
                    (is_array($status) && !in_array($invitation['ASSIGNMENT_STATUS'], $status, true))
                ) {
                    //si on a passé des statuts en paramètre, et que ce statut n'en fait pas partie, on le saute
                    continue;
                }
                $result[$invitation['ASSIGNMENT_STATUS']][] = $invitation;
            }

        }

        return $result;

    }

    /**
     * @param Episciences_user $reviewer
     * @param $docId
     * @param $rvId
     * @param $isTmpUser
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    private static function reviewerProcess(Episciences_user $reviewer, $docId, $rvId, $isTmpUser): array
    {

        return [
            'alias' => ($reviewer instanceof \Episciences_Reviewer) ? $reviewer->getAlias($docId) : null,
            'fullname' => $reviewer->getFullName(),
            'screenname' => $reviewer->getScreenName(),
            'username' => $reviewer->getUsername(),
            'email' => $reviewer->getEmail(),
            'hasRoles' => !$isTmpUser && $reviewer->hasRoles($reviewer->getUid(), $rvId),
            'isCasUserValid' => (bool)$reviewer->getValid()
        ];

    }

    /**
     * @param Zend_Form $currentForm
     * @param string $formPrefix
     * @param int $docId
     * @return Zend_Form
     * @throws Zend_Form_Exception
     */

    private static function addHiddenDocIdElement(Zend_Form $currentForm, string $formPrefix, int $docId): \Zend_Form
    {

        $currentForm->addElement('hidden', 'docid', [
            'id' => $formPrefix . '-hdocid-' . $docId,
            'value' => $docId
        ]);

        return $currentForm;

    }

    /**
     * @return array
     * @throws JsonException
     */
    private static function fetchPapersWithNoConflictsConfirmation(): array
    {

        if (!Zend_Registry::get('isCoiEnabled')) {
            return [];
        }

        $result = [0]; // The obligation to confirm the absence of a conflict

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $paperIds = Episciences_Paper_ConflictsManager::fetchSelectedCol('paper_id', [
            'answer' => Episciences_Paper_Conflict::AVAILABLE_ANSWER['no'],
            'by' => Episciences_Auth::getUid()
        ]);


        if (!empty($paperIds)) {

            $sql = $db->select()
                ->from(T_PAPERS, ['DOCID'])
                ->where('PAPERID IN (?)', $paperIds)
                ->where('RVID = ?', RVID);


            $result = $db->fetchCol($sql);


        }

        return $result;

    }

    /**
     * @param int $paperId
     * @param array $recipients
     * @return void
     */
    public static function keepOnlyUsersWithoutConflict(int $paperId, array &$recipients = []): void
    {

        $isCoiEnabled = false;


        try {
            $journalSettings = Zend_Registry::get('reviewSettings');
            $isCoiEnabled = isset($journalSettings[Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED]) && (int)$journalSettings[Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED] === 1;
        } catch (Zend_Exception $e) {
            trigger_error($e->getMessage());
        }


        if ($isCoiEnabled) {

            $cUidS = Episciences_Paper_ConflictsManager::fetchSelectedCol('by', ['answer' => Episciences_Paper_Conflict::AVAILABLE_ANSWER['no'], 'paper_id' => $paperId]);
            /** @var Episciences_User $recipient */

            foreach ($recipients as $recipient) {

                if ($recipient->hasOnlyAdministratorRole()) {
                    continue;
                }

                $rUid = $recipient->getUid();

                if (!in_array($rUid, $cUidS, false)) {
                    unset($recipients[$rUid]);
                }
            }

        }


    }


    /**
     * @param $docId
     * @return string
     */
    public static function buildDocumentPath($docId): string
    {
        return REVIEW_FILES_PATH . $docId;
    }


    public static function getJsonDocumentByDocId(int $docid)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->from(T_PAPERS, ['DOCUMENT'])->where('DOCID = ?', $docid);
        return $db->fetchOne($select);
    }

    public static function updateJsonDocumentData(int $docId): void
    {
        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $paper = self::get($docId, false);
            $toJson = $paper->toJson();
            $str = sprintf('UPDATE `PAPERS` set `DOCUMENT` = %s  WHERE DOCID = %s;', $db->quote($toJson), $docId);
            $db->query($str)->closeCursor();
        } catch (Zend_Db_Statement_Exception $e) {
            trigger_error($e->getMessage());
        }
    }

    /**
     * @param mixed $repoId
     * @param int $paperId
     * @param array|string $identifier
     * @param float $version
     * @param int $affectedRows
     * @return int
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private static function updateRecordDataHal(int $paperId, array|string $identifier, float $version, int $affectedRows): int
    {
        $affectedRows += Episciences_Paper_AuthorsManager::enrichAffiOrcidFromTeiHalInDB(Episciences_Repositories::HAL_REPO_ID, $paperId, $identifier, $version);
        //FUNDING
        $arrayIdEuAnr = Episciences_Paper_ProjectsManager::CallHAlApiForIdEuAndAnrFunding($identifier, $version);
        $decodeHalIdsResp = json_decode($arrayIdEuAnr, true, 512, JSON_THROW_ON_ERROR);
        $globalArrayJson = [];
        if (!empty($decodeHalIdsResp['response']['docs'])) {
            $globalArrayJson = Episciences_Paper_ProjectsManager::FormatFundingANREuToArray($decodeHalIdsResp['response']['docs'], $identifier, $globalArrayJson);
        }
        $mergeArrayANREU = [];
        if (!empty($globalArrayJson)) {
            foreach ($globalArrayJson as $globalPreJson) {
                $mergeArrayANREU[] = $globalPreJson[0];
            }
            $rowInDbHal = Episciences_Paper_ProjectsManager::getProjectsByPaperIdAndSourceId($paperId, Episciences_Repositories::HAL_REPO_ID);
            $affectedRows += Episciences_Paper_ProjectsManager::insertOrUpdateHalFunding($rowInDbHal, $mergeArrayANREU, $paperId);
        }
        return $affectedRows;
    }

    /**
     * @param array|string $doiTrim
     * @param int $paperId
     * @param int $affectedRows
     * @return int
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private static function updateRecordDataCallOpenAireTools(array|string $doiTrim, int $paperId, int $affectedRows): int
    {
// CHECK IF FILE EXIST TO KNOW IF WE CALL OPENAIRE OR NOT
        // BUT BEFORE CHECK GLOBAL CACHE
        Episciences_OpenAireResearchGraphTools::checkOpenAireGlobalInfoByDoi($doiTrim, $paperId);
        ///////////////////////////////////////////////////////////////////////////////////////////////////
        $setsGlobalOARG = Episciences_OpenAireResearchGraphTools::getsGlobalOARGCache($doiTrim);
        list($cacheCreator, $pathOpenAireCreator, $setsOpenAireCreator) = Episciences_OpenAireResearchGraphTools::getCreatorCacheOA($doiTrim);

        if ($setsGlobalOARG->isHit() && !$setsOpenAireCreator->isHit()) {
            //create cache with the global cache of OpenAire Research Graph created or not before -> ("checkOpenAireGlobalInfoByDoi")
            // WE PUT EMPTY ARRAY IF RESPONSE IS NOT OK
            try {
                $decodeOpenAireResp = json_decode($setsGlobalOARG->get(), true, 512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                Episciences_OpenAireResearchGraphTools::putCreatorInCache($decodeOpenAireResp, $doiTrim);
                Episciences_OpenAireResearchGraphTools::logErrorMsg('Create Cache from Global openAireResearchGraph cache file for ' . $doiTrim);
            } catch (JsonException $e) {

                $eMsg = $e->getMessage() . " for PAPER " . $paperId . ' URL called https://api.openaire.eu/search/publications/?doi=' . $doiTrim . '&format=json ';
                // OPENAIRE CAN RETURN MALFORMED JSON SO WE LOG URL OPENAIRE
                Episciences_OpenAireResearchGraphTools::logErrorMsg($eMsg);
                $setsOpenAireCreator->set(json_encode([""]));
                $cacheCreator->save($setsOpenAireCreator);
            }
        }

        //we need to refresh cache creator to get the new file
        ////// CACHE CREATOR ONLY
        [$cacheCreator, $pathOpenAireCreator, $setsOpenAireCreator] = Episciences_OpenAireResearchGraphTools::getCreatorCacheOA($doiTrim);

        $affectedRows += Episciences_OpenAireResearchGraphTools::insertOrcidAuthorFromOARG($setsOpenAireCreator, $paperId);

        ////////Funding OA and HAL
        list($cacheFundingOA, $pathOpenAireFunding, $setOAFunding) = Episciences_OpenAireResearchGraphTools::getFundingCacheOA($doiTrim);

        if ($setsGlobalOARG->isHit() && !$setOAFunding->isHit()) {
            // WE PUT EMPTY ARRAY IF RESPONSE IS NOT OK
            try {
                $decodeOpenAireResp = json_decode($setsGlobalOARG->get(), true, 512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                Episciences_OpenAireResearchGraphTools::putFundingsInCache($decodeOpenAireResp, $doiTrim);
                //create cache with the global cache of OpenAire Research Graph created or not before -> ("checkOpenAireGlobalInfoByDoi")
                Episciences_OpenAireResearchGraphTools::logErrorMsg('Create Cache from Global openAireResearchGraph cache file for ' . $doiTrim);

            } catch (JsonException $e) {
                // OPENAIRE CAN RETURN MALFORMED JSON SO WE LOG URL OPENAIRE
                Episciences_OpenAireResearchGraphTools::logErrorMsg($e->getMessage() . ' URL called https://api.openaire.eu/search/publications/?doi=' . $doiTrim . '&format=json');
                $setOAFunding->set(json_encode([""]));
                $cacheFundingOA->save($setOAFunding);
            }
        }
        try {
            [$cacheFundingOA, $pathOpenAireFunding, $setOAFunding] = Episciences_OpenAireResearchGraphTools::getFundingCacheOA($doiTrim);
        } catch (\Psr\Cache\InvalidArgumentException $e) {
            trigger_error($e->getMessage());
        }

        try {
            $fileFound = $setOAFunding->get() ? json_decode($setOAFunding->get(), true, 512, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null;
        } catch (JsonException $jsonException) {
            Episciences_OpenAireResearchGraphTools::logErrorMsg(sprintf('Error Code %s / Error Message %s', $jsonException->getCode(), $jsonException->getMessage()));
        }

        $globalfundingArray = [];
        if (!empty($fileFound[0])) {
            $fundingArray = [];
            $globalfundingArray = Episciences_Paper_ProjectsManager::formatFundingOAForDB($fileFound, $fundingArray, $globalfundingArray);
            $rowInDBGraph = Episciences_Paper_ProjectsManager::getProjectsByPaperIdAndSourceId($paperId, Episciences_Repositories::GRAPH_OPENAIRE_ID);
            $affectedRows += Episciences_Paper_ProjectsManager::insertOrUpdateFundingOA($globalfundingArray, $rowInDBGraph, $paperId);
        }
        return $affectedRows;
    }

    /**
     * @param $record1
     * @param mixed $docId
     * @param mixed $repoId
     * @param array|string $identifier
     * @param mixed $enrichment
     * @param mixed $affectedRows
     * @return array
     */
    private static function updateRecordDataProcessFilesHook($record1, mixed $docId, mixed $repoId, array|string $identifier, mixed $enrichment, mixed $affectedRows): array
    {
        $record = $record1;
        // delete all paper files
        Episciences_Paper_FilesManager::deleteByDocId($docId);

        $hookParams = ['repoId' => $repoId, 'identifier' => $identifier, 'docId' => $docId];

        // add all files
        $hookFiles = Episciences_Repositories::callHook(
            'hookFilesProcessing',
            (isset($enrichment['files'])) ? array_merge($hookParams, ['files' => $enrichment['files']]) : $hookParams
        );

        if (isset($hookFiles['affectedRows'])) {
            $affectedRows += $hookFiles['affectedRows'];

        }
        return array($record, $enrichment, $affectedRows);
    }

    /**
     * @param mixed $repoId
     * @param string $identifier
     * @param float $version
     * @param mixed $docId
     * @param int $affectedRows
     * @return int
     */
    private static function updateRecordDataProcessLicence(mixed $repoId, string $identifier, float $version, mixed $docId, int $affectedRows): int
    {
         try {
            $callArrayResp = Episciences_Paper_LicenceManager::getApiResponseByRepoId($repoId, $identifier, $version);
            try {
                $affectedRows += Episciences_Paper_LicenceManager::insertLicenceFromApiByRepoId($repoId, $callArrayResp, $docId, $identifier);
            } catch (JsonException|\Psr\Cache\InvalidArgumentException $e) {
                trigger_error($e->getMessage(), E_USER_WARNING);
            }
        } catch (GuzzleException|\Psr\Cache\InvalidArgumentException $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }
        return $affectedRows;
    }

    /**
     * Get All the DocIds associated to a PaperId
     */
    public static function getDocIdsFromPaperId(int $paperId): array
    {
        $result = [];
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db
            ->select()
            ->from(T_PAPERS, ['DOCID'])
            ->where('PAPERID = ?', $paperId);
        try {
            $query = $select->query();
            $result = $query->fetchAll();
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }
        return $result;

    }
}
