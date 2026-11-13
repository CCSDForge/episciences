<?php

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Metadata of an arXiv document read from DataCite, used when the arXiv OAI-PMH server does not answer.
 *
 * arXiv registers a DOI per document (not per version) at DataCite: 10.48550/arXiv.<id>. DataCite updates
 * the record at each new version: the metadata are those of the latest version, like the arXiv oai_dc
 * record, and the Submitted dates tell the versions (dateInformation "v1", "v2"...).
 *
 * The record built here has the shape of the arXiv oai_dc record, so that the submission handles it the
 * same way.
 */
class Episciences_Repositories_ArXiv_DataciteFallback
{
    /** Value of the record source when the record comes from DataCite */
    public const SOURCE = 'datacite';
    /** Constant (pwd.json) switching the fallback off when false */
    public const ENABLED_CONSTANT = 'EPISCIENCES_ARXIV_DATACITE_FALLBACK';

    public const REQUEST_TIMEOUT = 10;
    public const CONNECT_TIMEOUT = 5;

    /** New (2602.07473) and old (math/0601001) arXiv identifiers, without version */
    private const ARXIV_ID_PATTERN = '~^(?:\d{4}\.\d{4,5}|[a-zA-Z][a-zA-Z.\-]*/\d{7})$~';
    /** arXiv category appended by DataCite to the subject: "Artificial Intelligence (cs.AI)" */
    private const CATEGORY_SUFFIX_PATTERN = '~\s*\([a-zA-Z\-]+(?:\.[a-zA-Z\-]+)?\)$~';
    private const VERSION_PATTERN = '~^v([1-9]\d*)$~';

    private static ?ClientInterface $httpClient = null;
    private static ?bool $enabled = null;

    /**
     * Replace the HTTP client (tests); null restores a default Guzzle client
     */
    public static function setHttpClient(?ClientInterface $httpClient): void
    {
        self::$httpClient = $httpClient;
    }

    /**
     * Force the switch (tests); null restores the configuration
     */
    public static function setEnabled(?bool $enabled): void
    {
        self::$enabled = $enabled;
    }

    public static function isEnabled(): bool
    {
        if (self::$enabled !== null) {
            return self::$enabled;
        }

        return !defined(self::ENABLED_CONSTANT) || (bool)constant(self::ENABLED_CONSTANT);
    }

    /**
     * @param string $arxivId arXiv identifier without version
     * @return array{record: string, versions: int[], latestVersion: int|null}
     * @throws Ccsd_Error ID_DOES_NOT_EXIST_CODE when DataCite does not know the DOI; another Ccsd_Error when
     * DataCite cannot be read
     */
    public static function fetch(string $arxivId): array
    {
        $attributes = self::fetchAttributes($arxivId);
        $versions = self::extractVersions($attributes);

        return [
            'record' => self::toRecord($attributes, $arxivId),
            'versions' => $versions,
            'latestVersion' => $versions === [] ? null : max($versions),
        ];
    }

    /**
     * Attributes of the DataCite record of the arXiv document
     * @throws Ccsd_Error
     */
    public static function fetchAttributes(string $arxivId): array
    {
        if (!preg_match(self::ARXIV_ID_PATTERN, $arxivId)) {
            throw new Ccsd_Error(Ccsd_Error::ID_DOES_NOT_EXIST_CODE);
        }

        $url = Episciences_Repositories_Common::DATACITE_DOI_API . Episciences_Repositories_Common::ARXIV_DOI_PREFIX . $arxivId;

        try {
            $response = self::getHttpClient()->request('GET', $url, [
                'timeout' => self::REQUEST_TIMEOUT,
                'connect_timeout' => self::CONNECT_TIMEOUT,
                'headers' => [
                    'Accept' => 'application/vnd.api+json',
                    'User-Agent' => defined('DOMAIN') ? DOMAIN : 'Episciences',
                ],
            ]);
        } catch (BadResponseException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                throw new Ccsd_Error(Ccsd_Error::ID_DOES_NOT_EXIST_CODE);
            }
            throw new Ccsd_Error('DataCite request failed', $e->getResponse()->getStatusCode());
        } catch (GuzzleException $e) {
            throw new Ccsd_Error('DataCite request failed', (int)$e->getCode());
        }

        try {
            $json = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new Ccsd_Error('Invalid DataCite response');
        }

        $attributes = $json['data']['attributes'] ?? null;

        if (!is_array($attributes)) {
            throw new Ccsd_Error('Invalid DataCite response');
        }

