<?php

require_once "JournalScript.php";


class NewFieldsUpgradeUser extends JournalScript
{

    public function __construct()
    {
        $this->displayInfo('*** Upgrade USER table' . PHP_EOL, true, ['bold']);
        $args = [];
        $this->setArgs(array_merge($this->getArgs(), $args));
        parent::__construct();
    }

    public function run()
    {
        $t0 = time();

        if ($this->isVerbose()) {
            $this->displayInfo("Verbose mode: 1");
            $this->displayInfo("Debug mode: " . $this->isDebug());
        }

        $this->checkAppEnv();

        defineSQLTableConstants();
        defineApplicationConstants();

        $this->initApp();
        $this->initDb();

        if ($this->upgradeTable()) {
            $this->displayInfo('Update completed. Good Bye ! =)', true);
        } else {
            $this->displayCritical('Update incomplete.', true);
        }

        $t1 = time();
        $time = $t1 - $t0;

        $this->displayTrace("The script took $time seconds to run.", true);
    }

    /**
     * @return bool
     */
    public function upgradeTable(): bool
    {
        $isCloned = false;
        $isCloningStep = $this->existColumn('USERNAME', T_USERS);

        if (!$isCloningStep && $isCloned = $this->cloneTable(T_USERS)) {
            $this->displaySuccess('Generate a new Table => OK', true);
        }

        $sql = "ALTER TABLE USER  ADD USERNAME VARCHAR(100) NOT NULL, ADD API_PASSWORD VARCHAR(255) NOT NULL, ADD EMAIL VARCHAR(320) NOT NULL, ADD CIV VARCHAR(255) DEFAULT NULL, ADD LASTNAME VARCHAR(100) NOT NULL, ADD FIRSTNAME VARCHAR(100) DEFAULT NULL, ADD MIDDLENAME VARCHAR(100) DEFAULT NULL, ADD REGISTRATION_DATE DATETIME DEFAULT NULL, ADD MODIFICATION_DATE DATETIME DEFAULT NULL, ADD IS_VALID TINYINT(1) NOT NULL DEFAULT 1";

        if (!$isCloningStep && $isCloned) {
            $this->alterTable($sql);
        }

        $isProcessing = $this->updateProcessing();

        if ($isProcessing && !$isCloningStep) {
            $sql = 'ALTER TABLE USER ADD UNIQUE KEY `U_USERNAME` (`USERNAME`), ADD KEY `API_PASSWORD` (`API_PASSWORD`), ADD KEY `EMAIL` (`EMAIL`), ADD KEY `IS_VALID` (`IS_VALID`), ADD KEY `FIRSTNAME` (`FIRSTNAME`), ADD KEY `LASTNAME` (`LASTNAME`)';
            return $this->alterTable($sql);
        }

        return true;
    }

    private function alterTable(string $sql): bool
    {
        $this->displayInfo('**** ALTER TABLE ***', true);

        $this->displayTrace($sql, true);

        return $this->getDb()->prepare($sql)->execute();
    }

    private function updateProcessing(): bool
    {
        $nbUpdate = 0;
        $notInCasNbr = 0;
        $uidSNotFoundInCas = [];

        $db = $this->getDb();
        $dataQuery = $db->select()->from(T_USERS)->order('UID DESC');

        $uidS = $db->fetchCol($dataQuery);
        $count = count($uidS);

        $this->getProgressBar()->start();

        foreach ($uidS as $key => $uid) {
            $user = new Episciences_User();
            $casUser = new Ccsd_User_Models_UserMapper();

            $this->displayInfo('*** Update processing (' . $key . '/' . $count . ') [UID = ' . $uid . ' ] ***', true);
            $progress = round((($key + 1) * 100) / $count);
            $this->getProgressBar()->setProgress($progress);


            try {
                $casUser = $casUser->find($uid, $user);
                /** @var Episciences_User $localUserData */
                $localUserData = $user->find($uid);
                if($user->getScreenName() === ''){
                    $user->setScreenName();
                    $this->displayTrace('Generate ScreeName', true);
                }

            } catch (Exception $e) {
                $this->displayError($e->getMessage());
                return false;
            }

            if ($casUser) {
                $casUserData = $casUser->toArray();
                $localUserData['REGISTRATION_DATE'] = $user->getTime_modified(); // cas modified time
                $userData = array_merge($localUserData, $casUserData);
                unset($userData['TIME_REGISTERED'], $userData['TIME_MODIFIED'], $userData['VALID']);
                // Mise à jour des données locales
                $nbUpdate += $this->_db->update(T_USERS, $userData, ['UID = ?' => $uid]);
                unset($user, $casUser);
            } else {
                $this->displayCritical('PHP Fatal error:  Uncaught Error: Call to a member function toArray() on null => ');
                $this->displayError('CAS user (Uid = ' . $uid . ') not found !');
                $uidSNotFoundInCas[] = $uid;

                // Fix integrity constraint violation: 1062 Duplicate entry '' on update table indexes
                $casUserData = [
                    'USERNAME' => 'deletedUserName-'. $uid,
                    'EMAIL' => 'deletedEmail-'.$uid,
                    'LASTNAME' => 'deletedLastName-'.$uid
                ];

                $userData = array_merge($localUserData, $casUserData );
                $notInCasNbr += $this->_db->update(T_USERS, $userData, ['UID = ?' => $uid]);

            }

        }// end foreach

        $this->getProgressBar()->stop();

        $this->displayInfo('*** Update summary ***', true);
        $this->displaySuccess('Updated rows: ' . $nbUpdate, true);

        if( !empty($uidSNotFoundInCas)){
            $this->displayError('Not updated rows: (' . $notInCasNbr . ') ' . json_encode($uidSNotFoundInCas));
        }

        return true;
    }
}

$script = new NewFieldsUpgradeUser();
$script->run();