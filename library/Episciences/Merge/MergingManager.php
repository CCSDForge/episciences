<?php

class Episciences_Merge_MergingManager
{

    const TABLE = T_USER_MERGE;
    const TOKEN_LENGTH = 40;
    const SUCCESS = 'The merge has been successfully applied';
    const FAILED = 'Failed to merge';
    const HTTP_SUCCESS_STATUS = 200;
    const HTTP_ERROR_SERVER = 500;
    const HTTP_UNAUTHORIZED = 401;
    const MERGER_NOT_EXIST = 1;
    const KEEPER_MERGER_EXIST = 2;
    const KEEPER_NOT_EXIST = 3;
    const FORBIDDEN_UID = 4;
    const IDENTICAL_KEEPER_AND_MERGER = 5;
    const DUPLICATE_ENTRY = 23000;
    const RENAMED_GRID = 'renamed_grid';

    /**
     * Fusionne deux comptes
     * @param int $merger
     * @param int $keeper
     * @param string $token
     * @param bool $doMerge
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    public static function mergeAccounts(int $merger = 0, int $keeper = 0, string $token = '', bool $doMerge = false): array
    {

        if ($merger >= 0 && $keeper >= 0 && !empty($token) && is_string($token) && $doMerge) {
            $merge = self::applyMerge($token);
            $httpStatusCode = $merge['httpStatusCode'];
            $message = $merge['message'];
            if (array_key_exists('mergerId', $merge)) {
                $mergerId = $merge['mergerId'];
                self::updateMergingData($mergerId, $merge);
            }
            return ['httpStatusCode' => $httpStatusCode, 'message' => $message];
        }

        $merging = self::checkMerging($merger, $keeper);
        $httpStatusCode = $merging['httpStatusCode'];
        $message = $merging['message'];
        $code = $merging['code'];
        if ($httpStatusCode === self::HTTP_SUCCESS_STATUS) {
            try {
                $token = self::getToken();
            } catch (InvalidArgumentException $e) {
                return ['Exception' => $e->getMessage()];
            }

            $mergerUid = $merging['mergerUid'];
            $keeperUid = $merging['keeperUid'];
            if ($code === self::KEEPER_MERGER_EXIST || $code === self::KEEPER_NOT_EXIST) {
                self::backupMergingData(
                    $token,
                    $mergerUid,
                    $keeperUid,
                    $merging
                );
            }
        }
        return ['httpStatusCode' => $httpStatusCode, 'code' => $code, 'message' => $message, 'token' => $token];
    }

    /**
     * Applique la fusion de deux comptes
     * @param string $token
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    public static function applyMerge(string $token = ''): array
    {
        $mergerUid = null;
        $keeperUid = null;

        if (!is_string($token) || empty($token)) {
            $message = 'Parameter missing or incorrect : token';
            return ["codeHttp" => self::HTTP_ERROR_SERVER, "message" => $message];
        }

        if ($mergerInfos = self::findByToken($token)) {
            $mergerId = (int)$mergerInfos['MID'];
            $mergerUid = (int)$mergerInfos['MERGER_UID'];
            $keeperUid = (int)$mergerInfos['KEEPER_UID'];
            $codeHttp = self::HTTP_SUCCESS_STATUS;
            if ($mergerUid && $keeperUid) {
                // git #165 : la grille de relecture n'est pas déplacée et devient inaccessible
                $message [self::RENAMED_GRID] = self::renameReviewerGrid($mergerUid, $keeperUid);
                $message += self::updateDataTables($mergerUid, $keeperUid);
            } else {
                $message = 'Error merging : no match between the token and the retrieved information';
            }
            return ["mergerId" => $mergerId, "httpStatusCode" => $codeHttp, "message" => $message];
        }

        $message = "No informations recorded for this token";
        $codeHttp = self::HTTP_ERROR_SERVER;
        return ["httpStatusCode" => $codeHttp, "message" => $message];

    }

    /**
     * Retourne un enregistrement à partir d'un token
     * @param string $token
     * @return bool|mixed
     */

