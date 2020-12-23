<?php

use Solarium\Client;
use Solarium\QueryType\Update\Query\Document\Document;
use Solarium\QueryType\Update\Query\Query;
use Solarium\QueryType\Update\Result;

/**
 * Class Ccsd_Search_Solr_Indexer
 */
abstract class Ccsd_Search_Solr_Indexer extends Ccsd_Search_Solr
{
    const O_UPDATE = 'UPDATE';
    const O_DELETE = 'DELETE';
    public static $_maxSelectFromIndexQueue = 1000;
    public static $_maxDocsInBuffer = 1;
    public static $_coreName;
    /**
     * @var Solarium\QueryType\Update\Query\Query
     */
    protected static $_update;


    /** @var string */
    private $_logFilename = '';
    /**@var boolean */
    private $_debugMode;
    /** @var Solarium\QueryType\Update\Query\Document\Document document qui va être indexé */
    private $_doc;
    /** @var Zend_Db_Adapter_Pdo_Mysql */
    private $_db;
    /** @var int[] */
    private $bufferedDocidList = [];
    /** @var int */
    private $nbOfBufferedDocuments = 0;
    /** @var int */
    private $totalNbOfDocuments = 0;
    /** @var string UPDATE | DELETE */
    private $_origin;
    /** @var int numéro en cours du document dans la file d'indexation en cours de traitement */
    private $nbOfDocument = 0;
    /** @var  string */
    private $_dataSource;
    /** @var string : Indexer hostname */
    private $_hostname;
    /** @var string : message d'erreur de l'indexeur */
    private $errorMessage;


    /**
     * Ccsd_Search_Solr_Indexer constructor.
     * @param $options
     */
    public function __construct(array $options = [])
    {
        if (!empty($options)) {
            $this->setOptions($options);
            if (isset($options ['maxDocsInBuffer'])) {
                static::$_maxDocsInBuffer = (int)$options ['maxDocsInBuffer'];
            }
            $this->setConfig();
            parent::__construct($options);
        }
    }

    /**
     *
     */
    public function setConfig()
    {
        /** @var Zend_Db_Adapter_Pdo_Mysql $db */
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->setDb($db);
        $this->setHostname();
        $this->setLogFilename();
    }


    /**
     * Ajoute des docid à indexer dans la table d'indexation
     *
     * @param array $arrayOfDocid
     * @param string $application
     * @param string $origin
     * @param string $core
     * @param int $priority
     * @example Ccsd_Search_Solr_Indexer::addToIndexQueue($arrayOfDoc =
     *          array(1,52,83778,536), 'episciences', 'DELETE', 'episciences', 10);
     */
    public static function addToIndexQueue(array $arrayOfDocid, $application = 'episciences', $origin = self::O_UPDATE, $core = 'episciences', $priority = 0)
    {
        $sql = "INSERT INTO `INDEX_QUEUE` (`ID`, `DOCID`, `UPDATED`, `APPLICATION`, `ORIGIN`, `CORE`, `PRIORITY`, `STATUS`)
                 VALUES
                (NULL , :docid , CURRENT_TIMESTAMP, :application , :origin , :core , :priority , 'ok') ON DUPLICATE KEY UPDATE `STATUS` = 'ok', `PID` = 0, `MESSAGE` = '';";


        $dbAdapter = Ccsd_Db_Adapter_SolrIndexQueue::getAdapter();

        $stmt = $dbAdapter->prepare($sql);

        foreach ($arrayOfDocid as $docId) {
            $params ['docid'] = $docId;
            $params ['application'] = $application;
            $params ['origin'] = $origin;
            $params ['core'] = $core;
            $params ['priority'] = $priority;
            $stmt->execute($params);
        }
    }

    /**
     * @param $docid
     * @return bool
     */
    public static function is_validDocid($docid): bool
    {
        return ($docid >= 0);
    }

