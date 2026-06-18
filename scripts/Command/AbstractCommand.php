<?php
declare(strict_types=1);

namespace scripts\Command;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Zend_Db_Adapter_Abstract;

/**
 * Manages Context and Logging
 */
abstract class AbstractCommand extends Command
{
    public const IO_TABLE_ITEM_PER_PAGE = 50;

    protected SymfonyStyle $io;
    protected Bootstrapper $bootstrapper;
    protected LoggerInterface $logger;
    protected ?Zend_Db_Adapter_Abstract $db;
    protected string $logFile;
    protected string $env;

    public function __construct()
    {
        $this->bootstrapper = new Bootstrapper();
        // The name is defined in configure(), so we pass null here or use getName()
        parent::__construct();
    }

    /**
     * Context initialization (called automatically before "execute")
     * Configure the logger based on the console's verbosity level
     * @throws Exception
     */
    final protected function initializeContext(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        $commandName = $this->getName();
        $this->toSafeName($commandName);
        $this->bootstrapper->initialize($input, $output, $commandName);
        $this->logger = $this->bootstrapper->getLogger();
        $this->db = $this->bootstrapper->getDb();
        $this->logFile = $this->bootstrapper->getLogFile();
        $this->env = $this->bootstrapper->getEnvironment();

        // boot log
        $this->logger->info("Initializing the command: " . $this->getName() . " (Env: {$this->env})");
    }


    final public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->initializeContext($input, $output);

            return $this->runLogic($input, $output);

        } catch (Exception $e) {

            $this->logger->critical($e->getMessage());

            if ($this->io->isVerbose()) {
                $this->io->text($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    /**
     * Clean up the name to make it a valid filename:
     * - Replace ‘:’ with '_'
     * - Remove all non-alphanumeric characters, hyphens, and underscores
     * - Convert everything to lowercase
     */

    private function toSafeName(string &$name): void
    {
        $name = preg_replace('/[^a-z0-9_-]/', '', strtolower(str_replace(':', '_', $name)));
    }

    abstract protected function runLogic(InputInterface $input, OutputInterface $output): int;


    protected function showTable($headers, array $rows): void
    {
        $table = new Table($this->io);

        $table
                ->setHeaders($headers)
                ->setRows($rows)
                ->render();
    }

}