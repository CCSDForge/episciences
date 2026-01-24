<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Intl\Languages;

class Episciences_Repositories_Zenodo_Hooks implements Episciences_Repositories_HooksInterface
{
    public const API_RECORDS_URL = 'https://zenodo.org/api/records';
    public const CONCEPT_IDENTIFIER = 'conceptrecid';
    const ZENODO_OAI_PMH_API = 'https://zenodo.org/oai2d?verb=GetRecord&metadataPrefix=datacite&identifier=oai:zenodo.org:';


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
     * @throws Exception
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

        return [Episciences_Repositories_Common::META_IDENTIFIER => $identifier];
    }

    /**
     * @param array $hookParams
     * @return array
     * @throws Exception
     */
    public static function hookApiRecords(array $hookParams): array
    {
        if (!isset($hookParams[Episciences_Repositories_Common::META_IDENTIFIER])) {
            return [];
        }

        $identifier = $hookParams[Episciences_Repositories_Common::META_IDENTIFIER];

        try {
            $response = Episciences_Tools::callApi(self::API_RECORDS_URL . '/' . $identifier);
        } catch (GuzzleException $e) {
            throw new Ccsd_Error($e->getMessage(), (int)$e->getCode());
        }

        if ($response === false || $response === null) {
            throw new Ccsd_Error(Ccsd_Error::ID_DOES_NOT_EXIST_CODE);
        }

        if (!is_array($response)) {
            throw new Ccsd_Error('Unexpected API response format');
        }

        if ($response) {
            self::enrichmentProcess($response);
        }

        // Ensure the structure exists before enrichment from OAI
        if (!isset($response[Episciences_Repositories_Common::TO_COMPILE_OAI_DC]['body'])
            || !is_array($response[Episciences_Repositories_Common::TO_COMPILE_OAI_DC]['body'])) {
            $response[Episciences_Repositories_Common::TO_COMPILE_OAI_DC]['body'] = [];
        }

        $oaiData = self::getZenodoOaiDatacite($identifier);
        if ($oaiData) {
            $responseFromOai = self::enrichmentProcessFromOAI($oaiData);
            if (!empty($responseFromOai[Episciences_Repositories_Common::META_DESCRIPTION])) {
                $response[Episciences_Repositories_Common::TO_COMPILE_OAI_DC]['body'][Episciences_Repositories_Common::META_DESCRIPTION] = $responseFromOai[Episciences_Repositories_Common::META_DESCRIPTION];
            }
            if (!empty($responseFromOai['title'])) {
                $response[Episciences_Repositories_Common::TO_COMPILE_OAI_DC]['body']['title'] = $responseFromOai['title'];
            }
        }

        if (isset($response[Episciences_Repositories_Common::TO_COMPILE_OAI_DC])) {
            $response['record'] = Episciences_Repositories_Common::toDublinCore($response[Episciences_Repositories_Common::TO_COMPILE_OAI_DC]);
        }

        return $response ?: [];
    }
    /**
     * @param array $hookParams ['identifier' => '1234', 'response' => []]
     * @return array
     * @throws Exception
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
                $conceptIdentifier = $found[0][Episciences_Repositories_Common::META_IDENTIFIER];
            }
        }

        return ['conceptIdentifier' => $conceptIdentifier];
    }

    /**
     * Retourne l'identifiant unique qui lie les différentes versions
     * @param array $hookParams
     * @return array
     * @throws Exception
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

    /**
     * @param array $hookParams
     * @return array
     * @throws Exception
     */
    private static function checkResponse(array $hookParams): array
    {
        $response = [];
        if (isset($hookParams[Episciences_Repositories_Common::META_IDENTIFIER]) && empty($hookParams['response'])) {
            $response = self::hookApiRecords([Episciences_Repositories_Common::META_IDENTIFIER => $hookParams[Episciences_Repositories_Common::META_IDENTIFIER]]);
        } elseif (isset($hookParams['response'])) {
            $response = $hookParams['response'];
        }

        return $response;

    }

    /**
     * @param array $hookParams
     * @return array
     * @throws Exception
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
     * @throws Exception
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
     * @param array $data
     * @return void
     */
    private static function enrichmentProcess(array &$data = []): void
    {
        if (empty($data)) {
            return;
        }

        $identifiers = [];
        $datestamp = '';
        $xmlElements = [];
        $headers = [];
        $body = [];

        if (isset($data['doi_url'])) {
            $urlIdentifier = $data['doi_url'];
            $headers[Episciences_Repositories_Common::META_IDENTIFIER] = $urlIdentifier;
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


        if (isset($metadata['creators']) && is_array($metadata['creators'])) {

            list($creatorsDc, $authors) = self::enrichmentProcessCreators($metadata['creators'], $creatorsDc, $authors);
        }

        $language = isset($metadata['language']) ? lcfirst(mb_substr($metadata['language'], 0, 2)) : 'en';

        if (isset($metadata[Episciences_Repositories_Common::META_DESCRIPTION])) {
            $desValue = Episciences_Tools::epi_html_decode($metadata[Episciences_Repositories_Common::META_DESCRIPTION], ['HTML.AllowedElements' => 'p']);
            $value = trim(str_replace(['<p>', '</p>'], '', $desValue));
        } else {
            $value = '';
        }

        $description[] = [
            'value' => $value, // (exp. #10027122)
            'language' => $language
        ];

        $body['title'] = $metadata['title'] ?? '';
        $body['creator'] = $creatorsDc;
        $body['subject'] = $metadata['keywords'] ?? [];
        $body[Episciences_Repositories_Common::META_DESCRIPTION] = $description;
        $body['language'] = $language;

        if ($dcType) {
            $body['type'] = $dcType;
        }

        $body['date'] = $datestamp;
        $body[Episciences_Repositories_Common::META_IDENTIFIER] = $identifiers;

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

        $enrichment = [
            Episciences_Repositories_Common::CONTRIB_ENRICHMENT => $authors,
            Episciences_Repositories_Common::RESOURCE_TYPE_ENRICHMENT=> $dcType,
            Episciences_Repositories_Common::FILES =>  $data[Episciences_Repositories_Common::FILES]

        ];

        Episciences_Repositories_Common::assembleData(['headers' => $headers, 'body' => $body], $enrichment, $data);

    }

    /**
     * @param $creators
     * @param array $creatorsDc
     * @param array $authors
     * @return array
     */
    private static function enrichmentProcessCreators($creators, array $creatorsDc, array $authors): array
    {
        foreach ($creators as $author) {

            $affiliations = [];

            if (isset($author['name']) && $author['name'] !== '') {
                $name = $author['name'];
                $creatorsDc[] = $name;
                $explodedName = explode(', ', $name);
                // Clear memory to avoid carrying over data from the previous author
                $tmp = [];

                $tmp['fullname'] = $name;
                $tmp['given'] = isset($explodedName[1]) ? trim($explodedName[1]) : '';
                $tmp['family'] = isset($explodedName[0]) ? trim($explodedName[0]) : '';

                if (isset($author['orcid']) && $author['orcid'] !== '') {
                    $tmp['orcid'] = Episciences_Paper_AuthorsManager::normalizeOrcid($author['orcid']);
                }

                if (isset($author['affiliation'])) {

                    $affiliations[] = ['name' => $author['affiliation']];
                    $tmp['affiliation'] = $affiliations;

                }

                $authors[] = $tmp;
            }
        }
        return array($creatorsDc, $authors);
    }


    /**
     * @param $identifier
     * @return string
     * @throws Ccsd_Error
     */
    private static function getZenodoOaiDatacite($identifier): string
    {
        $client = new Client();
        try {
            $response = $client->get(self::ZENODO_OAI_PMH_API . $identifier);
        } catch (GuzzleException $e) {
            throw new Ccsd_Error($e->getMessage());
        }

        return $response->getBody()->getContents();
    }

    private static function extractDescriptions($metadata, $language): array
    {
        $descriptions = [];
        $descriptionNodes = $metadata->xpath('//datacite:descriptions/datacite:description');
        foreach ($descriptionNodes as $descNode) {

            $desValue = Episciences_Tools::epi_html_decode((string)$descNode, ['HTML.AllowedElements' => 'p']);
            $value = trim(str_replace(['<p>', '</p>'], '', $desValue));
            if (!empty($value)) {
                // Extract xml:lang attribute correctly
                $nodeLanguage = '';

                // Get all attributes including xml:lang
                $allAttributes = [];
                foreach ($descNode->attributes() as $attrName => $attrValue) {
                    $allAttributes[$attrName] = (string)$attrValue;
                }

                // Check XML namespace attributes
                $xmlAttributes = $descNode->attributes('xml', true);
                if ($xmlAttributes) {
                    foreach ($xmlAttributes as $attrName => $attrValue) {
                        $allAttributes['xml:' . $attrName] = (string)$attrValue;
                    }
                }

                // Extract xml:lang from the attributes array we just built
                if (isset($allAttributes[Episciences_Repositories_Common::XML_LANG_ATTR])) {
                    $nodeLanguage = $allAttributes[Episciences_Repositories_Common::XML_LANG_ATTR];
                }

                // Convert 3-letter language codes to 2-letter codes if needed
                if (strlen($nodeLanguage) > 2) {
                    try {
                        $nodeLanguage = Languages::getAlpha2Code($nodeLanguage);
                    } catch (\Exception $e) {
                        // If conversion fails, keep the original
                        // It will fallback to document language below
                        $nodeLanguage = '';
                    }
                }

                // Fallback to document language
                if (empty($nodeLanguage)) {
                    $nodeLanguage = $language;
                }

                $descriptions[] = [
                    'value' => $value,
                    'language' => $nodeLanguage
                ];
            }
        }

        return $descriptions;
    }

    private static function enrichmentProcessFromOAI(string $xmlString): array
    {
        $data = [];

        $metadata = simplexml_load_string($xmlString);
        if ($metadata === false) {
            throw new \http\Exception\InvalidArgumentException('Invalid XML');
        }

        // Register namespaces for OAI-PMH and DataCite
        $metadata->registerXPathNamespace('oai', 'http://www.openarchives.org/OAI/2.0/');
        $metadata->registerXPathNamespace('datacite', 'http://datacite.org/schema/kernel-4');


        // Extract language from XML
        $languageNodes = $metadata->xpath('//datacite:language');
        $language = !empty($languageNodes) ? (string)$languageNodes[0] : 'en';
        // Convert to 2-letter code if needed
        if (strlen($language) > 2) {
            try {
                $language = Languages::getAlpha2Code($language);
            } catch (\Exception $e) {
                // If conversion fails, keep the original or fallback to 'en'
                $language = 'en';
            }
        }

        // Extract titles
        $titles = Episciences_Repositories_Common::extractMultilingualContent($metadata, '//datacite:titles/datacite:title', $language);

        // Extract descriptions
        $descriptions = self::extractDescriptions($metadata, $language);

        // Build additional data
        $data['title'] = $titles;
        $data['titles'] = $titles;
        $data[Episciences_Repositories_Common::META_DESCRIPTION] = $descriptions;
        $data['language'] = $language;

        // Prepare body data for Dublin Core conversion
        $xmlElements = [];
        $xmlElements['body'] = $data;

        $data[Episciences_Repositories_Common::TO_COMPILE_OAI_DC] = $xmlElements;

        return $data;
    }
}