<?php

use Episciences\Tools\Translations;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Episciences_Repositories_ARCHE_Hooks implements Episciences_Repositories_HooksInterface
{


    const ARCHE_OAI_PMH_API = 'https://arche.acdh.oeaw.ac.at/oaipmh/?verb=GetRecord&metadataPrefix=oai_datacite&identifier=https://hdl.handle.net/';
    private const XML_LANG_ATTR = 'xml:lang';

    public static function hookCleanXMLRecordInput(array $input): array
    {
        return $input;
    }

    public static function hookFilesProcessing(array $hookParams): array
    {
        return [];
    }

    /**
     * @param array $hookParams
     * @return array
     * @throws Ccsd_Error
     */

    public static function hookApiRecords(array $hookParams): array
    {
        $xmlString = self::getArcheOaiDatacite($hookParams['identifier']);
        $data = self::enrichmentProcess($xmlString);


        if (isset($data[Episciences_Repositories_Common::TO_COMPILE_OAI_DC])) {
            $data['record'] = Episciences_Repositories_Common::toDublinCore($data[Episciences_Repositories_Common::TO_COMPILE_OAI_DC]);
        }



        return $data;
    }

    private static function enrichmentProcess(string $xmlString): array
    {
        $data = [];
        $creatorsDc = [];

        $metadata = simplexml_load_string($xmlString);
        if ($metadata === false) {
            throw new \http\Exception\InvalidArgumentException('Invalid XML');
        }

        // Register namespaces for OAI-PMH and DataCite
        $metadata->registerXPathNamespace('oai', 'http://www.openarchives.org/OAI/2.0/');
        $metadata->registerXPathNamespace('datacite', 'http://datacite.org/schema/kernel-3');

        // Extract persons (creators and contributors)
        $authors = Episciences_Repositories_Common::extractPersons($metadata, $creatorsDc);
        $data['authors'] = $authors;
        $data['creatorsDc'] = $creatorsDc;

        // Extract language from XML
        $languageNodes = $metadata->xpath('//datacite:language');
        $language = !empty($languageNodes) ? (string)$languageNodes[0] : 'en';

        // Convert to 2-letter code if needed
        if (strlen($language) > 2) {
            $language = Translations::findLanguageCodeByLanguageName($language, ['en', 'fr', 'de']);
        }

        // Extract titles
        $titles = Episciences_Repositories_Common::extractMultilingualContent($metadata, '//datacite:titles/datacite:title', $language);

        // Extract subjects
        $subjects = Episciences_Repositories_Common::extractMultilingualContent($metadata, '//datacite:subjects/datacite:subject', $language);

        // Extract descriptions
        $descriptions = self::extractDescriptions($metadata, $language);

        // Extract resourceType
        $resourceTypeNodes = $metadata->xpath('//datacite:resourceType');
        $dcType = !empty($resourceTypeNodes) ? (string)$resourceTypeNodes[0] : '';

        // Extract datestamp from OAI header
        $datestampNodes = $metadata->xpath('//oai:header/oai:datestamp');
        if (empty($datestampNodes)) {
            // Fallback to issued date from DataCite
            $issuedDateNodes = $metadata->xpath('//datacite:dates/datacite:date[@dateType="Issued"]');
            $datestamp = !empty($issuedDateNodes) ? (string)$issuedDateNodes[0] : '';
        } else {
            $datestamp = (string)$datestampNodes[0];
        }

        // Extract identifiers from OAI header
        $identifiers = [];
        $identifierNodes = $metadata->xpath('//oai:header/oai:identifier');
        if (!empty($identifierNodes)) {
            $identifiers[] = (string)$identifierNodes[0];
        }

        // Extract related identifiers
        $relatedIdentifiers = Episciences_Repositories_Common::extractRelatedIdentifiersFromMetadata($metadata);

        // Extract license information
        $rightsNodes = $metadata->xpath('//datacite:rightsList/datacite:rights');
        $license = '';
        if (!empty($rightsNodes)) {
            $rightsNode = $rightsNodes[0];
            $license = (string)$rightsNode['rightsURI'] ?: (string)$rightsNode;
        }

        // Build additional data
        $data['title'] = !empty($titles) ? $titles[0]['value'] : '';
        $data['titles'] = $titles;
        $data['creator'] = $creatorsDc;
        $data['subject'] = $subjects;
        $data['description'] = $descriptions;
        $data['language'] = $language;
        $data['type'] = $dcType;
        $data['date'] = $datestamp;
        $data['identifier'] = $identifiers;
        $data['license'] = $license;
        $data['related_identifiers'] = $relatedIdentifiers;

        // Extract identifier from request element
        $requestNodes = $metadata->xpath('//request');
        $urlIdentifier = '';
        if (!empty($requestNodes)) {
            $urlIdentifier = (string)$requestNodes[0]['identifier'];
        }

        $headers = [];
        $headers['datestamp'] = $datestamp;

        if ('' !== $datestamp) {
            $datestamp = date_create($datestamp)->format('Y-m-d');
            $headers['datestamp'] = $datestamp;
        }

        $headers['identifier'] = $urlIdentifier;

        // Prepare body data for Dublin Core conversion
        $body = $data;

        // Override language field to ensure only 2-letter code is used
        // Remove any existing language arrays and set single language code
        unset($body['language']);
        $body['language'] = (string)$language; // Force as single-element array

        $enrichment = [
            Episciences_Repositories_Common::CONTRIB_ENRICHMENT => $authors,
            Episciences_Repositories_Common::RESOURCE_TYPE_ENRICHMENT => $dcType,
        ];

        Episciences_Repositories_Common::assembleData(['headers' => $headers, 'body' => $body], $enrichment, $data);
        return $data;
    }

    private static function extractDescriptions($metadata, $language): array
    {
        $descriptions = [];
        $descriptionNodes = $metadata->xpath('//datacite:descriptions/datacite:description');

        foreach ($descriptionNodes as $descNode) {
            $desValue = Episciences_Tools::epi_html_decode((string)$descNode, ['HTML.AllowedElements' => 'p']);
            $value = trim(str_replace(['<p>', '</p>'], '', $desValue));
            if (!empty($value)) {
                $descriptions[] = [
                    'value' => $value,
                    'language' => (string)$descNode[self::XML_LANG_ATTR] ?: $language
                ];
            }
        }

        return $descriptions;
    }

    public static function hookCleanIdentifiers(array $hookParams): array
    {
        return [];
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

    public static function hookIsRequiredVersion(): array
    {
        return ['result' => false];
    }

    public static function hookLinkedDataProcessing(array $hookParams): array
    {
        $relatedIdentifiers = [];

        $xmlString = self::getArcheOaiDatacite($hookParams['identifier']);


        // Check if we have XML data to parse
        if (!empty($xmlString)) {
            $metadata = simplexml_load_string($xmlString);

            if ($metadata !== false) {
                // Register namespaces for DataCite
                $metadata->registerXPathNamespace('datacite', 'http://datacite.org/schema/kernel-3');
                $relatedIdentifiers = Episciences_Repositories_Common::extractRelatedIdentifiersFromMetadata($metadata);
            }
        }

        // Fallback to existing method if no XML or parsing failed
        if (empty($relatedIdentifiers)) {
            $relatedIdentifiers = self::extractRelatedIdentifiers($hookParams);
        }

        $affectedRows = Episciences_Submit::processDatasets($hookParams['docId'], $relatedIdentifiers);

        $response['affectedRows'] = $affectedRows;
        return $response;
    }

    public static function extractRelatedIdentifiers(array $hookParams): array
    {
        return $hookParams['metadata']['related_identifiers'] ?? [];
    }

    /**
     * @param $identifier
     * @return string
     * @throws Ccsd_Error
     */
    private static function getArcheOaiDatacite($identifier): string
    {
        $client = new Client();
        try {
            $response = $client->get(self::ARCHE_OAI_PMH_API . $identifier);
        } catch (GuzzleException $e) {
            throw new Ccsd_Error($e->getMessage());
        }

        return $response->getBody()->getContents();
    }


}

