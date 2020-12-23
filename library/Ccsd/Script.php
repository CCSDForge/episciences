<?php
/**
 * Created by PhpStorm.
 * User: marmol
 * Date: 12/01/18
 * Time: 09:29
 */
/* Pour trouver Zend */

/*foreach (array(__DIR__. '/../../../library', __DIR__. '/../../library') as $_libdir) {
    if (file_exists($_libdir . '/Zend') || ($_libdir = getenv('ZENDPATH'))) {
        set_include_path(realpath($_libdir) . PATH_SEPARATOR . get_include_path());
        break;
    }
}*/

require_once 'Runable.php';
/**
 * Class Ccsd_Script
 * From Episcience Script Class written by Kevin L
 */
abstract class Ccsd_Script extends Ccsd_Runable
{

    /** TODO
     * improve init:
     *  implement setOptions
     *  process array of options if specified at __construct.
     * improve log:
     *  use Ccsd_Log ? Zend_Log ?
     *  if no log file is defined, create "ScriptName.log" at startup
     *  rotate log files
     *  implement setLogFile & getLogFile
     * add hooks (pre and post run): spl observers ?
     * split class: create Script_Display ?
     * write tests
     */

    /**
     * @var Zend_Console_Getopt
     */
    protected $_getopt;

    /** @var array */
    protected $options = [];
    /**
     * script arguments
     * @var array
     */
    private $_commonOptions = [
        'help|h'      => 'display help',
        'env|app_env|e=s' => 'set application environment',
        'debug|d'     => 'enable debug mode (nothing will be modified)',
        'verbose|v'   => 'enable verbose mode (display a lot of stuff)',
        'nocolor'     => 'Affichage des messages sans la couleur',
        'logPath|l-s'   => '(optional) Path where to gzwrite logs, default= "."'
    ];

    /**
     * user defined parameters
     * @var array
     */
    protected $_params = array();

    /**
     * required parameters
     * @var array
     */
    protected $_required_params = [
        'app_env' => 'app_env|e is mandatory.',
    ];

    /**
     * valid environments
     * @var array
     */
    protected $_valid_envs = [
        'development',
        'testing',
        'preprod',
        'production'
    ];

    /**
     * Zend Application
     * @var Zend_Application
     */
    protected $_application = null;

    /**
     * Zend Translator
     * @var null
     */
    protected $_translator = null;

    /**
     * Database
     * @var null
     */
    protected $_db = null;

    protected $_init_time = null;
    protected $_progress_bar = null;
    public $environment = 'development';

    /** @var resource */
    protected $_logFile = null;
    /** @var string  */
    protected $_logFilename = '';

