<?php

use Episciences\Paper\Spdx\LicenseCode;
use Episciences\Paper\Spdx\LicenseSpdxResolver;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Cache\InvalidArgumentException as InvalidArgumentExceptionAlias;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class Episciences_Paper_LicenceManager
{
    public const ONE_MONTH = 3600 * 24 * 31;
    const ARXIV_DOI_PREFIX = '10.48550/arxiv.';
    const ZENODO_DOI_PREFIX = '10.5281/zenodo.';
    const DATACITE_DOI_API = 'https://api.datacite.org/dois/';

    /**
     * @param string|int $repoId
     * @param string $identifier
     * @param int $version
     * @return string
     * @throws GuzzleException
     * @throws InvalidArgumentExceptionAlias
     */
    public static function getApiResponseByRepoId($repoId, string $identifier, int $version): string
    {
        if (empty(trim($identifier))) {
            return '';
        }

        $repoId = (string)$repoId;

        $response = match ($repoId) {
            Episciences_Repositories::HAL_REPO_ID => self::getLicenceFromTeiHal($identifier, $version),
            Episciences_Repositories::ARXIV_REPO_ID => self::getDataciteLicence(self::ARXIV_DOI_PREFIX . $identifier),
            Episciences_Repositories::ZENODO_REPO_ID => self::getDataciteLicence(self::ZENODO_DOI_PREFIX . $identifier),
            Episciences_Repositories::ARCHE_ID => self::getLicenceFromArcheDatacite($identifier),
            default => ''
        };

        if (self::shouldRateLimit($repoId)) {
            self::applyRateLimit();
        }

        return $response;
    }

    /**
     * Check if rate limiting should be applied for the given repository
     *
     * @param string $repoId
     * @return bool
     */
    private static function shouldRateLimit(string $repoId): bool
    {
        return in_array($repoId, [
                Episciences_Repositories::ARXIV_REPO_ID,
                Episciences_Repositories::ZENODO_REPO_ID,
                Episciences_Repositories::ARCHE_ID
        ], true);
    }

    /**
     * Apply rate limiting to avoid overwhelming external APIs
     */
    private static function applyRateLimit(): void
    {
        sleep(1);
    }

    /**
     * Get license information from DataCite API
     *
     * @param string $doiIdentifier
     * @return string
     * @throws GuzzleException
     */
    private static function getDataciteLicence(string $doiIdentifier): string
    {
        $url = self::DATACITE_DOI_API . $doiIdentifier;
        return self::callApiForLicenceByRepoId($url);
    }

    /**
     * @param string $identifier
     * @param int $version
     * @return string
     * @throws InvalidArgumentExceptionAlias
     */
    public static function getLicenceFromTeiHal(string $identifier, int $version): string
    {
        $cacheTeiHal = Episciences_Hal_TeiCacheManager::fetchAndGet($identifier, $version);
        $xmlString = simplexml_load_string($cacheTeiHal);
        $licence = '';
        if (isset($xmlString->text->body->listBibl->biblFull->publicationStmt->availability->licence, $xmlString->text->body->listBibl->biblFull->publicationStmt->availability->licence->attributes()->target)) {
            $licence = (string)$xmlString->text->body->listBibl->biblFull->publicationStmt->availability->licence->attributes()->target;
            return self::cleanLicence($licence);
        }
        return $licence;
    }

    /**
     * @param string $identifier
     * @return string
     */
    public static function getLicenceFromArcheDatacite(string $identifier): string
    {
        $client = new Client();
        try {
            $response = $client->get(Episciences_Repositories_ARCHE_Hooks::ARCHE_OAI_PMH_API . $identifier);
        } catch (GuzzleException $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return '';
        }

        $xmlString = $response->getBody()->getContents();
        $metadata = simplexml_load_string($xmlString);
        if ($metadata === false) {
            trigger_error('Invalid XML', E_USER_WARNING);
            return '';
        }

        // Register namespaces for OAI-PMH and DataCite
        $metadata->registerXPathNamespace('oai', 'http://www.openarchives.org/OAI/2.0/');
        $metadata->registerXPathNamespace('datacite', 'http://datacite.org/schema/kernel-3');
        // Extract license information
        $rightsNodes = $metadata->xpath('//datacite:rightsList/datacite:rights');
        $license = '';
        if (!empty($rightsNodes)) {
            $rightsNode = $rightsNodes[0];
            $license = (string)$rightsNode['rightsURI'] ?: (string)$rightsNode;
        }

        return self::cleanLicence($license);
    }


    /**
     * @param string $licence
     * @return string
     */
    public static function cleanLicence(string $licence): string
    {
        $urlReplacements = [
                'http://hal.archives-ouvertes.fr/licences/etalab/' => 'https://raw.githubusercontent.com/DISIC/politique-de-contribution-open-source/master/LICENSE',
                'http://hal.archives-ouvertes.fr/licences/publicDomain/' => 'https://creativecommons.org/publicdomain/zero/1.0',
        ];

        $ccPatterns = [
                '/http:\/\/creativecommons\.org\/licenses\/by\/$/' => 'https://creativecommons.org/licenses/by/4.0',
                '/http:\/\/creativecommons\.org\/licenses\/by-nc-sa\/$/' => 'https://creativecommons.org/licenses/by-nc-sa/4.0',
                '/http:\/\/creativecommons\.org\/licenses\/by-sa\/$/' => 'https://creativecommons.org/licenses/by-sa/4.0',
                '/http:\/\/creativecommons\.org\/licenses\/by-nd\/$/' => 'https://creativecommons.org/licenses/by-nd/4.0',
                '/http:\/\/creativecommons\.org\/licenses\/by-nc\/$/' => 'https://creativecommons.org/licenses/by-nc/4.0',
                '/http:\/\/creativecommons\.org\/licenses\/by-nc-nd\/$/' => 'https://creativecommons.org/licenses/by-nc-nd/4.0',
        ];

        $licence = str_replace(array_keys($urlReplacements), array_values($urlReplacements), $licence);

        $licence = preg_replace(array_keys($ccPatterns), array_values($ccPatterns), $licence);

        $licence = str_replace('http://', 'https://', $licence);
        $licence = preg_replace('/\/legalcode$/', '', $licence);

        return rtrim($licence, '/');
    }

    /**
     * @param $url
     * @return string
     * @throws GuzzleException
     */
    public static function callApiForLicenceByRepoId($url): string
    {
        $client = new Client();
        try {
            return $client->get($url, [
                    'headers' => [
                            'User-Agent' => 'CCSD Episciences support@episciences.org',
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json'
                    ]
            ])->getBody()->getContents();
        } catch (ClientException $e) {
            trigger_error('Api call error: ' . $url);
            return "";
        }

    }

    /**
     * @throws JsonException|InvalidArgumentExceptionAlias
     */
    public static function insertLicenceFromApiByRepoId(string $repoId, string $callArrayResp, int $docId, string $identifier): int
    {
        if (empty($callArrayResp)) {
            return 0;
        }

        if (self::isPreviouslyUpdatedManually($docId)) {
            return 0;
        }

        $licenceData = self::extractLicenceData($repoId, $callArrayResp);

        if ($licenceData === null) {
            self::cacheEmptyResult($identifier);
            return 0;
        }

        self::cacheLicenceData($identifier, $licenceData['cacheData']);

        $paperLicenseResult = self::insert([
                [
                        'licence' => $licenceData['licence'],
                        'docid' => $docId,
                        'sourceId' => $licenceData['sourceId']
                ]
        ]);

        // Insert also into "paper_license_code" if the license code has an SPDX equivalent
        $resolved = (new LicenseSpdxResolver())->resolve($licenceData['licence']);

        if ($resolved !== LicenseSpdxResolver::NO_ASSERTION) {

            (new LicenseCode([
                    'code' => $resolved,
                    'docid' => $docId
            ]))->save();

        }

        return $paperLicenseResult;
    }

    /**
     * @param string $repoId
     * @param string $callArrayResp
     * @return array|null
     * @throws JsonException
     */
    private static function extractLicenceData(string $repoId, string $callArrayResp): ?array
    {
        $result = null;

        if ($repoId === Episciences_Repositories::ARXIV_REPO_ID || $repoId === Episciences_Repositories::ZENODO_REPO_ID) {
            $result = self::extractDataciteLicenceData($callArrayResp);
        } elseif ($repoId === Episciences_Repositories::HAL_REPO_ID) {
            $result = [
                    'licence' => $callArrayResp,
                    'sourceId' => Episciences_Repositories::HAL_REPO_ID,
                    'cacheData' => $callArrayResp
            ];
        } elseif ($repoId === Episciences_Repositories::ARCHE_ID) {
            $result = [
                    'licence' => $callArrayResp,
                    'sourceId' => Episciences_Repositories::ARCHE_ID,
                    'cacheData' => $callArrayResp
            ];
        }

        return $result;
    }

    /**
     * @param string $callArrayResp
     * @return array|null
     * @throws JsonException
     */
    private static function extractDataciteLicenceData(string $callArrayResp): ?array
    {
        $licenceArray = json_decode($callArrayResp, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($licenceArray['data']['attributes']['rightsList'][0]['rightsUri'])) {
            return null;
        }

        $rightsData = $licenceArray['data']['attributes']['rightsList'][0];
        $licenceUri = $rightsData['rightsUri'];

        return [
                'licence' => self::cleanLicence($licenceUri),
                'sourceId' => Episciences_Repositories::DATACITE_REPO_ID,
                'cacheData' => $rightsData
        ];
    }

    /**
     * @param string $identifier
     * @param mixed $data
     * @throws InvalidArgumentExceptionAlias
     * @throws JsonException
     */
    private static function cacheLicenceData(string $identifier, $data): void
    {
        $cleanID = md5($identifier);
        $cache = new FilesystemAdapter('enrichmentLicences', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
        $cacheItem = $cache->getItem($cleanID . "_licence.json");
        $cacheItem->expiresAfter(self::ONE_MONTH);
        $cacheItem->set(json_encode($data, JSON_THROW_ON_ERROR));
        $cache->save($cacheItem);
    }

    /**
     * @param string $identifier
     * @throws InvalidArgumentExceptionAlias
     * @throws JsonException
     */
    private static function cacheEmptyResult(string $identifier): void
    {
        self::cacheLicenceData($identifier, [""]);
    }

    /**
     * @param array|Episciences_Paper_Licence $licences
     * @param bool $force : overwrite the previous license
     * @return int
     */

    public static function insert(array|Episciences_Paper_Licence $licences, bool $force = true): int
    {
        if (!$force) {
            return 0;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $values = [];
        $affectedRows = 0;

        // Normalize to array
        $licences = is_array($licences) ? $licences : [$licences];

        foreach ($licences as $licence) {
            // Ensure proper object type
            if (!($licence instanceof Episciences_Paper_Licence)) {
                $licence = new Episciences_Paper_Licence($licence);
            }


            $values[] = sprintf(
                    '(%s, %s, %s,%s)',
                    $db->quote($licence->getLicence()),
                    $db->quote($licence->getDocid()),
                    $db->quote($licence->getSourceId()),
                    self::formatValue($db, $licence->getUid())
            );
        }

        if (!empty($values)) {
            try {
                $sql = sprintf(
                        'INSERT INTO %s (`licence`,`docid`,`source_id`,`uid`) VALUES %s ON DUPLICATE KEY UPDATE `licence`=VALUES(licence), `uid`=VALUES(uid) ',
                        $db->quoteIdentifier(T_PAPER_LICENCES),
                        implode(', ', $values)
                );

                $result = $db->query($sql);
                $affectedRows = $result->rowCount();
            } catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_ERROR);
            }
        }

        return $affectedRows;
    }

    private static function formatValue(Zend_Db_Adapter_Abstract $db, $value): string
    {
        return $value !== null && $value !== '' ? $db->quote($value) : 'NULL';
    }

    public static function getLicenceByDocId(int $docId = null): string
    {

        if (!$docId) {
            return '';
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = self::getLicenceByDocIdQuery($docId, ['licence', 'source_id']);

        $sql->joinLeft(['c' => T_PAPER_LICENSE_CODE], 'c.docid = pl.docid', ['c.code']);
        $result = $db->fetchRow($sql);

        if (isset($result['code'])) {
            return (new LicenseCode(['code' => $result['code']]))->getReference();
        }

        return $result['licence'] ?? '';

    }

    public static function deleteLicenceByDocId(int $docId): bool
    {
        if ($docId < 1) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->delete(T_PAPER_LICENCES, ['docid = ?' => $docId]) > 0;

    }

    private static function getLicenceByDocIdQuery(int $docId = null, string|array $field = '*'): ?Zend_Db_Select
    {
        if (!$docId) {
            return null;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db?->select()->from(['pl' => T_PAPER_LICENCES], $field)->where('pl.docid = ? ', $docId);
    }

    public static function getLicenceObjectByDocId(int $docId = null): ?Episciences_Paper_Licence
    {
        if (!$docId) {
            return null;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = self::getLicenceByDocIdQuery($docId);
        $result = $db?->fetchAll($sql)[0] ?? [];

        return new Episciences_Paper_Licence($result);

    }


    private static function isPreviouslyUpdatedManually($docId): bool
    {
        return self::getLicenceObjectByDocId($docId)?->getUid() > 0;
    }
}