    /**
     * @param string $whereCondition
     * @return array
     */
    public function getListOfDocIdToIndexFromDb($whereCondition = null)
    {
        $db = $this->getDb();
        $select = $db->select();

        $this->selectIds($select);

        if ($whereCondition != null) {
            $select->where($whereCondition);
            Ccsd_Log::message("Indexation des documents d'après la condition $whereCondition", $this->isDebugMode(), '', $this->getLogFilename());
            Ccsd_Log::message("SQL :  " . $select->__toString(), $this->isDebugMode(), '', $this->getLogFilename());
        }

        $stmt = $select->query();
        $arrayOfCode = $stmt->fetchAll(PDO::FETCH_NUM);
        return array_column($arrayOfCode, 0);
    }

    /**
     * @return Zend_Db_Adapter_Pdo_Mysql $_db
     */
    public function getDb()
    {
        return $this->_db;
    }

    /**
     * @param Zend_Db_Adapter_Pdo_Mysql $_db
     * @return Ccsd_Search_Solr_Indexer
     */
    public function setDb($_db)
    {
        $this->_db = $_db;
        return $this;
    }

    /** @param Zend_Db_Select $select */
    abstract protected function selectIds($select);

    /**
     * @return bool
     */
    public function isDebugMode()
    {
        return $this->_debugMode;
    }

    /**
     * @param bool $debugMode
     */
    public function setDebugMode($debugMode)
    {
        if (!is_bool($debugMode)) {
            $debugMode = false;
        }
        $this->_debugMode = $debugMode;
    }

    /**
     * @return string
     */
    public function getLogFilename()
    {
        return $this->_logFilename;
    }

    /**
     * Set current log file name
     */
    public function setLogFilename()
    {
        $logFilename = $this->getCore() . '_' . APPLICATION_ENV . '.log';
        $logPath = EPISCIENCES_SOLR_LOG_PATH;
        $this->_logFilename = $logPath . $logFilename;
    }

    /**
     * Retourne des données à indexer depuis la table d'indexation
     *
     * @return array tableau de docid à indexer pour un core donné
     */
    public function getListOfDocidFromIndexQueue()
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
     * Change l'état des lignes de ok à locked
     */
    private function lockRows()
    {
        $sqlUpdate = "UPDATE INDEX_QUEUE SET STATUS = 'locked', PID = :pid, HOSTNAME = :hostname WHERE CORE = :core AND ORIGIN=:origin AND STATUS= 'ok' LIMIT :limit";
        Ccsd_Log::message('Index queue locked by ' . $this->getHostname(), $this->isDebugMode(), 'INFO', $this->getLogFilename());
        $pid = getmypid();
        $hostname = $this->getHostname();
        $origin = $this->getOrigin();
        $dbAdapter = Ccsd_Db_Adapter_SolrIndexQueue::getAdapter();
        $stmt = $dbAdapter->prepare($sqlUpdate);
        $stmt->bindParam(':core', static::$_coreName, PDO::PARAM_STR);
        $stmt->bindParam(':pid', $pid, PDO::PARAM_INT);
        $stmt->bindParam(':hostname', $hostname, PDO::PARAM_STR);
        $stmt->bindParam(':origin', $origin, PDO::PARAM_STR);
        $stmt->bindParam(':limit', static::$_maxSelectFromIndexQueue, PDO::PARAM_INT);

        try {
            return $stmt->execute();
        } catch (Exception $e) {
            Ccsd_Log::message($e->getMessage(), $this->isDebugMode(), 'ERR', $this->getLogFilename());
            return false;
        }
    }

    /**
     * @return string : Indexer host name
     */
    public function getHostname()
    {
        return $this->_hostname;
    }

    /**
     * Sets the current hostname
     */
    public function setHostname()
    {
        $this->_hostname = filter_var(getHostname(), FILTER_SANITIZE_STRING);
    }

    /**
     *
     * @return string $_origin
     */
    public function getOrigin()
    {
        return $this->_origin;
    }

    /**
     * @param string $_origin
     * @return Ccsd_Search_Solr_Indexer
     */
    public function setOrigin($_origin)
    {
        if (($_origin != self::O_UPDATE) && ($_origin != self::O_DELETE)) {
            $_origin = self::O_UPDATE;
        }
        $this->_origin = $_origin;
        return $this;
    }


