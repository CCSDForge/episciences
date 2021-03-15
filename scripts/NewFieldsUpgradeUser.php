<?php

require_once "JournalScript.php";


class NewFieldsUpgradeUser extends JournalScript
{

    public function __construct()
    {
        $this->display('*** Upgrade USER table' . PHP_EOL, true, ['bold']);
        $args = [];
        $this->setArgs(array_merge($this->getArgs(), $args));
        parent::__construct();
    }

    public function run()
    {
        if ($this->isVerbose()) {
            $this->displayInfo("Verbose mode: 1");
            $this->displayInfo("Debug mode: " . $this->isDebug());
        }

        $this->checkAppEnv();

        define_table_constants();
        define_app_constants();

        $this->initApp();
        $this->initDb();

        if ($this->upgradeTable()) {
            $this->displayInfo('Update completed. Good Bye ! =)', true);
        } else {
            $this->displayCritical('Update incomplete.', true);
        }
    }

    /**
     * @return bool
     */
    public function upgradeTable(): bool
    {
        $db = $this->getDb();
        $dataQuery = $db->select()->from(T_USERS)->order('UID DESC');
        $uidS = $db->fetchCol($dataQuery);

        if ($isCloned = $this->cloneTable(T_USERS)) {
            $this->displaySuccess('Generated new Table OK !', true);
        }

        $sql = "ALTER TABLE USER  ADD USERNAME VARCHAR(100) NOT NULL, ADD API_PASSWORD VARCHAR(255) NOT NULL, ADD EMAIL VARCHAR(320) NOT NULL, ADD CIV VARCHAR(255) DEFAULT NULL, ADD LASTNAME VARCHAR(100) NOT NULL, ADD FIRSTNAME VARCHAR(100) DEFAULT NULL, ADD MIDDLENAME VARCHAR(100) DEFAULT NULL, ADD REGISTRATION_DATE DATETIME DEFAULT NULL, ADD MODIFICATION_DATE DATETIME DEFAULT NULL, ADD IS_VALID TINYINT(1) NOT NULL DEFAULT 1";

        $isCloningStep = $this->existColumn('USERNAME', T_USERS);

        if ($isCloningStep || ($isCloned && $this->alterTable($sql))) {

            $count = count($uidS);

            $this->getProgressBar()->start();

            foreach ($uidS as $key => $uid) {
                $user = new Episciences_User();
                $casUser = new Ccsd_User_Models_UserMapper();

                $this->displayInfo('*** Update processing (' . $key . '/' . $count . ') [UID = ' . $uid . ' ] ***', true);
                $progress = round((($key + 1) * 100) / $count);
                $this->getProgressBar()->setProgress($progress);

                $this->displayProgressBar();

                try {
                    $casUser = $casUser->find($uid, $user);
                    /** @var Episciences_User $localUserData */
                    $localUserData = $user->find($uid);

                } catch (Exception $e) {
                    $this->displayError($e->getMessage());
                    return false;
                }

                $userRoles = $user->loadRoles();

                if (empty($userRoles)) {
                    $this->displayCritical("*** User (UID = $uid) doesn't belong to any journal ! ***", true);
                    continue;
                }

                foreach ($userRoles as $rvId => $roles) {

                    if ($rvId) {
                        $review = Episciences_ReviewsManager::find($rvId);
                        $localUserData['REGISTRATION_DATE'] = $review->getCreation();
                        break;
                    }
                }

                $casUserData = $casUser->toArray();

                $userData = array_merge($localUserData, $casUserData);

                unset($userData['TIME_REGISTERED'], $userData['TIME_MODIFIED'], $userData['VALID']);

                // Mise à jour des données locales
                $this->_db->update(T_USERS, $userData, ['UID = ?' => $uid]);
                unset($user, $casUser);

            }

            $this->getProgressBar()->stop();

            if (!$isCloningStep) {
                $sql = 'ALTER TABLE USER ADD UNIQUE KEY `U_USERNAME` (`USERNAME`), ADD KEY `API_PASSWORD` (`API_PASSWORD`), ADD KEY `EMAIL` (`EMAIL`), ADD KEY `IS_VALID` (`IS_VALID`), ADD KEY `FIRSTNAME` (`FIRSTNAME`), ADD KEY `LASTNAME` (`LASTNAME`)';
                $this->alterTable($sql);
            }

        }

        return true;

    }

    private function alterTable(string $sql): bool
    {
        $this->displayInfo('**** ALTER TABLE ***', true);

        $this->displayTrace($sql, true);

        return $this->getDb()->prepare($sql)->execute();
    }
}

$script = new NewFieldsUpgradeUser();
$script->run();