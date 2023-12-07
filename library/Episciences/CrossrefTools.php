<?php
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class Episciences_CrossrefTools
{
    public const ONE_MONTH = 3600 * 24 * 31;
    public const CROSSREF_PLUS_API_TOKEN_HEADER_NAME = 'Crossref-Plus-API-Token';

    /**
     * @param string $doiWhoCite
     * @return mixed
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function callCrossRefOrGetCacheMetadata(string $doiWhoCite)
    {
        $cache = new FilesystemAdapter('enrichmentCitations', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
        $fileNameMetadataCr = $doiWhoCite . "_citationsMetadatas_crossref.json";
        $setsMetadataCr = $cache->getItem($fileNameMetadataCr);
        $setsMetadataCr->expiresAfter(self::ONE_MONTH);
        if (!$setsMetadataCr->isHit()) {
            if (PHP_SAPI === 'cli') {
                echo PHP_EOL . 'CALL API CROSSREF FOR EXTRA METADATAS ' . $doiWhoCite . PHP_EOL;
            }
            Episciences_Paper_CitationsManager::logInfoMessage( 'CALL API CROSSREF FOR EXTRA METADATAS ' . $doiWhoCite );
            $respCitationMetadataApi = '';
            if (!empty($doiWhoCite)) {
                $respCitationMetadataApi = self::getMetadatasCrossref($doiWhoCite);
            }
            if ($respCitationMetadataApi !== '') {
                $setsMetadataCr->set($respCitationMetadataApi);
            } else {
                $setsMetadataCr->set(json_encode([""], JSON_THROW_ON_ERROR));
            }
            $cache->save($setsMetadataCr);

        }
        return $setsMetadataCr;
    }


    /**
     * @param string $doi
     * @return string
     */
    public static function getMetadatasCrossref(string $doi): string
    {
        $client = new Client();
        $crossrefApiResponse = '';

        $headers = [
            'headers' => [
                'User-Agent' => EPISCIENCES_USER_AGENT,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ];

        if (defined(CROSSREF_PLUS_API_TOKEN) && CROSSREF_PLUS_API_TOKEN !== '') {
            $headers['headers'][self::CROSSREF_PLUS_API_TOKEN_HEADER_NAME] = 'Bearer ' . CROSSREF_PLUS_API_TOKEN;
        } else {
            // try to remain below rate limit with 0.5s sleep when no Crossref metadata plus token is available
            usleep(500000);
        }

        try {
            $crossrefApiResponse =  $client->get(CROSSREF_APIURL.$doi."?mailto=".CROSSREF_MAILTO, [$headers])->getBody()->getContents();
        } catch (GuzzleException $e) {
            trigger_error(sprintf("Code: %s Message: %s", $e->getCode(), $e->getMessage()));
        }

        return $crossrefApiResponse;
    }

    /**
     * @param $getBestOpenAccessInfo
     * @param $doiWhoCite
     * @return string
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function getLocationFromCrossref($getBestOpenAccessInfo, string $doiWhoCite): string
    {
        $getLocationFromCr = "";
        // if not found in openAlex surely Closed access citation and locations doesn't found in openAlex
        // we try to get at least in crossref the host of this citation
        if ($getBestOpenAccessInfo === "") {
            if (PHP_SAPI === 'cli') {
                echo PHP_EOL .'NO LOCATIONS IN OPENALEX ' . $doiWhoCite. PHP_EOL;
            }
            Episciences_Paper_CitationsManager::logInfoMessage('NO LOCATIONS IN OPENALEX ' . $doiWhoCite);
            $setsMetadataCr = self::callCrossRefOrGetCacheMetadata($doiWhoCite);
            $metadataInfoCitationCr = json_decode($setsMetadataCr->get(), true, 512, JSON_THROW_ON_ERROR);
            if (reset($metadataInfoCitationCr) !== "") {
                $getLocationFromCr = Episciences_CrossrefTools::getLocation($metadataInfoCitationCr);
            }
        }
        return $getLocationFromCr;
    }

    /**
     * @param $typeCrossref
     * @param $doiWhoCite
     * @param $globalInfoMetadata
     * @param int $i
     * @return array|mixed
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function addLocationEvent($typeCrossref, $doiWhoCite, $globalInfoMetadata, int $i)
    {
        if ($typeCrossref === 'proceedings-article') {
            $setsMetadataCr = self::callCrossRefOrGetCacheMetadata($doiWhoCite);
            $metadataInfoCitationCr = json_decode($setsMetadataCr->get(), true, 512, JSON_THROW_ON_ERROR);
            return self::addEventLocationInArray($metadataInfoCitationCr, $globalInfoMetadata, $i);
        }
        $globalInfoMetadata[$i]['event_place'] = "";
        return $globalInfoMetadata;
    }
    /**
     * @param $metadataInfoCitationCr
     * @param array $globalInfoMetadata
     * @param int $i
     * @return array
     */
    public static function addEventLocationInArray($metadataInfoCitationCr, array $globalInfoMetadata, int $i): array
    {
        if (reset($metadataInfoCitationCr) !== "") {
            $getEventPlace = Episciences_CrossrefTools::getEventPlace($metadataInfoCitationCr);
            $globalInfoMetadata[$i]['event_place'] = $getEventPlace;
        } else {
            $globalInfoMetadata[$i]['event_place'] = "";
        }
        return $globalInfoMetadata;
    }

    /**
     * @param $jsonCr
     * @return mixed
     */
    public static function getLocation($jsonCr) {
        return $jsonCr['message']['container-title'][0] ?? '';
    }
    public static function getEventPlace($jsonCr){
        return $jsonCr['message']['event']['location'] ?? '';
    }
}
