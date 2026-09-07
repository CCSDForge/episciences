<?php


use Episciences\Tools\Translations;
use Symfony\Component\Intl\Languages;

class Episciences_Repositories_Common
{
    public const URL_HDL = 'https://hdl.handle.net/';
    public const XML_OPENAIRE_TITLE_NODE = 'datacite:titles/datacite:title';
    public const XML_OPENAIRE_SUBJECT_NODE = 'datacite:subjects/datacite:subject';
    public const XML_OPENAIRE_DESCRIPTION_NODE = 'dc:description';
    public const XML_OPENAIRE_CREATOR_NODE = 'datacite:creators/datacite:creator';
    public const XML_OPENAIRE_CONTRIBUTOR_NODE = 'datacite:contributors/datacite:contributor';
    public const XML_OPENAIRE_RESSOURCE_TYPE_NODE = 'oaire:resourceType';
    public const XML_OPENAIRE_RIGHTS_NODE = 'datacite:rights';
    public const ENRICHMENT = 'enrichment';
    public const CITATIONS = 'citations'; // documents citing
    public const REFERENCES_EPI_CITATIONS = 'references'; // documents cited
    public const PROJECTS = 'projects';
    public const CONTRIB_ENRICHMENT = 'authors';
    public const LICENSE_ENRICHMENT = 'license';
    public const RESOURCE_TYPE_ENRICHMENT = 'type';
    public const RELATED_IDENTIFIERS = 'relatedIdentifiers';
    public const TO_COMPILE_OAI_DC = 'toCompileOaiDc';
    public const FILES = 'files';

    public const AVAILABLE_ENRICHMENT = [
        self::CONTRIB_ENRICHMENT,
        self::CITATIONS,
        self::PROJECTS,
        self::RESOURCE_TYPE_ENRICHMENT,
        self::RELATED_IDENTIFIERS
    ];
    public const META_IDENTIFIER = 'identifier';
    public const XML_LANG_ATTR = 'xml:lang';
    public const META_DESCRIPTION = 'description';
    public const CONCEPT_IDENTIFIER_KEY = 'conceptIdentifier';


    public static function isOpenAccessRight(array $hookParams): array
    {

        $isOpenAccessRight = false;
        $pattern = '<dc:rights>info:eu-repo/semantics/openAccess</dc:rights>';

        if (array_key_exists('record', $hookParams)) {
            $isOpenAccessRight = !empty(Episciences_Tools::extractPattern('~' . $pattern . '~', $hookParams['record']));
        }


        return ['isOpenAccessRight' => $isOpenAccessRight];

    }

    public static function cleanAndPrepare(array $params = []): array
    {

        if (!isset($params['id'])) {
            return [];
        }

        $identifier = trim($params['id']);

        if (
            isset($params['repoId']) &&
            Episciences_Repositories::isDataverse($params['repoId']) &&
            !str_starts_with($identifier, Episciences_Repositories_Dataverse_Hooks::IDENTIFIER_PREFIX)) {

            $identifier = Episciences_Repositories_Dataverse_Hooks::IDENTIFIER_PREFIX . $identifier;
        }

        return ['identifier' => $identifier];
    }


    public static function isRequiredVersion(bool $isRequired = true): bool
    {
        return $isRequired;
    }


    public static function toDublinCore(array $elements = ['headers' => [], 'body' => []]): string
    {

        $result = '';

        $xml = new Ccsd_DOMDocument('1.0', 'utf-8');

        $xml->formatOutput = false;
        $xml->substituteEntities = true;
        $xml->preserveWhiteSpace = false;


        try {

            $record = $xml->createElement('record');

            if (isset($elements['headers']) && is_array($elements['headers'])) {
                $header = $xml->createElement('header');
                $record->appendChild(self::addXmlElements($xml, $header, $elements['headers']));
            }

            $dc = $xml->createElement('oai_dc:dc');
            $dc->setAttributeNS(
                'http://www.w3.org/2000/xmlns/',
                'xmlns:oai_dc',
                'http://www.openarchives.org/OAI/2.0/oai_dc/'
            );
            $dc->setAttributeNS('http://www.w3.org/2000/xmlns/',
                'xmlns:dc',
                'http://purl.org/dc/elements/1.1/'
            );

            $metadata = $xml->createElement('metadata');

            if (isset($elements['body']) && is_array($elements['body'])) {
                $metadata->appendChild(self::addXmlElements($xml, $dc, $elements['body'], 'dc:'));
            }

            $record->appendChild($metadata);

            $result = $xml->saveXML($record);


        } catch (DOMException $e) {
            trigger_error($e->getMessage());
        }

        return $result;

    }


