<?php

/**
 * Service class handling the retrieval of the versions of a paper that are
 * available in its source repository.
 *
 * Extracted from AdministratepaperController to follow the separation of
 * concerns principle. Each supported repository has its own private method;
 * the public entry point dispatches to the right one depending on the paper's
 * repository.
 */
class Episciences_Paper_RepositoryVersionsService
{
    /** @var callable(string, array): mixed */
    private $apiCaller;

    /** @var callable(string, string): array */
    private $oaiFetcher;

    /** @var callable(string, array): array */
    private $hookCaller;

    /** @var callable(string): string */
    private $dateTimePattern;

    /**
     * The collaborators are injectable for testability, but default to the real
     * static implementations so the controller can keep instantiating the service
     * with no arguments.
     */
    public function __construct(
        ?callable $apiCaller = null,
        ?callable $oaiFetcher = null,
        ?callable $hookCaller = null,
        ?callable $dateTimePattern = null,
    ) {
        $this->apiCaller = $apiCaller ?? [Episciences_Tools::class, 'callApi'];
        $this->oaiFetcher = $oaiFetcher ?? static function (string $baseUrl, string $identifier): array {
            $oai = new Episciences_Oai_Client($baseUrl, 'xml');
            return Episciences_Submit::extractVersionsFromArXivRaw($oai->getArXivRawRecord($identifier));
        };
        $this->hookCaller = $hookCaller ?? [Episciences_Repositories::class, 'callHook'];
        $this->dateTimePattern = $dateTimePattern ?? [Episciences_Repositories_Common::class, 'getDateTimePattern'];
    }

    public function getAvailableVersions(Episciences_Paper $paper): array
    {
        $repoId = $paper->getRepoid();
        $api = Episciences_Repositories::getApiUrl($repoId);

        if ('' !== $api) {
            $versions = $this->getVersionsFromApi($paper, $repoId, $api);

            // Zenodo returns its own ordering; all other sources are sorted
            if ((int)Episciences_Repositories::ZENODO_REPO_ID === $repoId) {
                return $versions;
            }

            return $this->sortVersions($versions);
        }

        return $this->sortVersions($this->getVersionsFromOaiOrHook($paper, $repoId));
    }

    /**
     * Delegate version retrieval to the right API client for the repository.
     */
    private function getVersionsFromApi(Episciences_Paper $paper, int $repoId, string $api): array
    {
        if (Episciences_Repositories::isFromHalRepository($repoId)) {
            return $this->getVersionsFromHal($paper, $api);
        }

        if ((int)Episciences_Repositories::ZENODO_REPO_ID === $repoId) {
            return $this->getVersionsFromZenodo($paper);
        }

        if (
            $repoId === (int)Episciences_Repositories::BIO_RXIV_ID ||
            $repoId === (int)Episciences_Repositories::MED_RXIV_ID
        ) {
            return $this->getVersionsFromBioMedRxiv($paper, $api);
        }

        if (Episciences_Repositories::isDataverse($repoId)) {
            return $this->getVersionsFromDataverse($paper, $api);
        }

        return [];
    }

    private function getVersionsFromHal(Episciences_Paper $paper, string $api): array
    {
        $url = $api . '/search/?indent=true&q=' . $paper->getIdentifier() . '&fl=label_xml';

        try {
            $result = ($this->apiCaller)($url);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            trigger_error($e->getMessage());
            return [];
        }

        if (!is_array($result)) {
            return [];
        }

        $docs = $result['response']['docs'] ?? [];

        if (empty($docs)) {
            return [];
        }

        $xml = $docs[array_key_first($docs)]['label_xml'] ?? '';

        if ('' === $xml) {
            return [];
        }

        return $this->extractTeiVersions($xml);
    }

    /**
     * Extract the edition (version) numbers from a HAL TEI record.
     *
     * @return int[]
     */
    private function extractTeiVersions(string $teiXml): array
    {
        $dom = new DOMDocument();
        $loaded = @$dom->loadXML($teiXml);

        if (!$loaded) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('tei', 'http://www.tei-c.org/ns/1.0');

        $versions = [];

        foreach ($xpath->query('//tei:edition') as $node) {
            $editionNumber = $node->getAttribute('n');
            preg_match('/v?(\d+)/', $editionNumber, $matches);

            if (!empty($matches[1])) {
                $versions[] = (int)$matches[1];
            }
        }

        return array_values(array_unique($versions));
    }

