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

        $url = Episciences_Repositories::getRepositories()[
            $hookParams['repoId']][Episciences_Repositories::REPO_API_URL
        ];

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

        return ['record' => self::toDublinCore($requestedVersion)];

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
            'setSpec' => [$currentVersion['category']?? '']

        ];

        $strAuthors = $currentVersion['authors'] ?? '';
        $authors = explode(';', $strAuthors);

        $xmlElements = [
            'title' => $currentVersion['title'] ?? '',
            'creator' => $authors,
            'subject' => $currentVersion['category'] ?? '',
            'description' => $currentVersion['abstract'] ?? '',
            'date' => $currentVersion['date'] ?? '',
            'type' => $currentVersion['type'] ?? '',
            'identifier' => [$currentVersion['doi'] ?? '', $urlIdentifier],
            'source' => [$urlIdentifier]
        ];

        if (isset($currentVersion['license']) && str_contains(strtolower($currentVersion['license']), 'cc_')) {
            $xmlElements['rights'] = 'info:eu-repo/semantics/openAccess';
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
        DOMElement $root,
        array $xmlElements,
        string $prefix = ''
    ){
        $defaultLanguage = 'en';

        foreach ($xmlElements as $key => $values) {

            if (is_array($values)) {

                foreach ($values as $value) {

                    $xmlElement = $xml->createElement(
                        $prefix . $key,
                        ($key === 'identifier' && Episciences_Tools::isDoi($value)) ? 'info:doi:'. $value : $value
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
    private static function getRequestedVersionFromCollection(array $collection, int $requestedVersion = 1) : array{

        foreach ($collection as $values){

            if (isset($values['version']) && $requestedVersion === (int)$values['version']){
                return $values;
            }

        }

        return [];

    }
}