    /**
     * @throws DOMException
     */
    private static function addXmlElements(
        DOMDocument $xml,
        DOMElement  $root,
        array       $xmlElements,
        string      $prefix = ''
    ): \DOMElement
    {
        $defaultLanguage = 'en';

        foreach ($xmlElements as $key => $values) {

            if (is_array($values)) {

                foreach ($values as $value) {

                    if (is_array($value)) {
                        $currentValue = $value['value'] ?? '';
                        $currentLanguage = $value['language'] ?? $defaultLanguage;
                    } else {
                        $currentValue = $value;
                        $currentLanguage = $defaultLanguage;

                    }

                    $xmlElement = $xml->createElement(
                        $prefix . $key,
                        ($key === 'identifier' && Episciences_Tools::isDoi($currentValue)) ? 'info:doi:' . $currentValue : $currentValue
                    );

                    if (($key === 'description') || ($key === 'title')) {
                        $xmlElement->setAttribute('xml:lang', lcfirst(mb_substr($currentLanguage, 0, 2)));
                    }

                    $root->appendChild($xmlElement);

                }

            } else {

                $xmlElement = $xml->createElement($prefix . $key, $values);

                if (($key === 'description') || ($key === 'title')) {
                    $xmlElement->setAttribute('xml:lang', $defaultLanguage);
                }


                $root->appendChild($xmlElement);

            }

        }

        return $root;

    }

    public static function checkAndCleanRecord(string $record): string
    {

        $search = 'xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/';
        $replace = 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/';

        return str_replace($search, $replace, $record);

    }

    /**
     * Remove every <dc:description> node whose text content exactly matches one of
     * $exactTexts, comparing on whitespace normalized by
     * Episciences_Tools::spaceCleaner() and ignoring case.
     *
     * Some repositories push non-descriptive boilerplate through dc:description
     * (HAL's audience marker, for instance). Dropping those nodes from the raw XML
     * before it reaches PAPERS.RECORD keeps the two independent render paths -
     * Paper::getMetadata() on one side, Paper::getXslt() feeding public/xsl/*.xsl on
     * the other - consistent without a filter in each consumer.
     *
     * Matching is content-based, never positional: only text we explicitly name is
     * ever removed, so a real (possibly multilingual) abstract cannot be lost.
     *
     * The record is returned untouched when it cannot be parsed or when nothing
     * matches, so callers never have to guard against a corrupted result.
     *
     * @param string $record raw OAI-PMH XML
     * @param string[] $exactTexts description texts to drop
     */
    public static function removeDcDescriptionByText(string $record, array $exactTexts): string
    {
        if (trim($record) === '' || $exactTexts === []) {
            return $record;
        }

        $needles = [];
        foreach ($exactTexts as $exactText) {
            $normalized = (string)Episciences_Tools::spaceCleaner($exactText);
            if ($normalized !== '') {
                $needles[] = mb_strtolower($normalized);
            }
        }

        if ($needles === []) {
            return $record;
        }

        $dom = new DOMDocument();

        try {
            set_error_handler('\Ccsd\Xml\Exception::HandleXmlError');
            $loaded = $dom->loadXML($record);
        } catch (\Ccsd\Xml\Exception $e) {
            $loaded = false;
        } finally {
            restore_error_handler();
        }

        if (!$loaded || !$dom->documentElement) {
            return $record;
        }

        // Match on namespace URI rather than on a registered prefix: callers hand over
        // records at different stages of cleaning, some of which have already had the
        // default xmlns stripped (@see Episciences_PapersManager::cleanRecord()). An
        // empty namespace URI is therefore accepted too, while a same-named node from
        // another vocabulary (datacite:description) stays out of reach.
        $query = "//*[local-name()='description' and (namespace-uri()='http://purl.org/dc/elements/1.1/' or namespace-uri()='')]";

        $descriptions = (new DOMXPath($dom))->query($query);

        if ($descriptions === false || $descriptions->length === 0) {
            return $record;
        }

        $removed = 0;

        foreach ($descriptions as $description) {
            $text = mb_strtolower((string)Episciences_Tools::spaceCleaner($description->textContent));

            if (!in_array($text, $needles, true)) {
                continue;
            }

            // Drop the indentation left over from the removed node so the stored XML
            // keeps its original formatting instead of gaining a blank line.
            $previous = $description->previousSibling;
            if ($previous instanceof DOMText && trim($previous->nodeValue) === '') {
                $previous->parentNode?->removeChild($previous);
            }

            $description->parentNode?->removeChild($description);
            $removed++;
        }

        if ($removed === 0) {
            return $record;
        }

        // Preserve the prolog only when the input had one: callers store the serialized
        // result straight back into RECORD, next to records nobody rewrote.
        $cleaned = str_starts_with(ltrim($record), '<?xml')
            ? $dom->saveXML()
            : $dom->saveXML($dom->documentElement);

        return $cleaned !== false ? $cleaned : $record;
    }

