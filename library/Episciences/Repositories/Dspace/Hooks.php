<?php

class Episciences_Repositories_Dspace_Hooks implements Episciences_Repositories_CommonHooksInterface, Episciences_Repositories_HooksInterface
{
    /**
     * Le paramétrage se fait en base de données, table "metadata_sources"
     * Exemple
     * INSERT INTO `metadata_sources` (`id`, `name`, `type`, `status`, `identifier`, `base_url`, `doi_prefix`, `api_url`, `doc_url`, `paper_url`) VALUES
     * (null, 'rep-dspace.uminho.pt', 'dspace', 1, 'oai:rep-dspace.uminho.pt:%%ID', 'https://rep-dspace.uminho.pt/server/oai/openaire4', '', '', 'https://hdl.handle.net/%%ID', '');
     */
    public const METADATA_PREFIX = 'oai_openaire';
    public const IDENTIFIER_EXEMPLE = '(Handle) 1822/79894';

    public static function hookCleanXMLRecordInput(array $input): array
    {
        return $input;
    }

    public static function hookFilesProcessing(array $hookParams): array
    {

        $data = [];
        $files = $hookParams['files'] ?? [];

        foreach ($files as $file) {
            $name = $file['url'] ?? '';

            if ($name === '') {
                continue;
            }

            $name = str_replace('/download', '', $file['url']);
            $explodedName = explode('/', $name);
            $name = end($explodedName);
            $tmpData = [];
            $tmpData['doc_id'] = $hookParams['docId'];
            $tmpData['source'] = $hookParams['repoId'];
            $tmpData['file_name'] = $name;
            $tmpData['file_type'] = Episciences_Repositories_Common::getType($file['mimeType']) ?? 'not_valid_type';
            $tmpData['self_link'] = $file['url']; // L'URL est cassée ; le bug doit être corrigé côté archive
            $tmpData['checksum'] = md5($name);
            $tmpData['file_size'] = 0;
            // todo :
            // Une fois l'URL corrigée
            //$tmpData['file_size'] = Episciences_Repositories_Common::remoteFilesizeHeaders($file['url']);
            //$tmpData['checksum'] = hash_file('md5', $file['url']);
            $tmpData['checksum_type'] = 'MD5';
            $data[] = $tmpData;
        }

        $hookParams['affectedRows'] = Episciences_Paper_FilesManager::insert($data);

        return $hookParams;

    }

    /**
     * @param array $hookParams
     * @return array
     * @throws Ccsd_Error
     */

    public static function hookApiRecords(array $hookParams): array
    {
        //https://repositorium.uminho.pt/server/oai/openaire4?verb=GetRecord&metadataPrefix=oai_openaire&identifier=oai:repositorium.uminho.pt:1822/79894

        if (!isset($hookParams['identifier'])) {
            return [];
        }

        $oaiIdentifier = Episciences_Repositories::getIdentifier($hookParams['repoId'], $hookParams['identifier']);
        $baseUrl = Episciences_Repositories::getRepositories()[$hookParams['repoId']][Episciences_Repositories::REPO_BASEURL];
        $baseUrl = rtrim($baseUrl, DIRECTORY_SEPARATOR);

        //$oaiUrl = $baseUrl;
        //$oaiUrl .= sprintf('?verb=GetRecord&metadataPrefix=oai_openaire&identifier=%s', $oaiIdentifier);

        $record = Episciences_Repositories_Common::getRecord($baseUrl, $oaiIdentifier, self::METADATA_PREFIX);
        $data = self::extractMetadata($record);

        if (isset($data[Episciences_Repositories_Common::TO_COMPILE_OAI_DC])) {
            $data['record'] = Episciences_Repositories_Common::toDublinCore($data[Episciences_Repositories_Common::TO_COMPILE_OAI_DC]);
        }

        return $data;
    }

    public static function hookCleanIdentifiers(array $hookParams): array
    {
        $identifier = str_replace(Episciences_Repositories_Common::URL_HDL, '', $hookParams['id']);
        return [Episciences_Repositories_Common::META_IDENTIFIER => $identifier];
    }

    public static function hookVersion(array $hookParams): array
    {
        return [];
    }

