<?php
use GuzzleHttp\Client;

class Episciences_BibliographicalsReferencesTools
{
    public static function getBibRefFromApi($pdf) {
        $client = new Client();
        $apiEpiBibCitation = EPISCIENCES_BIBLIOREF['URL'];
        try {
            $response = $client->get($apiEpiBibCitation . "/visualize-citations?url=" . $pdf,['verify' => EPISCIENCES_BIBLIOREF["SSL_VERIFY"]])->getBody()->getContents();
            return self::referencesToArray($response);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            return [];
        }
    }
    public static function referencesToArray(string $rawCitations){
        try {
            $rawCitations = json_decode($rawCitations, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return [];
        }
        $formattedCitations = [];
        if (!array_key_exists("message",$rawCitations)) {
            $i = 0;
            foreach ($rawCitations as $citation) {
                $reference = json_decode($citation['ref'], true, 512, JSON_THROW_ON_ERROR);
                foreach ($reference as $key => $refInfo) {
                    if ($key === "raw_reference") {
                        $formattedCitations[$i]['unstructured_citation'] = $refInfo;
                    }
                    if ($key === 'doi') {
                        $formattedCitations[$i]['doi'] = $refInfo;
                    }
                }
                if (array_key_exists('csl',$citation)){
                    $formattedCitations[$i]['csl'] = $citation['csl'];
                }
                $i++;

            }
        }
        return $formattedCitations;
    }
}