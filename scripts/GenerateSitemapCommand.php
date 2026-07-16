<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
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
            ->setDescription('Generate a sitemap for one journal or all active journals.')
            ->addOption('rvcode', null, InputOption::VALUE_REQUIRED, 'The RV code of the journal — mutually exclusive with --all.')
            ->addOption('all',    null, InputOption::VALUE_NONE,     'Process all active journals (STATUS = 1) — mutually exclusive with --rvcode.')
            ->addOption('pretty', null, InputOption::VALUE_NONE,     'Pretty-print the XML sitemap.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io          = new SymfonyStyle($input, $output);
        $rvcode      = $input->getOption('rvcode');
        $all         = (bool) $input->getOption('all');
        $prettyPrint = (bool) $input->getOption('pretty');

        if ($rvcode && $all) {
            $io->error('--rvcode and --all are mutually exclusive.');
            return Command::FAILURE;
        }

        if (!$rvcode && !$all) {
            $io->error('Specify either --rvcode=CODE or --all.');
            return Command::FAILURE;
        }

        $io->title('Sitemap generation');
        $this->bootstrap();

        $logger = new Logger('sitemapGeneration');
        $logger->pushHandler(new StreamHandler(
            EPISCIENCES_LOG_PATH . 'sitemapGeneration_' . date('Y-m-d') . '.log', Logger::INFO
        ));
        if (!$io->isQuiet()) {
            $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }

        $rvcodes = $all ? $this->fetchActiveRvcodes($logger) : [$rvcode];

        if (empty($rvcodes)) {
            $io->warning('No active journal found (STATUS = 1).');
            return Command::SUCCESS;
        }

        $io->writeln(sprintf('Journals to process: %s', implode(', ', $rvcodes)));
        $logger->info(sprintf('Journals to process: %s', implode(', ', $rvcodes)));

        $client = new Client(['base_uri' => EPISCIENCES_API_URL]);
        $failures = [];

        foreach ($rvcodes as $code) {
            $io->section("Journal: {$code}");
            try {
                $this->generate($code, $prettyPrint, $client, $logger);
                $io->writeln("<info>Sitemap generated for {$code}.</info>");
            } catch (\Throwable $e) {
                $logger->error("Sitemap generation failed for {$code}: " . $e->getMessage());
                $io->error("[{$code}] " . $e->getMessage());
                $failures[] = $code;
            }
        }

        if (!empty($failures)) {
            $io->warning('Failed journals: ' . implode(', ', $failures));
            return Command::FAILURE;
        }

        $io->success('Sitemap generation completed.');
        return Command::SUCCESS;
    }

    private function generate(string $rvcode, bool $prettyPrint, Client $client, Logger $logger): void
    {
        $sitemapDir = APPLICATION_PATH . '/../data/' . $rvcode . '/sitemap';
        if (!is_dir($sitemapDir) && !mkdir($sitemapDir, 0777, true) && !is_dir($sitemapDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $sitemapDir));
        }

        $logger->info('Starting sitemap generation', ['rvcode' => $rvcode]);

        ['rvid' => $rvid, 'languages' => $languages] = $this->fetchJournalInfo($rvcode);

        $entries = array_merge(
            $this->getSitemapGenericEntries($rvcode, $languages),
            $this->getSitemapVolumeAndSectionEntries($rvcode, $rvid, $languages),
            $this->getSitemapPageEntries($rvcode, $client, $logger, $languages),
            $this->getSitemapArticleEntries($rvcode, $client, $logger, $languages),
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
     * @param string[] $languages
     * @return array<int, array<string, mixed>>
     */
    private function getSitemapArticleEntries(string $rvcode, Client $client, Logger $logger, array $languages): array
    {
        $base    = sprintf('https://%s.%s', $rvcode, DOMAIN);
        $url     = EPISCIENCES_API_URL . "papers/?rvcode={$rvcode}&itemsPerPage=30&pagination=true";
        $entries = [];

        try {
            do {
                $response = $client->get($url);
                $data     = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

                foreach ($data['hydra:member'] as $paper) {
                    $modDate = $paper['document']['database']['current']['dates']['modification_date']
                        ?? $paper['modification_date']
                        ?? null;
                    $lastmod = $modDate ? date('Y-m-d', strtotime($modDate)) : null;
                    foreach ($this->buildLocUrls($base, '/articles/' . $paper['docid'], $languages) as $loc) {
                        $entries[] = [
                            'loc'     => $loc,
                            'lastmod' => $lastmod,
                        ];
                    }
                }

                $url = $data['hydra:view']['hydra:next'] ?? null;
            } while ($url !== null);
        } catch (GuzzleException $e) {
            $logger->error('Error fetching papers from API', ['rvcode' => $rvcode, 'url' => $url, 'error' => $e->getMessage()]);
        }

        return $entries;
    }

    /**
     * Fetch visible page entries from the Episciences API.
     *
     * @param string[] $languages
     * @return array<int, array<string, mixed>>
     */
    private function getSitemapPageEntries(string $rvcode, Client $client, Logger $logger, array $languages): array
    {
        $base    = sprintf('https://%s.%s', $rvcode, DOMAIN);
        $url     = EPISCIENCES_API_URL . "pages?pagination=false&rvcode={$rvcode}";
        $entries = [];

        try {
            $response = $client->get($url);
            $pages    = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

            foreach ($pages as $page) {
                $dateUpdated = $page['date_updated'] ?? null;
                $lastmod     = $dateUpdated ? date('Y-m-d', strtotime($dateUpdated)) : null;
                foreach ($this->buildLocUrls($base, '/' . $page['page_code'], $languages) as $loc) {
                    $entries[] = [
                        'loc'     => $loc,
                        'lastmod' => $lastmod,
                    ];
                }
            }
        } catch (GuzzleException $e) {
            $logger->error('Error fetching pages from API', ['rvcode' => $rvcode, 'error' => $e->getMessage()]);
        }

        return $entries;
    }

    /**
     * Build static generic URL entries (home, articles, authors, volumes, sections, about).
     *
     * @param string[] $languages
     * @return array<int, array<string, mixed>>
     */
    private function getSitemapGenericEntries(string $rvcode, array $languages): array
    {
        $base  = sprintf('https://%s.%s', $rvcode, DOMAIN);
        $paths = ['/', '/articles', '/authors', '/volumes', '/sections', '/about'];

        $entries = [];
        foreach ($paths as $path) {
            foreach ($this->buildLocUrls($base, $path, $languages) as $loc) {
                $entries[] = ['loc' => $loc];
            }
        }

        return $entries;
    }

    /**
     * Build individual volume and section URL entries.
     *
     * @param string[] $languages
     * @return array<int, array<string, mixed>>
     */
    private function getSitemapVolumeAndSectionEntries(string $rvcode, int $rvid, array $languages): array
    {
        $base    = sprintf('https://%s.%s', $rvcode, DOMAIN);
        $db      = \Zend_Db_Table_Abstract::getDefaultAdapter();
        $entries = [];

        $vids = $db->fetchCol($db->select()->from(T_VOLUMES, 'VID')->where('RVID = ?', $rvid));
        foreach ($vids as $vid) {
            foreach ($this->buildLocUrls($base, '/volumes/' . $vid, $languages) as $loc) {
                $entries[] = ['loc' => $loc];
            }
        }

        $sids = $db->fetchCol($db->select()->from(T_SECTIONS, 'SID')->where('RVID = ?', $rvid));
        foreach ($sids as $sid) {
            foreach ($this->buildLocUrls($base, '/sections/' . $sid, $languages) as $loc) {
                $entries[] = ['loc' => $loc];
            }
        }

        return $entries;
    }

    /**
     * Resolve RVID and interface languages for a journal.
     *
     * @return array{rvid: int, languages: string[]}
     */
    private function fetchJournalInfo(string $rvcode): array
    {
        $review = \Episciences_ReviewsManager::findByRvcode($rvcode);
        if (!$review) {
            throw new \RuntimeException("Journal '{$rvcode}' not found.");
        }
        $rvid      = $review->getRvid();
        $website   = new \Ccsd_Website_Common($rvid, ['sidField' => 'SID']);
        $languages = $website->getLanguages();

        return ['rvid' => $rvid, 'languages' => $languages];
    }

    /**
     * Build one URL per language (with prefix) or a single URL (no prefix for legacy sites).
     *
     * @param string[] $languages
     * @return string[]
     */
    private function buildLocUrls(string $base, string $path, array $languages): array
    {
        if (empty($languages)) {
            return [$base . $path];
        }
        return array_map(static fn(string $lang) => $base . '/' . $lang . $path, $languages);
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

    /**
     * Return all rvcode values for active journals (STATUS = 1).
     *
     * @return string[]
     */
    private function fetchActiveRvcodes(Logger $logger): array
    {
        $db     = \Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql    = 'SELECT CODE FROM REVIEW WHERE STATUS = 1 ORDER BY CODE';
        $stmt   = $db->prepare($sql);
        $stmt->execute();
        $codes  = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $logger->info(sprintf('Found %d active journal(s) (STATUS = 1).', count($codes)));
        return $codes;
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
