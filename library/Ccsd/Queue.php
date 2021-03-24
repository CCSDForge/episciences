<?php

/**
 * Class Ccsd_Queue
 */
abstract class Ccsd_Queue
{

    const O_UPDATE = 'UPDATE';
    const O_DELETE = 'DELETE';

    static $_maxSelectFromIndexQueue = 100;

    /** @var int primary key */
    protected $_id;
    /** @var int document id to process */
    protected $_docid;
    /** @var  string */
    protected $_application;
    /** @var  string */
    protected $_origin;
    /** @var  string */
    protected $_core;
    /** @var  int */
    protected $_priority;
    /** @var string */
    protected $_hostname;
    /** @var string */
    protected $_status;
    /** @var string */
    protected $_message;

    /** @var  string table name */
    protected $_sqlTableName;
    /** @var  int sql limit number of lines to process at once */
    protected $_sqlSelectRowsLimit;

    /** @var  string */
    protected $_dataSource;

    /** @var */
    protected $_sqlDbAdapter;


    /**
     * Ccsd_Queue constructor.
     */
    public function __construct()
    {
        $this->setHostname();
    }


    /**
     * @return Zend_Db_Adapter_Abstract
     */
    public function getSqlDbAdapter()
    {
        return $this->_sqlDbAdapter;
    }

    /**
     * @param $sqlDbAdapter
     */
    public function setSqlDbAdapter($sqlDbAdapter)
    {
        $this->_sqlDbAdapter = $sqlDbAdapter;
    }

    /**
     * Retourne des données à indexer depuis la table d'indexation
     *
     * @return array tableau de docid à indexer pour un core donné
     */
    public function getListOfDocidFromQueue()
    {
        $this->setDataSource('cron');
        $this->lockRows();
        $rows = $this->selectLockedRows();
        if (!is_array($rows)) {
            return null;
        }
        return array_column($rows, 0);
    }

    /**
     *
     * @param string $_dataSource
     */
    public function setDataSource($_dataSource = 'cmdLine')
    {
        $this->_dataSource = $_dataSource;
    }

    /**
     * Change l'état des lignes de ok à locked
     */
    private function lockRows()
    {
        $sqlUpdate = "UPDATE " . $this->getSqlTableName() . " SET STATUS = 'locked', PID = :pid, HOSTNAME = :hostname WHERE CORE = :core AND ORIGIN=:origin AND STATUS= 'ok' LIMIT :limit";

        $stmt = $this->getSqlDbAdapter()->prepare($sqlUpdate);
        $stmt->bindValue(':core', $this->getCore(), PDO::PARAM_STR);
        $stmt->bindValue(':pid', $this->getPid(), PDO::PARAM_INT);
        $stmt->bindValue(':hostname', $this->getHostname(), PDO::PARAM_STR);
        $stmt->bindValue(':origin', $this->getOrigin(), PDO::PARAM_STR);
        $stmt->bindValue(':limit', $this->getSqlSelectRowsLimit(), PDO::PARAM_INT);

        try {
            return $stmt->execute();
        } catch (Exception $e) {
            Ccsd_Log::message($e->getMessage(), true, 'ERR');
            return false;
        }
    }

    /**
     * @return string
     */
    public function getSqlTableName(): string
    {
        return $this->_sqlTableName;
    }

    /**
     * @param string $tableName
     */
    public function setSqlTableName(string $tableName)
    {
        $this->_sqlTableName = filter_var($tableName, FILTER_SANITIZE_STRING);
    }

    /**
     * @return string
     */
    public function getCore(): string
    {
        return $this->_core;
    }

    /**
     * @param string $core
     */
    public function setCore(string $core)
    {
        $this->_core = filter_var($core, FILTER_SANITIZE_STRING);
    }

    /**
     * @return int
     */
    public function getPid(): int
    {
        return getmypid();
    }

    /**
     * @return string
     */
    public function getHostname(): string
    {
        return $this->_hostname;
    }

    /**
     *
     */
    public function setHostname()
    {
        $this->_hostname = filter_var(getHostname(), FILTER_SANITIZE_STRING);
    }

    /**
     * @return string
     */
    public function getOrigin(): string
    {
        return $this->_origin;
    }

    /**
     * @param string $origin
     */
    public function setOrigin(string $origin)
    {
        // compare uppercase
        $origin = mb_strtoupper($origin);

        if (($origin != self::O_UPDATE) && ($origin != self::O_DELETE)) {
            $origin = self::O_UPDATE;
        }
        $this->_origin = $origin;
    }

    /**
     * @return int
     */
    public function getSqlSelectRowsLimit()
    {
        if (null == $this->_sqlSelectRowsLimit) {
            return static::$_maxSelectFromIndexQueue;
        }
        return $this->_sqlSelectRowsLimit;
    }

    /**
     * @param int $sqlSelectRowsLimit
     */
    public function setSqlSelectRowsLimit($sqlSelectRowsLimit = null)
    {
        if (null == $sqlSelectRowsLimit) {
            $sqlSelectRowsLimit = static::$_maxSelectFromIndexQueue;
        }
        $this->_sqlSelectRowsLimit = (int)$sqlSelectRowsLimit;
    }

