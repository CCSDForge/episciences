<?php


class Episciences_Repositories_Zenodo_Hooks implements Episciences_Repositories_HooksInterface
{
    public const API_RECORDS_URL = 'https://zenodo.org/api/records';
    public const OAI_PMH_BASE_URL = 'https://zenodo.org/oai2d';
    public const CONCEPT_IDENTIFIER = 'conceptrecid';
    public const ENABLE_OAI_ENRICHMENT = true;



    public static function hookCleanXMLRecordInput(array $input): array
    {

        if (array_key_exists('record', $input)) {
            $input['record'] = Episciences_Repositories_Common::checkAndCleanRecord($input['record']);

        }

        return $input;

    }

    /**
     *  Extract the "files" metadata and save it in the database
     * @param array $hookParams
     * @return array
     */
    public static function hookFilesProcessing(array $hookParams): array
    {
        $data = [];
        $files = $hookParams['files'] ?? [];

        if (empty($files)) {
            $response = self::checkResponse($hookParams);
            $files = $response['files'] ?? [];
        }


        foreach ($files as $file) { // changes following Zenodo site update (13/10/2023)

            $tmpData = [];

            $explodedChecksum = explode(':', $file['checksum']);
            $explodedFileName = explode('.', $file['key']);

            $tmpData['doc_id'] = $hookParams['docId'];
            $tmpData['source'] = $hookParams['repoId'];
            $tmpData['file_name'] = $file['key'];
            $tmpData['file_type'] = $explodedFileName[array_key_last($explodedFileName)] ?? 'undefined';
            $tmpData['file_size'] = $file['size'];
            $tmpData['checksum'] = $explodedChecksum[array_key_last($explodedChecksum)] ?? null;
            $tmpData['checksum_type'] = $explodedChecksum[array_key_first($explodedChecksum)] ?? null;
            $tmpData['self_link'] = $file['links']['self'];

            $data[] = $tmpData;


        }

        $hookParams['affectedRows'] = Episciences_Paper_FilesManager::insert($data);

        return $hookParams;

    }

    public static function hookCleanIdentifiers(array $hookParams): array
    {
        $identifier = trim($hookParams['id']);
        $pattern1 = '#^http(s*)://doi.org/#';
        $pattern2 = '#^http(s*)://.+/record/#';

        if (preg_match($pattern1, $identifier)) {
            $identifier = preg_replace($pattern1, '', $identifier);
        }

        if (preg_match($pattern2, $identifier)) {
            $identifier = preg_replace($pattern2, '', $identifier);
        }

        $search = Episciences_Repositories::getRepoDoiPrefix($hookParams['repoId']) . '/' . mb_strtolower(Episciences_Repositories::getLabel($hookParams['repoId'])) . '.';

        $identifier = str_replace($search, '', $identifier);

        return ['identifier' => $identifier];
    }