    /**
     * Retourne une liste de docid à indexer qui sont en statut "locked" pour
     * traitement
     */
    private function selectLockedRows()
    {
        $sqlSelect = "SELECT DOCID FROM INDEX_QUEUE WHERE STATUS = 'locked' AND HOSTNAME = :hostname AND ORIGIN=:origin AND CORE = :core AND PID = :pid ORDER BY PRIORITY DESC LIMIT :limit";
        Ccsd_Log::message('Index queue selected by ' . $this->getHostname(), $this->isDebugMode(), 'INFO', $this->getLogFilename());
        $dbAdapter = Ccsd_Db_Adapter_SolrIndexQueue::getAdapter();
        $stmt = $dbAdapter->prepare($sqlSelect);
        $pid = getmypid();
        $hostname = $this->getHostname();
        $origin = $this->getOrigin();
        $stmt->bindParam(':core', static::$_coreName, PDO::PARAM_STR);
        $stmt->bindParam(':pid', $pid, PDO::PARAM_INT);
        $stmt->bindParam(':hostname', $hostname, PDO::PARAM_STR);
        $stmt->bindParam(':origin', $origin, PDO::PARAM_STR);
        $stmt->bindParam(':limit', static::$_maxSelectFromIndexQueue, PDO::PARAM_INT);
        try {
            $stmt->execute();
        } catch (Exception $e) {
            Ccsd_Log::message("Erreur lors de la selection des DOCID à indexer.", true, 'ERR', $this->getLogFilename());
            Ccsd_Log::message($e->getMessage(), true, 'ERR', $this->getLogFilename());
            return false;
        }
        return $stmt->fetchAll(PDO::FETCH_NUM);
    }

    /**
     * Traitement d'un tableau de docid
     *
     * @param int[] $arrayOfDocId
     *              Tableau de DOCID à traiter
     */
    public function processArrayOfDocid($arrayOfDocId)
    {
        $dbName = $this->getDb()->getConfig();
        Ccsd_Log::message('Master Host : ' . ENDPOINTS_INDEXING_HOST . ' / Core : ' . $this->getCore(), $this->isDebugMode(), '', $this->getLogFilename());
        Ccsd_Log::message('Database    : ' . $dbName ['dbname'], $this->isDebugMode(), '', $this->getLogFilename());
        Ccsd_Log::message('Script PID  : ' . getmypid(), $this->isDebugMode(), '', $this->getLogFilename());
        $this->setTotalNbOfDocuments(count($arrayOfDocId));
        if ($this->getTotalNbOfDocuments() == 0) {
            Ccsd_Log::message("Fin : pas de document à traiter.", $this->isDebugMode(), '', $this->getLogFilename());
        } else {
            Ccsd_Log::message("Documents à traiter : " . $this->getTotalNbOfDocuments(), $this->isDebugMode(), '', $this->getLogFilename());
        }
        // create a client instance
        $client = Ccsd_Search_Solr::getSolrIndexingClient();

        $client->getPlugin('postbigrequest');
        if ($this->getOrigin() === self::O_UPDATE) {
            $this->addDocids($client, $arrayOfDocId);
        }
        if ($this->getOrigin() === self::O_DELETE) {
            $this->deleteDocids($client, $arrayOfDocId);
        }
    }

    /**
     * @return int ; the $totalNbOfDocuments
     */
    public function getTotalNbOfDocuments()
    {
        return $this->totalNbOfDocuments;
    }

    /**
     *
     * @param number $totalNbOfDocuments
     * @return Ccsd_Search_Solr_Indexer
     */
    public function setTotalNbOfDocuments($totalNbOfDocuments)
    {
        $this->totalNbOfDocuments = $totalNbOfDocuments;
        return $this;
    }

    /**
     * @param Solarium\Client $client
     * @param int[] $arrayOfDocId
     * */
    private function addDocids($client, $arrayOfDocId)
    {
        foreach ($arrayOfDocId as $docId) {
            $updateQuery = self::getUpdate();
            $this->prepareSolrUpdate($updateQuery, $docId);
            $this->sendSolrQuery($client, $updateQuery);
        }
    }

