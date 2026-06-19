<?php

namespace scripts\Command;

use Episciences_Paper_MetaDataSourcesManager;
use Exception;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Zend_Application;
use Zend_Application_Exception;
use Zend_Db;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Exception;
use Zend_Db_Table;
use Zend_Loader_Autoloader;
use Zend_Locale;
use Zend_Registry;

class Bootstrapper
{
    private ?Zend_Db_Adapter_Abstract $db;
    private LoggerInterface $logger;
    private string $logFile;
    private string $env;

    private int $verbosityLevel;

    /**
     * @param string $env
     * @throws Zend_Application_Exception
     * @throws Zend_Db_Exception
     */

    public function __construct(string $env = 'production')
    {
        $this->env = $env;
        $this->bootstrap();
    }

    /**
     * Initialization based on the console context
     * @throws Exception
     */
    public function initialize(InputInterface $input, OutputInterface $output, ?string $loggerFileName = null): void
    {
        $io = new SymfonyStyle($input, $output);
        $this->verbosityLevel = $output->getVerbosity();
        $this->initLogger($io, $loggerFileName);
    }

    /**
     * @return void
     * @throws Zend_Application_Exception
     * @throws Zend_Db_Exception
     */

    private function bootstrap(): void
    {
        if (!defined('APPLICATION_PATH')) {
            define('APPLICATION_PATH', dirname(__DIR__, 2) . '/application');
        }

        require_once __DIR__ . '/../../public/const.php';
        require_once __DIR__ . '/../../public/bdd_const.php';

        defineProtocol();
        defineSimpleConstants();
        defineSQLTableConstants();
        defineApplicationConstants();
        defineJournalConstants();

        $libraries = [dirname(APPLICATION_PATH) . '/library'];
        set_include_path(implode(PATH_SEPARATOR, array_merge($libraries, [get_include_path()])));
        require_once 'Zend/Application.php';


        // Do NOT call $application->bootstrap() — APPLICATION_MODULE may be undefined
        // which causes Bootstrap::_initModule() to fail silently.

        $application = new Zend_Application($this->env, APPLICATION_PATH . '/configs/application.ini');
        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);
        $db = Zend_Db::factory('PDO_MYSQL', $application->getOption('resources')['db']['params']);
        Zend_Db_Table::setDefaultAdapter($db);


        Zend_Registry::set('metadataSources', Episciences_Paper_MetaDataSourcesManager::all(false));
        Zend_Registry::set('Zend_Locale', new Zend_Locale('en'));

        $this->db = $db;
    }

    /**
     * @param SymfonyStyle|null $io
     * @param string|null $customFileName
     * @return void
     * @throws Exception
     */

    protected function initLogger(SymfonyStyle $io = null, ?string $customFileName = null): void
    {

        if (!$customFileName) {
            $fileName = "default_bootstrap_command_" . date('Ymd_His') . '.log';
        } else {
            $fileName = $customFileName;
        }

        $fileName = sprintf('%s%s.log', EPISCIENCES_LOG_PATH, $fileName);
        $this->logFile = $fileName;

        $logger = new Logger($fileName);

        // Handler Fichier ( Toujours actif, niveau DEBUG pour garder la trace complète)

        $handler = new RotatingFileHandler(
                $this->logFile,
                0, // unlimited
                Logger::DEBUG,
                true,
                0664
        );

        // Console Handler (Conditional based on verbosity level)
        // If --quiet (-q) is passed, OutputInterface::VERBOSITY_QUIET is active
        // Do NOT add a console handler, or use NullHandler.

        if ($io?->isQuiet()) {
            // Quiet mode: A NullHandler is added to consume the logs without displaying anything.
            // DEBUG level to ensure that even critical errors are silently “swallowed.”
            // Note: If you want to display critical errors even in quiet mode, use StreamHandler(‘php://stderr’, Logger::CRITICAL) instead.
            $nullHandler = new NullHandler(Logger::DEBUG);
            $logger->pushHandler($nullHandler);
            // CRITICAL errors are still displayed in the console even in quiet mode:            //$criticalHandler = new StreamHandler('php://stderr', Logger::CRITICAL);
            //$logger->pushHandler($criticalHandler);
        } else {
            // Normal or Verbose Mode
            // Setting the console log level
            $logLevel = Logger::INFO; // Par défaut

            if ($io->isVerbose()) {
                $logLevel = Logger::DEBUG; // Affiche tout si -v ou -vv
            } elseif ($io->isVeryVerbose() || $io->isDebug()) {
                $logLevel = Logger::DEBUG;
            }

            $formatter = new LineFormatter(null, null, false, true);
            $handler->setFormatter($formatter);
            $logger->pushHandler($handler);
            $logger->pushHandler(new StreamHandler('php://stdout', $logLevel));
        }

        $this->logger = $logger;
    }

    public function getDb(): ?Zend_Db_Adapter_Abstract
    {
        return $this->db;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function getLogFile(): string
    {
        return $this->logFile;
    }

    public function getEnvironment(): string
    {
        return $this->env;
    }

}