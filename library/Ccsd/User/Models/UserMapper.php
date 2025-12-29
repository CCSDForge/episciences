<?php

/**
 * Mapper pour le modèle de la table utilisateurs CCSD
 * @author rtournoy
 *
 */
class Ccsd_User_Models_UserMapper {

    /** @var Zend_Db_Table_Abstract */
    protected $_dbTable;

    /**
     * @param $dbTable
     * @return Zend_Db_Table_Abstract
     * @throws Exception
     */
    public function setDbTable($dbTable) {
        if (is_string($dbTable)) {
            $dbTable = new $dbTable ();
        }
        if (!$dbTable instanceof Zend_Db_Table_Abstract) {
            throw new Exception('Invalid table data gateway provided');
        }
        $this->_dbTable = $dbTable;
        return $dbTable;
    }

    /**
     * @return Zend_Db_Table_Abstract
     * @throws Exception
     */
    public function getDbTable() {
        if (null === $this->_dbTable) {
            $this->setDbTable('Ccsd_User_Models_DbTable_User');
        }

        return $this->_dbTable;
    }

    /**
     * Enregistre un nouveau compte utilisateur ou sauvegarde les modifications d'un existant
     * @param Ccsd_User_Models_User $user object Utilisateur
     * @param bool $forceInsert
     * @return int
     * @throws Exception
     */
    public function save(Ccsd_User_Models_User $user, $forceInsert = false) {

        // création compte
        if ((null === $user->getUid()) || ( $forceInsert === true)) {

            $data = array(
                'USERNAME' => $user->getUsername(),
                'PASSWORD' => $user->getPassword(),
                'EMAIL' => $user->getEmail(),
                'CIV' => $user->getCiv(),
                'LASTNAME' => $user->getLastname(),
                'FIRSTNAME' => $user->getFirstname(),
                'MIDDLENAME' => $user->getMiddlename(),
                'TIME_REGISTERED' => $user->getTime_registered(),
                'TIME_MODIFIED' => $user->getTime_modified(),
                'VALID' => $user->getValid()
            );

            // Le UID est déjà connu, pas d'autoincrément
            if ($forceInsert === true) {
                $data ['UID'] = $user->getUid();
            }

            $lastInsertId = $this->getDbTable()->insert($data);

            if ($forceInsert === true) {
                // $user->setUid($lastInsertId);
            } else {
                $user->setUid($lastInsertId);
            }

            $user->setFtp_home();
            $this->saveFtpHome($user);

            // Le UID est déjà connu, pas d'autoincrément
            if ($forceInsert === true) {
                return $user->getUid();
            }

            return (int) $lastInsertId; // UID de l'utilisateur ajouté
        } else {
            // modification compte
            $user->setTime_modified();
            $data = array(
                'UID' => $user->getUid(),
                'PASSWORD' => $user->getPassword(),
                'EMAIL' => $user->getEmail(),
                'CIV' => $user->getCiv(),
                'LASTNAME' => $user->getLastname(),
                'FIRSTNAME' => $user->getFirstname(),
                'MIDDLENAME' => $user->getMiddlename(),
                'TIME_MODIFIED' => $user->getTime_modified(),
                'VALID' => $user->getValid()
            );

            // 'USERNAME' => $user->getUsername(),
            // on ne met à jour le password que si l'utilisateur à rempli le
            // champ
            if (null == $user->getPassword()) {
                unset($data ['PASSWORD']);
            }

            if (null == $user->getValid()) {
                unset($data ['VALID']);
            }

            $result = $this->getDbTable()->update($data, array(
                'UID = ?' => $user->getUid()
            ));

            if (1 != $result) {
                return false;
            } else {
                return $user->getUid();
            }
        }
    }

    /**
     *
     * @param Ccsd_User_Models_User $user
     * @return boolean
     * @throws Exception
     */
    private function saveFtpHome(Ccsd_User_Models_User $user) {
        $data = array(
            'FTP_HOME' => $user->getFtp_home()
        );

        try {
            $result = $this->getDbTable()->update($data, array(
                'UID = ?' => $user->getUid()
            ));

            if (1 != $result) {
                return false;
            } else {
                return true;
            }
        } catch (Zend_Db_Adapter_Exception $e) {
            return false;
        }
    }

    /**
     * Sauvegarde le mot de passe de l'utilisateur
     * @param Ccsd_User_Models_User $user
     * @return int
     * @throws Exception
     */
    public function savePassword(Ccsd_User_Models_User $user) {
        $data = array(
            'UID' => $user->getUid(),
            'PASSWORD' => $user->getPassword(),
            'TIME_MODIFIED' => $user->getTime_modified()
        );

        return $this->getDbTable()->update($data, array(
            'UID = ?' => $user->getUid()
        ));
    }

    /**
     * Recherche un utilisateur par son UID
     * @param integer $uid
     * @param Ccsd_User_Models_User|null $user
     * @return Zend_Db_Table_Row_Abstract
     * @throws Exception
     */
    public function find($uid, Ccsd_User_Models_User $user = null) {


        $select = $this->getDbTable()->select()->where('UID = ?', $uid);

        $select->from($this->getDbTable(), ['UID',
            'USERNAME',
            'EMAIL',
            'CIV',
            'FIRSTNAME',
            'MIDDLENAME',
            'LASTNAME',
            'TIME_REGISTERED',
            'TIME_MODIFIED',
            'VALID']);

        $row = $this->getDbTable()->fetchRow($select);


        if (!isset($row) || 0 === count($row->toArray())) {
            return null;
        }

        if ($user !== null) {
            $user->setUid($row->UID)->setUsername($row->USERNAME)->setEmail($row->EMAIL)->setCiv($row->CIV)->setLastname($row->LASTNAME)->setFirstname($row->FIRSTNAME)->setMiddlename($row->MIDDLENAME);
            $user->setTime_registered($row->TIME_REGISTERED)->setTime_modified($row->TIME_MODIFIED)->setValid($row->VALID);
        }
        return $row;
    }