    public static function findByToken(string $token = '')
    {
        if (empty($token) || !is_string($token)) {
            return false;
        }
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $cols = ['MID', 'MERGER_UID', 'KEEPER_UID'];
        $sql = $db
            ->select()
            ->from(self::TABLE, $cols)
            ->where('TOKEN = ? ', $token);
        return $db->fetchRow($sql);

    }

    /**
     * Met à jour l'attribut UID (de mergerUid à keeperUid) dans toutes les tables contenant ce dernier
     * lors de la fusion de deux comptes.
     * @param int $mergerUid
     * @param int $keeperUid
     * @return array : [string : message , array: les détails de l'opération]
     */
    public static function updateDataTables(int $mergerUid = 0, int $keeperUid = 0): array
    {
        $affected_rows = [];
        $rowTotal = 0;
        $result = self::FAILED . ' : no change in database';
        $exception = [];
        $affected_rows['USER_ROLES'] = 0;
        $affected_rows['REVIEWER_ALIAS'] = 0;
        $affected_rows['REVIEWER_POOL'] = 0;
        $affected_rows['REVIEWER_REPORT'] = 0;
        $affected_rows['PAPER_LOG'] = 0;
        $affected_rows['PAPER_COMMENTS'] = 0;
        $affected_rows['USER_ASSIGNMENT'] = 0;
        $affected_rows['NEWS'] = 0;
        $affected_rows['PAPERS'] = 0;
        $affected_rows['USER'] = 0;

        try { //USER_ROLES table
            $rowNb = Episciences_UsersManager::updateRolesUid($mergerUid, $keeperUid);
            $affected_rows['USER_ROLES'] += $rowNb;
            $rowTotal += $rowNb;
        } catch (Exception $e) {
            if ((int)$e->getCode() === self::DUPLICATE_ENTRY) {
                // On supprimer la ligne qui pose problème
                $rowNb = self::deleteDuplicateRow(T_USER_ROLES, $mergerUid);
                $affected_rows['USER_ROLES'] += $rowNb;
                $rowTotal += $rowNb;
                $exception['delete_in_' . T_USER_ROLES] = 'UID = ' . $mergerUid;
            } else {
                $exception[] = self::showException($e);
            }
        }

        try { //REVIEWER_ALIAS
            $rowNb = Episciences_Reviewer_AliasManager::updateUid($mergerUid, $keeperUid);
            $affected_rows['REVIEWER_ALIAS'] += $rowNb;
            $rowTotal += $rowNb;

        } catch (Exception $e) {
            if ((int)$e->getCode() === self::DUPLICATE_ENTRY) {
                // On supprime la ligne qui pose problème
                $rowNb = self::deleteDuplicateRow(T_ALIAS, $mergerUid);
                $affected_rows['REVIEWER_ALIAS'] += $rowNb;
                $rowTotal += $rowNb;
                $exception['delete_in_' . T_ALIAS] = 'UID = ' . $mergerUid;

            } else {
                $exception[] = self::showException($e);
            }

        }

        try {//REVIEWER_POOL
            $rowNb = Episciences_Reviewer_PoolManager::updateUid($mergerUid, $keeperUid);
            $affected_rows['REVIEWER_POOL'] += $rowNb;
            $rowTotal += $rowNb;

        } catch (Exception $e) {
            if ((int)$e->getCode() === self::DUPLICATE_ENTRY) {
                // On supprime la ligne qui pose problème
                $rowNb = self::deleteDuplicateRow(T_REVIEWER_POOL, $mergerUid);
                $affected_rows['REVIEWER_POOL'] += $rowNb;
                $rowTotal += $rowNb;
                $exception['delete_in_' . T_REVIEWER_POOL] = 'UID = ' . $mergerUid;
            } else {
                $exception[] = self::showException($e);
            }
        }

        try {//REVIEWER_REPORT table
            $rowNb = Episciences_Rating_ReportManager::updateUidS($mergerUid, $keeperUid);
            $affected_rows['REVIEWER_REPORT'] += $rowNb;
            $rowTotal += $rowNb;

        } catch (Exception $e) {
            if ((int)$e->getCode() === self::DUPLICATE_ENTRY) {
                // On supprime la ligne qui pose problème
                $rowNb = self::deleteDuplicateRow(T_REVIEWER_REPORTS, $mergerUid);
                $affected_rows['REVIEWER_REPORT'] += $rowNb;
                $rowTotal += $rowNb;
                $exception['delete_in_' . T_REVIEWER_REPORTS] = 'UID = ' . $mergerUid;

            } else {
                $exception[] = self::showException($e);
            }

        }
        try {// PAPER_LOG table
            $rowNb = Episciences_Paper_Logger::updateUid($mergerUid, $keeperUid);
            $affected_rows['PAPER_LOG'] += $rowNb;
            $rowTotal += $rowNb;

        } catch (Exception $e) {
            $exception[] = self::showException($e);
        }

        try { // PAPER_COMMENTS table
            $rowNb = Episciences_CommentsManager::updateUid($mergerUid, $keeperUid);
            $affected_rows['PAPER_COMMENTS'] += $rowNb;
            $rowTotal += $rowNb;

        } catch (Exception $e) {
            $exception[] = self::showException($e);
        }


        try {//USER_ASSIGNMENT table
            $rowNb = Episciences_User_AssignmentsManager::updateUid($mergerUid, $keeperUid);
            $affected_rows['USER_ASSIGNMENT'] += $rowNb;
            $rowTotal += $rowNb;

        } catch (Exception $e) {
            $exception[] = self::showException($e);
        }


        try { // NEWS table
            $rowNb = Episciences_News_NewsManager::updateUid($mergerUid, $keeperUid);
            $affected_rows['NEWS'] += $rowNb;
            $rowTotal += $rowNb;
        } catch (Exception $e) {
            $exception[] = self::showException($e);
        }
        //PAPERS table
        try {
            $rowNb = Episciences_PapersManager::updateUid($mergerUid, $keeperUid);
            $affected_rows['PAPERS'] += $rowNb;
            $rowTotal += $rowNb;
        } catch (Exception $e) {
            $exception[] = self::showException($e);
        }

        try { //USER table
            // if keeper not found: the account will be translated
            $keeperUser = new Episciences_User();
            if ($keeperUser->findWithCAS($keeperUid) && !$keeperUser->hasLocalData($keeperUid)) {

                $keeperUser->setScreenName('');

                $langId = ($keeperUser->getLangueid()) ?: Zend_Registry::get('Zend_Locale')->getLanguage();
                $rowNb = Episciences_UsersManager::insertLocalUser($keeperUid, $langId, $keeperUser->getScreenName());
                $affected_rows['USER'] += $rowNb;
                $rowTotal += $rowNb;
            }

            $rowNb = Episciences_UsersManager::removeUserUid($mergerUid);
            $affected_rows['USER'] += $rowNb;
            $rowTotal += $rowNb;

        } catch (Exception $e) {
            $exception[] = self::showException($e);
        }
        $affected_rows['Affected rows in database'] = $rowTotal;
        if ($rowTotal) {
            $result = self::SUCCESS;
        }
        return ['result' => $result, 'affected_rows' => $affected_rows, 'exception' => $exception];
    }

