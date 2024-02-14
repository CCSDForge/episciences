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
        if(Episciences_Tools::isArxiv($doi)) {
            $regexRemoveVersion = "~v[\d{1,100}]~"; // doi org doesn't accept arxiv version in call
            $doi = preg_replace($regexRemoveVersion,"",$doi);
            if (!preg_match("~(?i)(arxiv)~",$doi)){
                $doi = Episciences_Repositories::getRepoDoiPrefix(Episciences_Repositories::ARXIV_REPO_ID).'/arxiv.'.$doi;
            } else{
                $doi = Episciences_Repositories::getRepoDoiPrefix(Episciences_Repositories::ARXIV_REPO_ID).$doi;
            }
        }
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