    /**
     * @param array $hookParams
     * @return array
     * @throws Exception
     */
    public static function hookApiRecords(array $hookParams): array
    {

        if (!isset($hookParams['identifier'])) {
            return [];
        }

        try {
            //Fetch basic REST Data
            $response = Episciences_Tools::callApi(self::API_RECORDS_URL . '/' . $hookParams['identifier']);

            if (false === $response) {
                throw new Ccsd_Error(Ccsd_Error::ID_DOES_NOT_EXIST_CODE);
            }

            //Enrichment process
            if ($response) {
                 self::enrichmentProcess($response);
             }

            // Compile enriched data into Dublin Core metadata
            if (isset($response[Episciences_Repositories_Common::TO_COMPILE_OAI_DC])) {
                $response['record'] = Episciences_Repositories_Common::toDublinCore($response[Episciences_Repositories_Common::TO_COMPILE_OAI_DC]);
              }
            } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                throw new Ccsd_Error($e->getMessage());
            }
//Zend_Debug::dump($response);
        return $response ?: [];
    }


    /**
     * @param array $hookParams ['identifier' => '1234', 'response' => []]
     * @return array
     */
    public static function hookVersion(array $hookParams): array
    {
        $version = 1;
        $response = self::checkResponse($hookParams);
        if (!empty($response) && isset($response['metadata']['relations'])) {
            $version = $response['metadata']['relations']['version'][array_key_first($response['metadata']['relations']['version'])]['index'] + 1;
        }

        return ['version' => $version];
    }

    /**
     * @param array $hookParams
     * @return array
     */
    public static function hookIsOpenAccessRight(array $hookParams): array
    {
        return Episciences_Repositories_Common::isOpenAccessRight($hookParams);
    }

    public static function hookHasDoiInfoRepresentsAllVersions(array $hookParams): array
    {

        $hasDoiInfoRepresentsAllVersions = false;

        if (isset($hookParams['repoId'], $hookParams['record'], $hookParams['conceptIdentifier'])) {

            $pattern = '<dc:relation>';
            $pattern .= Episciences_DoiTools::DOI_ORG_PREFIX . Episciences_Repositories::getRepoDoiPrefix($hookParams['repoId']) . '/' . mb_strtolower(Episciences_Repositories::getLabel($hookParams['repoId'])) . '.';
            $pattern .= $hookParams['conceptIdentifier'];
            $pattern .= '</dc:relation>';

            $found = Episciences_Tools::extractPattern('#' . $pattern . '#', $hookParams['record']);

            $hasDoiInfoRepresentsAllVersions = !empty($found);
        }

        return ['hasDoiInfoRepresentsAllVersions' => $hasDoiInfoRepresentsAllVersions];

    }

    public static function hookGetConceptIdentifierFromRecord(array $hookParams): array
    {
        $conceptIdentifier = null;
        if (isset($hookParams['repoId'], $hookParams['record'])) {

            $pattern = '<dc:relation>';
            $pattern .= Episciences_DoiTools::DOI_ORG_PREFIX . Episciences_Repositories::getRepoDoiPrefix($hookParams['repoId']) . '/' . mb_strtolower(Episciences_Repositories::getLabel($hookParams['repoId'])) . '.';
            $pattern .= '\d+';
            $pattern .= '</dc:relation>';

            $found = Episciences_Tools::extractPattern('#' . $pattern . '#', $hookParams['record']);

            if (!empty($found)) {
                $found[0] = str_replace('<dc:relation>', '', $found[0]);
                $found[0] = str_replace('</dc:relation>', '', $found[0]);
                /** array */
                $found[0] = self::hookCleanIdentifiers(array_merge(['id' => $found[0]], $hookParams));
                $conceptIdentifier = $found[0]['identifier'];
            }
        }

        return ['conceptIdentifier' => $conceptIdentifier];
    }

    /**
     * Retourne l'identifiant unique qui lie les différentes  versions
     * @param array $hookParams
     * @return array
     */

    public static function hookConceptIdentifier(array $hookParams): array
    {

        $conceptIdentifier = null;

        $response = self::checkResponse($hookParams);

        if (array_key_exists(self::CONCEPT_IDENTIFIER, $response)) {
            $conceptIdentifier = $response[self::CONCEPT_IDENTIFIER];
        }

        return ['conceptIdentifier' => $conceptIdentifier];
    }

    //correct to avoid the recursive call to hookApiRecords
    /**
     * @param array $hookParams
     * @return array
     */
    private static function checkResponse(array $hookParams): array
    {
        //check if response is already set in hookParams
        if (isset($hookParams['response']) && !empty($hookParams['response'])) {
            return $hookParams['response'];
        }
        // If identifier is set, fetch the record from the API
        if (isset($hookParams['identifier']) && !empty($hookParams['identifier'])) {
            try {
                $response = Episciences_Tools::callApi(self::API_RECORDS_URL . '/' . $hookParams['identifier']);
                return $response ?: [];
            } catch (Exception $e) {
                return [];
            }
        }

        return [];

    }

    /**
     * @param array $hookParams
     * @return array
     */
    public static function hookGetLinkedIdentifiers(array $hookParams): array
    {
        $response = self::checkResponse($hookParams);

        if (!empty($response)) {
            return $response['metadata']['related_identifiers'] ?? $response['metadata']['alternate_identifiers'] ?? [];
        }

        return [];

    }

    /**
     * @param array $hookParams
     * @return array
     */

    public static function hookLinkedDataProcessing(array $hookParams): array
    {
        $linkedIdentifiers = self::hookGetLinkedIdentifiers($hookParams);
        $affectedRows = Episciences_Submit::processDatasets($hookParams['docId'], $linkedIdentifiers);
        $response = self::checkResponse($hookParams);
        $response['affectedRows'] = $affectedRows;
        return $response;
    }

    public static function hookIsRequiredVersion(): array
    {
        return ['result' => !Episciences_Repositories_Common::isRequiredVersion()];

    }

    /**
     * Unified enrichment process that handles both REST and OAI-PMH data
     * @param array $data
     * @return void
     */
    private static function enrichmentProcess(array &$data = []): void
    {
        if (empty($data)) {
            return;
        }
        // Initialize variables
        $identifiers = [];
        $datestamp = '';
        $xmlElements = [];
        $headers = [];
        $body = [];
        // Process identifiers
        if (isset($data['doi_url'])) {
            $urlIdentifier = $data['doi_url'];
            $headers['identifier'] = $urlIdentifier;
            $identifiers[] = $urlIdentifier;
        }

        if (isset($data['links']['self_html']) && $data['links']['self_html'] !== '') {
            $identifiers[] = $data['links']['self_html'];
        }

        if (isset($data['links']['self_doi']) && $data['links']['self_doi'] !== '') {
            $identifiers[] = $data['links']['self_doi'];
        }

        if (isset($data['modified'])) {
            $datestamp = $data['modified'];
        } elseif (isset($data['created'])) {
            $datestamp = $data['created'];
        }

        if ('' !== $datestamp) {
            $datestamp = date_create($datestamp)->format('Y-m-d');
            $headers['datestamp'] = $datestamp;
        }

        $creatorsDc = [];
        $type = []; // title, type & subtype;
        $authors = []; // enrichment
        $metadata = $data['metadata'];

        if (isset($metadata['resource_type'])) {
            $type = array_values($metadata['resource_type']);

        } else {
            if (isset($metadata['upload_type'])) {
                $type[Episciences_Paper::TYPE_TYPE_INDEX] = $metadata['upload_type'];
            }

            if (isset($metadata['publication_type'])) {
                $type[Episciences_Paper::TYPE_SUBTYPE_INDEX] = $metadata['publication_type'];
            }
        }


        $dcType = mb_strtolower($type[Episciences_Paper::TITLE_TYPE_INDEX] ??
            $type[Episciences_Paper::TYPE_TYPE_INDEX] ??
            $type[Episciences_Paper::TYPE_SUBTYPE_INDEX]);

        // Process authors and affiliations
        if (isset($metadata['creators']) && is_array($metadata['creators'])) {
            // Get OAI-PMH affiliations with ROR
            $oaiAffiliations = [];
            if (isset($data['id'])) {
                $oaiAffiliations = self::getOAIAffiliations($data['id']);
            }

            // Process each author with OAI affiliations
            foreach ($metadata['creators'] as $authorIndex => $author) {
                if (isset($author['name']) && $author['name'] !== '') {
                    $name = $author['name'];
                    $creatorsDc[] = $name;
                    $explodedName = explode(', ', $name);

                    $tmp = [];
                    $tmp['fullname'] = $name;
                    $tmp['given'] = isset($explodedName[1]) ? trim($explodedName[1]) : '';
                    $tmp['family'] = isset($explodedName[0]) ? trim($explodedName[0]) : '';

                    // Add ORCID if available
                    if (isset($author['orcid']) && $author['orcid'] !== '') {
                        $tmp['orcid'] = $author['orcid'];
                    }

                    // Process OAI-PMH affiliations with ROR
                    if (!empty($oaiAffiliations) && isset($oaiAffiliations[$authorIndex])) {
                        $oaiAuthor = $oaiAffiliations[$authorIndex];

                        if (!empty($oaiAuthor['affiliations'])) {
                            $authorAffiliations = [];

                            foreach ($oaiAuthor['affiliations'] as $oaiAff) {
                                $affiliationData = ['name' => $oaiAff['name']];

                                // Add ROR if available
                                if (isset($oaiAff['ror_id']) && $oaiAff['ror_id']) {
                                    $affiliationData['id'] = [[
                                        'id' => 'https://ror.org/' . $oaiAff['ror_id'],
                                        'id-type' => 'ROR'
                                    ]];
                                }

                                $authorAffiliations[] = $affiliationData;
                            }

                            $tmp['affiliation'] = $authorAffiliations;
                        }
                    }

                    $authors[] = $tmp;
                }
            }
        }

        // Fetch OAI-PMH descriptions if enabled
        $oaiDescriptions = [];
        if (self::isOaiEnrichmentEnabled() && isset($data['id'])) {
            $oaiDescriptions = self::getOAIDescriptions($data['id']);

            if (!empty($oaiDescriptions)) {
                //$data['oai_descriptions'] = $oaiDescriptions;
                $data[Episciences_Repositories_Common::ENRICHMENT]['descriptions'] = $oaiDescriptions;
            }
        }

        $language = isset($metadata['language']) ? lcfirst(mb_substr($metadata['language'], 0, 2)) : 'en';

        $description = [];

        if (!empty($oaiDescriptions)) {
            // Utiliser les descriptions enrichies OAI-PMH
            foreach ($oaiDescriptions as $oaiDesc) {
                $description[] = [
                    'value' => $oaiDesc['text'] ?? '',
                    'language' => $oaiDesc['language'] ?? $language
                ];
            }
        } else {
            if (isset($metadata['description'])) {
                $desValue = Episciences_Tools::epi_html_decode($metadata['description'], ['HTML.AllowedElements' => 'p']);
                $value = trim(str_replace(['<p>', '</p>'], '', $desValue));
            } else {
                $value = '';
            }

            $description[] = [
                'value' => $value, // (exp. #10027122)
                'language' => $language
            ];
        }

        $body['title'] = $metadata['title'] ?? '';
        $body['creator'] = $creatorsDc;
        $body['subject'] = $metadata['keywords'] ?? [];
        $body['description'] = $description;
        $body['language'] = $language;

        if ($dcType) {
            $body['type'] = $dcType;
        }

        $body['date'] = $datestamp;
        $body['identifier'] = $identifiers;

        $license = $metadata['license']['id'] ?? '';

        $conceptId = $data['conceptrecid'] ?? null;

        if ($conceptId) {
            $conceptIdentifierUrlDoi = sprintf('%s/%s.%s', Episciences_DoiTools::DOI_ORG_PREFIX . Episciences_Repositories::getRepoDoiPrefix(Episciences_Repositories::ZENODO_REPO_ID), mb_strtolower(Episciences_Repositories::getLabel(Episciences_Repositories::ZENODO_REPO_ID)), $conceptId);
            $body['relation'] = $conceptIdentifierUrlDoi;
        }

        if ($license !== '') {

            if (str_contains(strtolower($license), 'cc-')) {
                $license = 'https://creativecommons.org/licenses/' . $license;
            } else {
                $license = strtoupper($license);
            }

            $body['rights'][] = $license;
            $data[Episciences_Repositories_Common::ENRICHMENT][Episciences_Repositories_Common::LICENSE_ENRICHMENT] = $license;
        }

        if (isset($metadata['access_right']) && $metadata['access_right'] === 'open') {
            $body['rights'][] = 'info:eu-repo/semantics/openAccess';
        }


        $xmlElements['headers'] = $headers;
        $xmlElements['body'] = $body;

        $data[Episciences_Repositories_Common::TO_COMPILE_OAI_DC] = $xmlElements;

        if (!empty($authors)) {
            $data[Episciences_Repositories_Common::ENRICHMENT][Episciences_Repositories_Common::CONTRIB_ENRICHMENT] = $authors;
        }

        if (!empty($type)) {
            $data[Episciences_Repositories_Common::ENRICHMENT][Episciences_Repositories_Common::RESOURCE_TYPE_ENRICHMENT] = $type;
        }


        if (isset($data[Episciences_Repositories_Common::FILES])) {
            $data[Episciences_Repositories_Common::ENRICHMENT][Episciences_Repositories_Common::FILES] = $data[Episciences_Repositories_Common::FILES];
        }
    }

    /**
     * Get OAI-PMH affiliations with ROR identifiers
     * @param string $identifier
     * @return array
     */

    private static function getOAIAffiliations(string $identifier): array
    {
        if (empty($identifier)) {
            return [];
        }

        try {
            // Build OAI-PMH request URL
            $oaiUrl = self::OAI_PMH_BASE_URL . '?' . http_build_query([
                    'verb' => 'GetRecord',
                    'identifier' => 'oai:zenodo.org:' . $identifier,
                    'metadataPrefix' => 'datacite'
                ]);
            // Make HTTP request
            try {
                $xmlResponse = Episciences_Tools::callApi($oaiUrl, [
                    'timeout' => 30,
                    'allow_redirects' => true,
                    'headers' => [
                        'User-Agent' => 'CCSD Episciences support@episciences.org',
                        'Content-type' => 'application/xml'
                    ]
                ]);

                if ($xmlResponse === false || empty($xmlResponse)) {
                    return [];
                }
            } catch (Exception $e) {
                return [];
            }

            $result = self::parseOAIAffiliations($xmlResponse);
            // Parse XML response for affiliations only
            return $result;

        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Parse OAI-PMH XML to extract affiliations with ROR
     * @param string $xmlString
     * @return array
     */
    private static function parseOAIAffiliations(string $xmlString): array
    {
        if (empty($xmlString)) {
            return [];
        }

        try {
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $loaded = $dom->loadXML($xmlString);

            if (!$loaded) {
                return [];
            }

            $xpath = new DOMXPath($dom);
            // Register the datacite namespace
            $xpath->registerNamespace('datacite', 'http://datacite.org/schema/kernel-4');

            // Get all creator nodes
            $creatorNodes = $xpath->query('//datacite:creator');
            $authors = [];

            foreach ($creatorNodes as $creatorNode) {
                $authors[] = self::parseCreatorNode($xpath, $creatorNode);
            }

            return $authors;

        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Parse individual creator node to extract name and affiliations
     * @param DOMXPath $xpath
     * @param DOMNode $creatorNode
     * @return array
     */
    private static function parseCreatorNode(DOMXPath $xpath, DOMNode $creatorNode): array
    {
        $author = [
            'name' => '',
            'given_name' => '',
            'family_name' => '',
            'orcid' => '',
            'affiliations' => []
        ];

        // Extract creator name
        $creatorNameNode = $xpath->query('datacite:creatorName', $creatorNode)->item(0);
        if ($creatorNameNode) {
            $author['name'] = trim($creatorNameNode->nodeValue);
        }

        // Extract given name
        $givenNameNode = $xpath->query('datacite:givenName', $creatorNode)->item(0);
        if ($givenNameNode) {
            $author['given_name'] = trim($givenNameNode->nodeValue);
        }

        // Extract family name
        $familyNameNode = $xpath->query('datacite:familyName', $creatorNode)->item(0);
        if ($familyNameNode) {
            $author['family_name'] = trim($familyNameNode->nodeValue);
        }

        // Extract ORCID
        $orcidNode = $xpath->query('datacite:nameIdentifier[@nameIdentifierScheme="ORCID"]', $creatorNode)->item(0);
        if ($orcidNode) {
            $author['orcid'] = trim($orcidNode->nodeValue);
        }

        // Extract affiliations
        $affiliationNodes = $xpath->query('datacite:affiliation', $creatorNode);

        foreach ($affiliationNodes as $affiliationNode) {
            $affiliation = [
                'name' => trim($affiliationNode->nodeValue),
                'ror_id' => '',
                'ror_url' => ''
            ];

            // Check if affiliation has ROR identifier
            $rorId = $affiliationNode->getAttribute('affiliationIdentifier');
            $rorScheme = $affiliationNode->getAttribute('affiliationIdentifierScheme');

            if ($rorScheme === 'ROR' && !empty($rorId)) {
                $affiliation['ror_url'] = $rorId;
                // Extract ROR ID from URL
                $affiliation['ror_id'] = basename($rorId);
            }

            $author['affiliations'][] = $affiliation;
        }

        return $author;
    }

    /**
     * Get OAI-PMH descriptions
     * @param string $identifier
     * @return array
     */
    private static function getOAIDescriptions(string $identifier): array
    {
        if (empty($identifier)) {
            return [];
        }

        try {
            // Build OAI-PMH request URL
            $oaiUrl = self::OAI_PMH_BASE_URL . '?' . http_build_query([
                    'verb' => 'GetRecord',
                    'identifier' => 'oai:zenodo.org:' . $identifier,
                    'metadataPrefix' => 'datacite'
                ]);

            try {
                $xmlResponse = Episciences_Tools::callApi($oaiUrl, [
                    'timeout' => 30,
                    'allow_redirects' => true,
                    'headers' => [
                        'User-Agent' => 'CCSD Episciences support@episciences.org',
                        'Content-type' => 'application/xml'
                    ]
                ]);

                if ($xmlResponse === false || empty($xmlResponse)) {
                    return [];
                }
            } catch (Exception $e) {
                return [];
            }

            // Parse XML response for ALL descriptions
            $result = self::parseOAIDescriptions($xmlResponse);
            return $result;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Parse OAI-PMH XML to extract ALL descriptions
     * @param string $xmlString
     * @return array
     */
    private static function parseOAIDescriptions(string $xmlString): array
    {
        if (empty($xmlString)) {
            return [];
        }

        try {
            $abstracts = Episciences_Tools::xpath(
                $xmlString,
                '//*[local-name()="description"][@descriptionType="Abstract"]',
                true,
                true
            );

            $result = [];

            if (is_array($abstracts) && count($abstracts) > 0) {
                foreach ($abstracts as $key => $content) {
                    $description = [
                        'text' => $content,
                        'type' => 'Abstract',
                        'language' => is_numeric($key) ? 'en' : $key
                    ];

                    $result[] = $description;
                }
            }

            return $result;

        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Check if OAI-PMH enrichment is enabled
     * @return bool
     */
    private static function isOaiEnrichmentEnabled(): bool
    {
        return self::ENABLE_OAI_ENRICHMENT;
    }

}