    public static function formatReferences(array $reference = []): array
    {
        if (empty($reference)) {
            return [];
        }

        $parts = [
            $reference['authorsStr'] ?? '',
            sprintf('(%s)', $reference['year'] ?? ''),
            $reference['title'] ?? '',
            $reference['source'] ?? '',
            sprintf('%s(%s)', $reference['volume'] ?? '', $reference['issue'] ?? ''),
            $reference['page'] ?? '',
        ];

        $rawReference = implode('. ', array_filter($parts, static fn($part) => $part !== ''));
        $rawReference = rtrim($rawReference, '.') . '.';

        $tmp = [];

        if (!empty($reference['doi'])) {
            $rawReference .= ' ' . $reference['doi'] . '.';
            $tmp['doi'] = $reference['doi'];
        }

        if (!empty($reference['link'])) {
            $rawReference .= ' ' . $reference['link'] . '.';
        }

        $tmp['raw_reference'] = $rawReference;

        return $tmp;
    }

    public static function extractMultilingualContent($metadata, $xpath, $language): array
    {
        $result = [];
        $seenValues = [];
        $nodes = $metadata->xpath($xpath);

        foreach ($nodes as $node) {
            $value = (string)$node;

            // Skip empty values
            if (empty($value)) {
                continue;
            }

            // Skip duplicate values
            if (in_array($value, $seenValues, false)) {
                continue;
            }
            $seenValues[] = $value;

            // Try different ways to get xml:lang attribute
            $nodeLanguage = '';

            // Method 1: Direct attribute access
            $attributes = $node->attributes('xml', true);
            if (isset($attributes['lang'])) {
                $nodeLanguage = (string)$attributes['lang'];
            }

            // Method 2: Fallback using xpath on the current node
            if (empty($nodeLanguage)) {
                $langAttr = $node->xpath('@xml:lang');
                if (!empty($langAttr)) {
                    $nodeLanguage = (string)$langAttr[0];
                }
            }

            // Method 3: Fallback to document language, it should have been converted to 2 chars lang code
            if (empty($nodeLanguage)) {
                $nodeLanguage = $language;
            }

            $result[] = [
                'value' => $value,
                'language' => $nodeLanguage
            ];
        }

        return $result;
    }

