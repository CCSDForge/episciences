<?php

/**
 * Mapper pour l'objet UserTokens
 * @author rtournoy
 *
 */
class Ccsd_User_Models_UserTokensMapper
{

    protected $_dbTable;

    /**
     * Enregistre un token
     *
     * @param Ccsd_User_Models_UserTokens $userTokens
     * @return integer Dernier ID de token enregistré
     */
    public function save(Ccsd_User_Models_UserTokens $userTokens)
    {
        $data = [
            'UID' => $userTokens->getUid(),
            'EMAIL' => $userTokens->getEmail(),
            'TOKEN' => $userTokens->getToken(),
            'TIME_MODIFIED' => $userTokens->getTime_modified(),
            'USAGE' => $userTokens->getUsage()
        ];

        $dbTable = $this->getDbTable();

        return $dbTable->insert($data);
    }

    public function getDbTable()
    {
        if (null === $this->_dbTable) {
            $this->setDbTable('Ccsd_User_Models_DbTable_UserTokens');
        }
        return $this->_dbTable;
    }

    public function setDbTable($dbTable)
    {
        if (is_string($dbTable)) {
            $dbTable = new $dbTable();
        }
        if (!$dbTable instanceof Zend_Db_Table_Abstract) {
            throw new RuntimeException('Invalid table data gateway provided');
        }
        $this->_dbTable = $dbTable;
        return $this;
    }

    /**
     * Vérifie si un token existe
     * Si oui retourne les infos sur la ligne du token
     *
     * @param string $token
     * @param Ccsd_User_Models_UserTokens $userTokens
     * @return array|null
     */
    public function findByToken(string $token, Ccsd_User_Models_UserTokens $userTokens): ?array
    {
        /** @var Zend_Db_Table_Rowset $result */
        $result = $this->getDbTable()->find($token);
        $aResult = $result->toArray();

        if (0 === count($result->toArray()[Episciences_Tools::epi_array_key_first($aResult)])) {
            return null;
        }

        /** @var Zend_Db_Table_Row $row */

        $row = $result->current();
        $aRow = $row->toArray();

        $userTokens
            ->setUid($aRow['UID'])
            ->setEmail($aRow['EMAIL'])
            ->setToken($aRow['TOKEN'])
            ->setTime_modified($aRow['TIME_MODIFIED'])
            ->setUsage($aRow['USAGE']);

        return $aRow; // token data
    }

    /**
     * Supprime un token
     *
     * @param string $token
     */
    public function delete($token)
    {
        $this->getDbTable()->delete(['TOKEN = ?' => $token]);
    }
}