    /**
     * @return Query
     */
    public static function getUpdate(): Query
    {
        if (!self::$_update) {
            $client = Ccsd_Search_Solr::getSolrIndexingClient();
            $update = $client->createUpdate();
            $update->setOmitHeader(false);
            self::setUpdate($update);
        }
        return self::$_update;
    }

    /**
     * @param Query $_update
     */
    public static function setUpdate(Query $_update)
    {
        self::$_update = $_update;
    }

    /**
     * Prépare une requête de mise à jour pour solr
     * @param Solarium\QueryType\Update\Query\Query $updateQuery
     * @param $docId
     * @return Solarium\QueryType\Update\Query\Query
     * @throws Zend_Db_Statement_Exception
     */
    private function prepareSolrUpdate($updateQuery, $docId)
    {
        Ccsd_Log::message('In Core : ' . $this->getCore() . ' => ' . $this->getOrigin() . ' document UPDATED : ' . $docId, $this->isDebugMode(), '', $this->getLogFilename());
        /** @var $document Solarium\QueryType\Update\Query\Document\Document */
        $document = $updateQuery->createDocument();
        $document = $this->addMetadataToDoc($docId, $document);
        if ($document == null) {
            Ccsd_Log::message('Document non traité : ' . $docId, true, 'ERR', $this->getLogFilename());
            $this->putProcessedRowInError($docId);
        } else {
            $updateQuery->addDocument($document, true);
            $this->addBufferedDocidList($docId);
        }
        /** Le document est indique comme etant traite, meme si pas indexe.  Sinon le test de
         * @see isBufferFull en fin de traitement est faux!
         * @todo: on pourrait compter le nbr de document en erreur et ceux indexes
         */
        $this->setNbOfDocument();
        return $updateQuery;

    }

    /**
     * @param int $docId
     * @param Document $ndx
     * @return mixed
     */
    abstract protected function addMetadataToDoc(int $docId, Document $document);


    /**
     * @param $docId
     * @return bool
     * @throws Zend_Db_Statement_Exception
     */
    private function putProcessedRowInError($docId)
    {
        $sql = "UPDATE INDEX_QUEUE SET STATUS = 'error', MESSAGE = :message WHERE HOSTNAME = :hostname AND DOCID = :docid AND ORIGIN=:origin AND CORE = :core AND PID = :pid AND STATUS = 'locked' LIMIT 1";
        Ccsd_Log::message('Index queue errors found by ' . $this->getHostname(), $this->isDebugMode(), 'INFO', $this->getLogFilename());
        $dbAdapter = Ccsd_Db_Adapter_SolrIndexQueue::getAdapter();
        $stmt = $dbAdapter->prepare($sql);
        $pid = getmypid();
        $hostname = $this->getHostname();
        $origin = $this->getOrigin();
        $message = $this->getErrorMessage();
        $stmt->bindParam(':pid', $pid, PDO::PARAM_INT);
        $stmt->bindParam(':hostname', $hostname, PDO::PARAM_STR);
        $stmt->bindParam(':message', $message, PDO::PARAM_STR);
        $stmt->bindParam(':origin', $origin, PDO::PARAM_STR);
        $stmt->bindParam(':core', static::$_coreName, PDO::PARAM_STR);

        if (!is_array($docId)) {
            $docId = [$docId];
        }

        foreach ($docId as $oneDocid) {
            $stmt->bindParam(':docid', $oneDocid, PDO::PARAM_INT);
            $res = $stmt->execute();
            if ($res) {
                Ccsd_Log::message('DOCID : ' . $oneDocid . ' in index queue now in state : *error* ' . $this->getErrorMessage(), $this->isDebugMode(), '', $this->getLogFilename());
            }
        }
        return true;
    }

    /**
     * @return string message
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * @param int $docid
     * @return Ccsd_Search_Solr_Indexer
     */
    public function addBufferedDocidList($docid = 0)
    {
        if ($docid === 0) {
            return $this->resetBuffer();
        }
        $this->bufferedDocidList [] = $docid;
        $this->setNbOfBufferedDocuments($this->getNbOfBufferedDocuments() + 1);

        return $this;
    }

