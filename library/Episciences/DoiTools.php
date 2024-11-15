<?php
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Episciences_DoiTools
{
    /**
     * @const string DOI prefix
     */
    public const DOI_ORG_PREFIX = 'https://doi.org/';

    /**
     * @param string $doi
     * @return string
     */
    public static function getMetadataFromDoi(string $doi): string
    {

        $client = new Client();
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
            $doi = self::DOI_ORG_PREFIX . $doi;
        }

        try {
            $response = $client->get($doi,[
                'headers' => [
                    'Accept' => 'application/vnd.citationstyles.csl+json',
                    'Content-type' => "application/json"
                    ]
            ]);
            return $response->getBody()->getContents();
        } catch (GuzzleException $e) {
            error_log($e->getMessage(), E_USER_WARNING);
            return "";
        }
    }

    /**
     * @param string $doi
     * @return bool
     */
    public static function checkIfDomainExist(string $doi) : bool{
        $regexDomain = "~^https://(dx.)?doi\.org/~";
        return (bool)preg_match($regexDomain, $doi);

    }

    /**
     * to remove doi domain
     * @param string $doi
     * @return string
     */

    public static function cleanDoi(string $doi = ''): string
    {
        return ($doi !== '') ? str_replace(['https://doi.org/', 'https://dx.doi.org/'], '', $doi) : '';
    }

}
