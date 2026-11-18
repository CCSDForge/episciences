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
    private const ARTICLE_META_PATH = '/article/front/article-meta';
    /** subj-group types holding the document type, in order of precedence */
    private const TYPE_SUBJECT_GROUPS = ['author-type', 'heading'];


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

        $contributors = self::contributorsProcess($simpleXlmDoc);
        $license = self::licenseProcess($simpleXlmDoc);
        $kwd = self::keywordsProcess($simpleXlmDoc);
        $citations = self::referencesProcess($simpleXlmDoc);
        $type = self::typeProcess($simpleXlmDoc);

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
     * Authors of the JATS contrib-group, read from the XML document itself.
     * Once converted to an array (json_encode()), a single <contrib>, <aff> or <kwd> is not decoded as a list:
     * a preprint with a single author made the submission fail.
     * @return array<int, array<string, mixed>>
     */
    private static function contributorsProcess(SimpleXMLElement $doc): array
    {
        $affiliations = self::affiliationsProcess($doc);
        $contributors = [];

        foreach ($doc->xpath(self::ARTICLE_META_PATH . '/contrib-group/contrib') ?: [] as $contrib) {

            $contributor = self::contributorProcess($contrib);

            if ($contributor === null) {
                continue;
            }

            foreach (self::affiliationIds($contrib) as $affiliationId) {
                if (isset($affiliations[$affiliationId])) {
                    $contributor['affiliation'][] = $affiliations[$affiliationId];
                }
            }

            $contributors[] = $contributor;
        }

        return $contributors;
    }

    /**
     * @return array<string, mixed>|null null when the contributor has no name
     */
    private static function contributorProcess(SimpleXMLElement $contrib): ?array
    {
        $family = self::nodeText($contrib, 'name/surname');
        $given = self::nodeText($contrib, 'name/given-names');
        $fullname = trim($given . ' ' . $family);

        if ($fullname === '') {
            // Group author (<collab>): stored like an author name without given names
            $fullname = self::nodeText($contrib, 'collab');
            $family = $fullname;
        }

        if ($fullname === '') {
            return null;
        }

        $contributor = [
            'degrees' => self::nodeText($contrib, 'degrees'),
            'fullname' => $fullname,
            'family' => $family,
            'given' => $given,
            'email' => self::nodeText($contrib, 'email'),
        ];

        $orcid = (string)preg_replace(
            '#^https?://orcid.org/#',
            '',
            self::nodeText($contrib, 'contrib-id[@contrib-id-type="orcid" or not(@contrib-id-type)]')
        );

        if ($orcid !== '') {
            $contributor['orcid'] = Episciences_Paper_AuthorsManager::normalizeOrcid($orcid);
        }

        return $contributor;
    }

    /**
     * Ids of the affiliations of a contributor (an xref "rid" may hold several ids).
     * @return string[]
     */
    private static function affiliationIds(SimpleXMLElement $contrib): array
    {
        $ids = [];

        foreach ($contrib->xpath('xref[@ref-type="aff"]/@rid') ?: [] as $rid) {
            $ids[] = preg_split('/\s+/', trim((string)$rid), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        return array_merge(...$ids);
    }

    /**
     * @return array<string, array{name: string}> affiliations by id
     */
    private static function affiliationsProcess(SimpleXMLElement $doc): array
    {
        $affiliations = [];

        foreach ($doc->xpath(self::ARTICLE_META_PATH . '//aff[@id]') ?: [] as $aff) {

            $name = self::affiliationName($aff);

            if ($name !== '') {
                $affiliations[(string)$aff['id']] = ['name' => $name];
            }
        }

        return $affiliations;
    }

    /**
     * Institution(s) and country of a tagged affiliation, or its whole text (without its label).
     */
    private static function affiliationName(SimpleXMLElement $aff): string
    {
        $institutions = array_filter(array_map(
            static fn(SimpleXMLElement $institution): string => self::normalizeText($institution),
            $aff->xpath('institution') ?: []
        ), static fn(string $name): bool => $name !== '');

        if ($institutions === []) {
            $text = '';

            foreach (dom_import_simplexml($aff)->childNodes as $child) {
                if ($child->nodeName !== 'label') {
                    $text .= $child->textContent;
                }
            }

            return trim(self::normalizeSpaces($text), ' ,');
        }

        // An affiliation may name the same institution twice (one per site)
        $name = implode(', ', array_unique($institutions));
        $country = self::nodeText($aff, 'country');

        return $country === '' ? $name : $name . ', ' . $country;
    }

    /**
     * Keywords of all the kwd-groups; their inline markup (<italic>...) is kept as text.
     * @return string[]
     */
    private static function keywordsProcess(SimpleXMLElement $doc): array
    {
        $keywords = array_map(
            static fn(SimpleXMLElement $kwd): string => self::normalizeText($kwd),
            $doc->xpath(self::ARTICLE_META_PATH . '/kwd-group/kwd') ?: []
        );

        return array_values(array_unique(array_filter($keywords, static fn(string $kwd): bool => $kwd !== '')));
    }

    private static function licenseProcess(SimpleXMLElement $doc): string
    {
        $statement = self::nodeText($doc, self::ARTICLE_META_PATH . '/permissions/license/p');

        return $statement === '' ? '' : self::extractLicenseFromString($statement);
    }

    /**
     * Document types: subjects of the "author-type" (e.g. "Regular Article") and "heading" (e.g. "New Results")
     * groups. The "hwp-journal-coll" group holds the subject area, not a type: it is ignored.
     * @return string[]
     */
    private static function typeProcess(SimpleXMLElement $doc): array
    {
        $types = [];

        foreach (self::TYPE_SUBJECT_GROUPS as $groupType) {

            $path = sprintf('%s/article-categories/subj-group[@subj-group-type="%s"]/subject', self::ARTICLE_META_PATH, $groupType);

            foreach ($doc->xpath($path) ?: [] as $subject) {
                $type = self::normalizeText($subject);

                if ($type !== '') {
                    $types[] = $type;
                }
            }
        }

        return $types;
    }

    /**
     * Text of the first node matching $path, inline markup included, whitespace normalized ('' if none).
     */
    private static function nodeText(SimpleXMLElement $context, string $path): string
    {
        $nodes = $context->xpath($path) ?: [];

        return isset($nodes[0]) ? self::normalizeText($nodes[0]) : '';
    }

    private static function normalizeText(SimpleXMLElement $node): string
    {
        return self::normalizeSpaces((string)dom_import_simplexml($node)->textContent);
    }

    private static function normalizeSpaces(string $text): string
    {
        return trim((string)preg_replace('/\s+/u', ' ', $text));
    }


    /**
     * References of the JATS ref-list, as displayed by bioRxiv / medRxiv.
     * The text of each citation is already formatted by the repository (hw:use-reference-as-is="yes"):
     * it is kept as is rather than rebuilt from its tagged parts.
     * <element-citation> holds tagged fields without punctuation: its text would run the fields together.
     * It is not used by bioRxiv / medRxiv and is ignored.
     * @return array<int, array{raw_reference: string, doi?: string}>
     */
    private static function referencesProcess(SimpleXMLElement $doc): array
    {
        $citations = [];

        foreach ($doc->xpath('/article/back/ref-list/ref/*[self::citation or self::mixed-citation]') ?: [] as $citation) {

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

    public static function hookIsIdentifierCommonToAllVersions(): array
    {
        return ['result' => false];
    }
}