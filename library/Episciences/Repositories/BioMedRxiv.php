<?php


use Episciences\Repositories\CommonHooksInterface;
use Episciences\Repositories\InputSanitizerInterface;
use Episciences\Tools\Http\Exceptions\FileGetContentsException;
use GuzzleHttp\Exception\GuzzleException;

class Episciences_Repositories_BioMedRxiv implements CommonHooksInterface, InputSanitizerInterface
{
    public const SUCCESS_CODE = 'ok';
    public const AVAILABLE_SERVERS = [
        Episciences_Repositories::BIO_RXIV_ID => 'biorxiv',
        Episciences_Repositories::MED_RXIV_ID => 'medrxiv',
    ];
    public const RESPONSE_FORMAT = 'json';

    public const DOI_PREFIX = '10.1101';
    public const INSTITUTIONS = 'institutions';
    public const LICENSE = 'license';
    public const KEYWORDS = 'kwd';
    public const COLLECTION = 'collection';


    private string $doiPrefix = self::DOI_PREFIX;

    protected ?string $server = null;


    /**
     * @return string|null
     */
    public function getServer(): ?string
    {
        return $this->server;
    }

    /**
     * @param string|null $server
     */
    protected function setServer(?string $server): void
    {
        $this->server = $server;
    }

    /**
     * @param array $hookParams
     * @return array
     * @throws Exception
     */
    public static function hookApiRecords(array $hookParams): array
    {

        $options = [];


        if (!isset($hookParams['identifier'])) {
            return [];
        }

        $url = Episciences_Repositories::getRepositories()[$hookParams['repoId']][Episciences_Repositories::REPO_API_URL];

        $url .= $hookParams['identifier'];
        $url .= DIRECTORY_SEPARATOR;
        $url .= 'na';
        $url .= DIRECTORY_SEPARATOR;
        $url .= self::RESPONSE_FORMAT;


        $version = (int)$hookParams['version'];

        try {
            $response = Episciences_Tools::callApi($url, $options);

            if (!is_array($response) || empty($response[self::COLLECTION] ?? null)) {
                throw new Ccsd_Error(Ccsd_Error::ID_DOES_NOT_EXIST_CODE);
            }
        } catch (GuzzleException $e) {
            throw new Ccsd_Error($e->getMessage());
        }

        $messages = $response['messages'][array_key_first($response['messages'] ?? [])] ?? [];
        $collection = $response[self::COLLECTION]; // all versions

        if (
            (
                isset($messages['status']) &&
                $messages['status'] !== self::SUCCESS_CODE
            ) ||
            empty($requestedVersion = self::getRequestedVersionFromCollection($collection, $version))
        ) {

            if (
                isset($messages['status']) && $messages['status'] !== self::SUCCESS_CODE) {
                $result = $messages['status'];
            } else {
                $result = "Empty record";
            }

            return ['error' => $result, 'record' => null];

        }

        $result = [
            'record' => self::toDublinCore($requestedVersion)
        ];

        if (isset($requestedVersion[Episciences_Repositories_Common::ENRICHMENT])) {
            unset($requestedVersion[Episciences_Repositories_Common::ENRICHMENT][self::KEYWORDS], $requestedVersion[Episciences_Repositories_Common::ENRICHMENT][Episciences_Repositories_Common::ENRICHMENT]); // data already added to the DC @see self::toDublinCore
            $result[Episciences_Repositories_Common::ENRICHMENT] = $requestedVersion[Episciences_Repositories_Common::ENRICHMENT];
        }


        return $result;

    }

    public static function hookCleanIdentifiers(array $hookParams): array
    {

        if (!isset($hookParams['id'])) {
            return [];
        }

        return ['identifier' => trim($hookParams['id'])];
    }

    public static function hookVersion(array $hookParams): array
    {
        return [];
    }

    /**
     * @return string
     */
    public function getDoiPrefix(): string
    {
        return $this->doiPrefix;
    }

    /**
     * @param string $doiPrefix
     */
    public function setDoiPrefix(string $doiPrefix): void
    {
        $this->doiPrefix = $doiPrefix;
    }

    public static function hookIsRequiredVersion(): array
    {
        return ['result' => Episciences_Repositories_Common::isRequiredVersion()];
    }