    public static function extractRelatedIdentifiersFromMetadata($metadata): array
    {
        $relatedIdentifiers = [];
        $relatedIdentifierNodes = $metadata->xpath('//datacite:relatedIdentifiers/datacite:relatedIdentifier');

        foreach ($relatedIdentifierNodes as $relatedId) {
            $identifier = (string)$relatedId;
            $relationType = (string)$relatedId['relationType'];
            $relatedIdentifierType = (string)$relatedId['relatedIdentifierType'];
            $ressourceType = (string)$relatedId['resource_type'];

            if ($ressourceType === ''){
                $ressourceType = 'dataset';
            }

            if (!empty($identifier)) {
                $relatedIdentifiers[] = [
                    'identifier' => $identifier,
                    'relation' => $relationType,
                    'resource_type' => $ressourceType,
                    'scheme' => strtolower($relatedIdentifierType) === 'handle' ? 'url' : strtolower($relatedIdentifierType)
                ];
            }
        }

        return $relatedIdentifiers;
    }

    public static function extractPersons($metadata, &$creatorsDc, string $nameField = 'creatorName', string $prefix = null): array
    {
        $authors = [];
        $seenNames = [];

        // Find creators first (primary authors)
        $creators = $metadata->xpath('//' . self::XML_OPENAIRE_CREATOR_NODE);

        foreach ($creators as $creator) {
            $person = self::processPerson($creator, $nameField, $creatorsDc, $seenNames);
            if (!empty($person)) {
                $authors[] = $person;
            }
        }

        // Find contributors (additional contributors) - only include individual contributors, not institutions
        $contributors = $metadata->xpath('//' . self::XML_OPENAIRE_CONTRIBUTOR_NODE);

        foreach ($contributors as $contributor) {
            $contributorType = (string)$contributor['contributorType'];

            // Only process individual contributors, skip institutional ones
            $individualContributorTypes = [
                'ContactPerson',
                'DataCurator',
                'Editor',
                'ProjectLeader',
                'Other'
            ];

            if (in_array($contributorType, $individualContributorTypes)) {

                if (!empty($prefix)) {
                    $contributorFieldName = sprintf('%s:contributorName', $prefix);
                } else {
                    $contributorFieldName = 'contributorName';
                }

                $person = self::processPerson($contributor, $contributorFieldName, $creatorsDc, $seenNames);
                if (!empty($person)) {
                    $authors[] = $person;
                }
            }
        }

        return $authors;
    }

    private static function processPerson($person, $nameField, &$creatorsDc, &$seenNames): array
    {
        $affiliations = [];
        $tmp = [];


        $personName = (string)$person->$nameField; // pour ARCHE

        if (empty($personName)) {
            $result = $person->xpath($nameField);
            if (!empty($result)) {
                $personName = (string)$result[0];
            }
        }

        if (empty($personName)) {
            return [];
        }

        $name = $personName;

        // Check for duplicates
        if (in_array($name, $seenNames, true)) {
            return [];
        }

        $seenNames[] = $name;
        $creatorsDc[] = $name;

        // Parse name assuming "First Last" format (not "Last, First")
        $nameParts = explode(' ', $name);
        $tmp['fullname'] = $name;

        if (count($nameParts) >= 2) {
            // Last word is family name, rest is given name
            $tmp['family'] = array_pop($nameParts);
            $tmp['given'] = implode(' ', $nameParts);
        } else {
            // Single name - put it in family field
            $tmp['family'] = $name;
            $tmp['given'] = '';
        }

        // Extract ORCID and ARCHE identifiers
        foreach ($person->nameIdentifier as $identifier) {
            $scheme = (string)$identifier['nameIdentifierScheme'];
            $value = (string)$identifier;

            if ($scheme === 'ORCID' && !empty($value)) {
                $tmp['orcid'] = Episciences_Paper_AuthorsManager::normalizeOrcid($value);
            } // $scheme === 'ARCHE' is ignored for now
        }

        // Extract affiliation (if present)
        if (isset($person->affiliation)) {
            $affiliation = (string)$person->affiliation;
            if (!empty($affiliation)) {
                $affiliations[] = ['name' => $affiliation];
                $tmp['affiliation'] = $affiliations;
            }
        }

        return $tmp;
    }