    public static function hookIsOpenAccessRight(array $hookParams): array
    {
        return [];
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

    public static function hookIsRequiredVersion(): array
    {
        return ['result' => false];
    }

    /**
     * @param string $baseUrl
     * @param string $oaiIdentifier
     * @return array|string
     *
     * @throws Ccsd_Error
     */

    private static function getRecord(string $baseUrl, string $oaiIdentifier): array|string
    {

        $oai = new Episciences_Oai_Client($baseUrl, 'xml');

        try {
            $result = $oai->getRecord($oaiIdentifier, self::METADATA_PREFIX);
        } catch (Exception $e) {
            throw new Ccsd_Error($e->getMessage());
        }

        return $result;

    }

    private static function extractMetadata($xmlString): array
    {
        $xml = simplexml_load_string($xmlString);

        if ($xml === false) {
            return [];
        }

        self::registerDefaultNamespaces($xml);

        $creatorsDc = [];
        $data = [];
        $identifiers = [];

        // Extraction header
        $header = $xml->header;

        $header = [
            'identifier' => (string)$header->identifier,
            'datestamp' => (string)$header->datestamp,
            //'setSpecs' => array_map('strval', $header->xpath('setSpec'))
        ];

        // Extract datestamp from OAI header
        $datestamp = $header['datestamp'];

        // Extract identifiers from OAI header
        $identifiers[] = $header['identifier'];

        if ($datestamp === '') {
            $datestamp = self::firstXPathValue($xml, '//datacite:dates/datacite:date[@dateType="Issued"]');
        }

        $language = self::extractLanguage($xml);

        // Extract titles
        $titles = Episciences_Repositories_Common::extractMultilingualContent(
            $xml,
            '//' . Episciences_Repositories_Common::XML_OPENAIRE_TITLE_NODE,
            $language);
        // Extract subjects
        $subjects = Episciences_Repositories_Common::extractMultilingualContent($xml,
            '//' . Episciences_Repositories_Common::XML_OPENAIRE_SUBJECT_NODE,
            $language);
        // Extract descriptions
        $descriptions = Episciences_Repositories_Common::extractMultilingualContent($xml,
            '//' . Episciences_Repositories_Common::XML_OPENAIRE_DESCRIPTION_NODE,
            $language);

        // Extract resourceType
        $dcType = self::firstXPathValue($xml, '//' . Episciences_Repositories_Common::XML_OPENAIRE_RESSOURCE_TYPE_NODE);

        // Extract related identifiers
        $relatedIdentifiers = Episciences_Repositories_Common::extractRelatedIdentifiersFromMetadata($xml);

        // Extract license information
        $license = self::extractLicense($xml);

        // Extract persons (creators and contributors)
        $authors = Episciences_Repositories_Common::extractPersons($xml, $creatorsDc, 'datacite:creatorName'); // for enrichment

        // Extract publisher
        $publisher = self::firstXPathValue($xml, '//dc:publisher');
        // DataCite: dates
        $dates = array_map(static function ($date) {
            return [
                'value' => (string)$date,
                'type' => (string)$date['dateType']
            ];
        }, $xml->xpath('//datacite:dates/datacite:date'));


        // DataCite: sizes
        $sizes = array_map('strval', $xml->xpath('//datacite:sizes/datacite:size'));
        // DataCite: identifiers
        $identifier = self::firstXPathValue($xml, '//datacite:identifier');
        $format = self::firstXPathValue($xml, '//dc:format');

        // OpenAIRE file
        $fileNode = $xml->xpath('//oaire:file')[0] ?? null;
        $file = $fileNode ? [
            'url' => (string)$fileNode[0],
            'mimeType' => (string)$fileNode['mimeType'],
            'accessRights' => (string)$fileNode[0]['accessRightsURI'],
            'objectType' => (string)$fileNode['objectType']
        ] : null;

        // Build additional data for oai dc
        $body = self::buildDataForOaiDc(
            $titles,
            $creatorsDc,
            $subjects,
            $descriptions,
            $language,
            $dcType,
            $datestamp,
            $identifiers,
            $license,
            $publisher
        );

        $enrichment = [
            Episciences_Repositories_Common::CONTRIB_ENRICHMENT => $authors,
            Episciences_Repositories_Common::RESOURCE_TYPE_ENRICHMENT => $dcType,
        ];

        if ($file) {
            $enrichment[Episciences_Repositories_Common::FILES] = [$file];
        }

        if (!empty($relatedIdentifiers)) {
            $enrichment[Episciences_Repositories_Common::RELATED_IDENTIFIERS] = $relatedIdentifiers;
        }

        Episciences_Repositories_Common::assembleData(['headers' => $header, 'body' => $body], $enrichment, $data);
        return $data;
    }

    private static function registerDefaultNamespaces(SimpleXMLElement $xml): void
    {
        $xml->registerXPathNamespace('oaire', 'http://namespace.openaire.eu/schema/oaire/');
        $xml->registerXPathNamespace('datacite', 'http://datacite.org/schema/kernel-4');
        $xml->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');
    }

    private static function getXPathValues(SimpleXMLElement $xml, string $xpath): array
    {
        return $xml->xpath($xpath);
    }

    private static function firstXPathValue(SimpleXMLElement $xml, string $xpath): string
    {
        $nodes = self::getXPathValues($xml, $xpath);
        return !empty($nodes) ? (string)$nodes[0] : '';
    }

    /**
     * Extract language from XML and convert to 2-letter code if needed
     * @param SimpleXMLElement $xml
     * @return string
     */

    private static function extractLanguage(SimpleXMLElement $xml): string
    {
        $languageNodes = $xml->xpath('//dc:language');
        $language = !empty($languageNodes) ? (string)$languageNodes[0] : 'en';

        return Episciences_Repositories_Common::convertTo2LetterCode($language) ?? 'en';
    }

    private static function extractLicense(SimpleXMLElement $xml): string
    {
        $rightsNodes = $xml->xpath('//' . Episciences_Repositories_Common::XML_OPENAIRE_RIGHTS_NODE);
        if (empty($rightsNodes)) {
            return '';
        }

        $rightsNode = $rightsNodes[0];
        return (string)$rightsNode['rightsURI'] ?: (string)$rightsNode;
    }

    private static function buildDataForOaiDc(
        array  $titles,
        array  $creatorsDc,
        array  $subjects,
        array  $descriptions,
        string $language,
        string $dcType,
        string $datestamp,
        array  $identifiers,
        string $license,
        string | array $publisher
    ): array
    {
        return [
            'title' => !empty($titles) && isset($titles[0]['value']) ? $titles[0]['value'] : '',
            'titles' => $titles,
            'creator' => $creatorsDc,
            'subject' => $subjects,
            'description' => $descriptions,
            'language' => $language,
            'type' => $dcType,
            'date' => $datestamp,
            'identifier' => $identifiers,
            'rights' => $license,
            'publisher' => $publisher
        ];
    }

}

