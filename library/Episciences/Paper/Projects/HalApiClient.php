<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * HTTP client for the HAL archives-ouvertes.fr API.
 * No DB, no HTML, no cache — single responsibility.
 */
class Episciences_Paper_Projects_HalApiClient
{
    private const HAL_API_BASE       = 'https://api.archives-ouvertes.fr';
    private const EU_PROJECT_FIELDS  = 'projectTitle:title_s,acronym:acronym_s,code:reference_s,callId:callId_s,projectFinancing:financing_s';
    private const ANR_PROJECT_FIELDS = 'projectTitle:title_s,acronym:acronym_s,code:reference_s';
    private const ID_FIELDS          = 'europeanProjectId_i,anrProjectId_i';

    /**
     * Fetch European and ANR project IDs for a HAL document.
     * Replaces CallHAlApiForIdEuAndAnrFunding().
     */
    public static function fetchProjectIds(string $identifier, int $version): string
    {
        $url = self::HAL_API_BASE . '/search/?q=((halId_s:' . urlencode($identifier)
            . ' OR halIdSameAs_s:' . urlencode($identifier)
            . ') AND version_i:' . $version . ')&fl=' . self::ID_FIELDS;
        return self::doGet($url);
    }

    /**
     * Fetch European project details by HAL doc ID.
     * Replaces CallHAlApiForEuroProject().
     */
    public static function fetchEuropeanProject(int $halDocId): string
    {
        $url = self::HAL_API_BASE . '/ref/europeanproject/?q=docid:' . $halDocId
            . '&fl=' . self::EU_PROJECT_FIELDS;
        return self::doGet($url);
    }

    /**
     * Fetch ANR project details by HAL doc ID.
     * Replaces CallHAlApiForAnrProject().
     */
    public static function fetchAnrProject(int $halDocId): string
    {
        $url = self::HAL_API_BASE . '/ref/anrproject/?q=docid:' . $halDocId
            . '&fl=' . self::ANR_PROJECT_FIELDS;
        return self::doGet($url);
    }

    private static function doGet(string $url): string
    {
        $client = new Client();
        try {
            return $client->get($url, [
                'headers' => [
                    'User-Agent'   => EPISCIENCES_USER_AGENT,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
            ])->getBody()->getContents();
        } catch (GuzzleException $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return '';
        }
    }
}
