<?php

use Episciences\Repositories\CommonHooksInterface;
use Episciences\Repositories\FilesEnrichmentInterface;
use GuzzleHttp\Exception\GuzzleException;

class Episciences_Repositories_Dspace_Hooks implements CommonHooksInterface, FilesEnrichmentInterface, \Episciences\Repositories\InputSanitizerInterface
{
    /**
     * Le paramétrage se fait en base de données, table "metadata_sources"
     * Exemple
     * see src/mysql/2026-01-19-alter-metadata-sources.sql
     *
     */
    public const METADATA_PREFIX = 'oai_openaire';
    public const IDENTIFIER_EXEMPLE = '(Handle) 1822/79894';


    public static function hookFilesProcessing(array $hookParams): array
    {

        $data = [];

        $files = $hookParams['files'] ?? [];

        foreach ($files as $file) {

            $name = $file['url'] ?? '';

            if (empty($name)) {
                continue;
            }

            $infoFromApi = self::getFileInfoFromApi($name);

            if (!$infoFromApi) {
                continue;
            }

            $checksumInfo = $infoFromApi['checkSum'] ?? [];
            $checksum = $checksumInfo['value'] ?? null;
            $checkSumAlgorithm = $checksumInfo['checkSumAlgorithm'] ?? 'MD5';
            $contentLink = $infoFromApi['_links']['content']['href'] ?? $file['url'];
            $size = $infoFromApi['sizeBytes'] ?? 0;

            $name = $infoFromApi['name'] ?? null;

            if (!$name) {

                if (!$checksum) {
                    $checksum = md5_file($name);
                }

                $name = str_replace('/download', '', $file['url']);
                $explodedName = explode('/', $name);
                $name = end($explodedName);

            }

            $tmpData = [];
            $tmpData['doc_id'] = $hookParams['docId'];
            $tmpData['source'] = $hookParams['repoId'];
            $tmpData['file_name'] = $name;
            $tmpData['file_type'] = $infoFromApi['type'] ?? (Episciences_Repositories_Common::getType($file['mimeType']) ?? 'not_valid_type');
            $tmpData['self_link'] = $contentLink;
            $tmpData['checksum'] = $checksum;
            $tmpData['file_size'] = $size;
            $tmpData['checksum_type'] = $checkSumAlgorithm;
            $data[] = $tmpData;

            usleep(200000);
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

        $record = Episciences_Repositories_Common::getRecord($baseUrl, $oaiIdentifier, self::METADATA_PREFIX);
        $data = self::extractMetadata($record);

        if (isset($data[Episciences_Repositories_Common::TO_COMPILE_OAI_DC])) {
            $data['record'] = Episciences_Repositories_Common::toDublinCore($data[Episciences_Repositories_Common::TO_COMPILE_OAI_DC]);
        }

        // Pour RepositóriUM, le conceptId correspondra à la première version ; à vérifier donc lors d'ajout des autres repos compatible Dspace
        // Original version (v1) of record: https://hdl.handle.net/1822/92528
        // Version 4 of the same record: https://hdl.handle.net/1822/92528.4
        //$data['conceptrecid'] = Episciences_Repositories_Common::getConceptIdentifierFromString($hookParams['identifier']); // Identique pour toutes les versions

        return $data;
    }

    public static function hookCleanIdentifiers(array $hookParams): array
    {
        $identifier = str_replace(Episciences_Repositories_Common::URL_HDL, '', $hookParams['id']);
        return [Episciences_Repositories_Common::META_IDENTIFIER => $identifier];
    }

    public static function hookIsRequiredVersion(): array
    {
        return ['result' => false];
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
        //$sizes = array_map('strval', $xml->xpath('//datacite:sizes/datacite:size'));
        // DataCite: identifiers
        //$identifier = self::firstXPathValue($xml, '//datacite:identifier');
        //$format = self::firstXPathValue($xml, '//dc:format');

        // OpenAIRE file
        $fileNodes = $xml->xpath('//oaire:file') ?? [];
        $files = [];
        foreach ($fileNodes as $currentNode) {

            $tmpFile = [
                'url' => (string)$currentNode[0],
                'mimeType' => (string)$currentNode['mimeType'],
                'accessRights' => (string)$currentNode[0]['accessRightsURI'],
                'objectType' => (string)$currentNode['objectType']
            ];

            $files[] = $tmpFile;

        }

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

        if (count($files) > 0) {
            $enrichment[Episciences_Repositories_Common::FILES] = $files;
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
        array        $titles,
        array        $creatorsDc,
        array        $subjects,
        array        $descriptions,
        string       $language,
        string       $dcType,
        string       $datestamp,
        array        $identifiers,
        string       $license,
        string|array $publisher
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

    public static function hookIsIdentifierCommonToAllVersions(): array
    {
        return ['result' => false];
    }

    /**
     * @param string|null $fileUrl
     * @return string|array|bool|null
     */

    private static function getFileInfoFromApi(?string $fileUrl = null): string|array|bool|null
    {

        if (!$fileUrl) {
            return null;
        }

        $guid = Episciences_Tools::parseGuidFromText($fileUrl);
        $host = parse_url($fileUrl, PHP_URL_HOST);

        try {
            $bitstreamsUrl = sprintf('https://%s/server/api/core/bitstreams/%s', $host, $guid);
            return Episciences_Tools::callApi($bitstreamsUrl);
        } catch (GuzzleException $e) {
            // exp. de réponse : Client error: `GET https://repositorium.uminho.pt/server/api/core/bitstreams/61ba5e92-bab4-4901-aa31-9c68d2560510` resulted in a `401 Unauthorized` response:
            //{"timestamp":"2026-02-19T08:17:17.606+00:00","status":401,"error":"Unauthorized","message":"Authentication is required", (truncated...)
            Episciences_View_Helper_Log::log($e->getMessage());
            return null;
        }
    }

    public static function hookVersion(array $hookParams): array
    {
        $identifier = $hookParams[Episciences_Repositories_Common::META_IDENTIFIER] ?? null;

        if (!$identifier) {
            return [];
        }

        return ['version' => Episciences_Repositories_Common::getVersionFromIdentifier($identifier)];

    }

}

