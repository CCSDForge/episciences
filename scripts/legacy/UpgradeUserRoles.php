<?php

require_once "JournalScript.php";


class UpgradeUserRoles extends JournalScript
{
    private string $_table;

    public function __construct()
    {
        parent::__construct();

        $this->_table = 'USER_ROLES';

        $this->displayInfo('*** Upgrade USER ROLES table' . PHP_EOL, true, ['bold']);
        $args = [];
        $this->setArgs(array_merge($this->getArgs(), $args));

    }

    /**
     */
    public function run()
    {

        $t0 = time();

        if ($this->isDebug()) {
            $this->displayInfo("Debug mode: " . $this->isDebug(), true);
        }

        $this->checkAppEnv();

        defineSQLTableConstants();
        defineApplicationConstants();

        $this->initApp();
        $this->initDb();

        if ($this->upgradeTable()) {
            $this->displayInfo('Update completed. Good Bye ! =)', true);
        } else {
            $this->displayCritical('Update incomplete.');
        }

        $t1 = time();
        $time = $t1 - $t0;

        $this->displayTrace("The script took $time seconds to run.", true);
    }

    /**
     * @return bool
     *
     */
    public function upgradeTable(): bool
    {
        $result = false;

        if ($this->cloneTable($this->_table)) {
            $this->displaySuccess('Generate a new Table => OK', true);
            $result = $this->updateProcessing();
        }

        return $result;
    }


    /**
     * @return bool
     *
     */
    private function updateProcessing(): bool
    {

        $this->getProgressBar()
            ->start();

        $db = $this->getDb();


        $updatedRows = 0;

        $dataQuery = $db
            ->select()
            ->from($this->_table)
            ->order('RVID DESC');

        $uidS = $db
            ->fetchCol($dataQuery);

        $count = count($uidS);

        foreach ($uidS as $key => $uid) {
            $doUpdating = false;

            $this->displayInfo('*** Update processing (' . $key . '/' . $count . ') [UID = ' . $uid . ' ] ***', true);
            $progress = round((($key + 1) * 100) / $count);

            $this
                ->getProgressBar()
                ->setProgress($progress);

            $uid = (int)$uid;
            $user = new Episciences_User();
            try {
                $user->find($uid);
            } catch (Exception $e) {
                $this->displayCritical('Update incomplete: ' . $e->getMessage());
                exit(0);
            }

            $allRoles = $user->getAllRoles();

            foreach ($allRoles as $rvId => $roles) {
                $values = [];
                $nbRoles = count($roles);

                // add author role
                if (!in_array(Episciences_Acl::ROLE_AUTHOR, $roles, true) && $this->hasSubmissions($uid, $rvId)) {
                    $values[] = '(' . $user->getUid() . ',' . $rvId . ',' . $db->quote(Episciences_Acl::ROLE_AUTHOR) . ')';
                    $this->displayTrace(strtoupper(Episciences_Acl::ROLE_AUTHOR) . ' role to be added', true);
                    $doUpdating = true; // add author role
                    ++$nbRoles;
                }

                foreach ($roles as $roleId) {

                    if ($nbRoles > 1 && $roleId === Episciences_Acl::ROLE_MEMBER) {
                        $doUpdating = true;
                        $this->displayTrace(strtoupper(Episciences_Acl::ROLE_MEMBER) . ' role to be removed', true);
                        continue; // delete member role
                    }

                    $values[] = '(' . $user->getUid() . ',' . $rvId . ',' . $db->quote($roleId) . ')';
                }

                // update roles
                if ($doUpdating) {
                    $nbRows = $this->updateRoles($uid, (int)$rvId, $values);
                    $updatedRows += $nbRows;

                    $this->displaySuccess('OK: diff (inserted - deleted): ' . $nbRows, true);
                }

            }

        }


        $this->getProgressBar()->stop();

        $this->displayInfo('*** Update summary ***', true);
        $this->displaySuccess('Updated rows: ' . $updatedRows, true);

        return true;
    }


    /**
     * @param int $uid
     * @param int $rvId
     * @return bool
     */
    private function hasSubmissions(int $uid, int $rvId): bool
    {
        if ($uid === EPISCIENCES_UID) {
            return false;
        }

        $db = $this->getDb();
        $ignoredStatus = [
            Episciences_Paper::STATUS_OBSOLETE,
            Episciences_Paper::STATUS_DELETED,
            Episciences_Paper::STATUS_REMOVED];

        $submissionsQuery = $db
            ->select()->from(T_PAPERS, ['UID'])
            ->where('UID = ?', $uid)
            ->where('RVID = ?', $rvId)
            ->where('STATUS NOT IN (?)', $ignoredStatus);

        $paperUid = (int)$db->fetchOne($submissionsQuery);

        return ($paperUid > 0);
    }

    /**
     * @param int $uid
     * @param array $values
     * @param int $rvId
     * @return int
     */
    private function updateRoles(int $uid, int $rvId, array $values = []): int
    {

        $insertedRows = 0;

        if (empty($values) || $this->isDebug()) {
            return $insertedRows;
        }

        $db = $this->getDb();

        $deletedRows = $db->delete($this->_table, "UID = $uid and RVID = $rvId");
        $this->displayTrace($deletedRows . ' row(s) deleted', true);

        $sql = 'INSERT INTO ';
        $sql .= $db->quoteIdentifier($this->_table);
        $sql .= ' (`UID`, `RVID`, `ROLEID`) VALUES ';
        $sql .= implode(',', $values);
        $sql .= ' ON DUPLICATE KEY UPDATE ROLEID = VALUES(ROLEID)';

        $insert = $db->prepare($sql);

        try {
            $insert->execute();
        } catch (Exception $e) {
            $insert = null;
            trigger_error($e->getMessage(), E_USER_ERROR);
        }

        if ($insert) {
            try {
                $insertedRows = $insert->rowCount();
            } catch (Zend_Db_Statement_Exception $e) {
                $this->displayCritical('Update incomplete: ' . $e->getMessage());
                exit(0);
            }
            $this->displayTrace($insertedRows . ' row(s) inserted', true);

        }

        return abs($insertedRows - $deletedRows);
    }
}


$script = new UpgradeUserRoles();
$script->run();