     /**
     */
    public function getProgressBar()
    {
        return $this->_progress_bar;
    }
    /**
     *
     */
    public function ZendLoad() {
        $this->debug('Enter CcsdScript ZendLoad');
        require_once ('Zend/Loader/Autoloader.php');
        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);
    }

    /**
     * Ccsd_Script constructor.
     */
    public function __construct()
    {

        $this->debug('Enter construct CcsdScript');

        $this->_init_time = microtime(true);

        set_time_limit(0);
        ini_set("memory_limit", '2048M');
        ini_set("display_errors", '1');
        error_reporting(E_ALL);
        $this->ZendLoad();
        $this->initApp();

        try {
            $opts = new Zend_Console_Getopt($this->getOptions());
        } catch (Zend_Console_Getopt_Exception $e) {
            die ("Bad option parsing: ". $e->getMessage());
        }
        $this->_getopt = $opts;
        $opts->parse();

        // if -help param exists, display help and stop execution
        if (isset($opts->h)) {
            die($opts->getUsageMessage());
        }
        if (isset($opts->verbose)) {
            $this->setVerbose(isset($opts->verbose));
                $this->setVerbosity(self::SEVERITY_INFO);
        }
        if (isset($opts->debug)) {
            $this->setDebug(isset($opts->debug));
            $this->setVerbosity(self::SEVERITY_DEBUG);
        }
        $this->colorize = ! isset($opts->nocolor);
        if (isset($opts->e) && in_array($opts->e, $this->_valid_envs)) {
            $this->environment = $opts->e;
        }
        if (!defined('APPLICATION_ENV') ) {
            define('APPLICATION_ENV', $this->environment);
        }
        if (isset($opts->logPath)) {
            $this->setLogFilename($opts->logPath);
            $this->initLog();
            $this->enableLogs();
        }
        $this->setupApp();
    }

    public function run() {
        $this->preRun();
        $this->main($this->getOpts());
        $this->postRun();
    }

    /**
     * init Zend application
     */
    public function initApp()
    {
        $this->debug('Enter InitApp');
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

    }

    /** This is called after options was parsed so can use $this->>opts */
    public function setupApp()
    {
        $this->debug('Enter Empty SetupApp');
    }

    /**
     * This fonction is called before main() execution
     */
    public function preRun()
    {
        $this->debug("Script started");
    }

    /**
     * This fonction is called after main() execution
     */
    public function postRun()
    {
        $this->debug("Script ended");
        $endTiming = $this->getScriptTiming(microtime(true));
        $this->debug($endTiming."s");
        $this->closeLog();
    }

    public function getScriptTiming(float $timePick) {
        return $timePick - $this->_init_time;
    }

    /**
     *
     */
    protected function initSignalHandler()
    {
        declare(ticks=1);

        $script = $this;
        pcntl_signal(SIGINT, function ($signal) use ($script) {
            if ($signal == SIGINT) {
                // $script->getProgressBar()->stop();
                $output = $this->erase_current()
                    . $this->cursor_left(3)
                    . "Script aborted. Good Bye ! =)";
                $script->println('', $output, static::BASH_RED);
                echo PHP_EOL;
            }
            exit;
        });
    }

    /**
     * @return array // To be send to getOpts
     */
    public function getOptions()
    {
        return array_merge($this->options, $this->_commonOptions);
    }
    /**
     * @return Zend_Console_Getopt // To be send to getOpts
     */
    public function getOpts()
    {
        return $this->_getopt;
    }


    public function displayProgressBar()
    {
        if ($this->_progress_bar->isStarted()) {
            $output = static::$bashColors[static::BASH_DEFAULT]
                . static::$bashColors[static::BASH_BOLD]
                . $this->_progress_bar->getOutput()
                . PHP_EOL
                . $this->cursor_up(1);

            echo $output;
        }
    }

    protected function cursor_up($i)
    {
        return "\033[" . $i . "A";
    }

    protected function cursor_down($i)
    {
        return "\033[" . $i . "B";
    }

    protected function cursor_right($i)
    {
        return "\033[" . $i . "C";
    }

    protected function cursor_left($i)
    {
        return "\033[" . $i . "D";
    }

    protected function erase_current()
    {
        return "\033[2K";
    }

    /**
     * @param string $logPath
     */
    public function setLogFilename(string $logPath)
    {
        $path = (substr($logPath, -1, 1) === "/") ? substr($logPath, 0, -1) : $logPath;
        $path = realpath($path);
        $this->_logFilename = $path . "/" . strtolower(get_class($this)) . '_' . date("Y-m-dTHis") . Ccsd_Log::LOG_FILENAME_SUFFIX;
    }

    /**
     * @param resource $logFile
     */
    public function setLogFile($logFile)
    {
        $this->_logFile = $logFile;
    }


    public function setRegistryLogger()
    {
        $logger = new Zend_Log();
        try {
            $logger->addWriter(new Zend_Log_Writer_Stream($this->_logFile));
        } catch (Zend_Log_Exception $e) {
            $this->log("Cannot write in $this->_logFilename: logging the old-fashioned way...");
        }
        Zend_Registry::set(Ccsd_Log::REGISTRY_LOGGER_NAME, $logger);
    }

    /**
     * @param string $logPath
     */
    protected function initLog()
    {
        $logResource = gzopen($this->_logFilename . ".gz",'ab');
        if ($logResource === false) {
            $this->log("Cannot write in " . $this->_getopt->logPath . ": logging the old-fashioned way...");
        } else {
            $this->setLogFile($logResource);
            $this->setRegistryLogger();
        }
    }


    protected function closeLog()
    {
        if ($this->_logFile !== null) {
            gzclose($this->_logFile);
        }
    }

    protected function formatLogMessage ($message)
    {
        return  '[' . date("Y-m-d H:i:s") . '] '
            . preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $message);
    }

    /**
     * @param $message
     * @return bool
     */
    public function log($message, $print = true, $level = '')
    {
        if (!$this->_log_enabled) {
            return false;
        }

        $message = $this->formatLogMessage($message);
        if ($this->_logFile === null) {
            $filename = strtolower(get_class($this)) . '_' . date("Y-m-d") . '.log';
            $file = fopen($filename, 'ab');
            fwrite($file, $message);
            fclose($file);
        } else {
            Ccsd_Log::message($message, $print, $level, $this->_logFilename);
        }
        return true;
    }

    /**
     * Get time beetween begining of script an Now
     * @return int
     */
    public function getRunTime() {
        $endtime = microtime(true);
        $time = $endtime - $this->_init_time;
        return $time;
    }

    /**
     * convert RunTime to displayable time
     * @return string
     */
    public function getDisplayableRunTime() {
        return number_format($this->getRunTime(), 3) . ' sec.';
    }
}