        return $attributes;
    }

    /**
     * Versions of the document, from the Submitted dates of the DataCite record
     * @return int[] sorted
     */
    public static function extractVersions(array $attributes): array
    {
        $versions = array_keys(self::submittedDatesByVersion($attributes));

        if ($versions === [] && isset($attributes['version']) && preg_match('~^[1-9]\d*$~', (string)$attributes['version'])) {
            $versions = [(int)$attributes['version']];
        }

        sort($versions);

        return $versions;
    }

    /**
     * oai_dc record shaped like the one of the arXiv OAI-PMH server
     * @throws Ccsd_Error when the DataCite record has no title
     */
    public static function toRecord(array $attributes, string $arxivId): string
    {
        return Episciences_Repositories_Common::toDublinCore(self::toOaiDcElements($attributes, $arxivId));
    }

    /**
     * @return array{headers: array, body: array}
     * @throws Ccsd_Error when the DataCite record has no title
     */
    public static function toOaiDcElements(array $attributes, string $arxivId): array
    {
        $title = self::extractTitle($attributes);

        if ($title === null) {
            throw new Ccsd_Error('Invalid DataCite response');
        }

        $headers = ['identifier' => 'oai:arXiv.org:' . $arxivId];
        $datestamp = substr((string)($attributes['updated'] ?? ''), 0, 10);
        if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $datestamp)) {
            $headers['datestamp'] = $datestamp;
        }

        $body = [
            'title' => [$title],
            'creator' => self::extractCreators($attributes),
            'subject' => self::extractSubjects($attributes),
            'description' => self::extractAbstract($attributes),
            'date' => self::extractDates($attributes),
            'type' => ['text'],
            'identifier' => ['https://arxiv.org/abs/' . $arxivId, 'http://arxiv.org/abs/' . $arxivId],
        ];

        return ['headers' => $headers, 'body' => array_filter($body)];
    }

    /**
     * @return array{value: string, language: string}|null
     */
    private static function extractTitle(array $attributes): ?array
    {
        foreach ($attributes['titles'] ?? [] as $title) {
            if (!is_array($title) || isset($title['titleType'])) {
                continue; // subtitles and translated titles
            }
            $value = trim((string)($title['title'] ?? ''));
            if ($value !== '') {
                return ['value' => $value, 'language' => (string)($title['lang'] ?? 'en')];
            }
        }

        return null;
    }

    /**
     * "Family, Given", as in the arXiv oai_dc record
     * @return string[]
     */
    private static function extractCreators(array $attributes): array
    {
        $creators = [];

        foreach ($attributes['creators'] ?? [] as $creator) {
            if (!is_array($creator)) {
                continue;
            }
            $family = trim((string)($creator['familyName'] ?? ''));
            $given = trim((string)($creator['givenName'] ?? ''));
            $name = $family !== '' && $given !== '' ? $family . ', ' . $given : trim((string)($creator['name'] ?? $family));
            if ($name !== '') {
                $creators[] = $name;
            }
        }

        return $creators;
    }

    /**
     * arXiv categories only, without the category code: "Artificial Intelligence"
     * @return string[]
     */
    private static function extractSubjects(array $attributes): array
    {
        $subjects = [];

        foreach ($attributes['subjects'] ?? [] as $subject) {
            if (!is_array($subject) || ($subject['subjectScheme'] ?? '') !== 'arXiv') {
                continue;
            }
            $value = trim((string)preg_replace(self::CATEGORY_SUFFIX_PATTERN, '', (string)($subject['subject'] ?? '')));
            if ($value !== '') {
                $subjects[$value] = $value;
            }
        }

        return array_values($subjects);
    }

    /**
     * The abstract only, as kept by Episciences_Repositories_ArXiv_Hooks for the arXiv record
     * @return array<array{value: string, language: string}>
     */
    private static function extractAbstract(array $attributes): array
    {
        foreach ($attributes['descriptions'] ?? [] as $description) {
            if (!is_array($description) || ($description['descriptionType'] ?? '') !== 'Abstract') {
                continue;
            }
            $value = trim((string)($description['description'] ?? ''));
            if ($value !== '') {
                return [['value' => $value, 'language' => (string)($description['lang'] ?? 'en')]];
            }
        }

        return [];
    }

    /**
     * Submission dates of the first and of the latest versions (Y-m-d), as in the arXiv oai_dc record
     * @return string[]
     */
    private static function extractDates(array $attributes): array
    {
        $dates = self::submittedDatesByVersion($attributes);

        if ($dates === []) {
            $year = (string)($attributes['publicationYear'] ?? '');
            return preg_match('~^\d{4}$~', $year) ? [$year] : [];
        }

        ksort($dates);

        return array_values(array_unique([reset($dates), end($dates)]));
    }

    /**
     * @return array<int, string> version => submission date (Y-m-d)
     */
    private static function submittedDatesByVersion(array $attributes): array
    {
        $dates = [];

        foreach ($attributes['dates'] ?? [] as $date) {
            if (
                !is_array($date) ||
                ($date['dateType'] ?? '') !== 'Submitted' ||
                !preg_match(self::VERSION_PATTERN, (string)($date['dateInformation'] ?? ''), $matches)
            ) {
                continue;
            }
            $dates[(int)$matches[1]] = substr((string)($date['date'] ?? ''), 0, 10);
        }

        return $dates;
    }

    private static function getHttpClient(): ClientInterface
    {
        return self::$httpClient ?? new Client();
    }
}