    /**
     * Convert to 2-letter code if needed
     * @param string|null $language
     * @return string|null
     */

    public static function convertTo2LetterCode(string $language = null): ?string
    {
        if ($language === null) {
            return null;
        }
        $strLen = strlen($language);
        if ($strLen > 2) {
            if ($strLen === 3) {
                $language = Languages::getAlpha2Code($language);
            } else {
                $language = Translations::findLanguageCodeByLanguageName($language, ['en', 'fr', 'de']);
            }
        }

        return $language;
    }

    public static function assembleData(array $elementsToCompileOaiDc, array $enrichment, array &$assembled): void
    {

        $assembled[self::TO_COMPILE_OAI_DC] = $elementsToCompileOaiDc;

        if (
            isset($enrichment[self::CONTRIB_ENRICHMENT]) &&
            !empty($enrichment[self::CONTRIB_ENRICHMENT])
        ) {
            $assembled[self::ENRICHMENT][self::CONTRIB_ENRICHMENT] = $enrichment[self::CONTRIB_ENRICHMENT];
        }

        if (
            isset($enrichment[self::RESOURCE_TYPE_ENRICHMENT]) &&
            !empty($enrichment[self::RESOURCE_TYPE_ENRICHMENT])
        ) {
            $assembled[self::ENRICHMENT][self::RESOURCE_TYPE_ENRICHMENT] = $enrichment[self::RESOURCE_TYPE_ENRICHMENT];
        }

        if (isset($enrichment[self::FILES])) {
            $assembled[self::ENRICHMENT][self::FILES] = $enrichment[self::FILES];
        }

        if(isset($enrichment[self::RELATED_IDENTIFIERS])) {
            $assembled[self::ENRICHMENT][self::RELATED_IDENTIFIERS] = $enrichment[self::RELATED_IDENTIFIERS];
        }
    }

    public static function getType(string $mimeType): ?string {
        // Remove parameters, e.g. "text/html; charset=UTF-8" → "text/html"
        $base = explode(';', $mimeType, 2)[0];
        $base = trim($base);

        if (!str_contains($base, '/')) {
            return null; // Not a valid MIME type
        }
        $exploded = explode('/', $base,2);
        return end($exploded);
    }

    /**
     * @param string $baseUrl
     * @param string $oaiIdentifier
     * @param string $metadataPrefix
     * @return array|string
     * @throws Ccsd_Error
     */

    public static function getRecord(string $baseUrl, string $oaiIdentifier, string $metadataPrefix ='oai_dc'): array|string
    {

        $oai = new Episciences_Oai_Client($baseUrl, 'xml');

        try {
            $result = $oai->getRecord($oaiIdentifier, $metadataPrefix);
        } catch (Exception $e) {
            throw new Ccsd_Error($e->getMessage());
        }

        return $result;

    }

    /**
     * If parsing fails, return the original match unchanged
     * @param string $text
     * @return string
     *
     */
    public static function replaceYMDHMSWithTimestamp(string $text): string
    {
        return preg_replace_callback(
            '/\b(\d{8}):(\d{6})\b/',  // matches YYYYMMDD:HHMMSS
            static function ($m) {
                $originalMatch = $m[0];
                $dateStr = $m[1] . $m[2]; // YYYYMMDDHHMMSS

                if (empty($dateStr)) {
                    return '';
                }

                try {
                    $dt = DateTime::createFromFormat('YmdHis', $dateStr, new DateTimeZone('UTC'));

                } catch (DateInvalidTimeZoneException $e) {
                    Episciences_View_Helper_Log::log($e->getMessage());
                    return $originalMatch;
                }

                if ($dt === false) {
                    return $originalMatch;
                }

                return $dt->getTimestamp();
            },
            $text
        );
    }

    /**
     * @param string $input
     * @return string
     */

    public static function removeDateTimePattern(string $input): string
    {
        // Remove any occurrence of YYYYMMDD:HHMMSS
        return preg_replace('#\b\d{8}:\d{6}\b#', '', $input);
    }

