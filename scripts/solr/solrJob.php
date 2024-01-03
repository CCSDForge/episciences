<?php
ini_set("display_errors", '1');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_STRICT);
$timestart = microtime(true);
const APPLICATION_PATH = __DIR__ . '/../../application';
set_include_path(__DIR__ . '/../../library');


$localopts = [
    'docid|D=s' => ' % pour réindexer tous les DOCID',
    'c=s' => ' core Solr',
    'cron=s' => ' update ou delete pour un cron',
    'sqlwhere-s' => '= pour spécifier la condition SQL à utiliser pour trouver les DOCID',
    'delete=s' => " Suppression de l'index de Solr avec requête de type solr (docid:19) (*:*)",
    'buffer|b=i' => " Nombre de doc à envoyer en même temps à l'indexeur",
];

require_once __DIR__ . '/../../public/bdd_const.php';
require_once __DIR__ . '/../loadHeader.php';

// Autoloader
require_once 'Zend/Loader/Autoloader.php';
$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->setFallbackAutoloader(true);
# accepted core: episciences

$options = [];

if (!($opts->docid || $opts->sqlwhere || $opts->delete || $opts->cron || $opts->file)) {
    fwrite(STDERR, "I need a valid input : a docid, a file, an SQL command, the delete option or cron option\n");
    fwrite(STDERR, $opts->getUsageMessage());
    exit(1);
}

$options ['env'] = APPLICATION_ENV;

if (posix_getuid() === 0) {
    fwrite(STDERR, "Do NOT run this as root, this script must use an unprivileged user (an apache user is usually fine)");
    exit(1);
}

if ($opts->buffer) {
    $options [Ccsd_Search_Solr_Indexer_Core::OPTION_MAX_DOCS_IN_BUFFER] = (int)$opts->buffer;
}

$indexer = new Ccsd_Search_Solr_Indexer_Episciences($options);


if ($debug) {
    $indexer->setDebugMode(true);
} else {
    $indexer->setDebugMode(false);
}


Zend_Registry::set('metadataSources', Episciences_Paper_MetaDataSourcesManager::all(false));

$cronValue = strtolower($opts->cron);

Ccsd_Log::message('Indexation dans Apache Solr  | Solarium library version: ' . Solarium\Client::getVersion(), $debug, '', $indexer->getLogFilename());


// indexation via CRON
if ( ($cronValue === 'update') || ($cronValue === 'delete') ){
    Ccsd_Log::message(" Données récupérées dans la table d'indexation", $debug, '', $indexer->getLogFilename());
    $indexer->setOrigin(mb_strtoupper($opts->cron));
    $arrayOfDocId = $indexer->getListOfDocidFromIndexQueue();
    $indexer->processArrayOfDocid($arrayOfDocId);
    exit();
}


/*
 * Suppression de l'index par Requête
 */
if ($opts->delete) {
    $indexer->setOrigin('DELETE');
    $indexer->deleteDocument($opts->delete);
    exit();
}


// indexation par DOCID
if (($opts->docid) && ($opts->docid !== '%')) {
    $arrayOfDocId [] = $opts->docid;
    $indexer->setOrigin('UPDATE');
    $indexer->processArrayOfDocid($arrayOfDocId);

    // indexation par requête SQL
} elseif ($opts->sqlwhere || $opts->docid === '%') {
    $whereCondition = $opts->sqlwhere;
    $arrayOfDocId = $indexer->getListOfDocIdToIndexFromDb($whereCondition);
    $indexer->setOrigin('UPDATE');
    $indexer->processArrayOfDocid($arrayOfDocId);

    // indexation par fichier
} elseif ($opts->file) {
    $arrayOfDocId = $indexer->getListOfDocIdToIndexFromFile($opts->file);
    Ccsd_Log::message("Nombre de documents à indexer: " . count($arrayOfDocId), $debug, '', $indexer->getLogFilename());
    $indexer->setOrigin('UPDATE');
    $indexer->processArrayOfDocid($arrayOfDocId);
}

$timeend = microtime(true);
$time = $timeend - $timestart;

Ccsd_Log::message('Début du script: ' . date("H:i:s", $timestart) . '/ fin du script: ' . date("H:i:s", $timeend), $debug, '', $indexer->getLogFilename());
Ccsd_Log::message('Script executé en ' . number_format($time, 3) . ' sec.', $debug, '', $indexer->getLogFilename());
exit(0);


