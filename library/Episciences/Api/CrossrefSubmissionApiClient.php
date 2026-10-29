<?php
declare(strict_types=1);

namespace Episciences\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Crossref submission API client.
 *
 * Handles two distinct Crossref endpoints:
 *  - Deposit API  : POST multipart XML metadata (assign DOI)
 *  - Query API    : GET submission status
 *
 * Credentials and URLs are injected so the class can be unit-tested
 * without global constants.
 */
class CrossrefSubmissionApiClient
{
    public function __construct(
        private readonly Client          $client,
        private readonly LoggerInterface $logger,
        private readonly string          $depositUrl,
        private readonly string          $depositTestUrl,
        private readonly string          $queryUrl,
        private readonly string          $queryTestUrl,
        private readonly string          $login,
        private readonly string          $password,
    ) {}

    /**
     * POST XML metadata to the Crossref deposit API.
     *
     * @param string $xmlContent  Raw XML body, kept in memory only (no disk write).
     * @param string $fileName    Filename Crossref associates with the submission; also
     *                            reused as the correlation key by fetchStatus().
     * @throws GuzzleException    on HTTP/network failure (propagated to caller).
     */
    public function postMetadata(string $xmlContent, string $fileName, bool $dryRun): ResponseInterface
    {
        $url = $dryRun ? $this->depositTestUrl : $this->depositUrl;
        $this->logger->info("Posting {$fileName} to {$url}");

        return $this->client->request('POST', $url, [
            RequestOptions::MULTIPART => [
                ['name' => 'operation',    'contents' => 'doMDUpload'],
                ['name' => 'login_id',     'contents' => $this->login],
                ['name' => 'login_passwd', 'contents' => $this->password],
                ['name' => 'fname',        'filename' => $fileName, 'contents' => $xmlContent],
            ],
        ]);
    }

    /**
     * GET submission status from the Crossref query API.
     *
     * Returns the raw XML response body, or null on network/API error.
     */
    public function fetchStatus(string $fileName, bool $dryRun): ?string
    {
        $url = $dryRun ? $this->queryTestUrl : $this->queryUrl;

        try {
            $response = $this->client->request('GET', $url, [
                'query' => [
                    'usr'       => $this->login,
                    'pwd'       => $this->password,
                    'file_name' => $fileName,
                    'type'      => 'result',
                ],
            ]);
            return $response->getBody()->getContents();
        } catch (GuzzleException $e) {
            $this->logger->error("Crossref status query failed for {$fileName}: " . $e->getMessage());
            return null;
        }
    }
}