    /**
     * Retourne une liste de docid à indexer qui sont en statut "locked" pour
     * traitement
     */
    private function selectLockedRows()
    {
        $sqlSelect = "SELECT DOCID FROM " . $this->getSqlTableName() . " WHERE STATUS = 'locked' AND HOSTNAME = :hostname AND ORIGIN=:origin AND CORE = :core AND PID = :pid ORDER BY PRIORITY DESC LIMIT :limit";

        $stmt = $this->getSqlDbAdapter()->prepare($sqlSelect);

        $stmt->bindValue(':core', $this->getCore(), PDO::PARAM_STR);
        $stmt->bindValue(':pid', $this->getPid(), PDO::PARAM_INT);
        $stmt->bindValue(':hostname', $this->getHostname(), PDO::PARAM_STR);
        $stmt->bindValue(':origin', $this->getOrigin(), PDO::PARAM_STR);
        $stmt->bindValue(':limit', $this->getSqlSelectRowsLimit(), PDO::PARAM_INT);
        try {
            $stmt->execute();
        } catch (Exception $e) {
            Ccsd_Log::message("Erreur lors de la selection des DOCID à traiter.", true, 'ERR');
            Ccsd_Log::message($e->getMessage(), true, 'ERR');
            return false;
        }
        return $stmt->fetchAll(PDO::FETCH_NUM);
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->_id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id)
    {
        $this->_id = (int)$id;
    }

    /**
     * @return string
     */
    public function getApplication(): string
    {
        return $this->_application;
    }

    /**
     * @param string $application
     */
    public function setApplication(string $application)
    {
        $this->_application = filter_var($application, FILTER_SANITIZE_STRING);
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return $this->_priority;
    }

    /**
     * @param int $priority
     */
    public function setPriority(int $priority)
    {
        $this->_priority = (int)$priority;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->_status;
    }

    /**
     * @param string $status
     */
    public function setStatus(string $status)
    {
        $this->_status = filter_var($status, FILTER_SANITIZE_STRING);
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->_message;
    }

    /**
     * @param string $message
     */
    public function setMessage(string $message)
    {
        $this->_message = filter_var($message, FILTER_SANITIZE_STRING);
    }

    /**
     * @return int
     */
    public function getDocid(): int
    {
        return $this->_docid;
    }

    /**
     * @param int $docid
     */
    public function setDocid(int $docid)
    {
        $this->_docid = (int)$docid;
    }

    /**
     * Change le statut d'une ligne en Erreur, pour ne pas ré-essayer
     * de la traiter
     * @param int $docId
     * @return bool
     */
    public function putProcessedRowInError($docId)
    {
        $sql = "UPDATE " . $this->getSqlTableName() . " SET STATUS = 'error', MESSAGE = :message WHERE HOSTNAME = :hostname AND DOCID = :docid AND ORIGIN=:origin AND CORE = :core AND PID = :pid AND STATUS = 'locked' LIMIT 1";
        $stmt = $this->getSqlDbAdapter()->prepare($sql);

        $stmt->bindValue(':pid', $this->getPid(), PDO::PARAM_INT);
        $stmt->bindValue(':hostname', $this->getHostname(), PDO::PARAM_STR);
        $stmt->bindValue(':message', $this->getMessage(), PDO::PARAM_STR);
        $stmt->bindValue(':origin', $this->getOrigin(), PDO::PARAM_STR);
        $stmt->bindValue(':core', $this->getCore(), PDO::PARAM_STR);

        if (!is_array($docId)) {
            $docId = array($docId);
        }

        foreach ($docId as $oneDocid) {
            $stmt->bindValue(':docid', $oneDocid, PDO::PARAM_INT);
            $stmt->execute();
        }
        return true;
    }

    /**
     * Supprime les lignes traitées de la table
     *
     * @param array $arrayOfDocId
     */
    public function deleteProcessedRows($arrayOfDocId)
    {
        $sqlDelete = "DELETE FROM " . $this->getSqlTableName() . " WHERE HOSTNAME = :hostname AND DOCID = :docid AND ORIGIN=:origin AND CORE = :core AND PID = :pid AND STATUS = 'locked' LIMIT 1";
        $stmt = $this->getSqlDbAdapter()->prepare($sqlDelete);

        foreach ($arrayOfDocId as $docid) {
            $stmt->bindValue(':docid', $docid, PDO::PARAM_INT);
            $stmt->bindValue(':pid', $this->getPid(), PDO::PARAM_INT);
            $stmt->bindValue(':hostname', $this->getHostname(), PDO::PARAM_STR);
            $stmt->bindValue(':origin', $this->getOrigin(), PDO::PARAM_STR);
            $stmt->bindValue(':core', $this->getCore(), PDO::PARAM_STR);
            $stmt->execute();
        }
    }

    /**
     * Init Db Adapter
     * @return mixed
     */
    abstract protected function initDb();


}
