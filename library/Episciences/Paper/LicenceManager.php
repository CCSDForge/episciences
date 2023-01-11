<?php
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class Episciences_Paper_LicenceManager
{
    public const ONE_MONTH = 3600 * 24 * 31;

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

    /**
     * @param $url
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function callApiForLicenceByRepoId($url): string
    {
        $client = new Client();
        try {
            $callArrayResp = $client->get($url, [
                'headers' => [
                    'User-Agent' => 'CCSD Episciences support@episciences.org',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]
            ])->getBody()->getContents();
            return $callArrayResp;
        }catch (ClientException $e) {
            trigger_error('Api call error: '.$url);
            return "";
        }

    }

    /**
     * @param string $licenceGetter
     * @return string
     */
    public static function cleanLicence(string $licenceGetter): string
    {
        //specific url
        $licenceGetter = str_replace("http://hal.archives-ouvertes.fr/licences/etalab/","https://raw.githubusercontent.com/DISIC/politique-de-contribution-open-source/master/LICENSE",$licenceGetter);
        $licenceGetter = str_replace("http://hal.archives-ouvertes.fr/licences/publicDomain/","https://creativecommons.org/publicdomain/zero/1.0",$licenceGetter);

        $noVersionNcSa = "/http:\/\/creativecommons.org\/licenses\/by-nc-sa\/$/";
        $licenceGetter = preg_replace($noVersionNcSa, "https://creativecommons.org/licenses/by-nc-sa/4.0", $licenceGetter);

        $noVersionBySa = "/http:\/\/creativecommons.org\/licenses\/by-sa\/$/";
        $licenceGetter = preg_replace($noVersionBySa, "https://creativecommons.org/licenses/by-sa/4.0", $licenceGetter);

        $noVersionByNd = "/http:\/\/creativecommons.org\/licenses\/by-nd\/$/";
        $licenceGetter = preg_replace($noVersionByNd, "https://creativecommons.org/licenses/by-nd/4.0", $licenceGetter);

        $noVersionByNc = "/http:\/\/creativecommons.org\/licenses\/by-nc\/$/";
        $licenceGetter = preg_replace($noVersionByNc, "https://creativecommons.org/licenses/by-nc/4.0", $licenceGetter);

        $noVersionByNcNd = "/http:\/\/creativecommons.org\/licenses\/by-nc-nd\/$/";
        $licenceGetter = preg_replace($noVersionByNcNd, "https://creativecommons.org/licenses/by-nc-nd/4.0", $licenceGetter);
        //////////////
        $licenceGetter = str_replace("http://", "https://", $licenceGetter);
        $licenceGetter = preg_replace("/\/legalcode/", '', $licenceGetter);

        return rtrim($licenceGetter, '/');
    }

    /**
     * @param $repoId
     * @param $identifier
     * @param $version
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function getApiResponseByRepoId($repoId, $identifier, int $version): string
    {
        $callArrayResp = '';
        $prefixArxiv = '10.48550/arxiv.';
        $prefixZen = '10.5281/zenodo.';
        $communUrlArXZen = 'https://api.datacite.org/dois/';
        switch ($repoId) {
            case "1": //HAL
                $callArrayResp = self::getLicenceFromTeiHal($identifier, $version);
                break;
            case "2": //ARXIV
                $url = $communUrlArXZen . $prefixArxiv . $identifier;
                $callArrayResp = self::callApiForLicenceByRepoId($url);

                if (PHP_SAPI === 'cli'){
                    echo PHP_EOL . 'CALL: ' . $url;
                }
                //echo PHP_EOL . 'API RESPONSE ' . $callArrayResp;
                sleep(1);
                break;
            case "4": //ZENODO
                $url = $communUrlArXZen . $prefixZen . $identifier;

                $callArrayResp = self::callApiForLicenceByRepoId($url);
                if (PHP_SAPI === 'cli') {
                    echo PHP_EOL . 'CALL: ' . $url;
                }
                //echo PHP_EOL . 'API RESPONSE ' . $callArrayResp;
                sleep(1);
                break;
            default: //OTHERS
                break;
        }
        return $callArrayResp;
    }

    /**
     * @param $repoId
     * @param $callArrayResp
     * @param $docId
     * @param $identifier
     * @return int
     * @throws JsonException
     */
    public static function InsertLicenceFromApiByRepoId($repoId, $callArrayResp, $docId, $identifier): int
    {
        $pathFile =  APPLICATION_PATH . '/../data/enrichmentLicences/';
        $cleanID = str_replace('/', '', $identifier); // ARXIV CAN HAVE "/" in ID
        $repoId = (string) $repoId;
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
                    if (PHP_SAPI === 'cli'){
                        echo PHP_EOL . $licenceGetter;
                    }
                    return self::insert([
                        [
                            'licence' => $licenceGetter,
                            'docId' => (int) $docId,
                            'sourceId' => Episciences_Repositories::DATACITE_REPO_ID
                        ]
                    ]);
                } else {
                    $sets->set(json_encode([""]));
                    $cache->save($sets);
                }
            } elseif ($repoId === Episciences_Repositories::HAL_REPO_ID) {
                if (PHP_SAPI === 'cli'){
                    echo PHP_EOL . $callArrayResp;
                }
                $sets->set(json_encode($callArrayResp, JSON_THROW_ON_ERROR));
                $cache->save($sets);
                return self::insert([
                    [
                        'licence' => $callArrayResp,
                        'docId' => (int) $docId,
                        'sourceId' => Episciences_Repositories::HAL_REPO_ID
                    ]
                ]);
            }
        }
        return 0;
    }


    public static function getLicenceByDocId($docId): string
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(T_PAPER_LICENCES, ['licence','source_id'])->where('docid = ? ', $docId);
        return $db->fetchOne($sql);

    }

    public static function deleteLicenceByDocId(int $docId): bool
    {
        if ($docId < 1) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return ($db->delete(T_PAPER_LICENCES, ['docid = ?' => $docId]) > 0);

    }

    /**
     * @param string $identifier
     * @param int $version
     * @return string
     */
    public static function getLicenceFromTeiHal(string $identifier, int $version): string
    {
        Episciences_Paper_AuthorsManager::getHalTei($identifier, $version);
        $cacheTeiHal = Episciences_Paper_AuthorsManager::getHalTeiCache($identifier, $version);
        $xmlString = simplexml_load_string($cacheTeiHal);
        $licence = '';
        if (isset($xmlString->teiHeader->fileDesc->publicationStmt->availability->licence, $xmlString->teiHeader->fileDesc->publicationStmt->availability->licence->attributes()->target)) {
                $licence = (string) $xmlString->teiHeader->fileDesc->publicationStmt->availability->licence->attributes()->target;
                //very specific ~~> try to replace bad url licence by
                $badby = "/http:\/\/creativecommons.org\/licenses\/by\/$/";
                $licence = preg_replace($badby, "https://creativecommons.org/licenses/by/1.0", $licence);
                return self::cleanLicence(str_replace('http://', 'https://', $licence));
        }
        return $licence;
    }

}