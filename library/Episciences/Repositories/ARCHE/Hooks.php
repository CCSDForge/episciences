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
        $authors = self::extractPersons($metadata, $creatorsDc);
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
        $titles = self::extractMultilingualContent($metadata, '//datacite:titles/datacite:title', $language);

        // Extract subjects
        $subjects = self::extractMultilingualContent($metadata, '//datacite:subjects/datacite:subject', $language);

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
        $relatedIdentifiers = self::extractRelatedIdentifiersFromMetadata($metadata);

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


        $xmlElements = [];
        $xmlElements['headers'] = $headers;
        $xmlElements['body'] = $body;



        $data[Episciences_Repositories_Common::TO_COMPILE_OAI_DC] = $xmlElements;

        if (!empty($authors)) {
            $data[Episciences_Repositories_Common::ENRICHMENT][Episciences_Repositories_Common::CONTRIB_ENRICHMENT] = $authors;
        }

        if (!empty($dcType)) {
            $data[Episciences_Repositories_Common::ENRICHMENT][Episciences_Repositories_Common::RESOURCE_TYPE_ENRICHMENT] = $dcType;
        }

        return $data;
    }

    private static function extractPersons($metadata, &$creatorsDc): array
    {
        $authors = [];
        $seenNames = [];

        // Find creators first (primary authors)
        $creators = $metadata->xpath('//datacite:creators/datacite:creator');
        foreach ($creators as $creator) {
            $person = self::processPerson($creator, 'creatorName', $creatorsDc, $seenNames);
            if (!empty($person)) {
                $authors[] = $person;
            }
        }

        // Find contributors (additional contributors) - only include individual contributors, not institutions
        $contributors = $metadata->xpath('//datacite:contributors/datacite:contributor');
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
                $person = self::processPerson($contributor, 'contributorName', $creatorsDc, $seenNames);
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

        $personName = (string)$person->$nameField;
        if (empty($personName)) {
            return [];
        }

        $name = $personName;

        // Check for duplicates
        if (in_array($name, $seenNames)) {
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
                $tmp['orcid'] = $value;
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

    private static function extractMultilingualContent($metadata, $xpath, $language): array
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
            if (in_array($value, $seenValues)) {
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

    private static function extractRelatedIdentifiersFromMetadata($metadata): array
    {
        $relatedIdentifiers = [];
        $relatedIdentifierNodes = $metadata->xpath('//datacite:relatedIdentifiers/datacite:relatedIdentifier');

        foreach ($relatedIdentifierNodes as $relatedId) {
            $identifier = (string)$relatedId;
            $relationType = (string)$relatedId['relationType'];
            $relatedIdentifierType = (string)$relatedId['relatedIdentifierType'];

            if (!empty($identifier)) {
                $relatedIdentifiers[] = [
                    'identifier' => $identifier,
                    'relation' => $relationType,
                    'resource_type' => 'dataset', // Default to dataset as shown in example
                    'scheme' => strtolower($relatedIdentifierType) === 'handle' ? 'url' : strtolower($relatedIdentifierType)
                ];
            }
        }

        return $relatedIdentifiers;
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
                $relatedIdentifiers = self::extractRelatedIdentifiersFromMetadata($metadata);
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