    /**
     * @param array $currentVersion
     * @return string
     */
    private static function toDublinCore(array $currentVersion): string
    {

        $urlIdentifier = '';

        if (isset($currentVersion['server'], $currentVersion['doi'])) {

            $urlIdentifier = 'https://www.';
            $urlIdentifier .= $currentVersion['server'];
            $urlIdentifier .= '.org/content/';
            $urlIdentifier .= $currentVersion['doi'];
        }

        if ($urlIdentifier === '') {
            $message = 'toDublinCore Error @ Episciences_Repositories_BioMedRxiv::hookApiRecords: ';
            $message .= 'Undefined identifier';
            trigger_error($message);
        }

        $strAuthors = $currentVersion['authors'] ?? '';
        $authors = explode(';', $strAuthors);

        $subject = $currentVersion['category'] ?? [];

        if (
            isset($currentVersion[Episciences_Repositories_Common::ENRICHMENT][self::KEYWORDS]) &&
            !empty(($currentVersion[Episciences_Repositories_Common::ENRICHMENT][self::KEYWORDS]))
        ) {
            $subject = $currentVersion[Episciences_Repositories_Common::ENRICHMENT][self::KEYWORDS];
        }

        $xmlElements = [
            'title' => $currentVersion['title'] ?? '',
            'creator' => $authors,
            'subject' => $subject,
            'description' => $currentVersion['abstract'] ?? '',
            'date' => $currentVersion['date'] ?? '',
            'type' => $currentVersion['type'] ?? '',
            'identifier' => [$currentVersion['doi'] ?? '', $urlIdentifier],
            'source' => [$urlIdentifier],
        ];

        if (isset($currentVersion[Episciences_Repositories_Common::ENRICHMENT][self::LICENSE]) && $currentVersion[Episciences_Repositories_Common::ENRICHMENT][self::LICENSE] !== '') {
            $xmlElements['rights'][] = $currentVersion[Episciences_Repositories_Common::ENRICHMENT][self::LICENSE];
        }

        $license = (
            isset($currentVersion[self::LICENSE]) &&
            $currentVersion[self::LICENSE] !== ''
        ) ? strtolower($currentVersion[self::LICENSE]) : 'cc_no';


        if ($license !== 'cc_no' && str_contains($license, 'cc_')) { //todo Should we block this type of submission [$license !== 'cc_no]?
            $xmlElements['rights'][] = 'info:eu-repo/semantics/openAccess';
        }

        $elements = [
            'headers' => [
                'identifier' => $urlIdentifier,
                'datestamp' => $currentVersion['date'] ?? '',
                'setSpec' => [$currentVersion['category'] ?? '']

            ],
            'body' => $xmlElements
        ];

        return Episciences_Repositories_Common::toDublinCore($elements);

    }

    /**
     * @param array $collection
     * @param int $requestedVersion
     * @return array
     */
    private static function getRequestedVersionFromCollection(
        array $collection,
        int   $requestedVersion = 1
    ): array
    {

        foreach ($collection as $values) {

            if (isset($values['version']) && $requestedVersion === (int)$values['version']) {
                self::enrichmentFromJatsXmlProcess($values);
                return $values;
            }

        }

        return [];

    }

    private static function enrichmentFromJatsXmlProcess(array &$values = []): void
    {

        if (!isset($values['jatsxml'])) {
            return;
        }

        try {
            $content = Episciences_Tools::safeFileGetContents($values['jatsxml']);
        } catch (FileGetContentsException $e) {
            Episciences_View_Helper_Log::log($e->getMessage());
            return;
        }


        libxml_use_internal_errors(true);
        /** @var SimpleXMLElement $simpleXlmDoc */
        $simpleXlmDoc = simplexml_load_string($content);
        libxml_clear_errors();

        if (!$simpleXlmDoc) {
            return;
        }

        $contributors = [];
        $institutions = [];
        $license = '';
        $kwd = [];
        $type = [];

        try {
            $json = json_encode($simpleXlmDoc, JSON_THROW_ON_ERROR);
            $doc = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            trigger_error($e->getMessage());
            return;
        }

        $articleMeta = $doc['front']['article-meta'] ?? [];

        self::articleMetaProcess($articleMeta, $license, $contributors, $institutions, $kwd);
        $citations = self::referencesProcess($simpleXlmDoc);

        if (isset($articleMeta['article-categories']['subj-group'])) {
            self::typeProcess($articleMeta['article-categories']['subj-group'], $type);
        }

        if (
            !empty($contributors) ||
            !empty($license) ||
            !empty($citations) ||
            !empty($type)
        ) {

            $values[Episciences_Repositories_Common::ENRICHMENT] = [
                Episciences_Repositories_Common::CONTRIB_ENRICHMENT => $contributors,
                Episciences_Repositories_Common::LICENSE_ENRICHMENT => $license,
                Episciences_Repositories_Common::REFERENCES_EPI_CITATIONS => $citations,
                self::KEYWORDS => $kwd,
                Episciences_Repositories_Common::RESOURCE_TYPE_ENRICHMENT => $type
            ];
        }
    }


