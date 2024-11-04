<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class TranslationUpdaterCommand extends Command
{
    protected static $defaultName = 'app:update-translations';
    private Logger $logger;

    public function __construct()
    {
        parent::__construct();
        $this->logger = new Logger('translationUpdater');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Update translation strings in a PHP array file')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to the YAML config file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configFile = $input->getOption('config');
        if (!$configFile || !file_exists($configFile)) {
            $output->writeln('<error>Config file is missing or invalid.</error>');
            return Command::FAILURE;
        }

        // Parse configuration file
        $config = Yaml::parseFile($configFile);

        // Validate configuration
        $requiredOptions = ['inputFile', 'outputFile', 'search', 'replace', 'case-sensitive', 'force', 'silent', 'log'];
        foreach ($requiredOptions as $option) {
            if (!isset($config[$option])) {
                $output->writeln("<error>Missing required config option: $option</error>");
                return Command::FAILURE;
            }
        }

        $inputFile = $config['inputFile'];
        $outputFile = $config['outputFile'];
        $search = (array)$config['search'];  // Ensure search and replace are arrays
        $replace = (array)$config['replace'];
        $caseSensitive = $config['case-sensitive'];
        $force = $config['force'];
        $silent = $config['silent'];
        $logFile = $config['log'];

        // Setup logging
        $this->logger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));
        $this->logger->info("Starting translation update process.");

        if (!file_exists($inputFile)) {
            $output->writeln("<error>Input file does not exist: $inputFile</error>");
            return Command::FAILURE;
        }

        if ($inputFile === $outputFile) {
            $output->writeln('<error>Input and output files cannot be the same to prevent overwriting.</error>');
            return Command::FAILURE;
        }

        if (file_exists($outputFile) && !$force) {
            if (!$silent) {
                $output->writeln("<question>Output file already exists. Do you want to overwrite it? [y/N]</question>");
                $confirmation = trim(fgets(STDIN));
                if (strtolower($confirmation) !== 'y') {
                    $output->writeln("<info>Operation aborted by user.</info>");
                    return Command::SUCCESS;
                }
            }
        }

        // Check if search and replace arrays are the same length
        if (count($search) !== count($replace)) {
            $output->writeln("<error>The 'search' and 'replace' arrays must have the same number of elements.</error>");
            return Command::FAILURE;
        }

        // Read the file content as text
        $fileContent = file_get_contents($inputFile);
        if ($fileContent === false) {
            $output->writeln("<error>Failed to read the input file.</error>");
            return Command::FAILURE;
        }

        // Pattern to match each array entry with optional single or double quotes for keys and values
        $pattern = '/([\'"])(.*?)\1\s*=>\s*([\'"])(.*?)\3/s';
        $replacementCount = 0;

        // Apply each search-replace pair
        $updatedContent = preg_replace_callback(
            $pattern,
            function ($matches) use ($search, $replace, $caseSensitive, &$replacementCount) {
                [$fullMatch, $keyQuote, $key, $valueQuote, $value] = $matches;

                // Apply each search-replace pair on the value
                foreach ($search as $index => $searchTerm) {
                    $replaceTerm = $replace[$index];
                    if ($caseSensitive) {
                        $newValue = str_replace($searchTerm, $replaceTerm, $value);
                    } else {
                        $newValue = str_ireplace($searchTerm, $replaceTerm, $value);
                    }
                    if ($newValue !== $value) {
                        $value = $newValue;
                        $replacementCount++;
                    }
                }

                // Return the key with its original quotes and the updated value
                return "{$keyQuote}{$key}{$keyQuote} => {$valueQuote}{$value}{$valueQuote}";
            },
            $fileContent
        );

        if ($updatedContent === null) {
            $output->writeln("<error>Error processing the input file content.</error>");
            return Command::FAILURE;
        }

        // Write updated content to the output file
        if (file_put_contents($outputFile, $updatedContent) === false) {
            $output->writeln("<error>Failed to write to the output file.</error>");
            return Command::FAILURE;
        }

        // Count and compare significant lines between input and output
        $inputSignificantLines = $this->countSignificantLines($fileContent);
        $outputSignificantLines = $this->countSignificantLines($updatedContent);

        // Output the comparison results
        if ($inputSignificantLines === $outputSignificantLines) {
            $output->writeln("<info>No change in the number of significant lines.</info>");
        } else {
            $output->writeln("<info>Significant lines - Input: $inputSignificantLines, Output: $outputSignificantLines</info>");
        }

        $this->logger->info("Translation update completed successfully.", [
            'replacements' => $replacementCount,
            'inputLines' => $inputSignificantLines,
            'outputLines' => $outputSignificantLines,
        ]);

        $output->writeln("<info>Translation update completed successfully. Output file: $outputFile, Replacements made: $replacementCount</info>");

        return Command::SUCCESS;
    }

    private function countSignificantLines(string $content): int
    {
        $lines = explode(PHP_EOL, $content);
        $significantLines = 0;

        foreach ($lines as $line) {
            // Consider a line significant if it matches the pattern for a key-value pair
            if (preg_match('/([\'"])(.*?)\1\s*=>\s*([\'"])(.*?)\3/', $line)) {
                $significantLines++;
            }
        }

        return $significantLines;
    }
}

$application = new Application();
$application->add(new TranslationUpdaterCommand());
$application->run();
