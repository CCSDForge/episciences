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
        }
    }

    /**
     * @return bool
     */
    public function upgradeTable(): bool
    {
        $db = $this->getDb();
        $dataQuery = $db->select()->from(T_USERS);
        $uidS = $db->fetchCol($dataQuery);

        $isCloned = $this->cloneTable(T_USERS);

        if ($this->existColumn('USERNAME', T_USERS) || ($isCloned && $this->alterTable())) {

            $user = new Episciences_User();
            $casUser = new Ccsd_User_Models_UserMapper();

            foreach ($uidS as $uid) {

                try {
                    $casUser = $casUser->find($uid, $user);
                    $localUserData = $user->find($uid);

                } catch (Exception $e) {
                    $this->displayError($e->getMessage());
                    return false;
                }

                $casUserData = $casUser->toArray();

                $userData = array_merge($localUserData, $casUserData);

                unset($userData['TIME_REGISTERED'], $userData['TIME_MODIFIED'], $userData['VALID']);

                // Mise à jour des données locales
                $this->_db->update(T_USERS, $userData, ['UID = ?' => $uid]);

            }
        }

        return true;

    }


    private function alterTable(): bool
    {
        $this->displayInfo('**** ALTER TABLE ***', true);

        $sql = "ALTER TABLE USER  ADD USERNAME VARCHAR(100) NOT NULL, ADD API_PASSWORD VARCHAR(255) NOT NULL, ADD EMAIL VARCHAR(320) NOT NULL, ADD CIV VARCHAR(255) DEFAULT NULL,
    ADD LASTNAME VARCHAR(100) NOT NULL, ADD FIRSTNAME VARCHAR(100) DEFAULT NULL, ADD MIDDLENAME VARCHAR(100) DEFAULT NULL, ADD REGISTRATION_DATE DATETIME DEFAULT NULL, ADD MODIFICATION_DATE DATETIME DEFAULT NULL,
    ADD IS_VALID TINYINT(1) NOT NULL";

        return $this->getDb()->prepare($sql)->execute();
    }
}

$script = new NewFieldsUpgradeUser();
$script->run();