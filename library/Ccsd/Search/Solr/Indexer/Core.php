<?php
/**
 * Created by PhpStorm.
 * User: marmol
 * Date: 16/01/18
 * Time: 11:51
 */

require_once __DIR__ . "/../../../Runable.php";

/**
 * Class Ccsd_Search_Solr_Indexer_Core
 *
 * Use as sub program for scripts to group all what is needed to run indexer
 */
class Ccsd_Search_Solr_Indexer_Core extends Ccsd_Runable
{

    static private $coresList = [];
    /** @var string Name of indexer class
     * declared here , be must absolutly be declared in subclass
     * This value is a foo value
     */
    static protected $indexerClass = 'NoSuchClass: Must be define in subclasses';
    /** @var string Name of indexer class
     * declared here , be must absolutly be declared in subclass
     */
    static protected $coreName = 'FooCoreName';
    /**
     * Array of option taken by indexer with their value
     * All option must be set, either by initialization with default @see addIndexerOption
     * or by modifying options as specify by command line arguments @see setIndexerOption
     *
     * @var array
     */
    protected $indexerOptions =  ['maxDocsInBuffer' => 0];
    /**
     * Set indexerOptions accordingly with command line arguments
     * @param Zend_Console_Getopt $getopt
     */
    protected function treadIndexerOptions($getopt) {
        $this->indexerOptions ['env'] = APPLICATION_ENV;
        if ($getopt->buffer) {
            $this->indexerOptions ['maxDocsInBuffer'] = (int)$getopt->buffer;
        }
    }

    /**
     * @param ReflectionClass $class
     * @param string $parentName
     * @return bool
     */
    private static function hasParent($class, $parentName)
    {
        while ($parent = $class->getParentClass()) {
            if ($parent->getName() == $parentName) {
                return true;
            }
            $class = $parent;
        }
        return false;
    }
    /**
     * Add a Core to the coreList
     * @param string $className
     */
    public static function registerCore($className) {
        /** Serait bien de tester que c'est une classe qui herite de la classe Core...  */
        try {
            $class = new ReflectionClass($className);
            if (!self::hasParent($class, 'Ccsd_Search_Solr_Indexer_Core')) {
                die("Class $className is not a Ccsd_Search_Solr_Indexer_Core\n");
            }
        } catch (ReflectionException $e) {
            die("Core class $className not exists");
        }
        /** Attention que les classe soient bien loadee .... */
        /** @var string $subclass */
        $vars = get_class_vars($className);
        $subclass = $vars['indexerClass'];

        $vars = get_class_vars($subclass);
        $classCoreName = $vars['_coreName'];

        self::$coresList[$classCoreName] = $className;
    }

    /** Liste les Core precedemment enregistree avec
     * @see Ccsd_Search_Solr_Indexer_Core::registerCore */
    public static function getCores() {
        return self::$coresList;
    }
    /** Add an option to the indexer with this default value.
     *  Use setIndexerOption to differently set this option after initialization
     *
     * @see $indexerOptions
     * @param string $option
     * @param mixed $defaultValue
     */
    public function addIndexerOption($option, $defaultValue) {
        $this -> indexerOptions[$option] = $defaultValue;
    }
    /** Modify a previouly set option
     * @param string $option
     * @param mixed $newvalue
     * Note: same code than addIndexerOption but different semantic
     */
    public function setIndexerOption($option, $newvalue) {
        $this -> indexerOptions[$option] = $newvalue;
    }

    /**
     * This array must be totaly compatible with option needed by Ccsd_Search_Solr_Indexer class
     * @return array
     */
    public function getIndexerOptions() {
        return $this -> indexerOptions;
    }
    /** Main function to run indexer
     * @param Zend_Console_Getopt $getopt
     */
    public function main($getopt)
    {
        $docid    = $getopt->docid;
        $sqlwhere = $getopt->sqlwhere;
        $cron     = strtoupper($getopt->cron);
        $file     = $getopt->file;
        $delete   = $getopt->delete;
        $debug    = $getopt->debug;

        $UPDATE = Ccsd_Search_Solr_Indexer::O_UPDATE;
        $DELETE = Ccsd_Search_Solr_Indexer::O_DELETE;

        /** @var Zend_Console_Getopt $getopt */
        if (!($docid || $sqlwhere || $delete || $cron || $file)) {
            fwrite(STDERR, "I need a valid input : a docid, a file, an SQL command, the delete option or cron option\n");
            fwrite(STDERR, $getopt -> getUsageMessage());
            exit(1);
        }

        if ($docid && $cron) {
            fwrite(STDERR, "Argument -D and --cron can't be use in the same command");
            exit(1);
        }
        /** get options from command line, can be in subclass */
        $this -> treadIndexerOptions($getopt);

        /** @var  Ccsd_Search_Solr_Indexer $indexer */
        $indexer = new static::$indexerClass($this->getIndexerOptions());
        $indexer->setDebugMode($debug);

        // indexation via CRONourrions travailler avec vous sur cette question afin de rendre HAL le plus
        if (($cron == $UPDATE) || ($cron == $DELETE)) {
            Ccsd_Log::message($indexer::$_coreName . " Données récupérées dans la table d'indexation", $debug, '', $indexer->getLogFilename());
            $indexer->setOrigin(strtoupper($cron));
            $arrayOfDocId = $indexer->getListOfDocidFromIndexQueue();
            $indexer->processArrayOfDocid($arrayOfDocId);
            return;
        }
        /**
         * Suppression de l'index par Requête
         */
        if ($delete) {
            $indexer -> setOrigin($DELETE);
            $indexer->deleteDocument($delete);
            return;
        }

        if ($docid && ($docid != '%')) {
            $indexer -> setOrigin($UPDATE);
            $arrayOfDocId [] = $docid;
            $indexer->processArrayOfDocid($arrayOfDocId);
            return;
        }

        // indexation par fichier
        if ($file) {
            $indexer -> setOrigin($UPDATE);
            $arrayOfDocId = $indexer->getListOfDocIdToIndexFromFile($file);
            Zend_Debug::dump($arrayOfDocId);
            $indexer->processArrayOfDocid($arrayOfDocId);
            return;
        }
        //Une requete sql
        if ($sqlwhere || $docid == '%') {
            $indexer -> setOrigin($UPDATE);
            $whereCondition = $sqlwhere;
            $arrayOfDocId = $indexer->getListOfDocIdToIndexFromDb($whereCondition);
            $indexer->processArrayOfDocid($arrayOfDocId);
            return;
        }
    }
}
