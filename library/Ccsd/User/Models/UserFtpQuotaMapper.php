<?php

/**
 * Class Ccsd_User_Models_UserFtpQuotaMapper
 */
class Ccsd_User_Models_UserFtpQuotaMapper
{


    const USERNAME = 'username';
    const QUOTA_TYPE = 'quota_type';
    const PAR_SESSION = 'par_session';
    const LIMIT_TYPE = 'limit_type';
    const BYTES_UP_TOTAL = 'bytes_up_total';
    const BYTES_DOWN_TOTAL = 'bytes_down_total';
    const BYTES_TRANSFER_TOTAL = 'bytes_transfer_total';
    const FILES_UP_TOTAL = 'files_up_total';
    const FILES_DOWN_TOTAL = 'files_down_total';
    const FILES_TRANSFER_TOTAL = 'files_transfer_total';
    const BYTES_UP_LIMIT = 'bytes_up_limit';
    const BYTES_DOWN_LIMIT = 'bytes_down_limit';
    const BYTES_TRANSFER_LIMIT = 'bytes_transfer_limit';
    const FILES_UP_LIMIT = 'files_up_limit';
    const FILES_DOWN_LIMIT = 'files_down_limit';
    const FILES_TRANSFER_LIMIT = 'files_transfer_limit';


    /**
     * @var string|Zend_Db_Table_Abstract
     */
    protected $_dbTable;

    public function save(Ccsd_User_Models_UserFtpQuota $ufq)
    {
        if ('' == $ufq->getId()) {

            $data = array(
                self::USERNAME => $ufq->getUsername(),
                self::QUOTA_TYPE => $ufq->getQuota_type(),
                self::PAR_SESSION => $ufq->getPar_session(),
                self::LIMIT_TYPE => $ufq->getLimit_type(),
                self::BYTES_UP_LIMIT => $ufq->getBytes_up_limit(),
                self::BYTES_DOWN_LIMIT => $ufq->getBytes_down_limit(),
                self::BYTES_TRANSFER_LIMIT => $ufq->getBytes_transfer_limit(),
                self::FILES_UP_LIMIT => $ufq->getFiles_up_limit(),
                self::FILES_DOWN_LIMIT => $ufq->getFiles_down_limit(),
                self::FILES_TRANSFER_LIMIT => $ufq->getFiles_transfer_limit()
            );

            try {
                $lastInsertId = $this->getDbTable()->insert($data);
            } catch (Zend_Db_Adapter_Exception $e) {
                error_log($e->getMessage());
                return false;
            }

            $ufq->setId($lastInsertId);

        } else {

            // modification

            $data = array(
                'Id' => $ufq->getId(),
                self::USERNAME => $ufq->getUsername(),
                self::QUOTA_TYPE => $ufq->getQuota_type(),
                self::PAR_SESSION => $ufq->getPar_session(),
                self::LIMIT_TYPE => $ufq->getLimit_type(),
                self::BYTES_UP_LIMIT => $ufq->getBytes_up_limit(),
                self::BYTES_DOWN_LIMIT => $ufq->getBytes_down_limit(),
                self::BYTES_TRANSFER_LIMIT => $ufq->getBytes_transfer_limit(),
                self::FILES_UP_LIMIT => $ufq->getFiles_up_limit(),
                self::FILES_DOWN_LIMIT => $ufq->getFiles_down_limit(),
                self::FILES_TRANSFER_LIMIT => $ufq->getFiles_transfer_limit()
            );

            try {
                $this->getDbTable()->update($data, array(
                    self::USERNAME . ' = ?' => $ufq->getUsername()
                ));
            } catch (Zend_Db_Adapter_Exception $e) {
                error_log($e->getMessage());
                return false;
            }

        }

        return $this;

    }

    /**
     * @return Zend_Db_Table_Abstract
     */
    public function getDbTable()
    {
        if (null === $this->_dbTable) {
            $this->setDbTable('Ccsd_User_Models_DbTable_UserFtpQuota');
        }

        return $this->_dbTable;
    }

    public function setDbTable($dbTable)
    {
        if (is_string($dbTable)) {
            $dbTable = new $dbTable();
        }
        if (!$dbTable instanceof Zend_Db_Table_Abstract) {
            throw new InvalidArgumentException('Invalid table data gateway provided');
        }
        $this->_dbTable = $dbTable;
        return $this;
    }

    public function findLimitsByUsername($username)
    {
        $select = $this->getDbTable()
            ->select()
            ->where(self::USERNAME . ' = ?', $username);

        $select->from($this->getDbTable(), array(
            self::BYTES_UP_LIMIT,
            self::BYTES_DOWN_LIMIT,
            self::BYTES_TRANSFER_LIMIT,
            self::FILES_UP_LIMIT,
            self::FILES_DOWN_LIMIT,
            self::FILES_TRANSFER_LIMIT
        ));

        $rows = $this->getDbTable()->fetchAll($select);

        if (0 == count($rows)) {
            return null;
        }

        return $rows;
    }

    public function findQuotaByUsername($username)
    {
        $select = $this->setDbTable('Ccsd_User_Models_DbTable_UserFtpQuotaTotal')
            ->select()
            ->where(self::USERNAME . ' = ?', $username);

        $select->from($this->getDbTable(), array(
            self::BYTES_UP_TOTAL,
            self::BYTES_DOWN_TOTAL,
            self::BYTES_TRANSFER_TOTAL,
            self::FILES_UP_TOTAL,
            self::FILES_DOWN_TOTAL,
            self::FILES_TRANSFER_TOTAL
        ));

        $rows = $this->getDbTable()->fetchAll($select);

        if (0 == count($rows)) {
            return null;
        }

        return $rows;
    }
} ///end Class




















