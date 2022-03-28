<?php

use GuzzleHttp\Exception\GuzzleException;

/**
 * Class Episciences_Repositories
 * List repositories available as submit sources
 */
class Episciences_Repositories
{

    const TYPE_DATA = 'data';
    const TYPE_PAPERS = 'papers';

    const REPO_LABEL = 'label';
    const REPO_IDENTIFIER = 'identifier';
    const REPO_DOCURL = 'docurl';
    const REPO_PAPERURL = 'paperurl';
    const REPO_BASEURL = 'baseurl';
    const REPO_EXAMPLE = 'example';
    const REPO_TYPE = 'type';
    const REPO_API_URL = '';

    public const REPO_DOI_PREFIX = null;


    private static $_repositories = [
        [
            self::REPO_LABEL => 'Episciences',
            self::REPO_IDENTIFIER => null,
            self::REPO_DOCURL => '/tmp_files/%%ID',
            self::REPO_PAPERURL => '/tmp_files/%%ID'
        ],
        [
            self::REPO_LABEL => 'Hal',
            self::REPO_BASEURL => 'https://api.archives-ouvertes.fr/oai/hal/',
            self::REPO_IDENTIFIER => 'oai:HAL:%%IDv%%VERSION',
            self::REPO_EXAMPLE => 'hal-01234567',
            self::REPO_DOCURL => 'https://hal.archives-ouvertes.fr/%%IDv%%VERSION',
            self::REPO_PAPERURL => 'https://hal.archives-ouvertes.fr/%%IDv%%VERSION/document',
            self::REPO_TYPE => self::TYPE_PAPERS,
            self::REPO_API_URL => 'https://api.archives-ouvertes.fr'
        ],
        [
            // identifier example: 1511.01076
            self::REPO_LABEL => 'arXiv',
            self::REPO_EXAMPLE => '0123.45678',
            self::REPO_BASEURL => 'http://export.arXiv.org/oai2',
            self::REPO_IDENTIFIER => 'oai:arXiv.org:%%ID',
            self::REPO_DOCURL => 'https://arxiv.org/abs/%%IDv%%VERSION',
            self::REPO_PAPERURL => 'https://arxiv.org/pdf/%%IDv%%VERSION',
            self::REPO_TYPE => self::TYPE_PAPERS,
            self::REPO_DOI_PREFIX => '10.48550'
        ],
        [
            self::REPO_LABEL => 'CWI',
            self::REPO_EXAMPLE => '22211',
            self::REPO_BASEURL => 'http://oai.cwi.nl/oai',
            self::REPO_IDENTIFIER => 'oai:cwi.nl:%%ID',
            self::REPO_DOCURL => 'http://persistent-identifier.org/?identifier=urn:nbn:nl:ui:18-%%ID',
            self::REPO_PAPERURL => 'https://repository.cwi.nl/noauth/directaccess.php?publnr=%%ID',
            self::REPO_TYPE => self::TYPE_PAPERS
        ],

        [
            // example https://zenodo.org/oai2d?verb=GetRecord&identifier=oai:zenodo.org:3752641&metadataPrefix=oai_dc
            self::REPO_LABEL => 'Zenodo',
            self::REPO_EXAMPLE => '123456 / (DOI)10.5281/zenodo.123456',
            self::REPO_BASEURL => 'https://zenodo.org/oai2d',
            self::REPO_IDENTIFIER => 'oai:zenodo.org:%%ID',
            self::REPO_DOCURL => 'https://zenodo.org/record/%%ID',
            self::REPO_PAPERURL => 'https://zenodo.org/record/files/%%ID',
            self::REPO_DOI_PREFIX => '10.5281',
            self::REPO_TYPE => self::TYPE_DATA
        ],
    ];


    public static function getRepositories(): array
    {
        return self::$_repositories;
    }

    public static function getRepoIdByLabel($label)
    {
        $r = Episciences_Tools::search_multiarray(self::$_repositories, $label);
        return ($r) ? $r[0] : null;
    }

    public static function getLabel($repoId)
    {
        return self::$_repositories[$repoId][self::REPO_LABEL];
    }

    public static function getBaseUrl($repoId)
    {
        return self::$_repositories[$repoId][self::REPO_BASEURL];
    }

    public static function getIdentifier($repoId, $identifier, $version = null)
    {
        if ($version) {
            return str_replace(['%%ID', '%%VERSION'], [$identifier, $version], self::$_repositories[$repoId][self::REPO_IDENTIFIER]);
        }
        return str_replace(['%%ID', 'v%%VERSION'], [$identifier, ''], self::$_repositories[$repoId][self::REPO_IDENTIFIER]);
    }

    public static function getDocUrl($repoId, $identifier, $version = null)
    {
        return str_replace(['%%ID', '%%VERSION'], [$identifier, $version], self::$_repositories[$repoId][self::REPO_DOCURL]);
    }

    public static function getApiUrl($repoId)
    {
        return self::$_repositories[$repoId][self::REPO_API_URL];
    }

    /**
     * @param $repoId
     * @param $identifier
     * @param null $version
     * @return string
     */
    public static function getPaperUrl($repoId, $identifier, $version = null)
    {
        return str_replace(['%%ID', '%%VERSION'], [$identifier, $version], self::$_repositories[$repoId][self::REPO_PAPERURL]);
    }

    public static function getRepoDoiPrefix($repoId)
    {
        return Ccsd_Tools::ifsetor(self::$_repositories[$repoId][self::REPO_DOI_PREFIX], self::REPO_DOI_PREFIX);
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
        return __CLASS__ . '_' . self::getLabel($repoId) . '_Hooks';
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
}