    private function getVersionsFromZenodo(Episciences_Paper $paper): array
    {
        // last identifier known to the journal
        $latestIdentifier = $paper->getIdentifier();
        $url = sprintf('https://zenodo.org/api/records/%s/versions', $latestIdentifier);

        $options = [
            'headers' => ['Accept' => 'application/json', 'Content-type' => 'application/json'],
            'query' => ['size' => 25],
            'timeout' => 10,
        ];

        try {
            $result = ($this->apiCaller)($url, $options)['hits']['hits'] ?? [];
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return [];
        }

        $versions = [];
        $maxIndex = count($result);

        foreach ($result as $index => $hit) {
            if (!isset($hit['id'])) {
                continue;
            }

            $currentIdentifier = $hit['id'];

            // We only keep IDs that are higher than the last known identifier
            if ($currentIdentifier > $latestIdentifier) {
                $versions[$maxIndex - $index] = $currentIdentifier;
            }
        }

        return $versions;
    }

    private function getVersionsFromBioMedRxiv(Episciences_Paper $paper, string $api): array
    {
        $url = $api . $paper->getIdentifier() . DIRECTORY_SEPARATOR . 'na' . DIRECTORY_SEPARATOR . 'json';

        try {
            $response = ($this->apiCaller)($url);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            trigger_error($e->getMessage());
            return [];
        }
        $messages = (is_array($response)) ? ($response['messages'][array_key_first($response['messages'])] ?? null) : null;
        $collection = (is_array($response)) ? ($response['collection'] ?? []) : [];

        $versions = [];

        if (
            isset($messages['status']) &&
            $messages['status'] === Episciences_Repositories_BioMedRxiv::SUCCESS_CODE
        ) {
            foreach ($collection as $index => $values) {
                $versions[$index + 1] = $values['version'];
            }
        }

        return $versions;
    }

    private function getVersionsFromDataverse(Episciences_Paper $paper, string $api): array
    {
        $url = $api . 'datasets/:persistentId/?persistentId=' . $paper->getIdentifier();

        try {
            $response = ($this->apiCaller)($url);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            trigger_error($e->getMessage());
            return [];
        }
        $versions = [];

        if (
            isset($response['status']) &&
            mb_strtolower($response['status']) === Episciences_Repositories_Dataverse_Hooks::SUCCESS_CODE
        ) {
            $latestVersion = $response['data']['latestVersion']['versionNumber'] ?? 1;
            $versionMinorNumber = $response['data']['latestVersion']['versionMinorNumber'] ?? 0;

            $version = (float)($latestVersion . '.' . $versionMinorNumber);

            while ($version > 0) {
                $versions[] = $version . '.' . $versionMinorNumber;
                $version -= 1.0;
            }
        }

        return $versions;
    }

    private function getVersionsFromOaiOrHook(Episciences_Paper $paper, int $repoId): array
    {
        $identifier = Episciences_Repositories::getIdentifier($repoId, $paper->getIdentifier());
        $baseUrl = Episciences_Repositories::getBaseUrl($repoId);

        if ((int)Episciences_Repositories::ARXIV_REPO_ID === $repoId) {
            return $this->fetchArXivVersions($baseUrl, $identifier);
        }

        return $this->getVersionsFromHook($paper, $repoId);
    }

    private function fetchArXivVersions(string $baseUrl, string $identifier): array
    {
        try {
            return ($this->oaiFetcher)($baseUrl, $identifier);
        } catch (Exception $e) {
            trigger_error($e->getMessage());
            return [];
        }
    }

    private function getVersionsFromHook(Episciences_Paper $paper, int $repoId): array
    {
        $hookApiRecord = ($this->hookCaller)('hookApiRecords', [
            'identifier' => $paper->getConcept_identifier(),
            'repoId' => $paper->getRepoid()
        ]);

        if ((int)Episciences_Repositories::CRYPTOLOGY_EPRINT !== $repoId) {
            return [];
        }

        $latestVersionDateTime = $hookApiRecord[Episciences_Repositories_CryptologyePrint_Hooks::UPDATE_DATETIME] ?? null;
        $previousPaperVersionDateTime = ($this->dateTimePattern)($paper->getIdentifier());
        $latestIdentifier = sprintf('%s/%s', $paper->getConcept_identifier(), $latestVersionDateTime);

        // This behavior is intentional because a submission without a specific version is the most recent version.
        // If the paper does not yet have a datetime in its identifier, getDateTimePattern() returns ''.
        if ($latestVersionDateTime > $previousPaperVersionDateTime) {
            return [$latestIdentifier];
        }

        return [];
    }

    private function sortVersions(array $versions): array
    {
        arsort($versions);
        return $versions;
    }
}
