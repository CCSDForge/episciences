<?php
declare(strict_types=1);

namespace Episciences\Paper\Import;

use Ccsd_Tools;
use DateTime;
use Episciences_Paper;
use Episciences_Tools;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Resolves the publication date for a paper being imported/updated, trying in order:
 *   1. the CSV row's publication_date column
 *   2. the paper's own current publication date (relevant for updates)
 *   3. the OAI dc:date field of the freshly fetched record
 *   4. the archives-ouvertes.fr (HAL) API
 *   5. now()
 *
 * Ported from scripts/update_papers.php's getPublicationDate*() methods, fixing a bug
 * where steps 2 and 3 validated the wrong (always-empty) variable and were dead code.
 */
final class PublicationDateResolver
{
    public function __construct(private readonly Client $httpClient = new Client())
    {
    }

    public function resolve(Row $row, Episciences_Paper $paper): string
    {
        return $this->fromArgs($row)
            ?? $this->fromPaper($paper)
            ?? $this->fromOai($paper->getMetadata('record'))
            ?? $this->fromHalApi($paper)
            ?? date('Y-m-d H:i:s');
    }

    private function fromArgs(Row $row): ?string
    {
        if ($row->publicationDate === null) {
            return null;
        }

        return $this->validated(Episciences_Tools::getValidSQLDateTime($row->publicationDate));
    }

    private function fromPaper(Episciences_Paper $paper): ?string
    {
        $current = $paper->getPublication_date();
        if (!$current) {
            return null;
        }

        return $this->validated(Episciences_Tools::getValidSQLDateTime($current));
    }

    /**
     * @param mixed $record
     */
    private function fromOai($record): ?string
    {
        if (!$record) {
            return null;
        }

        $dcDate = Ccsd_Tools::xpath($record, '//dc:date');
        if (!$dcDate) {
            return null;
        }

        if (is_array($dcDate)) {
            $dcDate = array_shift($dcDate);
        }

        return $this->validated(Episciences_Tools::getValidSQLDateTime($dcDate));
    }

    private function fromHalApi(Episciences_Paper $paper): ?string
    {
        $identifier = $paper->getIdentifier();
        if (!$identifier) {
            return null;
        }

        $url = self::buildHalApiUrl($identifier, (string)($paper->getVersion() ?: 1));

        try {
            $response = $this->httpClient->get($url, ['headers' => ['Content-type' => 'application/json']]);
        } catch (GuzzleException) {
            return null;
        }

        $result = json_decode($response->getBody()->getContents(), true);
        $rawDate = $result['response']['docs'][0]['publicationDate_tdate'] ?? null;

        if (!is_string($rawDate) || $rawDate === '') {
            return null;
        }

        // expecting e.g. 2000-12-10T00:00:00Z
        return (new DateTime($rawDate))->format('Y-m-d H:i:s');
    }

    public static function buildHalApiUrl(string $identifier, string $version): string
    {
        return sprintf(
            'https://api.archives-ouvertes.fr/search/?indent=true&q=halId_s:%s&fq=version_i:%s&fl=publicationDate_tdate&wt=json',
            rawurlencode($identifier),
            rawurlencode($version)
        );
    }

    private function validated(mixed $dateTested): ?string
    {
        return Episciences_Tools::isValidSQLDateTime($dateTested) ? $dateTested : null;
    }
}
