<?php

use Solarium\Client;
use Solarium\QueryType\Update\Query\Document;
use Solarium\QueryType\Update\Query\Query;
use Solarium\QueryType\Update\Result;


abstract class Ccsd_Search_Solr_Indexer extends Ccsd_Search_Solr
{
    public const O_UPDATE = 'UPDATE';
    public const O_DELETE = 'DELETE';
    public const BIND_CORE = ':core';
    public const BIND_PID = ':pid';
    public const BIND_HOSTNAME = ':hostname';
    public const BIND_ORIGIN = ':origin';
    public const BIND_LIMIT = ':limit';
    public const BIND_DOCID = ':docid';
    public const BIND_MESSAGE = ':message';
    public static int $maxSelectFromIndexQueue = 1000;
    public static int $maxDocsInBuffer = 1;
    public static string $coreName;
    protected static ?Query $update = null;
    private string $logFilename = '';
    private bool $debugMode = false;
    private Document $doc;
    private Zend_Db_Adapter_Abstract $db;
    private array $bufferedDocidList = [];
    private int $nbOfBufferedDocuments = 0;
    private int $totalNbOfDocuments = 0;
    /** @var string UPDATE | DELETE */
    private string $origin;
    private int $nbOfDocument = 0;
    private string $dataSource = '';
    private string $hostname;
    private $errorMessage;


    /**
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        if (!empty($options)) {
            $this->setOptions($options);
            if (isset($options [Ccsd_Search_Solr_Indexer_Core::OPTION_MAX_DOCS_IN_BUFFER])) {
                static::$maxDocsInBuffer = (int)$options [Ccsd_Search_Solr_Indexer_Core::OPTION_MAX_DOCS_IN_BUFFER];
            }
            $this->setConfig();
            parent::__construct($options);
        }
    }


    public function setConfig(): void
    {
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
        return $docid >= 0;
    }

    /**
     * @param string|null $whereCondition
     * @return array
     */
    public function getListOfDocIdToIndexFromDb(string $whereCondition = null): array
    {
        $db = $this->getDb();
        $select = $db->select();

        $this->selectIds($select);

        if ($whereCondition !== null) {
            $select->where($whereCondition);
            Ccsd_Log::message("Indexation des documents d'après la condition $whereCondition", $this->isDebugMode(), '', $this->getLogFilename());
            Ccsd_Log::message("SQL :  " . $select->__toString(), $this->isDebugMode(), '', $this->getLogFilename());
        }

        $stmt = $select->query();
        $arrayOfCode = $stmt->fetchAll(PDO::FETCH_NUM);
        return array_column($arrayOfCode, 0);
    }


    public function getDb(): Zend_Db_Adapter_Abstract
    {
        return $this->db;
    }


    public function setDb(Zend_Db_Adapter_Abstract $db): Ccsd_Search_Solr_Indexer
    {
        $this->db = $db;
        return $this;
    }

    /** @param Zend_Db_Select $select */
    abstract protected function selectIds(Zend_Db_Select $select);

    /**
     * @return bool
     */
    public function isDebugMode(): bool
    {
        return $this->debugMode;
    }

    /**
     * @param bool $debugMode
     */
    public function setDebugMode($debugMode): void
    {
        if (!is_bool($debugMode)) {
            $debugMode = false;
        }
        $this->debugMode = $debugMode;
    }

    /**
     * @return string
     */
    public function getLogFilename(): string
    {
        return $this->logFilename;
    }

    /**
     * Set current log file name
     */
    public function setLogFilename(): void
    {
        $logFilename = $this->getCore() . '_' . APPLICATION_ENV;
        $logPath = EPISCIENCES_SOLR_LOG_PATH;
        $this->logFilename = $logPath . $logFilename;
    }