    /**
     * Reset Document buffer list
     * @return $this
     */
    public function resetBuffer()
    {
        $this->bufferedDocidList = [];
        $this->setNbOfBufferedDocuments(0);
        return $this;
    }

    /**
     * @return int $nbOfBufferedDocuments
     */
    public function getNbOfBufferedDocuments()
    {
        return $this->nbOfBufferedDocuments;
    }

    /**
     * @param int $nbOfBufferedDocuments
     * @return Ccsd_Search_Solr_Indexer
     */
    public function setNbOfBufferedDocuments($nbOfBufferedDocuments)
    {
        $this->nbOfBufferedDocuments = $nbOfBufferedDocuments;
        return $this;
    }

    /**
     * Envoi la requête à solr si le buffer est plein
     * @param Client $client
     * @param Query $update
     * @return void
     * @todo: Le addCommit ne devrait etre fait que lors d'une ligne de commande, donc plus en amont dans le script
     *        ou bien en mettant un argument a la fonction pour demander explicitement a commiter les changements Solr
     */
    private function sendSolrQuery($client, $update)
    {

        if ($this->getTotalNbOfDocuments() == 1) {
            $update->addCommit();
            Ccsd_Log::message('COMMIT.', $this->isDebugMode(), $this->getLogFilename());
        }

        if ($this->isBufferFull() === true || ($this->getTotalNbOfDocuments() == 1)) {

            $message = 'Solr ' . $this->getOrigin() . ' Core : ' . $this->getCore() . ' DOCID : ' . implode(' ; ', $this->getBufferedDocidList());
            try {
                $result = $client->update($update);
                $this->processUpdateResult($result);
                $logLevel = 'NOTICE';
            } catch (Exception $e) {
                $logLevel = 'ERR';
                $message .= ' : ' . ENDPOINTS_INDEXING_HOST . ' ' . $e->getMessage();
            }

            Ccsd_Log::message($message, $this->isDebugMode(), $logLevel, $this->getLogFilename());

            $this->resetBuffer();
        }
    }

    /**
     * @return bool
     * On considere que le buffer est plein lorsqu'il faut envoyer le requete a Solr
     * Soit le buffer est plein, soit on a traiter l'ensemble des document demandes
     * (Le > ne devrait pas etre necessaire! mais si on ajoute plusieurs documents sans appeler le commit )
     */
    private function isBufferFull()
    {
        return (($this->getNbOfBufferedDocuments() == static::$_maxDocsInBuffer) || ($this->getNbOfDocument() >= $this->getTotalNbOfDocuments()));
    }

    /**
     * @return int
     */
    public function getNbOfDocument()
    {
        return $this->nbOfDocument;
    }

    /**
     * Ajoute un doc au nombre de doc indexés
     *
     * @return $this
     */
    public function setNbOfDocument()
    {
        $this->nbOfDocument++;
        return $this;
    }

    /**
     * @return int[] $bufferedDocidList
     */
    public function getBufferedDocidList()
    {
        return $this->bufferedDocidList;
    }

    /**
     * @param Result $result
     */
    private function processUpdateResult($result)
    {
        $msgPrefix = 'Core : ' . $this->getCore();
        $logFilename = $this->getLogFilename();
        if (is_object($result)) {

            if ($result->getStatus() === 0) {
                Ccsd_Log::message($msgPrefix . ' - Doc ' . $this->getNbOfDocument() . '/' . $this->getTotalNbOfDocuments() . ' Succès requête Solr', $this->isDebugMode(), '', $logFilename);
                Ccsd_Log::message($msgPrefix . ' - Durée de la requête Solr: ' . $result->getQueryTime() . ' ms' . PHP_EOL, $this->isDebugMode(), '', $logFilename);

                if ($this->getDataSource() === 'cron') {
                    // Ordre vient de la DB INDEXQUEUE, il faut la mettre a jour...
                    $this->deleteProcessedRows($this->getBufferedDocidList());
                }
            } else {
                Ccsd_Log::message($msgPrefix . ' - Doc ' . $this->getNbOfDocument() . '/' . $this->getTotalNbOfDocuments() . ' Echec requête Solr', true, 'ERR', $logFilename);
            }
        } else {
            Ccsd_Log::message($msgPrefix . ' - Requête Solr getStatus DOCID ' . implode(' ; ', $this->getBufferedDocidList()), true, 'ERR', $logFilename);
        }
    }

