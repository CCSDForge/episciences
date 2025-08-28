<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class Episciences_Paper_LicenceManager
{
    public const ONE_MONTH = 3600 * 24 * 31;
    const ARXIV_DOI_PREFIX = '10.48550/arxiv.';
    const ZENODO_DOI_PREFIX = '10.5281/zenodo.';
    const DATACITE_DOI_API = 'https://api.datacite.org/dois/';

    /**
     * @param $repoId
     * @param $identifier
     * @param int $version
     * @return string
     * @throws GuzzleException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function getApiResponseByRepoId($repoId, $identifier, int $version): string
    {
        $callArrayResp = '';
        switch ($repoId) {
            case "1": //HAL
                $callArrayResp = self::getLicenceFromTeiHal($identifier, $version);
                break;
            case "2": //ARXIV
                $url = self::DATACITE_DOI_API . self::ARXIV_DOI_PREFIX . $identifier;
                $callArrayResp = self::callApiForLicenceByRepoId($url);
                sleep(1);
                break;
            case "4": //ZENODO
                $url = self::DATACITE_DOI_API . self::ZENODO_DOI_PREFIX . $identifier;
                $callArrayResp = self::callApiForLicenceByRepoId($url);
                sleep(1);
                break;
            default: //OTHERS
                break;
        }
        return $callArrayResp;
    }

    /**
     * @param string $identifier
     * @param int $version
     * @return string
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function getLicenceFromTeiHal(string $identifier, int $version): string
    {
        Episciences_Paper_AuthorsManager::getHalTei($identifier, $version);
        $cacheTeiHal = Episciences_Paper_AuthorsManager::getHalTeiCache($identifier, $version);
        $xmlString = simplexml_load_string($cacheTeiHal);
        $licence = '';
        if (isset($xmlString->text->body->listBibl->biblFull->publicationStmt->availability->licence, $xmlString->text->body->listBibl->biblFull->publicationStmt->availability->licence->attributes()->target)) {
            $licence = (string)$xmlString->text->body->listBibl->biblFull->publicationStmt->availability->licence->attributes()->target;
            return self::cleanLicence($licence);
        }
        return $licence;
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
     * @param $repoId
     * @param $callArrayResp
     * @param $docId
     * @param $identifier
     * @return int
     * @throws JsonException|\Psr\Cache\InvalidArgumentException
     */
    public static function InsertLicenceFromApiByRepoId($repoId, $callArrayResp, $docId, $identifier): int
    {
        $cleanID = md5($identifier);
        $repoId = (string)$repoId;
        $cache = new FilesystemAdapter('enrichmentLicences', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
        $sets = $cache->getItem($cleanID . "_licence.json");
        $sets->expiresAfter(self::ONE_MONTH);
        if ($callArrayResp !== "") {
            if ($repoId === Episciences_Repositories::ARXIV_REPO_ID || $repoId === Episciences_Repositories::ZENODO_REPO_ID) {
                $licenceArray = json_decode($callArrayResp, true, 512, JSON_THROW_ON_ERROR);
                if (isset($licenceArray['data']['attributes']['rightsList'][0]['rightsUri'])) {
                    $sets->set(json_encode($licenceArray['data']['attributes']['rightsList'][0], JSON_THROW_ON_ERROR));
                    $cache->save($sets);
                    $licenceGetter = $licenceArray['data']['attributes']['rightsList'][0]['rightsUri'];
                    $licenceGetter = self::cleanLicence($licenceGetter);
                      return self::insert([
                        [
                            'licence' => $licenceGetter,
                            'docId' => (int)$docId,
                            'sourceId' => Episciences_Repositories::DATACITE_REPO_ID
                        ]
                    ]);
                } else {
                    $sets->set(json_encode([""]));
                    $cache->save($sets);
                }
            } elseif ($repoId === Episciences_Repositories::HAL_REPO_ID) {
                $sets->set(json_encode($callArrayResp, JSON_THROW_ON_ERROR));
                $cache->save($sets);
                return self::insert([
                    [
                        'licence' => $callArrayResp,
                        'docId' => (int)$docId,
                        'sourceId' => Episciences_Repositories::HAL_REPO_ID
                    ]
                ]);
            }
        }
        return 0;
    }

    /**
     * @param array $licences
     * @return int
     */

    public static function insert(array $licences): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $values = [];

        $affectedRows = 0;

        foreach ($licences as $licence) {
            if (!($licence instanceof Episciences_Paper_Licence)) {
                $licence = new Episciences_Paper_Licence($licence);
            }
            $values[] = '(' . $db->quote($licence->getLicence()) . ',' . $db->quote($licence->getDocId()) . ',' . $db->quote($licence->getSourceId()) . ')';
        }

        $sql = 'INSERT INTO ' . $db->quoteIdentifier(T_PAPER_LICENCES) . ' (`licence`,`docid`,`source_id`) VALUES ';


        if (!empty($values)) {
            try {
                //Prepares and executes an SQL
                /** @var Zend_Db_Statement_Interface $result */
                $result = $db->query($sql . implode(', ', $values) . ' ON DUPLICATE KEY UPDATE licence=VALUES(licence)');
                $affectedRows = $result->rowCount();

            } catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_ERROR);
            }
        }

        return $affectedRows;

    }

    public static function getLicenceByDocId(int $docId = null): string
    {

        if (!$docId) {
            return '';
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(T_PAPER_LICENCES, ['licence', 'source_id'])->where('docid = ? ', $docId);
        return $db->fetchOne($sql);

    }

    public static function deleteLicenceByDocId(int $docId): bool
    {
        if ($docId < 1) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->delete(T_PAPER_LICENCES, ['docid = ?' => $docId]) > 0;

    }

}