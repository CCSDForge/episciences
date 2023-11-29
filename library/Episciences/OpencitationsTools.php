<?php
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class Episciences_OpencitationsTools {

    public const ONE_MONTH = 3600 * 24 * 31;
    public const CITATIONS_PREFIX_VALUE = "coci => ";
    /**
     * @param string $doi
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function getOpenCitationCitedByDoi(string $doi)
    {
        $cache = new FilesystemAdapter('enrichmentCitations', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
        $trimDoi = trim($doi);
        $fileName = $trimDoi . "_citations.json";
        $sets = $cache->getItem($fileName);
        $sets->expiresAfter(self::ONE_MONTH);
        if (!$sets->isHit()) {
            if (PHP_SAPI === 'cli') {
                echo PHP_EOL .'Call API Opencitations for ' . $trimDoi . PHP_EOL;
            }
            $respCitationsApi = self::retrieveAllCitationsByDoi($trimDoi);
            if ($respCitationsApi !== '' && $respCitationsApi !== '[]') {
                $sets->set($respCitationsApi);
                $cache->save($sets);
                if (PHP_SAPI === 'cli') {
                    echo PHP_EOL .'PUT CACHE CALL FOR ' . $trimDoi . PHP_EOL;
                }
            } else {
                if (PHP_SAPI === 'cli') {
                    echo PHP_EOL .'EMPTY RESPONSE ' . $trimDoi . PHP_EOL;
                }
                $sets->set(json_encode([""]));
                return $sets;
            }
        }
        if (PHP_SAPI === 'cli') {
            echo PHP_EOL .'GET CACHE CALL FOR ' . $trimDoi . PHP_EOL;
        }
        return $sets;
    }
    public static function retrieveAllCitationsByDoi(string $doi)
    {

        $client = new Client();
        $openCitationCall = '';
        try {
            usleep(500000);
            return $client->get(OPENCITATIONS_APIURL . $doi, [
                'headers' => [
                    'User-Agent' => EPISCIENCES_USER_AGENT,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'authorization' => OPENCITATIONS_TOKEN
                ]
            ])->getBody()->getContents();
        } catch (GuzzleException $e) {
            trigger_error($e->getMessage());
        }
        return $openCitationCall;
    }


    /**
     * @param array $apiCallCitationCache
     * @return array
     */
    public static function cleanDoisCitingFound(array $apiCallCitationCache): array
    {
        $globalArrayCiteDOI = array_map(static function ($citationsValues) {
            $citationHasDoi = 0;
            $doi = str_replace(self::CITATIONS_PREFIX_VALUE, "", $citationsValues['citing']);
            $separateAllIds = explode(" ",$doi);
            foreach ($separateAllIds as $id) {
                if (preg_match("~^doi:~",$id)){
                    $doi = str_replace('doi:',"",$id);
                    $citationHasDoi = 1;
                    break;
                }
            }
            if ($citationHasDoi === 0) {
                $doi = '';
            }
            return preg_replace("~;(?<=;)\s.*~", "", $doi);
        }, $apiCallCitationCache);
        return $globalArrayCiteDOI;
    }
}