    /**
     *
     * @return string $_dataSource
     */
    public function getDataSource()
    {
        return $this->_dataSource;
    }

    /**
     * @param string $_dataSource
     * @return $this
     */
    public function setDataSource($_dataSource = 'cmdLine')
    {
        $this->_dataSource = $_dataSource;
        return $this;
    }

    /**
     * @param array $arrayOfDocId
     * @throws Zend_Db_Statement_Exception
     */
    private function deleteProcessedRows($arrayOfDocId)
    {
        $sqlDelete = "DELETE FROM INDEX_QUEUE WHERE HOSTNAME = :hostname AND DOCID = :docid AND ORIGIN=:origin AND CORE = :core AND PID = :pid AND STATUS = 'locked' LIMIT 1";
        $getmypid = getmypid();
        $origin = $this->getOrigin();
        $hostname = $this->getHostname();

        Ccsd_Log::message('Index queue Cleaned by ' . $hostname, $this->isDebugMode(), 'INFO', $this->getLogFilename());
        $dbAdapter = Ccsd_Db_Adapter_SolrIndexQueue::getAdapter();
        $stmt = $dbAdapter->prepare($sqlDelete);


        foreach ($arrayOfDocId as $docid) {
            $stmt->bindParam(':docid', $docid, PDO::PARAM_INT);
            $stmt->bindParam(':pid', $getmypid, PDO::PARAM_INT);
            $stmt->bindParam(':hostname', $hostname, PDO::PARAM_STR);
            $stmt->bindParam(':origin', $origin, PDO::PARAM_STR);
            $stmt->bindParam(':core', static::$_coreName, PDO::PARAM_STR);
            $stmt->execute();
        }
    }

    /**
     * Delete an array of docid
     * @param $client Solarium\Client
     * @param array $arrayOfDocId
     */
    private function deleteDocids($client, $arrayOfDocId)
    {
        $update = null;
        foreach ($arrayOfDocId as $docId) {
            $update = self::getUpdate();
            $update->setOmitHeader(false);
            $this->prepareSolrDelete($update, $docId);
            $this->sendSolrQuery($client, $update);
        }
    }

    /**
     * Prépare une requête de suppression pour solr
     * @param Query $deleteQuery
     * @param int $docId
     * @return mixed
     */
    private function prepareSolrDelete($deleteQuery, $docId)
    {
        Ccsd_Log::message('Core : ' . $this->getCore() . ' => ' . $this->getOrigin() . ' document DELETED : ' . $docId, true, '', $this->getLogFilename());
        $this->addBufferedDocidList($docId);
        $deleteQuery->addDeleteQuery('docid:' . $docId);
        $this->setNbOfDocument();
        return $deleteQuery;
    }

    /**
     * Traitement d'un docid
     *
     * @param $docid
     */
    public function processDocid($docid)
    {
        // create a client instance
        $client = Ccsd_Search_Solr::getSolrIndexingClient();
        $client->getPlugin('postbigrequest');
        $arrayOfDocId = [$docid];
        if ($this->getOrigin() === self::O_UPDATE) {
            $this->addDocids($client, $arrayOfDocId);
        }
        if ($this->getOrigin() === self::O_DELETE) {
            $this->deleteDocids($client, $arrayOfDocId);
        }
    }

