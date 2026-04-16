<?php
declare(strict_types=1);

require_once __DIR__ . '/../library/Episciences/Translation/Replacer.php';

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Symfony Console command: update translation strings in a PHP array file.
 *
 * YAML config (--config) must provide:
 *   inputFile  : path to the source PHP translation file
 *   outputFile : path to the destination file (must differ from inputFile)
 *   search     : string or list of strings to search for
 *   replace    : string or list of strings used as replacements (same length as search)
 *
 * Flags moved from YAML to CLI options: --force, --case-sensitive, --log.
 * Legacy YAML keys (force / silent / case-sensitive / log) are ignored with a warning.
 *
 * Replaces: scripts/translationUpdater.php
 */
class UpdateTranslationsCommand extends Command
{
    protected static $defaultName = 'translations:update';

    /** @var list<string> Keys that existed in the old YAML format and are now CLI options. */
    private const LEGACY_YAML_KEYS = ['force', 'silent', 'case-sensitive', 'log'];

    private Logger $logger;

    protected function configure(): void
    {
        $this
            ->setDescription('Update translation strings in a PHP array file.')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to the YAML config file')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite the output file without asking')
            ->addOption('case-sensitive', null, InputOption::VALUE_NONE, 'Use case-sensitive search (default: case-insensitive)')
            ->addOption('log', null, InputOption::VALUE_REQUIRED, 'Path to a log file (optional; defaults to stdout only)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io            = new SymfonyStyle($input, $output);
        $force         = (bool) $input->getOption('force');
        $caseSensitive = (bool) $input->getOption('case-sensitive');
        $logFile       = $input->getOption('log');
        $configPath    = $input->getOption('config');

        if (!is_string($configPath) || $configPath === '') {
            $io->error('Missing required option: --config');
            return Command::FAILURE;
        }

        $this->initLogger($logFile, $io->isQuiet());
        $io->title('Translation update');

        $config = $this->loadConfig($configPath, $io);
        if ($config === null) {
            return Command::FAILURE;
        }

        $this->warnLegacyKeys($config, $io);

        $inputFile  = $this->requireStringConfig($config, 'inputFile', $io);
        $outputFile = $this->requireStringConfig($config, 'outputFile', $io);
        $search     = $this->requireStringArrayConfig($config, 'search', $io);
        $replace    = $this->requireStringArrayConfig($config, 'replace', $io);

        if ($inputFile === null || $outputFile === null || $search === null || $replace === null) {
            return Command::FAILURE;
        }

        if (count($search) !== count($replace)) {
            $io->error("'search' and 'replace' arrays must have the same number of elements.");
            return Command::FAILURE;
        }

        if (!file_exists($inputFile)) {
            $io->error("Input file does not exist: {$inputFile}");
            return Command::FAILURE;
        }

        if ($inputFile === $outputFile) {
            $io->error('Input and output files must differ to prevent overwriting the source.');
            return Command::FAILURE;
        }

        if (file_exists($outputFile) && !$force) {
            if (!$io->confirm("Output file already exists. Overwrite?", false)) {
                $io->note('Operation aborted.');
                return Command::SUCCESS;
            }
        }

        $content = file_get_contents($inputFile);
        if ($content === false) {
            $io->error("Failed to read input file: {$inputFile}");
            return Command::FAILURE;
        }

        $this->logger->info('Starting translation update.', [
            'input'         => $inputFile,
            'output'        => $outputFile,
            'caseSensitive' => $caseSensitive,
            'pairs'         => count($search),
        ]);

        $replacer = new Episciences_Translation_Replacer($search, $replace, $caseSensitive);

        try {
            $updated = $replacer->replace($content);
        } catch (\RuntimeException $e) {
            $io->error('Regex processing error: ' . $e->getMessage());
            $this->logger->error('Regex processing error.', ['exception' => $e->getMessage()]);
            return Command::FAILURE;
        }

        if (file_put_contents($outputFile, $updated) === false) {
            $io->error("Failed to write output file: {$outputFile}");
            return Command::FAILURE;
        }

        $replacementCount   = $replacer->getReplacementCount();
        $inputLineCount     = Episciences_Translation_Replacer::countSignificantLines($content);
        $outputLineCount    = Episciences_Translation_Replacer::countSignificantLines($updated);

        $this->logger->info('Translation update completed.', [
            'replacements' => $replacementCount,
            'inputLines'   => $inputLineCount,
            'outputLines'  => $outputLineCount,
        ]);

        $io->success(sprintf(
            'Done. Output: %s — %d replacement(s) made.',
            $outputFile,
            $replacementCount
        ));

        if ($inputLineCount !== $outputLineCount) {
            $io->warning(sprintf(
                'Significant line count changed: input=%d, output=%d.',
                $inputLineCount,
                $outputLineCount
            ));
        } else {
            $io->note('Significant line count unchanged.');
        }

        return Command::SUCCESS;
    }

    /**
     * @param mixed $logFile
     */
    private function initLogger(mixed $logFile, bool $quiet): void
    {
        $this->logger = new Logger('translations-update');

        if (is_string($logFile) && $logFile !== '') {
            $fileHandler = new StreamHandler($logFile, Logger::DEBUG);
            $fileHandler->setFormatter(new LineFormatter("[%datetime%] %level_name%: %message% %context%\n", null, false, true));
            $this->logger->pushHandler($fileHandler);
        }

        if (!$quiet) {
            $consoleHandler = new StreamHandler('php://stdout', Logger::INFO);
            $consoleHandler->setFormatter(new LineFormatter("%level_name%: %message%\n", null, false, false));
            $this->logger->pushHandler($consoleHandler);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadConfig(string $configPath, SymfonyStyle $io): ?array
    {
        if (!file_exists($configPath)) {
            $io->error("Config file not found: {$configPath}");
            return null;
        }

        try {
            $raw = Yaml::parseFile($configPath);
        } catch (ParseException $e) {
            $io->error('Failed to parse YAML config: ' . $e->getMessage());
            return null;
        }

        if (!is_array($raw)) {
            $io->error('Config file must be a YAML mapping (key: value pairs).');
            return null;
        }

        /** @var array<string, mixed> $raw */
        return $raw;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function requireStringConfig(array $config, string $key, SymfonyStyle $io): ?string
    {
        if (!isset($config[$key]) || !is_string($config[$key])) {
            $io->error("Missing or invalid config key '{$key}': expected a non-empty string.");
            return null;
        }
        return $config[$key];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<int, string>|null
     */
    private function requireStringArrayConfig(array $config, string $key, SymfonyStyle $io): ?array
    {
        if (!isset($config[$key])) {
            $io->error("Missing required config key: '{$key}'.");
            return null;
        }

        $raw   = $config[$key];
        $items = is_array($raw) ? $raw : [$raw];
        $result = [];

        foreach ($items as $item) {
            if (!is_string($item)) {
                $io->error("Config key '{$key}' must be a string or a list of strings.");
                return null;
            }
            $result[] = $item;
        }

        return $result;
    }

    /**
     * Warn about YAML keys that were valid in the old script but are now CLI options.
     *
     * @param array<string, mixed> $config
     */
    private function warnLegacyKeys(array $config, SymfonyStyle $io): void
    {
        foreach (self::LEGACY_YAML_KEYS as $key) {
            if (array_key_exists($key, $config)) {
                $cliKey = $key === 'silent' ? '-q / --quiet' : "--{$key}";
                $io->warning("YAML key '{$key}' is no longer used. Pass {$cliKey} as a CLI option instead.");
            }
        }
    }
}