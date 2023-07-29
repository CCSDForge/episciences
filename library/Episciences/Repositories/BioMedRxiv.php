<?php


class Episciences_Repositories_BioMedRxiv implements Episciences_Repositories_HooksInterface
{
    public const SUCCESS_CODE = 'ok';
    public const AVAILABLE_SERVERS = [
        Episciences_Repositories::BIO_RXIV_ID => 'biorxiv',
        Episciences_Repositories::MED_RXIV_ID => 'medrxiv',
    ];
    public const RESPONSE_FORMAT = 'json';

    public const DOI_PREFIX = '10.1101';
    public const ENRICHMENT = 'enrichment';
    public const CONTRIB_ENRICHMENT = 'contributors';
    public const INSTITUTIONS = 'institutions';
    public const LICENSE = 'license';
    public const CITATIONS = 'citations';
    public const KEYWORDS = 'kwd';


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


    public static function hookCleanXMLRecordInput(array $input): array
    {
        return $input;
    }

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

        $response = Episciences_Tools::callApi($url, $options);

        $messages = $response['messages'][array_key_first($response['messages'])];
        $collection = $response['collection']; // all versions

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

        if (isset($requestedVersion[self::ENRICHMENT])) {
            unset($requestedVersion[self::ENRICHMENT][self::KEYWORDS], $requestedVersion[self::ENRICHMENT][self::LICENSE]); // data already added to the DC @see self::toDublinCore
            $result[self::ENRICHMENT] = $requestedVersion[self::ENRICHMENT];
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

    public static function hookIsOpenAccessRight(array $hookParams): array
    {
        return ['isOpenAccessRight' => true];
    }

    public static function hookHasDoiInfoRepresentsAllVersions(array $hookParams): array
    {
        return [];
    }

    public static function hookGetConceptIdentifierFromRecord(array $hookParams): array
    {
        return [];
    }

    public static function hookConceptIdentifier(array $hookParams): array
    {
        return [];
    }

    public static function hookLinkedDataProcessing(array $hookParams): array
    {
        return [];
    }

    public static function hookFilesProcessing(array $hookParams): array
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

    public static function isRequiredVersion(): array
    {
        return [
            'result' => true
        ];

    }

    /**
     * @param array $currentVersion
     * @return string
     */
    private static function toDublinCore(array $currentVersion): string
    {

        $result = '';
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

        $headers = [
            'identifier' => $urlIdentifier,
            'datestamp' => $currentVersion['date'] ?? '',
            'setSpec' => [$currentVersion['category'] ?? '']

        ];

        $strAuthors = $currentVersion['authors'] ?? '';
        $authors = explode(';', $strAuthors);

        $subject = $currentVersion['category'] ?? [];

        if (
            isset($currentVersion[self::ENRICHMENT][self::KEYWORDS]) &&
            !empty(($currentVersion[self::ENRICHMENT][self::KEYWORDS]))
        ) {
            $subject = $currentVersion[self::ENRICHMENT][self::KEYWORDS];
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

        if (isset($currentVersion[self::ENRICHMENT][self::LICENSE]) && $currentVersion[self::ENRICHMENT][self::LICENSE] !== '') {
            $xmlElements['rights'][] = $currentVersion[self::ENRICHMENT][self::LICENSE];
        }

        $license = (
            isset($currentVersion[self::LICENSE]) &&
            $currentVersion[self::LICENSE] !== ''
        ) ? strtolower($currentVersion[self::LICENSE]) : 'cc_no';


        if (
            str_contains($license, 'cc_') &&
            $license !== 'cc_no' // todo Should we block this type of submission?

        ) {
            $xmlElements['rights'][] = 'info:eu-repo/semantics/openAccess';
        }

        $xml = new Ccsd_DOMDocument('1.0', 'utf-8');

        $xml->formatOutput = true;
        $xml->substituteEntities = true;
        $xml->preserveWhiteSpace = false;


        try {

            $record = $xml->createElement('record');

            $header = $xml->createElement('header');
            $record->appendChild(self::addXmlElements($xml, $header, $headers));


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
            $metadata->appendChild(self::addXmlElements($xml, $dc, $xmlElements, 'dc:'));

            $record->appendChild($metadata);

            $result = $xml->saveXML($record);


        } catch (DOMException $e) {
            trigger_error($e->getMessage());
        }

        return $result;

    }

    /**
     * @param DOMDocument $xml
     * @param DOMElement $root
     * @param array $xmlElements
     * @param string $prefix
     * @return DOMElement
     * @throws DOMException
     */
    private static function addXmlElements(
        DOMDocument $xml,
        DOMElement  $root,
        array       $xmlElements,
        string      $prefix = ''
    )
    {
        $defaultLanguage = 'en';

        foreach ($xmlElements as $key => $values) {

            if (is_array($values)) {

                foreach ($values as $value) {

                    $xmlElement = $xml->createElement(
                        $prefix . $key,
                        ($key === 'identifier' && Episciences_Tools::isDoi($value)) ? 'info:doi:' . $value : $value
                    );

                    if ($key === 'description') {

                        $xmlElement->setAttribute('xml:lang', $defaultLanguage);
                    }

                    $root->appendChild($xmlElement);

                }

            } else {

                $xmlElement = $xml->createElement($prefix . $key, $values);

                if ($key === 'description') {

                    $xmlElement->setAttribute('xml:lang', $defaultLanguage);
                }

                $root->appendChild($xmlElement);

            }

        }

        return $root;

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


        libxml_use_internal_errors(true);
        /** @var SimpleXMLElement $simpleXlmDoc */
        $simpleXlmDoc = simplexml_load_string(file_get_contents($values['jatsxml']));
        libxml_clear_errors();

        if (!$simpleXlmDoc) {
            return;
        }

        $contributors = [];
        $institutions = [];
        $license = '';
        $citations = [];
        $kwd = [];


        $json = json_encode($simpleXlmDoc);
        $doc = json_decode($json, true);

        $articleMeta = $doc['front']['article-meta'] ?? [];
        $references = $doc['back']['ref-list']['ref'] ?? [];

        self::articleMetaProcess($articleMeta, $license, $contributors, $institutions, $kwd);
        self::referencesProcess($references, $citations);

        if (
            !empty($contributors) ||
            !empty($institutions) ||
            !empty($license) ||
            !empty($citations)
        ) {

            $values[self::ENRICHMENT] = [
                self::CONTRIB_ENRICHMENT => $contributors,
                self::INSTITUTIONS => $institutions,
                self::LICENSE => $license,
                self::CITATIONS => $citations,
                self::KEYWORDS => $kwd
            ];
        }
    }


    private static function articleMetaProcess(
        array  $articleMeta,
        string &$strLicense = '',
        array  &$contributors = [],
               &$institutions = [],
        array  &$keyWords = []
    ): void
    {

        foreach ($articleMeta as $ak => $aVals) {

            if ($ak !== 'contrib-group' && $ak !== 'permissions' && $ak !== 'kwd-group') {
                continue;
            }

            if ($ak === 'contrib-group') {

                $contrib = $aVals['contrib'] ?? [];

                foreach ($contrib as $cVals) {

                    $contributors[] = [
                        'degrees' => $cVals['degrees'] ?? '',
                        'fullname' => $cVals['name']['given-names'] . '' . $cVals['name']['surname'],
                        'family' => $cVals['name']['surname'],
                        'given' => $cVals['name']['given-names'],
                        'orcidUrl' => $cVals['contrib-id'] ?? '',
                        'email' => $cVals['email'] ?? '',
                    ];

                }

                $aff = $aVals['aff'] ?? [];

                foreach ($aff as $affVals) {
                    $institutions[] = [
                        'name' => $affVals['institution'] ?? $affVals, // @see medrxiv 10.1101/2023.06.18.23291577
                        'country' => $affVals['country'] ?? ''
                    ];
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


    private static function referencesProcess(array $references, array &$citations = []): void
    {

        foreach ($references as $reference) {

            $currentCitation = $reference['citation'] ?? [];

            if (!empty($currentCitation)) {

                $authorStr = '';
                $title = '';
                $page = '';


                $stringName = $currentCitation['string-name'] ?? [];


                foreach ($stringName as $index => $sn) {

                    if (!isset($sn['surname']) && !isset($sn['given-names'])) {
                        continue;
                    }

                    $authorStr .= $sn['surname'] . ', ' . $sn['given-names'];

                    if ($index <= count($sn) - 1) {
                        $authorStr .= '; ';
                    }
                }

                if (isset($currentCitation['article-title'])) {
                    $title = $currentCitation['article-title'];
                } elseif (isset($currentCitation['chapter-title'])) {
                    $title = $currentCitation['chapter-title'];
                }


                if (isset($currentCitation['fpage'])) {

                    $page = $currentCitation['fpage'];

                    if (isset($currentCitation['lpage'])) {
                        $page .= '-' . $currentCitation['lpage'];
                    }

                }

                $citations[] = [
                    'author' => $authorStr,
                    'year' => $currentCitation['year'] ?? '',
                    'date_in_citation' => $currentCitation['date-in-citation'] ?? '',
                    'title' => $title,
                    'source_title' => $currentCitation['source'] ?? '',
                    'volume' => $currentCitation['volume'] ?? '',
                    'issue' => $currentCitation['issue'] ?? '',
                    'page' => $page,
                    'doi' => $currentCitation['doi'] ?? '',
                    'oa_link' => $currentCitation['ext-link'] ?? '',
                    'publisher_name' => $currentCitation['publisher-name'] ?? ''
                ];
            }
        }
    }


    private static function extractLicenseFromString(string $string): string
    {
        $license = '';

        $explodedStr = explode(' ', explode(', ', $string)[1]);


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
        foreach ($keywords as $index => $keyword){
            if (is_array($keyword)) { // exp. ['italic => "Cebus"] bioRxiv => 10.1101/011908v1
                $keywords[$index] = array_shift($keyword);
            }
        }
    }
}