    /**
     * Cherche des logins à partir d'une adresse mail
     * @param string $email
     * @return NULL Zend_Db_Table_Rowset
     * @throws Exception
     */
    public function findLoginByEmail(string $email) :?Zend_Db_Table_Rowset {
        $select = $this->getDbTable()->select()->where('EMAIL = ?', $email)->order(array(
            'VALID DESC',
            'USERNAME ASC'
        ));

        $select->from($this->getDbTable(), array(
            'USERNAME',
            'TIME_REGISTERED',
            'VALID'
        ));

        $rows = $this->getDbTable()->fetchAll($select);

        if (! $rows) {
            return null;
        }

        return $rows;
    }

    /**
     * Trouve un utilisateur avec un compte actif, par son login, sinon renvoi
     * null
     *
     * @param string $username
     * @return null|Zend_Db_Table_Rowset_Abstract object:
     * @throws Exception
     */
    public function findByUsername($username) {
        $select = $this->getDbTable()->select()->from($this->getDbTable())->where('USERNAME = ?', $username)->where('VALID= ?', 1);
        return $this->getDbTable()->fetchAll($select);
    }


    /**
     * Trouve un utilisateur avec un compte actif ou pas, par son login ou UID, sinon renvoi
     * null
     *
     * @param string|int $info
     * @param bool $strict [true: active accounts only]
     * @return Zend_Db_Table_Rowset_Abstract
     * @throws Exception
     */
    public function findByUsernameOrUID($info, bool $strict = true): ?\Zend_Db_Table_Rowset_Abstract
    {
        $select = $this->getDbTable()->select()->where('USERNAME = ? OR UID = ?', $info);

        if ($strict) {
            $select->where('VALID = ?', 1);
        }

        $select->from($this->getDbTable(), ['UID',
            'USERNAME',
            'EMAIL',
            'CIV',
            'FIRSTNAME',
            'MIDDLENAME',
            'LASTNAME',
            'TIME_REGISTERED',
            'TIME_MODIFIED']);

        $rows = $this->getDbTable()->fetchAll($select);

        if (count($rows) === 0) {
            return null;
        }
        return $rows;
    }

    /**
     * @param string $username
     * @param string $password
     * @return null|Zend_Db_Table_Row_Abstract
     * @throws Exception
     */
    public function findByUsernamePassword($username, $password) {
        $user = new Ccsd_User_Models_User(array(
            'USERNAME' => $username,
            'PASSWORD' => $password
        ));
        $password = $user->getPassword();
        $username = $user->getUsername();

        $select = $this->getDbTable()->select()->where('USERNAME = ?', $username)->where('PASSWORD = ?', $password)->where('VALID= ?', 1);

        $select->from($this->getDbTable(), array(
            'UID',
            'USERNAME',
            'EMAIL',
            'CIV',
            'LASTNAME',
            'FIRSTNAME',
            'MIDDLENAME',
            'TIME_REGISTERED',
            'TIME_MODIFIED'
        ));
        $rows = $this->getDbTable()->fetchAll($select);

        if (0 == count($rows)) {
            return null;
        }

        return $rows->current();
    }

    /**
     * Active un compte selon son UID
     *
     * @param integer $uid
     * @return void true si succès | false si échec
     * @throws Zend_Db_Adapter_Exception
     */
    public function activateAccountByUid($uid) {
        $uid = intval(filter_var($uid, FILTER_SANITIZE_NUMBER_INT));

        $result = $this->getDbTable()->update(array(
            'VALID' => 1
                ), array(
            'UID = ?' => $uid
        ));

        if ($result != 1) {
            throw new Zend_Db_Adapter_Exception("Erreur lors de l'activation du compte. Echec de la requête.");
        }

        // cherche le username de l'utilisateur activé
        $userMapper = new Ccsd_User_Models_UserMapper ();
        $user = new Ccsd_User_Models_User ();

        $userResult = $userMapper->find($uid, $user);
        $user->setUsername($userResult->USERNAME);

        // ajoute quota FTP à l'utilisateur activé
        $ufq = new Ccsd_User_Models_UserFtpQuota(array(
            'username' => $user->getUsername()
        ));

        $result = $ufq->save();

        if ($result == false) {
            throw new Zend_Db_Adapter_Exception("Erreur lors de l'activation des quotas FTP du compte.");
        }
    }

    /**
     * Enregistre le changement d'identité d'un utilisateur
     *
     * @param int $fromUid
     * @param int $toUid
     * @param string $application
     * @param string $action
     *            [GRANTED|DENIED]
     * @return bool
     */
    static public function suLog($fromUid, $toUid, $action, $application = 'unknown') { //  Déplacer les arguments "$application" après les arguments sans valeur par défaut
        $db = new Ccsd_User_Models_DbTable_SuLog ();
        try {
            $data = array(
                'FROM_UID' => (int) $fromUid,
                'TO_UID' => (int) $toUid,
                'APPLICATION' => $application,
                'ACTION' => $action
            );

            return !empty($db->insert($data));
        } catch (Exception $e) {
            return false;
        }
    }

}
