<?php

use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__) . '/public/const.php';
require_once dirname(__DIR__)  . '/public/bdd_const.php';
require_once 'ProgressBar.php';

abstract class Script {

    /* TODO
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

    const BASH_RED = 'red';
    const BASH_BLUE = 'blue';
    const BASH_GREEN = 'green';
    const BASH_CYAN = 'cyan';
    const BASH_PURPLE = 'purple';
    const BASH_LIGHT_GREY = 'light_grey';
    const BASH_DARK_GREY = 'dark_grey';
    const BASH_LIGHT_BLUE = 'light_blue';
    const BASH_LIGHT_GREEN = 'light_green';
    const BASH_LIGHT_CYAN = 'light_cyan';
    const BASH_LIGHT_RED = 'light_red';
    const BASH_LIGHT_PURPLE = 'light_purple';
    const BASH_YELLOW = 'yellow';
    const BASH_BOLD = 'bold';
    const BASH_DEFAULT = 'default';

    const SEVERITY_DEBUG = 'debug';
    const SEVERITY_TRACE = 'trace';
    const SEVERITY_INFO = 'info';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_ERROR = 'error';
    const SEVERITY_CRITICAL = 'critical';

    protected static $bashColors = [
        self::BASH_RED          => "\033[0;31m",
        self::BASH_BLUE         => "\033[0;34m",
        self::BASH_GREEN        => "\033[0;32m",
        self::BASH_CYAN         => "\033[0;36m",
        self::BASH_PURPLE       => "\033[0;35m",
        self::BASH_LIGHT_GREY   => "\033[0;37m",
        self::BASH_DARK_GREY    => "\033[1;30m",
        self::BASH_LIGHT_BLUE   => "\033[1;34m",
        self::BASH_LIGHT_GREEN  => "\033[1;32m",
        self::BASH_LIGHT_CYAN   => "\033[1;36m",
        self::BASH_LIGHT_RED    => "\033[1;31m",
        self::BASH_LIGHT_PURPLE => "\033[1;35m",
        self::BASH_YELLOW       => "\033[1;33m",
        self::BASH_BOLD         => "\033[1m",
        self::BASH_DEFAULT      => "\033[0m",
    ];

    /**
     * @var Zend_Console_Getopt
     */
    protected $_zend_console;

    /**
     * script arguments
     * @var array
     */
    protected $_args = [
        'help|h'        => 'display help',
        'debug|d'       => 'enable debug mode (nothing will be modified)',
        'force|f'       => "won't ask for any confirmation",
        'verbose|v'     => 'enable verbose mode (display a lot of stuff)',
        'app_env|e=s'   => 'set application environment'];

    /**
     * user defined parameters
     * @var array
     */
    protected $_params = array();

    /**
     * required parameters
     * @var array
     */
    protected $_required_params = [];

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
    protected $_log_enabled = true;


    public function enableLogs()
    {
        $this->_log_enabled = true;
    }

    public function disableLogs()
    {
        $this->_log_enabled = false;
    }

    public function getInitTime()
    {
        return $this->_init_time;
    }

    public function getProgressBar()
    {
        return $this->_progress_bar;
    }