    /**
     * Retourne des données à indexer depuis la table d'indexation
     *
     * @return array tableau de docid à indexer pour un core donné
     */
    public function getListOfDocidFromIndexQueue(): ?array
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
    private function lockRows(): bool
    {
        $sqlUpdate = sprintf("UPDATE INDEX_QUEUE SET STATUS = 'locked', PID = %s, HOSTNAME = %s WHERE CORE = %s AND ORIGIN=%s AND STATUS= 'ok' LIMIT %s", self::BIND_PID, self::BIND_HOSTNAME, self::BIND_CORE, self::BIND_ORIGIN, self::BIND_LIMIT);
        Ccsd_Log::message('Index queue locked by ' . $this->getHostname(), $this->isDebugMode(), 'INFO', $this->getLogFilename());
        $pid = getmypid();
        $hostname = $this->getHostname();
        $origin = $this->getOrigin();
        $dbAdapter = Ccsd_Db_Adapter_SolrIndexQueue::getAdapter();
        $stmt = $dbAdapter->prepare($sqlUpdate);
        try {
            $stmt->bindParam(self::BIND_CORE, static::$coreName, PDO::PARAM_STR);
            $stmt->bindParam(self::BIND_PID, $pid, PDO::PARAM_INT);
            $stmt->bindParam(self::BIND_HOSTNAME, $hostname, PDO::PARAM_STR);
            $stmt->bindParam(self::BIND_ORIGIN, $origin, PDO::PARAM_STR);
            $stmt->bindParam(self::BIND_LIMIT, static::$maxSelectFromIndexQueue, PDO::PARAM_INT);
        } catch (Zend_Db_Statement_Exception $e) {
            Ccsd_Log::message($e->getMessage(), $this->isDebugMode(), 'ERR', $this->getLogFilename());
            return false;
        }


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
    public function getHostname(): string
    {
        return $this->hostname;
    }

    /**
     * Sets the current hostname
     */
    public function setHostname(): void
    {
        $this->hostname = filter_var(getHostname());
    }

    /**
     *
     * @return string $_origin
     */
    public function getOrigin(): string
    {
        return $this->origin;
    }

    /**
     * @param string $origin
     * @return Ccsd_Search_Solr_Indexer
     */
    public function setOrigin(string $origin): Ccsd_Search_Solr_Indexer
    {
        if (($origin !== self::O_UPDATE) && ($origin !== self::O_DELETE)) {
            $origin = self::O_UPDATE;
        }
        $this->origin = $origin;
        return $this;
    }


    /**
     * Retourne une liste de docid à indexer qui sont en statut "locked" pour
     * traitement
     */
    private function selectLockedRows()
    {
        $sqlSelect = sprintf("SELECT DOCID FROM INDEX_QUEUE WHERE STATUS = 'locked' AND HOSTNAME = %s AND ORIGIN= %s AND CORE = %s AND PID = %s ORDER BY PRIORITY DESC LIMIT %s", self::BIND_HOSTNAME, self::BIND_ORIGIN, self::BIND_CORE, self::BIND_PID, self::BIND_LIMIT);
        Ccsd_Log::message('Index queue selected by ' . $this->getHostname(), $this->isDebugMode(), 'INFO', $this->getLogFilename());
        $dbAdapter = Ccsd_Db_Adapter_SolrIndexQueue::getAdapter();
        $stmt = $dbAdapter->prepare($sqlSelect);
        $pid = getmypid();
        $hostname = $this->getHostname();
        $origin = $this->getOrigin();
        try {
            $stmt->bindParam(self::BIND_CORE, static::$coreName, PDO::PARAM_STR);
            $stmt->bindParam(self::BIND_PID, $pid, PDO::PARAM_INT);
            $stmt->bindParam(self::BIND_HOSTNAME, $hostname, PDO::PARAM_STR);
            $stmt->bindParam(self::BIND_ORIGIN, $origin, PDO::PARAM_STR);
            $stmt->bindParam(self::BIND_LIMIT, static::$maxSelectFromIndexQueue, PDO::PARAM_INT);
        } catch (Zend_Db_Statement_Exception $e) {
            return false;
        }

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
     * @throws Zend_Db_Statement_Exception
     */
    public function processArrayOfDocid(array $arrayOfDocId): void
    {
        $dbName = $this->getDb()->getConfig();
        Ccsd_Log::message('Master Host : ' . ENDPOINTS_INDEXING_HOST . ' / Core : ' . $this->getCore(), $this->isDebugMode(), '', $this->getLogFilename());
        Ccsd_Log::message('Database    : ' . $dbName ['dbname'], $this->isDebugMode(), '', $this->getLogFilename());
        Ccsd_Log::message('Script PID  : ' . getmypid(), $this->isDebugMode(), '', $this->getLogFilename());
        $this->setTotalNbOfDocuments(count($arrayOfDocId));
        if ($this->getTotalNbOfDocuments() === 0) {
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
    public function getTotalNbOfDocuments(): int
    {
        return $this->totalNbOfDocuments;
    }

    /**
     *
     * @param number $totalNbOfDocuments
     * @return Ccsd_Search_Solr_Indexer
     */
    public function setTotalNbOfDocuments($totalNbOfDocuments): Ccsd_Search_Solr_Indexer
    {
        $this->totalNbOfDocuments = $totalNbOfDocuments;
        return $this;
    }

    /**
     * @param Solarium\Client $client
     * @param int[] $arrayOfDocId
     *
     * @throws Zend_Db_Statement_Exception
     */
    private function addDocids(Client $client, array $arrayOfDocId): void
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
        if (!self::$update) {
            $client = Ccsd_Search_Solr::getSolrIndexingClient();
            $update = $client->createUpdate();
            $update->setOmitHeader(false);
            self::setUpdate($update);
        }
        return self::$update;
    }

    /**
     * @param Query $update
     */
    public static function setUpdate(Query $update): void
    {
        self::$update = $update;
    }


    /**
     * @throws Zend_Db_Statement_Exception
     */
    private function prepareSolrUpdate($updateQuery, $docId): void
    {
        Ccsd_Log::message('In Core : ' . $this->getCore() . ' => ' . $this->getOrigin() . ' document UPDATED : ' . $docId, $this->isDebugMode(), '', $this->getLogFilename());
        $document = $updateQuery->createDocument();
        $document = $this->addMetadataToDoc($docId, $document);
        if ((!$document) || ($document === null)) {
            $errorMessage = 'Document not indexed: ' . $docId;
            $this->setErrorMessage($errorMessage);
            Ccsd_Log::message($errorMessage, true, 'ERR', $this->getLogFilename());
            $this->putProcessedRowInError($docId);
        } else {
            $updateQuery->addDocument($document, true);
            $this->addBufferedDocidList($docId);
        }

        $this->setNbOfDocument();

    }

    /**
     * @param int $docId
     * @param Document $docToIndex
     * @return mixed
     */
    abstract protected function addMetadataToDoc(int $docId, Document $docToIndex);


    /**
     * @param $docId
     * @return bool
     * @throws Zend_Db_Statement_Exception
     */
    private function putProcessedRowInError($docId): bool
    {
        $sql = "UPDATE INDEX_QUEUE SET STATUS = 'error', MESSAGE = :message WHERE HOSTNAME = :hostname AND DOCID = :docid AND ORIGIN=:origin AND CORE = :core AND PID = :pid AND STATUS = 'locked' LIMIT 1";
        Ccsd_Log::message('Index queue errors found by ' . $this->getHostname(), $this->isDebugMode(), 'INFO', $this->getLogFilename());
        $dbAdapter = Ccsd_Db_Adapter_SolrIndexQueue::getAdapter();
        $stmt = $dbAdapter->prepare($sql);
        $pid = getmypid();
        $hostname = $this->getHostname();
        $origin = $this->getOrigin();
        $message = $this->getErrorMessage();
        $stmt->bindParam(self::BIND_PID, $pid, PDO::PARAM_INT);
        $stmt->bindParam(self::BIND_HOSTNAME, $hostname, PDO::PARAM_STR);
        $stmt->bindParam(self::BIND_MESSAGE, $message, PDO::PARAM_STR);
        $stmt->bindParam(self::BIND_ORIGIN, $origin, PDO::PARAM_STR);
        $stmt->bindParam(self::BIND_CORE, static::$coreName, PDO::PARAM_STR);

        if (!is_array($docId)) {
            $docId = [$docId];
        }

        foreach ($docId as $oneDocid) {
            $stmt->bindParam(self::BIND_DOCID, $oneDocid, PDO::PARAM_INT);
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
    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }


    public function setErrorMessage(string $errorMessage = 'Unknown Error'): void
    {
        $this->errorMessage = $errorMessage;
    }

    /**
     * @param int $docid
     * @return Ccsd_Search_Solr_Indexer
     */
    public function addBufferedDocidList(int $docid = 0): Ccsd_Search_Solr_Indexer
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
    public function resetBuffer(): Ccsd_Search_Solr_Indexer
    {
        $this->bufferedDocidList = [];
        $this->setNbOfBufferedDocuments(0);
        return $this;
    }

    /**
     * @return int $nbOfBufferedDocuments
     */
    public function getNbOfBufferedDocuments(): int
    {
        return $this->nbOfBufferedDocuments;
    }

    /**
     * @param int $nbOfBufferedDocuments
     * @return Ccsd_Search_Solr_Indexer
     */
    public function setNbOfBufferedDocuments(int $nbOfBufferedDocuments): Ccsd_Search_Solr_Indexer
    {
        $this->nbOfBufferedDocuments = $nbOfBufferedDocuments;
        return $this;
    }

    /**
     * Envoi la requête à solr si le buffer est plein
     * @param Client $client
     * @param Query $update
     * @return void
     */
    private function sendSolrQuery(Client $client, Query $update): void
    {

        if ($this->getTotalNbOfDocuments() === 1) {
            $update->addCommit();
            Ccsd_Log::message('COMMIT.', $this->isDebugMode(), $this->getLogFilename());
        }

        if ($this->isBufferFull() === true || ($this->getTotalNbOfDocuments() === 1)) {

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
            // Reset the static Query so the next batch starts with an empty document list.
            // Without this, self::$update accumulates ALL documents across every batch,
            // causing Json.php to re-serialize everything on each flush → OOM.
            self::$update = null;
            $this->resetBuffer();
        }
    }


    private function isBufferFull(): bool
    {
        return ($this->getNbOfBufferedDocuments() === static::$maxDocsInBuffer) || ($this->getNbOfDocument() >= $this->getTotalNbOfDocuments());
    }

    /**
     * @return int
     */
    public function getNbOfDocument(): int
    {
        return $this->nbOfDocument;
    }

    /**
     * Ajoute un doc au nombre de doc indexés
     *
     * @return $this
     */
    public function setNbOfDocument(): Ccsd_Search_Solr_Indexer
    {
        $this->nbOfDocument++;
        return $this;
    }

    /**
     * @return int[] $bufferedDocidList
     */
    public function getBufferedDocidList(): array
    {
        return $this->bufferedDocidList;
    }

    /**
     * @param Result $result
     * @throws Zend_Db_Statement_Exception
     */
    private function processUpdateResult(Result $result): void
    {
        $msgPrefix = 'Core : ' . $this->getCore();
        $logFilename = $this->getLogFilename();


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

    }

    /**
     *
     * @return string $_dataSource
     */
    public function getDataSource(): string
    {
        return $this->dataSource;
    }

    /**
     * @param string $dataSource
     * @return $this
     */
    public function setDataSource(string $dataSource = 'cmdLine'): Ccsd_Search_Solr_Indexer
    {
        $this->dataSource = $dataSource;
        return $this;
    }

    /**
     * @param array $arrayOfDocId
     * @throws Zend_Db_Statement_Exception
     */
    private function deleteProcessedRows(array $arrayOfDocId): void
    {
        $sqlDelete = "DELETE FROM INDEX_QUEUE WHERE HOSTNAME = :hostname AND DOCID = :docid AND ORIGIN=:origin AND CORE = :core AND PID = :pid AND STATUS = 'locked' LIMIT 1";
        $getmypid = getmypid();
        $origin = $this->getOrigin();
        $hostname = $this->getHostname();

        Ccsd_Log::message('Index queue Cleaned by ' . $hostname, $this->isDebugMode(), 'INFO', $this->getLogFilename());
        $dbAdapter = Ccsd_Db_Adapter_SolrIndexQueue::getAdapter();
        $stmt = $dbAdapter->prepare($sqlDelete);


        foreach ($arrayOfDocId as $docid) {
            $stmt->bindParam(self::BIND_DOCID, $docid, PDO::PARAM_INT);
            $stmt->bindParam(self::BIND_PID, $getmypid, PDO::PARAM_INT);
            $stmt->bindParam(self::BIND_HOSTNAME, $hostname, PDO::PARAM_STR);
            $stmt->bindParam(self::BIND_ORIGIN, $origin, PDO::PARAM_STR);
            $stmt->bindParam(self::BIND_CORE, static::$coreName, PDO::PARAM_STR);
            $stmt->execute();
        }
    }

    /**
     * Delete an array of docid
     * @param $client Solarium\Client
     * @param array $arrayOfDocId
     */
    private function deleteDocids(Client $client, array $arrayOfDocId): void
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
     * @return void
     */
    private function prepareSolrDelete(Query $deleteQuery, int $docId): void
    {
        Ccsd_Log::message('Core : ' . $this->getCore() . ' => ' . $this->getOrigin() . ' document DELETED : ' . $docId, true, '', $this->getLogFilename());
        $this->addBufferedDocidList($docId);
        $deleteQuery->addDeleteQuery('docid:' . $docId);
        $this->setNbOfDocument();
    }

    /**
     * Traitement d'un docid
     *
     * @param $docid
     */
    public function processDocid($docid): void
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
     * @param string|null $query
     * @return void
     * @example docid:19 *:*
     */
    public function deleteDocument(string $query = null): void
    {
        if ($query === null) {
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
     * @param string|null $indexPrefix
     * @param Solarium\QueryType\Update\Query\Document|null $doc
     * @return Solarium\QueryType\Update\Query\Document
     */
    protected function addArrayOfMetaToDoc(array $dataToIndex, string $indexPrefix = null, Document $doc = null): Document
    {
        if ($doc === null) {
            $doc = $this->getDoc();
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
     * @return Document
     */
    public function getDoc(): Document
    {
        return $this->doc;
    }

    /**
     * @param Solarium\QueryType\Update\Query\Document $doc
     * @return Ccsd_Search_Solr_Indexer
     */
    public function setDoc(Document $doc): Ccsd_Search_Solr_Indexer
    {
        $this->doc = $doc;
        return $this;
    }

    /**
     * Ajoute une métadonnée à un document
     * Filtre les problèmes les plus courants
     * @param string $fieldName
     * @param string $dataToIndex
     * @param Solarium\QueryType\Update\Query\Document $doc
     * @return Solarium\QueryType\Update\Query\Document // Document en cours d'indexation
     */
    private static function addMetaToDoc(string $fieldName, $dataToIndex, Document $doc): Document
    {
        $fieldName = trim($fieldName);
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
