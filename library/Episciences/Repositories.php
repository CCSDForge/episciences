<?php

use GuzzleHttp\Exception\GuzzleException;

/**
 * Class Episciences_Repositories
 * List repositories available as submit sources
 */
class Episciences_Repositories
{

    public const TYPE_DATAVERSE = 'dataverse';
    public const TYPE_PAPERS_REPOSITORY = 'repository';
    public const TYPE_DSPACE = 'dspace';

    public const  REPO_LABEL = 'name';
    public const  REPO_IDENTIFIER = 'identifier';
    public const  REPO_DOCURL = 'doc_url';
    public const  REPO_PAPERURL = 'paper_url';
    public const  REPO_BASEURL = 'base_url';
    public const  REPO_EXAMPLE = 'example';
    public const  REPO_TYPE = 'type';
    public const  REPO_API_URL = 'api_url';

    public const REPO_DOI_PREFIX = 'doi_prefix';

    // IDs in metadata_sources table : Episciences repository => '0'

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

    public const ZBMATH_OPEN = '17';

    public const ARCHE_ID = '18';

    public const EPI_USER_ID = '12';
    public const HAL_LABEL = 'HAL';

    private static array $_repositories = [];

    public const IDENTIFIER_EXEMPLES = [
        self::HAL_REPO_ID => 'hal-01234567',
        self::ARXIV_REPO_ID => '0123.45678',
        self::CWI_REPO_ID => '22211',
        self::ZENODO_REPO_ID => '123456 or 10.5281/zenodo.123456',
        self::BIO_RXIV_ID => '10.1101/339747',
        self::MED_RXIV_ID => '10.1101/339747',
        self::ARCHE_ID => '(Handle) 21.11115/0000-000B-C715-D'
    ];

    public static function getRepositories(): array
    {

        if (empty(self::$_repositories)) {

            try {
                self::$_repositories = array_filter(Zend_Registry::get('metadataSources'), static function ($source) {
                    return $source[self::REPO_LABEL] !== 'Software Heritage' &&
                        (
                            $source[self::REPO_TYPE] === self::TYPE_DSPACE ||
                            $source[self::REPO_TYPE] === self::TYPE_DATAVERSE ||
                            $source[self::REPO_TYPE] === self::TYPE_PAPERS_REPOSITORY
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
        $repositories = self::getRepositories();
        return isset($repositories[$repoId]) ? $repositories[$repoId][self::REPO_BASEURL] : null;
    }

    public static function getIdentifier($repoId, $identifier, $version = null)
    {
        $repoIdentifier = self::getRepositories()[$repoId][self::REPO_IDENTIFIER];

        if (!empty($repoIdentifier)) {

            if ($version) {
                return str_replace(['%%ID', '%%VERSION'], [$identifier, $version], self::getRepositories()[$repoId][self::REPO_IDENTIFIER]);
            }

            return str_replace(['%%ID', 'v%%VERSION'], [$identifier, ''], self::getRepositories()[$repoId][self::REPO_IDENTIFIER]);

        }

        return $repoIdentifier;

    }

    public static function getDocUrl($repoId, $identifier, $version = null, $versionMinorNumber = Episciences_Repositories_Dataverse_Hooks::VERSION_MINOR_NUMBER)

    {
        if ($version && self::isDataverse($repoId)) {
            $exploded = explode('.', (string)$version);
            $version = (int)($exploded[0] ?? 1);
            $versionMinorNumber = (int)($exploded[1] ?? Episciences_Repositories_Dataverse_Hooks::VERSION_MINOR_NUMBER);
        }

        $repoDocUrl = self::getRepositories()[$repoId][self::REPO_DOCURL];

        if (!empty($repoDocUrl)) {
            return str_replace(['%%ID', '%%VERSION', '%%V_MINOR_NUMBER'], [$identifier, $version, $versionMinorNumber], $repoDocUrl);
        }

        return $repoDocUrl;
    }

    public static function getApiUrl($repoId)
    {
        return self::getRepositories()[$repoId][self::REPO_API_URL] ?? '';
    }

    /**
     * @param $repoId
     * @param $identifier
     * @param float|null $version
     * @return string
     */
    public static function getPaperUrl($repoId, $identifier, ?float $version = 1): string
    {
        $repoPaperUrl = self::getRepositories()[$repoId][self::REPO_PAPERURL];

        if (!empty($repoPaperUrl)) {
            return str_replace(['%%ID', '%%VERSION'], [$identifier, $version], self::getRepositories()[$repoId][self::REPO_PAPERURL]);
        }

        return $repoPaperUrl;
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
        if (!self::isDataverse($repoId) && !self::isDspace($repoId)) {
            $label = self::getLabel($repoId);
        } elseif (self::isDataverse($repoId)) {
            $label = self::TYPE_DATAVERSE;

        } else {
            $label = self::TYPE_DSPACE;
        }
        return __CLASS__ . '_' . ucfirst($label) . '_Hooks';
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
        $skipId = (int) self::EPISCIENCES_REPO_ID;

        foreach (self::getRepositories() as $repoId => $repository) {
            if ($repoId !== $skipId) {
                $labels[$repoId] = $repository[self::REPO_LABEL];
            }
        }

        return $labels;
    }


    public static function isDataverse(int $repoId): bool
    {


        if (!isset(self::getRepositories()[$repoId][self::REPO_TYPE])) {
            return false;
        }

        return self::getRepositories()[$repoId][self::REPO_TYPE] === self::TYPE_DATAVERSE;
    }

    public static function getIdentifierExemple(int $repoId): string
    {

        if (self::isDataverse($repoId)) {
            return Episciences_Repositories_Dataverse_Hooks::DATAVERSE_IDENTIFIER_EXEMPLE;
        }

        if (self::isDspace($repoId)) {
            return Episciences_Repositories_Dspace_Hooks::IDENTIFIER_EXEMPLE;
        }

        if (isset(self::IDENTIFIER_EXEMPLES[(string)$repoId])) {
            return self::IDENTIFIER_EXEMPLES[(string)$repoId];
        }

        if (self::isFromHalRepository($repoId)) {
            return self::IDENTIFIER_EXEMPLES[self::HAL_REPO_ID];
        }

        return 'Uninitialized variable';

    }

    public static function isFromHalRepository(int $repoId): bool
    {
        return str_contains(self::getLabel($repoId), self::HAL_LABEL);

    }


    public static function getRepositoriesByLabel(): array
    {

        $repositoriesByLabel = [];

        foreach (self::getRepositories() as $repository) {
            $repositoriesByLabel[$repository[self::REPO_LABEL]] = $repository;
        }

        return $repositoriesByLabel;

    }

    public static function isDspace(int $repoId): bool
    {
        return self::getTypeByIdentifier($repoId) === self::TYPE_DSPACE;
    }

    public static function getTypeByIdentifier(int $repoId): string
    {
        return self::getRepositories()[$repoId][self::REPO_TYPE];
    }
}

