<?php
use GuzzleHttp\Client;

class Episciences_Paper_LicenceManager
{
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
        return $client->get($url, [
            'headers' => [
                'User-Agent' => 'CCSD Episciences support@episciences.org',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]
        ])->getBody()->getContents();
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
        //////////////
        $licenceGetter = str_replace("http://", "https://", $licenceGetter);
        $licenceGetter = rtrim($licenceGetter, '/legalcode');
        return rtrim($licenceGetter, '/');
    }

    /**
     * @param $repoId
     * @param $identifier
     * @param $version
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function getApiResponseByRepoId($repoId, $identifier, $version): string
    {
        $callArrayResp = '';
        $prefixArxiv = '10.48550/arxiv.';
        $prefixZen = '10.5281/zenodo.';
        $communUrlArXZen = 'https://api.datacite.org/dois/';
        switch ($repoId) {
            case "1": //HAL
                $url = "https://api.archives-ouvertes.fr/search/?q=((halId_s:" . $identifier . " OR halIdSameAs_s:" . $identifier . ") AND version_i:" . $version . ")&rows=1&wt=json&fl=licence_s";
                $callArrayResp = self::callApiForLicenceByRepoId($url);
                echo PHP_EOL . 'CALL: ' . $url;
                echo PHP_EOL . 'API RESPONSE ' . $callArrayResp;
                break;
            case "2": //ARXIV
                $url = $communUrlArXZen . $prefixArxiv . $identifier;
                $callArrayResp = self::callApiForLicenceByRepoId($url);
                echo PHP_EOL . 'CALL: ' . $url;
                //echo PHP_EOL . 'API RESPONSE ' . $callArrayResp;
                sleep(1);
                break;
            case "4": //ZENODO
                $url = $communUrlArXZen . $prefixZen . $identifier;

                $callArrayResp = self::callApiForLicenceByRepoId($url);
                echo PHP_EOL . 'CALL: ' . $url;
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
     * @return void
     * @throws JsonException
     */
    public static function InsertLicenceFromApiByRepoId($repoId, $callArrayResp, $docId, $identifier): void
    {
        $pathFile =  APPLICATION_PATH . '/../data/enrichmentLicences/';
        $cleanID = str_replace('/', '', $identifier); // ARXIV CAN HAVE "/" in ID
        $repoId = (string) $repoId;
        if ($repoId === Episciences_Repositories::ARXIV_REPO_ID || $repoId === Episciences_Repositories::ZENODO_REPO_ID) {
            $licenceArray = json_decode($callArrayResp, true, 512, JSON_THROW_ON_ERROR);
            if (isset($licenceArray['data']['attributes']['rightsList'][0]['rightsUri'])) {

                file_put_contents($pathFile . $cleanID . "_licence.json", json_encode($licenceArray['data']['attributes']['rightsList'][0], JSON_THROW_ON_ERROR));
                $licenceGetter = $licenceArray['data']['attributes']['rightsList'][0]['rightsUri'];
                $licenceGetter = self::cleanLicence($licenceGetter);
                echo PHP_EOL . $licenceGetter;
                self::insert([
                    [
                        'licence' => $licenceGetter,
                        'docId' => (int) $docId,
                        'sourceId' => Episciences_Repositories::DATACITE_REPO_ID
                    ]
                ]);
                echo PHP_EOL . 'INSERT DONE ';
            } else {
                file_put_contents($pathFile . $identifier . "_licence.json", json_encode([""], JSON_THROW_ON_ERROR));
            }
        } elseif ($repoId === Episciences_Repositories::HAL_REPO_ID) {
            $licenceArray = json_decode($callArrayResp, true, 512, JSON_THROW_ON_ERROR);
            if ($licenceArray['response']['numFound'] !== 0 && array_key_exists('licence_s', $licenceArray['response']['docs'][0])) {
                $licenceGetter = $licenceArray['response']['docs'][0]['licence_s'];
                $licenceGetter = self::cleanLicence($licenceGetter);
                echo PHP_EOL . $licenceGetter;
                file_put_contents($pathFile . $identifier . "_licence.json", json_encode($licenceArray['response'], JSON_THROW_ON_ERROR));
                self::insert([
                    [
                        'licence' => $licenceGetter,
                        'docId' => (int) $docId,
                        'sourceId' => Episciences_Repositories::HAL_REPO_ID
                    ]
                ]);
                echo PHP_EOL . 'INSERT DONE ';
            } else {
                file_put_contents($pathFile . $identifier . "_licence.json", json_encode([""], JSON_THROW_ON_ERROR));
            }
        }
    }


    public static function getLicenceByDocId($docId): string
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(T_PAPER_LICENCES, ['licence','source_id'])->where('docid = ? ', $docId);
        return $db->fetchOne($sql);

    }
}