    /**
     * return the first matched pattern (YYYYMMDD:HHMMSS) or empty string
     * @param string $input
     * @return string
     */

    public static function getDateTimePattern(string $input): string
    {
        if (!preg_match('#\b\d{8}:\d{6}\b#', $input, $matches)) {
            return '';
        }
        return $matches[array_key_first($matches)];
    }

    public static function getConceptIdentifierFromString(string $string): string
    {
        $isMatched = preg_match('#(\d+)/(\d+)\.?#', $string, $matches);

        if ($isMatched) {
            return sprintf('%s/%s', $matches[1], $matches[2]);
        }

        return $string;
    }

    /**
     * @param string $identifier
     * @return string
     * If the version has multiple parts (1.1), keep it intact.
     * Otherwise (just "1"), return it as is.
     */

    public static function getVersionFromIdentifier(string $identifier): string
    {
        if (preg_match('/\d+\.([0-9.]+)/', $identifier, $matches)) {
            return $matches[1];
        }
        return '1';
    }

    public static function safeDateFormat(string $datestamp): string
    {
        if ($datestamp === '') {
            return '';
        }
        $dt = date_create($datestamp);
        return $dt !== false ? $dt->format('Y-m-d') : '';
    }

    /**
     * Returns $hookParams['response'] as is, or fetches it via $fetchRecord when
     * absent — the common body of the per-repository checkResponse() hooks.
     *
     * @param array<string, mixed> $hookParams
     * @param callable(array<string, mixed>): array<string, mixed> $fetchRecord
     * @return array<string, mixed>
     */
    public static function resolveResponse(array $hookParams, callable $fetchRecord): array
    {
        if (isset($hookParams[self::META_IDENTIFIER]) && empty($hookParams['response'])) {
            return $fetchRecord([self::META_IDENTIFIER => $hookParams[self::META_IDENTIFIER]]);
        }

        return $hookParams['response'] ?? [];
    }

    /**
     * Compiles a DataCite <descriptions> block into value/language pairs,
     * decoding the <p>-wrapped HTML each repository's descriptions carry.
     *
     * $preferAbstractType and $convertLanguageCodeToAlpha2 default to false so
     * every existing caller keeps its exact prior behavior when opting in only
     * to what it already relied on.
     *
     * @return array<int, array<string, string>>
     */
    public static function extractDescriptions(
        SimpleXMLElement $metadata,
        string $language,
        bool $preferAbstractType = false,
        bool $convertLanguageCodeToAlpha2 = false
    ): array {
        $xpath = '//datacite:descriptions/datacite:description';
        $nodes = $preferAbstractType ? $metadata->xpath($xpath . '[@descriptionType="Abstract"]') : [];

        if (empty($nodes)) {
            $nodes = $metadata->xpath($xpath);
        }

        $descriptions = [];

        foreach ($nodes as $node) {
            $decoded = Episciences_Tools::epi_html_decode((string)$node, ['HTML.AllowedElements' => 'p']);
            $value = trim(str_replace(['<p>', '</p>'], '', $decoded));

            if ($value === '') {
                continue;
            }

            $nodeLanguage = '';
            $xmlAttributes = $node->attributes('xml', true);

            if (isset($xmlAttributes['lang'])) {
                $nodeLanguage = (string)$xmlAttributes['lang'];
            }

            if ($nodeLanguage === '') {
                $langXpath = $node->xpath('@xml:lang');
                if (!empty($langXpath)) {
                    $nodeLanguage = (string)$langXpath[0];
                }
            }

            if ($convertLanguageCodeToAlpha2 && strlen($nodeLanguage) > 2) {
                try {
                    $nodeLanguage = Languages::getAlpha2Code($nodeLanguage);
                } catch (Exception $e) {
                    $nodeLanguage = '';
                }
            }

            $descriptions[] = [
                'value' => $value,
                'language' => $nodeLanguage !== '' ? $nodeLanguage : $language,
            ];
        }

        return $descriptions;
    }
}