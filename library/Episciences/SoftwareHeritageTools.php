<?php
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Episciences_SoftwareHeritageTools
{
    public const SH_DOMAIN_API = "https://archive.softwareheritage.org/api/1";
    public const API_HAL_URL = "https://hal.science/";

    public const PREFIX_SWHID_DIR = 'swh:1:dir:';

    /**
     * @param $url
     * @return string
     * @throws JsonException
     */
    public static function getCodeMetaFromHal($url) : string {
        $client = new Client();
        try {
            $res = $client->request('GET', self::API_HAL_URL.$url.'/codemeta')->getBody()->getContents();
            $resJson = json_decode($res);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $res;
            }
        } catch (GuzzleException $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }
        return '';
    }
    public static function getCitationsFullFromHal($halId,int $version = 0) : string {
        $client = new Client();
        try {

            $strQuery = '((halId_s:'.$halId.' OR halIdSameAs_s:'.$halId.')';

            if ($version !== 0){
                $strQuery.=' AND version_i:'.$version;
            }
            $strQuery.= ')&fl=docType_s,citationFull_s,swhidId_s';
            $res = $client->request('GET',  Episciences_Repositories::getApiUrl(
                    Episciences_Repositories::HAL_REPO_ID).'/search?q='.$strQuery)
                ->getBody()->getContents();
            $resJson = json_decode($res);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $res;
            }
        } catch (GuzzleException $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }
        return '';
    }

    public static function getCodeMetaFromDirSwh(string $swhidDir) {

        $client = new Client();
        $cleanSwhid = str_replace(self::PREFIX_SWHID_DIR,'',$swhidDir);
        try {
            $res = $client->request('GET', self::SH_DOMAIN_API."/directory/".$cleanSwhid.'/codemeta.json')->getBody()->getContents();
            $res = json_decode($res, true, 512, JSON_THROW_ON_ERROR);
            if (array_key_exists("target_url",$res)){
                try {
                    $res = $client->request('GET', $res['target_url'].'raw')->getBody()->getContents();
                    if ($res !== ""){
                        return $res;
                    }

                } catch (GuzzleException $e){
                    trigger_error($e->getMessage(), E_USER_WARNING);
                }

            }
        } catch (GuzzleException $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }
        return '';

    }

    public static function replaceByBadgeHalCitation(string $swhid, string $citation){
        $regex = "~&#x27E8;".$swhid."&#x27E9;~";
        return preg_replace($regex,'<img src="https://archive.softwareheritage.org/badge/'.$swhid.'" alt="Archived | '.$swhid.' "/>',$citation);
    }

}