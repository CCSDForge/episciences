<?php
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Episciences_DoiTools
{
    /**
     * @param string $doi
     */
    public static function getMetadataFromDoi(string $doi) {

        $client = new Client();
        $arrayRes = [];
        if (self::checkIfDomainExist($doi) === false){
            $doi = Episciences_Paper_Dataset::$_datasetsLink['doi'].$doi;
        }
        try {
            $response = $client->get($doi,[
                'headers' => [
                    'Accept' => 'application/vnd.citationstyles.csl+json',
                    'Content-type' => "application/json"
                    ]
            ]);
            $stream = $response->getBody();
            return $stream->getContents();
        } catch (GuzzleException $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }

        return "";
    }

    /**
     * @param string $doi
     * @return bool
     */
    public static function checkIfDomainExist(string $doi) : bool{
        $regexDomain = "~^https://doi\.org/~";
        return (bool)preg_match($regexDomain, $doi);

    }
}