    /**
     * Supprimer une ligne dans la base
     * @param $table_name
     * @param $key
     * @return int
     */

    static function deleteDuplicateRow($table_name, $key): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $where['UID = ?'] = (int)$key;
        return $db->delete($table_name, $where);
    }

    /**
     * affiche les détails de l'exception
     * @param Exception $e
     * @return array
     */

    static function showException(Exception $e): array
    {
        $result = self::FAILED;
        return ['result' => $result, 'exception' => [
            'code' => $e->getCode(), 'message' => $e->getMessage()]];
    }

    /**
     * Met à jour les données de fusion
     * @param int $mergerId
     * @param array|null $detail
     * @return bool
     */

    public static function updateMergingData(int $mergerId = 0, array $detail = null)
    {

        try {
            if ($mergerId <= 0 || null === $detail || !is_array($detail)) {
                return false;
            }
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $data['DETAIL'] = Zend_Json_Encoder::encode($detail);
            $data['DATE'] = new Zend_Db_Expr('NOW()');
            $data['TOKEN'] = null;
            $where['MID = ?'] = (int)$mergerId;
            $db->update(self::TABLE, $data, $where);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Vérifie la possibilité de la fusion de deux comptes passés en parametres
     * @param int $merger
     * @param int $keeper
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    public static function checkMerging(int $merger = 0, int $keeper = 0): array
    {
        if ($merger <= 0) {
            $message = 'Parameter incorrect : merger [ ' . $merger . ' ]';
            $code = self::MERGER_NOT_EXIST;
            return ['message' => $message, 'code' => $code, 'httpStatusCode' => self::HTTP_ERROR_SERVER];
        }

        if ($keeper <= 0) {
            $message = 'Parameter incorrect : keeper [ ' . $keeper . ' ]';
            $code = self::KEEPER_NOT_EXIST;
            return ['message' => $message, 'code' => $code, 'httpStatusCode' => self::HTTP_ERROR_SERVER];
        }

        if ($merger === EPISCIENCES_UID || $merger === ROOT_UID ||
            $keeper === EPISCIENCES_UID || $keeper === ROOT_UID) {
            $code = self::FORBIDDEN_UID;
            $message = 'UID forbidden, do not merge it';

        } else { // Les deux paramètres sont corrects

            $user = new Episciences_User();
            $user->find($merger);

            if ($merger !== $user->getUid()) {
                $code = self::MERGER_NOT_EXIST;
                $message = 'Merger ' . $merger . ' not found: no changes to make ';
            } else {
                $user->find($keeper);
                if ($keeper !== $user->getUid()) {
                    $code = self::KEEPER_NOT_EXIST;
                    $message = 'Keeper ' . $keeper . ' not found: the account will be translated';
                } else if ($keeper === $merger) {
                    $code = self::IDENTICAL_KEEPER_AND_MERGER;
                    $message = 'Identical Kepper and Merger UIDs: no changes to make';
                } else {
                    $code = self::KEEPER_MERGER_EXIST;
                    $message = 'Episciences merger ' . $merger . ' and Episciences keeper ' .
                        $keeper . ' found: merge user accounts';
                }

            }
        }
        return [
            'message' => $message,
            'httpStatusCode' => self::HTTP_SUCCESS_STATUS,
            'code' => $code,
            'mergerUid' => $merger,
            'keeperUid' => $keeper
        ];

    }

    /**
     * Retourne un jeton
     * @return mixed|string
     * @throws InvalidArgumentException
     */
    public static function getToken()
    {
        $token = (sha1(time() . uniqid(rand(), true)));
        $token = filter_var($token, FILTER_SANITIZE_STRING);
        if (strlen($token) !== self::TOKEN_LENGTH) {
            throw new InvalidArgumentException("Le jeton n'est pas valide");
        }
        return $token;
    }

    /**
     * Sauvegarde la trace de la fusion de deux comptes
     * @param string $token
     * @param int $keeper_uid
     * @param int $merger_uid
     * @param array|null $detail
     * @return bool|string
     */

    public static function backupMergingData(string $token = '', int $merger_uid = 0, int $keeper_uid = 0, array $detail = null)
    {
        try {
            if (empty($token) || !is_string($token) || $keeper_uid <= 0 || $merger_uid <= 0) {
                return false;
            }
            if (null !== $detail && is_array($detail)) {
                $data['DETAIL'] = Zend_Json_Encoder::encode($detail);
            }
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $data['TOKEN'] = $token;
            $data['KEEPER_UID'] = (int)$keeper_uid;
            $data['MERGER_UID'] = (int)$merger_uid;
            $db->insert(self::TABLE, $data);
            return $db->lastInsertId(self::TABLE);

        } catch (Exception $e) {
            return false;
        }

    }

    /**
     * @param int $merger
     * @param int $keeper
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    public static function renameReviewerGrid(int $merger = 0, int $keeper = 0): array
    {

        $result = [self::RENAMED_GRID => false, 'logs' => []];

        $keeperReviewer = new Episciences_Reviewer();
        $mergerReviewer = new Episciences_Reviewer();

        if (!$keeperReviewer->hasLocalData($keeper)) {
            $keeperReviewer->findWithCAS($keeper);

        } else {
            $keeperReviewer->find($keeper);
        }

        $mergerReviewer->find($merger);

        if (!$keeperReviewer->getUid()) {
            $message = 'APPLICATION PANIC: failed to find user UID = ' . $keeper;
            error_log($message);
            $result['logs'] = $message;
            return $result;
        }

        if (!$mergerReviewer->getUid()) {
            $message = 'APPLICATION PANIC: failed to find reviewer UID = ' . $merger;
            error_log($message);
            $result['logs'] = $message;
            return $result;
        }

        try {
            $keeperPapers = $keeperReviewer->getAssignedPapers(['is' => ['rvid' => RVID]]);

        } catch (Exception $e) {
            $message = 'APPLICATION PANIC: failed to get assigned papers for reviewer UID = ' . $keeper;
            error_log($message);
            return $result;
        }

        try {
            $mergerPapers = $mergerReviewer->getAssignedPapers(['is' => ['rvid' => RVID]]);

        } catch (Exception $e) {
            $message = 'APPLICATION PANIC: failed to get assigned papers for reviewer UID = ' . $merger;
            error_log($message);
            return $result;
        }


        $keeperPapersKeys = array_keys($keeperPapers);
        $mergerPapersKeys = array_keys($mergerPapers);

        $arrayEquality = Episciences_Tools::checkArrayEquality($keeperPapersKeys, $mergerPapersKeys);

        if ($arrayEquality['equality'] && !empty($mergerPapersKeys)) {// Documents relus deux fois avec 2 UID(s) diff.

            foreach ($mergerPapersKeys as $docId) {

                $reportsPath = REVIEW_FILES_PATH . $docId . '/reports/';
                $mergerPath = $reportsPath . $merger . '/';

                $newPath = $reportsPath . 'merger_' . $merger . '_keeper_' . $keeper . '_' . date("Y-m-d") . '_' . date("H:i:s") . '.save/';
                $renaming = self::applyRenaming($mergerPath, $newPath);
                $result[self::RENAMED_GRID][$docId] = $renaming[self::RENAMED_GRID];
                $result['logs'][$docId] = $renaming['logs'];
            }

        } elseif (!empty($arrayEquality['arrayDiff'][1])) { // Documents relus avec l'UID merger (pas avec l'UID keeper)

            foreach ($arrayEquality[1] as $docId) {

                $reportsPath = REVIEW_FILES_PATH . $docId . '/reports/';
                $mergerPath = $reportsPath . $merger . '/';
                $keeperPath = $reportsPath . $merger . '/';
                $renaming = self::applyRenaming($mergerPath, $keeperPath);
                $result[self::RENAMED_GRID][$docId] = $renaming[self::RENAMED_GRID];
                $result['logs'][$docId] = $renaming['logs'];
            }
        } else {
            $result['logs'] = 'No grid(s) found for this reviewer_uid = ' . $merger;
        }

        return $result;

    }

    /**
     * @param string $mergerPath
     * @param string $keeperPath
     * @return array
     */
    private static function applyRenaming(string $mergerPath = '', string $keeperPath = ''): array
    {
        $result = [self::RENAMED_GRID => false, 'logs' => []];

        if (!is_dir($mergerPath)) {
            error_log('Merging APPLICATION PANIC: ' . $mergerPath . ' is invalid.');
            return $result;
        }

        $result[self::RENAMED_GRID] = rename($mergerPath, $keeperPath);

        if (!$result[self::RENAMED_GRID]) {
            $result['logs'] = 'Failed to rename ' . $mergerPath . ' to ' . $keeperPath;

        } else {
            $result['logs'] = $mergerPath . ' renamed to ' . $keeperPath;

        }
        return $result;
    }

}