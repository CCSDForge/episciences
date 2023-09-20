<?php

use GuzzleHttp\Exception\GuzzleException;

/**
 * Class Episciences_Repositories
 * List repositories available as submit sources
 */
class Episciences_Repositories
{

    public const TYPE_DATA = 'data';
    public const TYPE_PAPERS_REPOSITORY = 'repository';

    public const  REPO_LABEL = 'name';
    public const  REPO_IDENTIFIER = 'identifier';
    public const  REPO_DOCURL = 'doc_url';
    public const  REPO_PAPERURL = 'paper_url';
    public const  REPO_BASEURL = 'base_url';
    public const  REPO_EXAMPLE = 'example';
    public const  REPO_TYPE = 'type';
    public const  REPO_API_URL = 'api_url';

    public const REPO_DOI_PREFIX = 'doi_prefix';

    public const EPISCIENCES_REPO_ID = '0';
    public const HAL_REPO_ID = '1';
    public const ARXIV_REPO_ID = '2';
    public const CWI_REPO_ID = '3';
    public const ZENODO_REPO_ID = '4';
    public const SCHOLEXPLORER_ID = '5';
    public const DATACITE_REPO_ID = '7';
    public const GRAPH_OPENAIRE_ID = '8';
    public const OPENCITATIONS_ID = '13';
    public const BIO_RXIV_ID = '10';
    public const MED_RXIV_ID = '11';

    public const EPI_USER_ID = '12';
    private static array $_repositories = [];

    public static array $_identifierExemples = [
        self::HAL_REPO_ID => 'hal-01234567',
        self::ARXIV_REPO_ID => '0123.45678',
        self::CWI_REPO_ID => '22211',
        self::ZENODO_REPO_ID => '123456 / (DOI)10.5281/zenodo.123456',
        self::BIO_RXIV_ID => '(DOI)10.1101/339747',
        self::MED_RXIV_ID => '(DOI)10.1101/339747',
    ];


    public static function getRepositories(): array
    {

        if (empty(self::$_repositories)) {

            try {
                self::$_repositories = array_filter(Zend_Registry::get('metadataSources'), static function ($source) {
                    return (
                        $source[self::REPO_LABEL] !== 'Software Heritage' &&
                        ($source[self::REPO_TYPE] === self::TYPE_DATA || $source[self::REPO_TYPE] === self::TYPE_PAPERS_REPOSITORY)
                    );
                });
            } catch (Zend_Exception $e) {
                trigger_error($e->getMessage(), E_USER_ERROR);
            }

        }

        return self::$_repositories;

    }

    public static function getRepoIdByLabel($label)
    {
        $r = Episciences_Tools::search_multiarray(self::getRepositories(), $label);
        return ($r) ? $r[0] : null;
    }

    public static function getLabel($repoId)
    {
        return isset (self::getRepositories()[$repoId]) ? self::getRepositories()[$repoId][self::REPO_LABEL] : '';
    }

    public static function getBaseUrl($repoId)
    {
        return self::getRepositories()[$repoId][self::REPO_BASEURL];
    }

    public static function getIdentifier($repoId, $identifier, $version = null)
    {
        if ($version) {
            return str_replace(['%%ID', '%%VERSION'], [$identifier, $version], self::getRepositories()[$repoId][self::REPO_IDENTIFIER]);
        }
        return str_replace(['%%ID', 'v%%VERSION'], [$identifier, ''], self::getRepositories()[$repoId][self::REPO_IDENTIFIER]);
    }

    public static function getDocUrl($repoId, $identifier, $version = null)
    {
        return str_replace(['%%ID', '%%VERSION'], [$identifier, $version], self::getRepositories()[$repoId][self::REPO_DOCURL]);
    }

    public static function getApiUrl($repoId)
    {
        return self::getRepositories()[$repoId][self::REPO_API_URL] ?? '';
    }

    /**
     * @param $repoId
     * @param $identifier
     * @param null $version
     * @return string
     * @throws Zend_Exception
     */
    public static function getPaperUrl($repoId, $identifier, $version = null): string
    {
        return str_replace(['%%ID', '%%VERSION'], [$identifier, $version], self::getRepositories()[$repoId][self::REPO_PAPERURL]);
    }

    public static function getRepoDoiPrefix($repoId)
    {
        return Ccsd_Tools::ifsetor(self::getRepositories()[$repoId][self::REPO_DOI_PREFIX], self::REPO_DOI_PREFIX);
    }

    /**
     * @param int $repoId
     * @return string
     */
    public static function hasHook(int $repoId): string
    {

        $className = self::makeHookClassNameByRepoId($repoId);
        if (!class_exists($className)) {
            $className = '';
        }
        return $className;
    }

    /**
     * @param int $repoId
     * @return string
     */
    private static function makeHookClassNameByRepoId(int $repoId): string
    {
        return __CLASS__ . '_' . ucfirst(self::getLabel($repoId)) . '_Hooks';
    }

    /**
     * @param string $hookName
     * @param array $hookParams
     * @return array
     */
    public static function callHook(string $hookName, array $hookParams): array
    {
        $className = '';

        if (array_key_exists('repoId', $hookParams)) {
            $className = self::hasHook((int)$hookParams['repoId']);
        }

        if ($className !== '') {
            return $className::$hookName($hookParams);
        }

        return [];
    }


    /**
     * @return array
     */
    public static function getLabels(): array
    {
        $labels = [];

        foreach (self::getRepositories() as $repoId => $repository) {

            if ($repoId === 0) {
                // skip Episciences repository
                continue;
            }

            $labels[$repoId] = $repository[self::REPO_LABEL];
        }

        return $labels;

    }
}

