<?php


class Episciences_Repositories_Zenodo_Hooks implements Episciences_Repositories_HooksInterface
{
    public const API_RECORDS_URL = 'https://zenodo.org/api/records';
    public const CONCEPT_IDENTIFIER = 'conceptrecid';


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
            $tmpData['file_name'] = $explodedFileName[array_key_first($explodedFileName)] ?? 'undefined';
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
            $response = Episciences_Tools::callApi(self::API_RECORDS_URL . '/' . $hookParams['identifier']);

            if (false === $response) {
                throw new Ccsd_Error(Ccsd_Error::ID_DOES_NOT_EXIST_CODE);
            }

        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            throw new Ccsd_Error($e->getMessage());
        }

        if ($response) {
            self::enrichmentProcess($response);
        }

        if (isset($response[Episciences_Repositories_Common::TO_COMPILE_OAI_DC])) {
            $response['record'] = Episciences_Repositories_Common::toDublinCore($response[Episciences_Repositories_Common::TO_COMPILE_OAI_DC]);
        }

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

    /**
     * @param array $hookParams
     * @return array
     */
    private static function checkResponse(array $hookParams): array
    {
        $response = [];
        if (isset($hookParams['identifier']) && empty($hookParams['response'])) {
            $response = self::hookApiRecords(['identifier' => $hookParams['identifier']]);
        } elseif (isset($hookParams['response'])) {
            $response = $hookParams['response'];
        }

        return $response;

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


        if (isset($metadata['creators']) && is_array($metadata['creators'])) {

            foreach ($metadata['creators'] as $author) {

                $affiliations = [];

                if (isset($author['name']) && $author['name'] !== '') {
                    $name = $author['name'];
                    $creatorsDc[] = $name;
                    $explodedName = explode(', ', $name);
                    $tmp['fullname'] = $name;
                    $tmp['given'] = isset($explodedName[1]) ? trim($explodedName[1]) : '';
                    $tmp['family'] = isset($explodedName[0]) ? trim($explodedName[0]) : '';

                    if (isset($author['orcid']) && $author['orcid'] !== '') {
                        $tmp['orcid'] = $author['orcid'];
                    }

                    if (isset($author['affiliation'])) {

                        $affiliations[] = ['name' => $author['affiliation']];
                        $tmp['affiliation'] = $affiliations;

                    }

                    $authors[] = $tmp;
                }
            }
        }

        $language = isset($metadata['language']) ? lcfirst(mb_substr($metadata['language'], 0, 2)) : 'en';

        $description[] = [
            'value' => trim(str_replace(['<p>', '</p>'], '', Episciences_Tools::epi_html_decode($metadata['description'], ['HTML.AllowedElements' => 'p']))), // (exp. #10027122)
            'language' => $language
        ];

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


}