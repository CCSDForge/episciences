<?php

/**
 * Fusion de profil utilisateurs
 */
class Ccsd_User_Merge extends Zend_Db_Table_Abstract
{

    /**
     * Tables de la BDD à ne pas modifier
     * La méthode qui fusionne les profils ne modifiera pas ces tables
     * @var array
     */
    protected $_tablesBlacklist = [];
    /**
     * Db adapter
     * @var object
     */
    protected $_db;
    /**
     * Table des profils utilisateurs dans l'application
     * @var string
     */
    private $_applicationUsersTable;
    /**
     * UID de l'utilisateur source
     * @var int
     */
    private $_uidFrom;

    /**
     *
     * @var int
     */
    private $_uidTo;

    /**
     *
     * @var string
     */
    private $_userMergeLogTable;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_db = Zend_Db_Table_Abstract::getDefaultAdapter();
    }

    /**
     * Retourne le nombre d'occurence de la valeur $colValue dans la colonne $columnName
     * @param string $columnName
     * @param mixed $colValue
     * @return int[]
     * @throws Zend_Db_Exception
     */
    public function getValueOccurr($columnName, $colValue)
    {
        $res = [];
        foreach ($this->getTablesWithColumnName($columnName) as $tableName) {
            $numberOfLines = $this->getLineCount($tableName, $columnName, $colValue);
            if ($numberOfLines != 0) {
                $res[$tableName] = $numberOfLines;
            }
        }
        return $res;
    }

    /**
     * Retourne une liste de tables contenant les colonnes du tableau $columnName
     * @param string $columnName
     * @return string[]
     * @throws Zend_Db_Exception
     */
    public function getTablesWithColumnName($columnName)
    {
        $DBName = $this->_db->getConfig()['dbname'];
        $res = [];
        $blackListTables = $this->getTablesBlacklist();

        $sql = "SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '$DBName' AND  COLUMN_NAME = '$columnName'";

        $stmt = $this->_db->query($sql);
        $rows = $stmt->fetchAll();

        foreach ($rows as $k => $v) {
            $tableName = $v['TABLE_NAME'];
            // Tables à ne pas toucher
            if (in_array($tableName, $blackListTables)) {
                continue;
            }
            $res[] = $tableName;
        }
        return $res;
    }

    /**
     * @return array
     */
    public function getTablesBlacklist()
    {
        return $this->_tablesBlacklist;
    }

    /**
     * @param $tablesBlacklist
     * @return $this
     */
    public function setTablesBlacklist($tablesBlacklist)
    {
        $this->_tablesBlacklist = $tablesBlacklist;
        return $this;
    }

    /**
     * Retourne le nombre de lignes contenant une valeur $colValue dans une table $tableName pour la colonne $columnName
     * @param string $tableName
     * @param string $columnName
     * @param string[|int $colValue
     * @return int  nombre de lignes contenant la valeur
     */
    public function getLineCount($tableName, $columnName, $colValue)
    {
        try {
            $queryNumberOccurr = $this->_db->select()->from($tableName, ["numberOccurr" => "COUNT(*)"])->where($columnName . ' = ?', $colValue);

            $res = $this->_db->fetchRow($queryNumberOccurr);
            $lineCount = (int)$res["numberOccurr"];
        } catch (Exception $exc) {
            $lineCount = null;
        }

        return $lineCount;
    }

    /**
     * Remplace le profil de l'utilisateur cible par celui de l'utilisateur source
     * Pour le cas ou l'utilisateur cible n'a pas de profil dans l'application
     * @return int nombre de lignes modifiées
     */
    public function moveUserProfile()
    {

        try {
            $nb = $this->_db->update($this->getApplicationUsersTable(), ['UID' => $this->getUidTo()], 'UID = ' . $this->getUidFrom());
        } catch (Exception $exc) {
            $nb = 0;
        }

        return (int)$nb;
    }

    /**
     * @return string
     */
    public function getApplicationUsersTable()
    {
        return $this->_applicationUsersTable;
    }

    /**
     * @param $applicationUsersTable
     * @return $this
     */
    public function setApplicationUsersTable($applicationUsersTable)
    {
        $this->_applicationUsersTable = $applicationUsersTable;
        return $this;
    }

    /**
     * @return int
     */
    public function getUidTo()
    {
        return $this->_uidTo;
    }

    /**
     * @param $uidTo
     * @return $this
     */
    public function setUidTo($uidTo)
    {
        $this->_uidTo = (int)$uidTo;
        return $this;
    }

    /**
     * @return int
     */
    public function getUidFrom()
    {
        return $this->_uidFrom;
    }

    /**
     * @param $uidFrom
     * @return $this
     */
    public function setUidFrom($uidFrom)
    {
        $this->_uidFrom = (int)$uidFrom;
        return $this;
    }

    /**
     * Fusionne les profils utilisateurs
     * @param array $tables Tables à modifier
     * @return array
     */
    public function mergeUsers($tables)
    {
        $result = [];
        $uidFrom = $this->getUidFrom();
        $uidTo = $this->getUidTo();
        if (!(($uidFrom > 0) && ($uidTo > 0))) {
            die("Uids cannot be null here! $uidFrom -> $uidTo");
        }
        $bindData = ['UID' => $uidTo];

        foreach ($tables as $table) {
            if ($table == 'USER_LIBRARY_SHELF') {
                $this->_db->delete($table, "UID = $uidFrom");
                continue;
            }

            if (in_array($table, $this->getTablesBlacklist())) {
                continue;
            }

            try {
                $result[$table]['ok'] = $this->_db->update($table, $bindData, 'UID = ' . $this->getUidFrom());
            } catch (Exception $e) {
                $result[$table]['error'] = 'Erreur : pas de modification';
            }
        }

        return $result;
    }

    /**
     * Loggue l'action de fusion de profil
     * @param int $uidOfMergeOperator UID de l'utilisateur qui fusionne les profils
     * @return boolean
     */
    public function logUserMerge($uidOfMergeOperator)
    {

        try {
            $res = $this->_db->insert($this->getUserMergeLogTable(), ['UID_OPERATOR' => (int)$uidOfMergeOperator, 'UID_FROM' => $this->getUidFrom(), 'UID_TO' => $this->getUidTo()]);
        } catch (Zend_Db_Adapter_Exception $exc) {
            $res = false;
            error_log($exc->getMessage(), 0);
        }

        return $res;
    }

    /**
     * @return string
     */
    public function getUserMergeLogTable()
    {
        return $this->_userMergeLogTable;
    }

    /**
     * @param $_userMergeLogTable
     * @return $this
     */
    public function setUserMergeLogTable($_userMergeLogTable)
    {
        $this->_userMergeLogTable = $_userMergeLogTable;
        return $this;
    }

    /**
     * Supprime un profil utilisateur
     * @return int nombre de profils utilisateurs supprimés
     */
    public function removeUserProfile()
    {

        try {
            $nb = $this->_db->delete($this->getApplicationUsersTable(), 'UID = ' . $this->getUidFrom());
        } catch (Exception $exc) {
            $nb = 0;
        }

        return (int)$nb;
    }

}
