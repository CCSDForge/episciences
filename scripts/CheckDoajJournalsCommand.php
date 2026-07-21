<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Symfony Console command: check journals presence in DOAJ by ISSN.
 */
class CheckDoajJournalsCommand extends Command
{
    protected static $defaultName = 'doaj:check-journals';

    protected function configure(): void
    {
        $this
            ->setDescription('Display the list of journals present in DOAJ by querying DOAJ API with ISSNs from database.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bootstrap();

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        // Fetch all unique ISSN values from REVIEW_SETTING
        $select = $db->select()
            ->from(T_REVIEW_SETTINGS, ['VALUE'])
            ->where('SETTING = ?', 'issn')
            ->where('VALUE IS NOT NULL')
            ->where('VALUE != ?', '');

        $rows = $db->fetchAll($select);
        $issns = array_filter(array_unique(array_column($rows, 'VALUE')));

        if (empty($issns)) {
            $output->writeln("No ISSN found in the database.");
            return Command::SUCCESS;
        }

        $client = new Client([
            'headers' => [
                'accept' => 'application/json',
                'User-Agent' => defined('EPISCIENCES_USER_AGENT') ? EPISCIENCES_USER_AGENT : 'Episciences CLI',
            ],
            'timeout' => 10.0,
        ]);

        $journals = [];

        foreach ($issns as $issn) {
            $issn = trim($issn);
            if ($issn === '') {
                continue;
            }

            try {
                // DOAJ API endpoint
                $url = 'https://doaj.org/api/search/journals/issn%3A' . urlencode($issn);

                $response = $client->get($url);
                $body = $response->getBody()->getContents();
                $data = json_decode($body, true);

                if (is_array($data) && !empty($data['results']) && isset($data['total']) && $data['total'] > 0) {
                    foreach ($data['results'] as $result) {
                        if (!is_array($result)) {
                            continue;
                        }
                        $title = $result['bibjson']['title'] ?? '';
                        $journalUrl = $result['bibjson']['ref']['journal'] ?? '';

                        $journals[] = [
                            'title' => (string) $title,
                            'url' => (string) $journalUrl,
                            'issn' => $issn,
                        ];
                    }
                }
            } catch (GuzzleException $e) {
                // We write the error on stderr to keep stdout clean for the CSV output
                if ($output instanceof \Symfony\Component\Console\Output\ConsoleOutputInterface) {
                    $output->getErrorOutput()->writeln(sprintf("Error querying DOAJ API for ISSN %s: %s", $issn, $e->getMessage()));
                }
            } catch (\Throwable $e) {
                if ($output instanceof \Symfony\Component\Console\Output\ConsoleOutputInterface) {
                    $output->getErrorOutput()->writeln(sprintf("Unexpected error for ISSN %s: %s", $issn, $e->getMessage()));
                }
            }
        }

        // Sort journals alphabetically by title
        usort($journals, function (array $a, array $b): int {
            return strcasecmp($a['title'], $b['title']);
        });

        // Print CSV Header
        $output->writeln("'Journal Name';'Journal URL';'ISSN'");

        // Print results
        foreach ($journals as $journal) {
            $output->writeln(sprintf("'%s';'%s';'%s'",
                str_replace("'", "\\'", $journal['title']),
                str_replace("'", "\\'", $journal['url']),
                str_replace("'", "\\'", $journal['issn'])
            ));
        }

        return Command::SUCCESS;
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

        $application = new Zend_Application('production', APPLICATION_PATH . '/configs/application.ini');

        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);

        $db = Zend_Db::factory('PDO_MYSQL', $application->getOption('resources')['db']['params']);
        Zend_Db_Table::setDefaultAdapter($db);

        Zend_Registry::set('metadataSources', Episciences_Paper_MetaDataSourcesManager::all(false));
        Zend_Registry::set('Zend_Locale', new Zend_Locale('en'));
    }
}
