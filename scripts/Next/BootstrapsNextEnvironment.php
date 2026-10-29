<?php

declare(strict_types=1);

/**
 * Shared bootstrap for the Next.js revalidation CLI surface
 * (next:revalidate-cache and the "next_revalidation" transport profile) —
 * extracted verbatim from RevalidateNextCacheCommand::bootstrap() so the
 * boilerplate isn't duplicated between the two.
 *
 * Deliberately lighter than BootstrapsSolrEnvironment: the revalidation
 * handler never touches a domain object (Episciences_Paper, Export, ...),
 * only NEXT_BASE_URL, the journal token file and Guzzle — so it skips
 * defineJournalConstants(), the translator, and metadataSources.
 */
trait BootstrapsNextEnvironment
{
    private function bootstrapNextEnvironment(): void
    {
        if (!defined('APPLICATION_PATH')) {
            define('APPLICATION_PATH', realpath(__DIR__ . '/../../application'));
        }
        require_once __DIR__ . '/../../public/const.php';
        require_once __DIR__ . '/../../public/bdd_const.php';

        defineProtocol();
        defineSimpleConstants();
        defineSQLTableConstants();
        defineApplicationConstants();

        $libraries = [realpath(APPLICATION_PATH . '/../library')];
        set_include_path(implode(PATH_SEPARATOR, array_merge($libraries, [get_include_path()])));
        require_once 'Zend/Application.php';

        $application = new Zend_Application('production', APPLICATION_PATH . '/configs/application.ini');

        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);

        $db = Zend_Db::factory('PDO_MYSQL', $application->getOption('resources')['db']['params']);
        Zend_Db_Table::setDefaultAdapter($db);
    }
}
