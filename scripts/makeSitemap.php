<?php
/**
 * Generate a sitemap for the specified RV code
 * Example usage:
 * php scripts/makeSitemap.php app:generate-sitemap dmtcs --pretty
 */

namespace App\Command;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

$localopts = [
    'pretty' => "Pretty printing",
    'rvcode' => 'rvcode',
];

require_once __DIR__ . '/loadHeader.php';
require_once "JournalScript.php";

class GenerateSitemap extends Command
{
    protected static $defaultName = 'app:generate-sitemap';
    private Logger $logger;
    private Client $httpClient;

    public function __construct()
    {
        parent::__construct();

        // Initialize the HTTP client and logger
        $this->httpClient = new Client(['base_uri' => EPISCIENCES_API_URL]);

        $this->logger = new Logger('sitemap');
        $this->logger->pushHandler(new StreamHandler(EPISCIENCES_LOG_PATH . 'sitemap.log', Logger::DEBUG));
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Generate a sitemap for the specified RV code.')
            ->addArgument('rvcode', InputArgument::REQUIRED, 'The RV code for which the sitemap should be generated.')
            ->addOption(
                'pretty',
                null,
                InputOption::VALUE_NONE,
                'If set, the XML sitemap will be pretty-printed.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rvcode = $input->getArgument('rvcode');
        $prettyPrint = $input->getOption('pretty');

        $output->writeln("Generating sitemap for RV code: {$rvcode}");

        try {
            $this->generate($rvcode, $prettyPrint);
            $output->writeln("Sitemap generation completed.");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("Error: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function generate(string $rvcode, bool $prettyPrint): void
    {
        $sitemapDir = APPLICATION_PATH . '/../data/' . $rvcode . '/sitemap';
        if (!is_dir($sitemapDir)) {
            mkdir($sitemapDir, 0777, true);
        }

        $this->logger->info("Starting sitemap generation", ['rvcode' => $rvcode]);

        // Fetch entries from the API
        $entries = $this->getSitemapArticleEntries($rvcode);

        if (empty($entries)) {
            $this->logger->warning("No entries found for the provided RV code", ['rvcode' => $rvcode]);
            throw new \RuntimeException("No entries found to generate the sitemap.");
        }

        $sitemapFile = "$sitemapDir/sitemap.xml";
        $this->generateSitemapFile($entries, $sitemapFile, $prettyPrint);

        $this->logger->info("Sitemap generation completed", ['rvcode' => $rvcode, 'file' => $sitemapFile]);
    }

    private function getSitemapArticleEntries(string $rvcode): array
    {
        $journalBaseUrl = $rvcode . '.' . DOMAIN;
        $baseUrl = EPISCIENCES_API_URL . "papers/?rvcode={$rvcode}&itemsPerPage=30&pagination=true";
        $entries = [];
        $url = $baseUrl;

        try {
            do {
                $response = $this->httpClient->get($url);
                $data = json_decode($response->getBody()->getContents(), true);

                foreach ($data['hydra:member'] as $paper) {
                    $entries[] = [
                        'loc' => sprintf("https://%s/articles/%s", $journalBaseUrl, $paper['docid']),
                        'lastmod' => null,
                        'changefreq' => 'monthly',
                        'priority' => '0.5',
                    ];
                }

                $url = $data['hydra:view']['hydra:next'] ?? null;
            } while ($url);
        } catch (GuzzleException $e) {
            $this->logger->error("Error fetching data from API", ['rvcode' => $rvcode, 'error' => $e->getMessage()]);
        }

        return $entries;
    }

    private function generateSitemapFile(array $entries, string $filePath, bool $prettyPrint): void
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');

        foreach ($entries as $entry) {
            $url = $xml->addChild('url');
            $url->addChild('loc', htmlspecialchars($entry['loc']));
            if (!empty($entry['lastmod'])) {
                $url->addChild('lastmod', $entry['lastmod']);
            }
            if (!empty($entry['changefreq'])) {
                $url->addChild('changefreq', $entry['changefreq']);
            }
            if (!empty($entry['priority'])) {
                $url->addChild('priority', $entry['priority']);
            }
        }

        if ($prettyPrint) {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            $dom->loadXML($xml->asXML());
            $dom->save($filePath);
        } else {
            $xml->asXML($filePath);
        }
    }
}

// Setup and run the application
$application = new Application();
$application->add(new GenerateSitemap());
$application->run();
