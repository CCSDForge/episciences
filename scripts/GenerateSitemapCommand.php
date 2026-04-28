<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: generate a sitemap for the specified journal.
 *
 * Replaces: scripts/makeSitemap.php (standalone Symfony Console app)
 */
class GenerateSitemapCommand extends Command
{
    protected static $defaultName = 'sitemap:generate';

    protected function configure(): void
    {
        $this
            ->setDescription('Generate a sitemap for the specified journal (rvcode).')
            ->addArgument('rvcode', InputArgument::REQUIRED, 'The RV code for which the sitemap should be generated.')
            ->addOption('pretty', null, InputOption::VALUE_NONE, 'Pretty-print the XML sitemap.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io          = new SymfonyStyle($input, $output);
        $rvcode      = (string) $input->getArgument('rvcode');
        $prettyPrint = (bool)   $input->getOption('pretty');

        $io->title("Sitemap generation for journal: {$rvcode}");
        $this->bootstrap();

        $logger = new Logger('sitemapGeneration');
        $logger->pushHandler(new StreamHandler(
            EPISCIENCES_LOG_PATH . 'sitemapGeneration_' . date('Y-m-d') . '.log', Logger::INFO
        ));
        if (!$io->isQuiet()) {
            $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }

        $client = new Client();

        try {
            $this->generate($rvcode, $prettyPrint, $client, $logger);
            $io->success('Sitemap generation completed.');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $logger->error('Sitemap generation failed: ' . $e->getMessage());
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    private function generate(string $rvcode, bool $prettyPrint, Client $client, Logger $logger): void
    {
        $sitemapDir = APPLICATION_PATH . '/../data/' . $rvcode . '/sitemap';
        if (!is_dir($sitemapDir) && !mkdir($sitemapDir, 0777, true) && !is_dir($sitemapDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $sitemapDir));
        }

        $logger->info('Starting sitemap generation', ['rvcode' => $rvcode]);

        $entries = array_merge(
            $this->getSitemapGenericEntries($rvcode),
            $this->getSitemapArticleEntries($rvcode, $client, $logger)
        );

        if (empty($entries)) {
            $logger->warning('No entries found for the provided RV code', ['rvcode' => $rvcode]);
            throw new \RuntimeException('No entries found to generate the sitemap.');
        }

        $sitemapFile = "{$sitemapDir}/sitemap.xml";
        $this->generateSitemapFile($entries, $sitemapFile, $prettyPrint);

        $logger->info('Sitemap generation completed', ['rvcode' => $rvcode, 'file' => $sitemapFile]);
    }

    /**
     * Fetch article entries from the Episciences API (paginated).
     *
     * @return array<int, array<string, mixed>>
     */
    private function getSitemapArticleEntries(string $rvcode, Client $client, Logger $logger): array
    {
        $journalBaseUrl = $rvcode . '.' . DOMAIN;
        $url            = EPISCIENCES_API_URL . "papers/?rvcode={$rvcode}&itemsPerPage=30&pagination=true";
        $entries        = [];

        try {
            do {
                $response = $client->get($url);
                $data     = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

                foreach ($data['hydra:member'] as $paper) {
                    $entries[] = [
                        'loc'        => sprintf('https://%s/articles/%s', $journalBaseUrl, $paper['docid']),
                        'lastmod'    => null,
                        'changefreq' => 'weekly',
                        'priority'   => '0.9',
                    ];
                }

                $url = $data['hydra:view']['hydra:next'] ?? null;
            } while ($url !== null);
        } catch (GuzzleException $e) {
            $logger->error('Error fetching papers from API', ['rvcode' => $rvcode, 'error' => $e->getMessage()]);
        }

        return $entries;
    }

    /**
     * Build static generic URL entries (home, articles, authors, volumes, sections, about).
     *
     * @return array<int, array<string, mixed>>
     */
    private function getSitemapGenericEntries(string $rvcode): array
    {
        $journalBaseUrl = sprintf('%s.%s', $rvcode, DOMAIN);

        $createEntry = static fn(string $path, string $changefreq, string $priority): array => [
            'loc'        => sprintf('https://%s%s', $journalBaseUrl, $path),
            'lastmod'    => null,
            'changefreq' => $changefreq,
            'priority'   => $priority,
        ];

        // Keys encode the changefreq as the first underscore-delimited segment.
        $urlCollections = [
            'daily' => [
                'priority' => '1',
                'paths'    => ['/'],
            ],
            'daily_articles_authors' => [
                'priority' => '0.8',
                'paths'    => ['/articles', '/authors'],
            ],
            'weekly' => [
                'priority' => '0.8',
                'paths'    => ['/volumes', '/sections', '/about'],
            ],
        ];

        $entries = [];
        foreach ($urlCollections as $key => $data) {
            $changefreq = explode('_', $key, 2)[0];
            foreach ($data['paths'] as $path) {
                $entries[] = $createEntry($path, $changefreq, $data['priority']);
            }
        }

        return $entries;
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    private function generateSitemapFile(array $entries, string $filePath, bool $prettyPrint): void
    {
        $xml = new \SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>'
        );

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
            $dom                   = new \DOMDocument('1.0', 'UTF-8');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput     = true;
            $dom->loadXML((string) $xml->asXML());
            $dom->save($filePath);
        } else {
            $xml->asXML($filePath);
        }
    }

    private function bootstrap(): void
    {
        if (!defined('APPLICATION_PATH')) {
            define('APPLICATION_PATH', realpath(__DIR__ . '/../application'));
        }
        require_once __DIR__ . '/../public/const.php';
        require_once __DIR__ . '/../public/bdd_const.php';

        defineProtocol();
        defineSimpleConstants();
        defineSQLTableConstants();
        defineApplicationConstants();
        defineJournalConstants();

        $libraries = [realpath(APPLICATION_PATH . '/../library')];
        set_include_path(implode(PATH_SEPARATOR, array_merge($libraries, [get_include_path()])));
        require_once 'Zend/Application.php';

        // Do NOT call $application->bootstrap() — APPLICATION_MODULE may be undefined
        // (no rvcode) which causes Bootstrap::_initModule() to fail silently.
        // Mirrors legacy JournalScript pattern: initApp() reads config, initDb() sets adapter.
        $application = new Zend_Application('production', APPLICATION_PATH . '/configs/application.ini');

        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);

        $db = Zend_Db::factory('PDO_MYSQL', $application->getOption('resources')['db']['params']);
        Zend_Db_Table::setDefaultAdapter($db);

        Zend_Registry::set('metadataSources', Episciences_Paper_MetaDataSourcesManager::all(false));
        Zend_Registry::set('Zend_Locale', new Zend_Locale('en'));
    }
}