    /**
     * @param array $articleMeta
     * @param string $strLicense
     * @param array $contributors
     * @param array $institutions
     * @param array $keyWords
     * @return void
     */
    private static function articleMetaProcess(
        array  $articleMeta,
        string &$strLicense = '',
        array  &$contributors = [],
        array  &$institutions = [],
        array  &$keyWords = []
    ): void
    {

        foreach ($articleMeta as $ak => $aVals) {

            if ($ak !== 'contrib-group' && $ak !== 'permissions' && $ak !== 'kwd-group') {
                continue;
            }

            if ($ak === 'contrib-group') {

                $aff = $aVals['aff'] ?? [];

                foreach ($aff as $affVals) {

                    if (!is_array($affVals)) { // bioRxiv
                        $institutions[1] = ['name' => implode(', ', $aff)];
                        break;
                    }

                    // medRxiv
                    $name = $affVals['institution'] ?? $aff['institution'] ?? '';
                    $label = $affVals['label'] ?? null;
                    if (isset($affVals['country']) || isset($aff['country'])) {
                        $name .= ', ';
                        if (isset($affVals['country'])) {
                            $name .= $affVals['country'];
                        } elseif ($aff['country']) {
                            $name .= $aff['country'];
                        }
                    }
                    $institutions[$label] = ['name' => $name];
                }

                $contrib = $aVals['contrib'] ?? [];

                foreach ($contrib as $cVals) {

                    $xref = $cVals['xref'] ?? [];

                    if (!is_array($xref)) {
                        $xref = [$xref];
                    }

                    $contribLabelAffiliation = array_filter($xref, static function ($value) {
                        return is_numeric($value);
                    }); // affiliation labels

                    $orcid = isset($cVals['contrib-id']) ? preg_replace('#^https?://orcid.org/#', '', $cVals['contrib-id']) : '';

                    $tmp = [
                        'degrees' => $cVals['degrees'] ?? '',
                        'fullname' => $cVals['name']['given-names'] . ' ' . $cVals['name']['surname'],
                        'family' => $cVals['name']['surname'],
                        'given' => $cVals['name']['given-names'],
                        'email' => $cVals['email'] ?? '',
                    ];

                    if ($orcid !== '') {
                        $tmp['orcid'] = Episciences_Paper_AuthorsManager::normalizeOrcid($orcid);
                    }

                    foreach ($contribLabelAffiliation as $label) {
                        if (isset($institutions[$label])) {
                            $tmp['affiliation'][] = $institutions[$label];
                        }

                    }

                    $contributors[] = $tmp;

                }

            } elseif ($ak === 'permissions') {
                $strLicense = isset($aVals[self::LICENSE]['p']) ?
                    self::extractLicenseFromString($aVals[self::LICENSE]['p']) :
                    '';
            } elseif ($ak === 'kwd-group') {
                $keyWords = $aVals[self::KEYWORDS] ?? [];
                self::cleanRepairKeywords($keyWords);
            }

        } // end foreach articleMeta

    }


    /**
     * References of the JATS ref-list, as displayed by bioRxiv / medRxiv.
     * The text of each citation is already formatted by the repository (hw:use-reference-as-is="yes"):
     * it is kept as is rather than rebuilt from its tagged parts.
     * @return array<int, array{raw_reference: string, doi?: string}>
     */
    private static function referencesProcess(SimpleXMLElement $doc): array
    {
        $citations = [];

        foreach ($doc->xpath('/article/back/ref-list/ref/*[self::citation or self::mixed-citation or self::element-citation]') ?: [] as $citation) {

            $rawReference = trim((string)preg_replace('/\s+/u', ' ', (string)dom_import_simplexml($citation)->textContent));

            if ($rawReference === '') {
                continue;
            }

            $reference = ['raw_reference' => $rawReference];

            $doi = trim((string)($citation->xpath('pub-id[@pub-id-type="doi"]')[0] ?? ''));

            if ($doi !== '') {
                $reference['doi'] = $doi;
            }

            $citations[] = $reference;
        }

        return $citations;
    }

    private static function extractLicenseFromString(string $string): string
    {
        $license = '';

        $parts = explode(', ', $string);
        if (!isset($parts[1])) {
            return '[CC_NO] ' . $string;
        }
        $explodedStr = explode(' ', $parts[1]);


        if (strtolower($explodedStr[0]) === 'cc') {

            $license = 'https://creativecommons.org/licenses/';

            if (isset($explodedStr[1])) {
                $license .= (strtolower($explodedStr[1]) . '/');
            }

            if (isset($explodedStr[2])) {

                $license .= $explodedStr[2];

            }


        } else {
            $license .= '[CC_NO] ' . $string;
        }

        return $license;
    }

    private static function cleanRepairKeywords(array &$keywords = []): void
    {
        foreach ($keywords as $index => $keyword) {
            if (is_array($keyword)) { // exp. ['italic => "Cebus"] bioRxiv => 10.1101/011908v1
                $keywords[$index] = array_shift($keyword);
            }
        }
    }

    /**
     * @param array $rawTypes
     * @param array $type
     * @return void
     */
    private static function typeProcess(array $rawTypes = [], array &$type = []): void
    {
        foreach ($rawTypes as $values) {
            if (is_array($values)) {
                foreach ($values as $k => $value) {

                    if ($k !== 'subject' && $k !== 'subj-group-type') {
                        continue;
                    }

                    $type[] = $value;
                }
            } else {
                $type[] = $values;
            }
        }

    }

    public static function hookIsIdentifierCommonToAllVersions(): array
    {
        return ['result' => false];
    }
}