<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class Episciences_OpenalexTools {
    public const PARAMS_OALEX = "?select=title,authorships,open_access,biblio,primary_location,locations,publication_year,best_oa_location,type_crossref";

    public const ONE_MONTH = 3600 * 24 * 31;

    /**
     * @param string $doiWhoCite
     * @return mixed
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function getMetadataOpenAlexByDoi(string $doiWhoCite)
    {
        $cache = new FilesystemAdapter('enrichmentCitations', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
        $fileNameMetadata = $doiWhoCite . "_citationsMetadatas.json";
        $setsMetadata = $cache->getItem($fileNameMetadata);
        $setsMetadata->expiresAfter(self::ONE_MONTH);
        if (!$setsMetadata->isHit()) {
            if (PHP_SAPI === 'cli') {
                echo PHP_EOL . 'CALL API FOR METADATA ' . $doiWhoCite . PHP_EOL;
            }
            Episciences_Paper_CitationsManager::logInfoMessage('CALL API FOR METADATA ' . $doiWhoCite);
            $respCitationMetadataApi = '';
            if (!empty($doiWhoCite)) {
                $respCitationMetadataApi = self::getMetadataByDoiCite($doiWhoCite);
            }
            if ($respCitationMetadataApi !== '') {
                $setsMetadata->set($respCitationMetadataApi);
            } else {
                $setsMetadata->set(json_encode([""], JSON_THROW_ON_ERROR));
            }
            $cache->save($setsMetadata);
        }
        return $setsMetadata;
    }

    /**
     * @param string $doi
     * @return string
     */
    public static function getMetadataByDoiCite(string $doi): string
    {

        $client = new Client();
        $openAlexMetadataCall = '';
        try {
            usleep(500000);
            return $client->get(OPENALEX_APIURL ."https://doi.org/". $doi . self::PARAMS_OALEX . "&mailto=". OPENALEX_MAILTO, [
                'headers' => [
                    'User-Agent' => EPISCIENCES_USER_AGENT,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]
            ])->getBody()->getContents();
        } catch (GuzzleException $e) {
            trigger_error($e->getMessage());
        }
        return $openAlexMetadataCall;
    }

    /**
     * @param array $authorList
     * @return string
     */
    public static function getAuthors(array $authorList): string {
        $strAuthor ='';
        $kLast = array_key_last($authorList);
        foreach ($authorList as $key => $authorInfo){
            $strAuthor .= $authorInfo['raw_author_name'];
            if (isset($authorInfo['author']['orcid'])){
                $strAuthor .= ", ".str_replace("https://orcid.org/",'',$authorInfo['author']['orcid']);
            }
            if ($kLast !== $key){
                $strAuthor.="; ";
            }
        }
        return $strAuthor;
    }

    /**
     * @param string|null $fp
     * @param string|null $lp
     * @return string
     */
    public static function getPages(?string $fp, ?string $lp): string {
        if (is_null($fp)) {
            return "";
        }
        return ($fp === $lp) ? $fp : $fp."-".$lp ;
    }

    /**
     * @param array $locations
     * @return mixed|void
     */
    public static function getFirstAlternativeLocations(array $locations) {
        foreach ($locations as $location){
            if (!is_null($location['source'])){
                $arrayOa = ['source_title' => $location['source']['display_name'], 'oa_link' => ""];
                if ($location['is_oa'] === true){
                    $arrayOa['oa_link'] = $location['source']['landing_page_url'];
                }
                return $arrayOa;
            }
        }
        return "";
    }

    /**
     * @param array|null $locations
     * @return array|mixed|string|null
     */
    public static function getBestOaInfo($primaryLocation, array $locations, $bestOaLocation) {
        if ($bestOaLocation !== null && !is_null($bestOaLocation['source'])) {
          return ['source_title' => $bestOaLocation['source']['display_name'],'oa_link' => $bestOaLocation['landing_page_url']];
        }
        if ($primaryLocation['is_oa'] === true && !is_null($primaryLocation['source'])) {
            return ['source_title' => $primaryLocation['source']['display_name'],'oa_link' => $primaryLocation['landing_page_url']];
        }
        foreach ($locations as $location) {
            if ($location['is_oa'] === true && !is_null($location['source'])) {
                return ['source_title' => $location['source']['display_name'],'oa_link' => $location['landing_page_url']];
            }
        }
        return self::getFirstAlternativeLocations($locations);
    }
}