    public function __construct()
    {
        if (!headers_sent() && session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->_init_time = microtime();
        $this->_progress_bar = new ProgressBar();

        ini_set ( "display_errors", '1' );

        if (!defined('APPLICATION_PATH')) {
            define('APPLICATION_PATH', __DIR__ . '/../application');
        }

        $libraries = array(realpath(APPLICATION_PATH . '/../library'));
        set_include_path(implode(PATH_SEPARATOR, array_merge($libraries, array(get_include_path()))));

        /** Zend_Application */
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
        } else {
            /** Zend_Application */
            require_once 'Zend/Application.php';
        }

        $autoloader = Zend_Loader_Autoloader::getInstance ();
        $autoloader->setFallbackAutoloader ( true );

        try {
            $this->_zend_console = new Zend_Console_Getopt($this->getArgs());
            foreach ($this->_zend_console->getOptions() as $name) {
                $this->setParam($name, $this->_zend_console->$name);
            }
        } catch (Zend_Console_Getopt_Exception $e) {
            die($e->getMessage() . PHP_EOL);
        }

        // if -help param exists, display help and stop execution
        if ($this->hasParam('help')) {
            $this->displayHelp();
            die;
        }

        // check required parameters
        $errors = [];
        foreach ($this->getRequiredParams() as $param=>$message) {
            if (!$this->hasParam($param)) {
                $errors[] = $message;
            }
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->displayError($error);
            }
            $this->displayHelp();
            die;
        }
    }

    protected function initSignalHandler()
    {
        declare(ticks = 1);

        $script = $this;
        pcntl_signal(SIGINT, function($signal) use ($script) {
            if ($signal == SIGINT) {
                $script->getProgressBar()->stop();
                $output = $this->erase_current()
                    . $this->cursor_left(3)
                    . "Script aborted. Good Bye ! =)";
                $script->display($output, true, [static::BASH_RED, static::BASH_BOLD], true);
                echo PHP_EOL;
            }
            exit;
        });
    }

    public function setArgs(array $args)
    {
        $this->_args = $args;
    }

    public function getArgs()
    {
        return $this->_args;
    }

    public function setRequiredParams(array $params) {
        $this->_required_params = $params;
    }

    public function getRequiredParams() {
        return $this->_required_params;
    }

    // return true if verbose mode is on
    public function isVerbose()
    {
        return ($this->getParam('verbose'));
    }

    // return true if debug mode is on
    public function isDebug()
    {
        return ($this->getParam('debug'));
    }

    // return true if force mode is on
    public function isForce()
    {
        return ($this->getParam('debug'));
    }

    /**
     * display a message, if $force is true, or if verbose mode is on
     * @param $message
     * @param bool $force
     * @param array $styles
     * @param bool $log
     */
    public function display($message, $force = false, $styles = null, $log = true) {
        if ($force || $this->isVerbose()) {
            // format message
            if (!empty($styles)) {
                if (!is_array($styles)) {
                    $styles = [$styles];
                }
                foreach ($styles as $style) {
                    echo static::$bashColors[$style];
                }
            }
            // display message
            echo "\033[2K"
                . $message
                . PHP_EOL
                . static::$bashColors[static::BASH_DEFAULT];

            if ($this->_log_enabled && $log) {
                $this->log($message);
            }

            if ($this->_progress_bar) {
                $this->displayProgressBar();
            }
        }
    }

    /**
     * display a dark grey formatted text, if verbose mode is on (or force is true)
     * @param $message
     * @param bool $force
     */
    public function displayDebug($message, $force=false) {
        $this->display('[:debug] '.$message, $force, static::BASH_DARK_GREY);
    }

    /**
     * display a light grey formatted text, if verbose mode is on (or force is true)
     * @param $message
     * @param bool $force
     */
    public function displayTrace($message, $force=false) {
        $this->display('[:trace] '.$message, $force, static::BASH_LIGHT_GREY);
    }

    /**
     * display a blue formatted text, if verbose mode is on (or force is true)
     * @param $message
     * @param bool $force
     */
    public function displayInfo($message, $force=false) {
        $this->display('[:info] '.$message, $force, static::BASH_BLUE);
    }

    /**
     * display a yellow formatted text, if verbose mode is on (or force is true)
     * @param $message
     * @param bool $force
     */
    public function displayWarning($message, $force=false) {
        $this->display('[:warning] '.$message, $force, static::BASH_YELLOW);
    }

    /**
     * display a red formatted text, even if verbose mode is off (unless force is false)
     * @param $message
     * @param bool $force
     */
    public function displayError($message, $force=true) {
        $this->display('[:error] '.$message, $force, static::BASH_RED);
    }

    /**
     * display a red bold formatted text, even if verbose mode is off (unless force is false)
     * @param $message
     * @param bool $force
     */
    public function displayCritical($message, $force=true) {
        $this->display('[:critical] '.$message, $force, [static::BASH_RED, static::BASH_BOLD]);
    }

    /**
     * display a green formatted text, if verbose mode is on (or force is true)
     * @param $message
     * @param bool $force
     */
    public function displaySuccess($message, $force=false) {
        $this->display('[:success] '.$message, $force, [static::BASH_GREEN]);
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

    /**
     * display the script help message
     */
    public function displayHelp()
    {
        $this->display("This script must be run from its matching environment.", true, [static::BASH_BLUE, static::BASH_BOLD], false);
        $message = PHP_EOL . $this->_zend_console->getUsageMessage();
        $this->display($message, true, null, false);
    }

    protected function cursor_up($i)
    {
        return "\033[".$i."A";
    }

    protected function cursor_down($i)
    {
        return "\033[".$i."B";
    }

    protected function cursor_right($i)
    {
        return "\033[".$i."C";
    }

    protected function cursor_left($i)
    {
        return "\033[".$i."D";
    }

    protected function erase_current()
    {
        return "\033[2K";
    }

    /**
     * run script
     * @return mixed
     */
    abstract public function run();

    public function preRun()
    {
        $this->display("Script started");
    }

    public function postRun()
    {
        $this->display("Script ended");
    }

    /**
     * init Zend application
     */
    public function initApp($isRequiredAppEnv = true): void
    {
        // check environment is valid
        if ($isRequiredAppEnv && $this->getParam('app_env') && !in_array($this->getParam('app_env'), $this->_valid_envs, true))  {
            $this->displayError("Incorrect application environment: " . $this->getParam('app_env') . PHP_EOL . "Should be one of these: " . implode(', ', $this->_valid_envs));
        } elseif(empty($_ENV)) {
            $dotEnv = new Dotenv();
            $envPath = sprintf('%s/.env', dirname(__DIR__));
            //Loads env vars from .env. local. php if the file exists or from the other .env files otherwise
            $dotEnv->bootEnv($envPath);
        }

        // set environment constant
        if (!defined('APPLICATION_ENV')) {
            if ($this->getParam('app_env')) {
                define('APPLICATION_ENV', $this->getParam('app_env'));
            } elseif (isset($_ENV['APP_ENV'])) {
                define('APPLICATION_ENV', $_ENV['APP_ENV']);
            } else {
                $this->displayError("Undefined application environment.");
                die;
            }
        }

        // load constants
        require_once (__DIR__ . '/../public/const.php');

        try {
            $application = new Zend_Application(APPLICATION_ENV, APPLICATION_PATH . '/configs/application.ini');
            $this->_application = $application;
        } catch ( Exception $e ) {
            echo $e->getMessage ();
        }
    }

    public function log($message)
    {
        if (!$this->_log_enabled) {
            return false;
        }

        $filename = EPISCIENCES_LOG_PATH . strtolower(get_class($this)) . '_' . date("Y-m-d") . '.log';

        $file = fopen($filename, 'ab');
        $message = '[' . date("Y-m-d H:i:s") . '] '
            . preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $message)
            . PHP_EOL;
        fwrite($file, $message);
        fclose($file);

        return true;
    }

    /**
     * init database
     */
    public function initDb()
    {
        $db = Zend_Db::factory('PDO_MYSQL', $this->_application->getOption('resources')['db']['params']);
        Zend_Db_Table::setDefaultAdapter($db);

        $this->setDb($db);

        Zend_Registry::set('metadataSources', Episciences_Paper_MetaDataSourcesManager::all(false));

    }

    public function setDb($db)
    {
        $this->_db = $db;
    }

    /**
     * @return Zend_Db_Adapter_Abstract
     */
    public function getDb()
    {
        return $this->_db;
    }

    /**
     * init Zend Translator
     * @param string|null $locale
     * @throws Zend_Locale_Exception
     * @throws Zend_Translate_Exception
     */
    public function initTranslator(string $locale = null)
    {

        $locale = $locale ?: null;

        $translator = new Zend_Translate(
            Zend_Translate::AN_ARRAY,
            APPLICATION_PATH . '/languages',
            $locale
            ,
            array('scan' => Zend_Translate::LOCALE_DIRECTORY));

        Zend_Registry::set('Zend_Translate', $translator);
        Zend_Registry::set('Zend_Locale', new Zend_Locale($translator->getLocale()));

        if (defined('REVIEW_PATH') && is_dir(REVIEW_PATH . 'languages') && count(scandir(REVIEW_PATH . 'languages')) > 2) {
            $translator->addTranslation(REVIEW_PATH . 'languages');
        }

        $this->_translator = $translator;
    }

    public function getTranslator()
    {
        return $this->_translator;
    }

    /**
     * return all user defined parameters
     * @return array
     */
    public function getParams()
    {
        return $this->_params;
    }

    /**
     * return user defined parameter
     * @param $name
     * @return string
     */
    public function getParam($name)
    {
        return (array_key_exists($name, $this->_params)) ? $this->_params[$name] : null;
    }

    /**
     * set user defined parameter
     * @param $name
     * @param $value
     * @param bool $force
     * @return bool
     */
    public function setParam($name, $value, bool $force = true)
    {
        $this->_params[$name] = $value;
        if ($force){
            $this->displayTrace($name . ' param has been set to: ' . $value);
        }

        if ($name === 'app_env' && !defined('APPLICATION_ENV')) {

            if ($force){
                $this->displayTrace('APPLICATION_ENV constant has been set to: ' . $value);
            }

            define('APPLICATION_ENV', $value);
        }

        return true;
    }

    /**
     * return true if user defined parameter exists
     * @param $name
     * @return bool
     */
    public function hasParam($name)
    {
        return array_key_exists($name, $this->_params);
    }

    /**
     * ask for a user input, and return it
     * @param string $message [optional] <p>
     * if specified, message will be shown before waiting for user input
     * </p>
     * @param array $valid_inputs [optional]
     * @param string $default
     * @return string
     */
    /**
     * @param string $message
     * @param array $valid_inputs
     * @param null $color
     * @param string $default
     * @return string
     */
    public function ask($message = '', $valid_inputs=[], $color = null, $default = '')
    {
        // format and display message
        if ($message != '') {
            if ($color && array_key_exists($color, static::$bashColors)) {
                $message = static::$bashColors[$color] . $message;
            }
            $message = static::$bashColors[static::BASH_BOLD] . $message . static::$bashColors[static::BASH_DEFAULT];
            $this->display($message, true, null, false);
        }

        // if valid inputs array is specified, display it so the user can choose
        if (!empty($valid_inputs)) {
            $valid_inputs_string = array();
            foreach ($valid_inputs as $i=>$valid_input) {
                $valid_inputs_string[] = static::$bashColors[static::BASH_BOLD] . static::$bashColors[static::BASH_YELLOW]
                    . $i
                    . static::$bashColors[static::BASH_DEFAULT]
                    . ': ' . $valid_input;
            }
            $this->display(implode(PHP_EOL, $valid_inputs_string), true, null, false);
        }

        // get user input
        echo static::$bashColors[static::BASH_BOLD] . '> ' . static::$bashColors[static::BASH_DEFAULT];
        $input = trim(fgets(STDIN));

        // set empty user input to default, if $default id specified
        if (empty($input) && $default != '') {
            $input = $default;
        }

        // check that user input is valid
        if (!empty($valid_inputs) && !array_key_exists($input, $valid_inputs)) {
            $this->displayError('Invalid input.');
            $input = $this->ask('Please pick one of these:', $valid_inputs, $color);
        }

        return $input;
    }

    protected function checkAppEnv()
    {
        // if missing app_env, ask for it
        if (!$this->hasParam('app_env')) {
            $input = $this->ask('Missing application environment. Please pick one of these:', $this->_valid_envs, static::BASH_YELLOW);
            $this->setParam('app_env', $this->_valid_envs[$input]);
        }

        // if production environment, asks if we want to run it in debug mode
        if (!$this->isForce() && $this->getParam('app_env') == 'production' && !$this->isDebug()) {
            $input = $this->ask("Script is running in production environment. Do you want to run it in debug mode ?", ['yes', 'no']);
            if ($input === 0) {
                $this->setParam('debug', true);
            } else {
                $this->display("OK ! Let's rock !", false, null, false);
            }
        }
    }
}