    /**
     * Supprime un doc par requête
     *
     * @param string $query
     * @return void
     * @example docid:19 *:*
     */
    public function deleteDocument($query = null)
    {
        if ($query == null) {
            Ccsd_Log::message('Requête de suppression vide', true, 'ERR', $this->getLogFilename());
            return;
        }

        if (($query === '*:*') && (APPLICATION_ENV === ENV_PROD)) {
            echo "/!\ Pour supprimer les données d'un core en production : " . PHP_EOL;
            echo "1. Désactiver la réplication du core " . PHP_EOL;
            echo "2. Décharger le core (unload)" . PHP_EOL;
            echo "3. Supprimer manuellement le répertoire data /opt/solrData/data/coreName/data" . PHP_EOL;
            echo "4. Recharger le core (reload)" . PHP_EOL;
            exit();
        }

        // create a client instance
        $client = Ccsd_Search_Solr::getSolrIndexingClient();

        // get an update query instance
        $update = self::getUpdate();

        Ccsd_Log::message('Requête de suppression : ' . $query, true, $this->getLogFilename());

        // add the delete query and a commit command to the update query
        $update->addDeleteQuery($query);
        $update->addCommit();

        try {
            // this executes the query and returns the result
            $client->update($update);
            Ccsd_Log::message('Requête de suppression OK', true, $this->getLogFilename());
        } catch (Solarium\Exception\HttpException $e) {
            Ccsd_Log::message('Erreur : ' . $e->getMessage(), true, 'ERR', $this->getLogFilename());
        }
    }

    /**
     * Read input file to array
     * @param $file
     * @return array|bool
     */
    public function getListOfDocIdToIndexFromFile($file)
    {
        if (!is_readable($file)) {
            Ccsd_Log::message('Error: unable to read input file: ' . $file, true, 'ERR', $this->getLogFilename());
            exit;
        }

        $arrayOfDocid = file($file);
        $arrayOfDocid = array_map('trim', $arrayOfDocid);
        $arrayOfDocid = array_filter($arrayOfDocid, 'is_numeric');
        $arrayOfDocid = array_filter($arrayOfDocid, [__CLASS__, 'is_validDocid']);
        return array_unique($arrayOfDocid);
    }

    /**
     * @param $docId
     * @return mixed
     */
    abstract protected function getDocidData($docId);


    /**
     * Ajoute un tableau de données au document à indexer
     * @param array $dataToIndex
     * @param string $indexPrefix
     * @param Solarium\QueryType\Update\Query\Document\Document $doc
     * @return null|Solarium\QueryType\Update\Query\Document\Document
     */
    protected function addArrayOfMetaToDoc($dataToIndex, $indexPrefix = null, $doc = null)
    {
        if ($doc === null) {
            $doc = $this->getDoc();
        }

        if (!is_array($dataToIndex)) {
            return $doc;
        }

        foreach ($dataToIndex as $fieldName => $fieldValue) {

            if ($indexPrefix !== null) {
                $fieldName = $indexPrefix . ucfirst($fieldName);
            }

            if (is_array($fieldValue)) {
                $fieldValue = array_unique($fieldValue);
                foreach ($fieldValue as $value) {
                    $doc = self::addMetaToDoc($fieldName, $value, $doc);
                }
            } else {
                $doc = self::addMetaToDoc($fieldName, $fieldValue, $doc);
            }
        }
        $this->setDoc($doc);
        return $doc;
    }

    /**
     * @return Solarium\QueryType\Update\Query\Document\Document
     */
    public function getDoc()
    {
        return $this->_doc;
    }

    /**
     * @param Solarium\QueryType\Update\Query\Document\Document $doc
     * @return Ccsd_Search_Solr_Indexer
     */
    public function setDoc($doc): Ccsd_Search_Solr_Indexer
    {
        $this->_doc = $doc;
        return $this;
    }

    /**
     * Ajoute une métadonnée à un document
     * Filtre les problèmes les plus courants
     * @param string $fieldName
     * @param string $dataToIndex
     * @param Solarium\QueryType\Update\Query\Document\Document $doc
     * @return Solarium\QueryType\Update\Query\Document\Document // Document en cours d'indexation
     */
    private static function addMetaToDoc($fieldName, $dataToIndex, $doc)
    {
        if (is_string($dataToIndex)) {
            $dataToIndex = Ccsd_Tools_String::stripCtrlChars($dataToIndex);
            $dataToIndex = trim($dataToIndex);
        }

        if (($dataToIndex !== '') && ($dataToIndex !== "0000-00-00") && ($dataToIndex !== parent::SOLR_FACET_SEPARATOR)) {
            $doc->addField($fieldName, $dataToIndex);
        }
        return $doc;
    }


}
