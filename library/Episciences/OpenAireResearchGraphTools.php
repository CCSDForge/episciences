<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class Episciences_OpenAireResearchGraphTools
{
    public const ONE_MONTH = 3600 * 24 * 31;

    // check if we have already the cache for the doi
    // if not call one time api and put cache for all concerned info like licences and creator (at this moment) and all the response from this api
    // avoid multiple call for this api

    public static function checkOpenAireGlobalInfoByDoi($doi,$paperId): void
    {

        $dir = CACHE_PATH_METADATA . 'openAireResearchGraph/';

        if (!file_exists($dir)) {
            $result = mkdir($dir);
            if (!$result) {
                die('Fatal error: Failed to create directory: ' . $dir);
            }
        }


        $fileOpenAireGlobalResponse = trim(explode("/", $doi)[1]) . ".json";
        $cache = new FilesystemAdapter('openAireResearchGraph', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
        $sets = $cache->getItem($fileOpenAireGlobalResponse);
        $sets->expiresAfter(self::ONE_MONTH);
        if (!$sets->isHit()) {
            $client = new Client();
            $info = PHP_EOL . 'https://api.openaire.eu/search/publications/?doi=' . $doi . '&format=json'. PHP_EOL ;
            echo $info;
            $openAireCallArrayResp = self::callOpenAireApi($client, $doi);
            try {
                $decodeOpenAireResp = json_decode($openAireCallArrayResp, true, 512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                $sets->set(json_encode($decodeOpenAireResp,JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                $cache->save($sets);
            }catch (JsonException $e){
                $eMsg = $e->getMessage() . " for PAPER " . $paperId . ' URL called https://api.openaire.eu/search/publications/?doi=' . $doi . '&format=json ';
                echo PHP_EOL. $eMsg .PHP_EOL;
                // OPENAIRE CAN RETURN MALFORMED JSON SO WE LOG URL OPENAIRE
                self::logErrorMsg($eMsg);
                $sets->set(json_encode([""], JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                $cache->save($sets);
            }
        }
    }

    /**
     * @param Client $client
     * @param $doi
     * @return string
     */
    public static function callOpenAireApi(Client $client, $doi): string
    {

        $openAireCallArrayResp = '';

        try {

            return $client->get('https://api.openaire.eu/search/publications/?doi=' . $doi . '&format=json', [
                'headers' => [
                    'User-Agent' => 'CCSD Episciences support@episciences.org',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]
            ])->getBody()->getContents();

        } catch (GuzzleException $e) {

            trigger_error($e->getMessage());

        }
        sleep(1);
        return $openAireCallArrayResp;
    }

    public static function logErrorMsg($msg): void
    {
        $logger = new Logger('my_logger');
        $logger->pushHandler(new StreamHandler(EPISCIENCES_LOG_PATH . 'openAireResearchGraph_'.date('Y-m-d').'.log', Logger::INFO));

        $logger->info($msg);
    }
}
