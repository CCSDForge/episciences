<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection as DbalConnection;
use Episciences\Solr\Indexing\Build\AuthorFieldsBuilder;
use Episciences\Solr\Indexing\Build\DateFieldsBuilder;
use Episciences\Solr\Indexing\Build\DocumentBuilder;
use Episciences\Solr\Indexing\Build\ExportFieldsBuilder;
use Episciences\Solr\Indexing\Build\KeywordFieldsBuilder;
use Episciences\Solr\Indexing\Build\LocaleFieldsBuilder;
use Episciences\Solr\Indexing\Build\VolumeSectionResolver;
use Episciences\Solr\Indexing\Messenger\Dbal\DbalConnectionFactory;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shared bootstrap for the solr:* commands. Extracts exactly the boilerplate
 * already duplicated verbatim across ~10 existing scripts/*Command.php files
 * (e.g. GetClassificationJelCommand::bootstrap()) — used only by the Solr
 * commands; existing commands are left untouched, not refactored onto this
 * trait.
 */
trait BootstrapsSolrEnvironment
{
    private function bootstrapSolrEnvironment(): void
    {
        if (!defined('APPLICATION_PATH')) {
            define('APPLICATION_PATH', realpath(__DIR__ . '/../../application'));
        }
        require_once __DIR__ . '/../../public/const.php';
        require_once __DIR__ . '/../../public/bdd_const.php';

        // public/const.php deliberately skips defining APPLICATION_ENV when run
        // from CLI (see its $isFromCli guard) — legacy CLI scripts define it
        // themselves from a -e option (scripts/loadHeader.php). None of the
        // other scripts/*Command.php bootstrap() methods need it, but the
        // domain code exercised while building a Solr document (Episciences_Paper,
        // Export, etc.) does reference it in places.
        if (!defined('APPLICATION_ENV')) {
            define('APPLICATION_ENV', getenv('APPLICATION_ENV') ?: 'production');
        }

        // defineJournalConstants() only defines APPLICATION_MODULE when called
        // with an $rvCode (it isn't, here — Solr indexing spans every journal in
        // one run). Episciences_Paper_Tei needs it regardless. Solr indexing is
        // always in "journal" context, never "oai"/"portal".
        if (!defined('APPLICATION_MODULE')) {
            define('APPLICATION_MODULE', 'journal');
        }

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
        // Mirrors the pattern already established by the other scripts/*Command.php files.
        $application = new Zend_Application('production', APPLICATION_PATH . '/configs/application.ini');

        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);

        $db = Zend_Db::factory('PDO_MYSQL', $application->getOption('resources')['db']['params']);
        Zend_Db_Table::setDefaultAdapter($db);

        Zend_Registry::set('metadataSources', Episciences_Paper_MetaDataSourcesManager::all(false));

        // Export::getTei() (used by ExportFieldsBuilder) needs a 'Zend_Translate'
        // registry entry, normally populated by the full web bootstrap. Mirrors
        // the initTranslator() helper already used for the same reason by the
        // legacy scripts/solr/solrJob.php CLI entrypoint.
        $translator = new Zend_Translate(
            Zend_Translate::AN_ARRAY,
            APPLICATION_PATH . '/languages',
            null,
            ['scan' => Zend_Translate::LOCALE_DIRECTORY]
        );
        Zend_Registry::set('Zend_Translate', $translator);
        Zend_Registry::set('Zend_Locale', new Zend_Locale($translator->getLocale()));
        Zend_Registry::set('lang', $translator->getLocale());
    }

    private function createSolrLogger(SymfonyStyle $io, string $channel): Logger
    {
        $logger = new Logger($channel);
        $logger->pushHandler(new StreamHandler(
            EPISCIENCES_LOG_PATH . $channel . '_' . date('Y-m-d') . '.log',
            Logger::INFO
        ));
        if (!$io->isQuiet()) {
            $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }

        return $logger;
    }

    /**
     * Doctrine DBAL connection to the main application database, reusing the
     * already-bootstrapped Zend_Db adapter's own connection parameters — see
     * Messenger\Dbal\DbalConnectionFactory for why this DB (not a separate one).
     */
    private function createDbalConnection(): DbalConnection
    {
        return DbalConnectionFactory::fromZendAdapter(Zend_Db_Table_Abstract::getDefaultAdapter());
    }

    /**
     * A fresh cache scoped to this single command invocation only — unlike
     * legacy's Ccsd_Search_Solr_Indexer_Episciences, which holds one
     * ArrayAdapter for the entire lifetime of a (potentially very long) bulk
     * run. Each solr:* invocation processes a bounded batch, so the staleness
     * window for a mid-run journal/volume/section change is bounded to that
     * batch, not to an indefinitely long-running cron process.
     */
    private function createDocumentBuilder(): DocumentBuilder
    {
        return new DocumentBuilder(
            new ExportFieldsBuilder(),
            new AuthorFieldsBuilder(),
            new DateFieldsBuilder(),
            new LocaleFieldsBuilder(),
            new VolumeSectionResolver(new ArrayAdapter(0, false)),
            new KeywordFieldsBuilder(),
        );
    }
}
