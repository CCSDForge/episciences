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
        $tmpData = [];
        $response = self::checkResponse($hookParams);
        /** @var array $files */
        $files = array_key_exists('files', $response) ? $response['files'] : [];

        /** @var array $file */
        foreach ($files as $file) {

            [$checksumType, $checksum] = explode(':', $file['checksum']);

            $tmpData['doc_id'] = $hookParams['docId'];
            $tmpData['source'] = $hookParams['repoId'];
            $tmpData['file_name'] = $file['key'];
            $tmpData['file_type'] = $file['type'];
            $tmpData['file_size'] = $file['size'];
            $tmpData['checksum'] = $checksum;
            $tmpData['checksum_type'] = $checksumType;
            $tmpData['self_link'] = $file['links']['self'];

            $data[] = $tmpData;

            $tmpData = [];

        }

        unset($tmpData);

        $response['affectedRows'] = Episciences_Paper_FilesManager::insert($data);

        return $response;

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
     */
    public static function hookApiRecords(array $hookParams): array
    {
        if (!isset($hookParams['identifier'])) {
            return [];
        }

        $response = Episciences_Tools::callApi(self::API_RECORDS_URL . '/' . $hookParams['identifier']);

        self::enrichmentProcess($response);

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
        if (!empty($response)) {
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

            $pattern = '<dc:relation>doi:';
            $pattern .= Episciences_Repositories::getRepoDoiPrefix($hookParams['repoId']) . '\/' . mb_strtolower(Episciences_Repositories::getLabel($hookParams['repoId'])) . '.';
            $pattern .= $hookParams['conceptIdentifier'];
            $pattern .= '<\/dc:relation>';

            $found = Episciences_Tools::extractPattern('/' . $pattern . '/', $hookParams['record']);

            $hasDoiInfoRepresentsAllVersions = !empty($found);
        }

        return ['hasDoiInfoRepresentsAllVersions' => $hasDoiInfoRepresentsAllVersions];

    }

    public static function hookGetConceptIdentifierFromRecord(array $hookParams): array
    {
        $conceptIdentifier = null;
        if (isset($hookParams['repoId'], $hookParams['record'])) {

            $pattern = '<dc:relation>doi:';
            $pattern .= Episciences_Repositories::getRepoDoiPrefix($hookParams['repoId']) . '\/' . mb_strtolower(Episciences_Repositories::getLabel($hookParams['repoId'])) . '.';
            $pattern .= '\d+';
            $pattern .= '<\/dc:relation>';

            $found = Episciences_Tools::extractPattern('/' . $pattern . '/', $hookParams['record']);

            if (!empty($found)) {
                $found[0] = str_replace('<dc:relation>doi:', '', $found[0]);
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

        $relatedIdentifiers = [];
        $alternateIdentifiers = [];

        if (!empty($response)) {

            if (array_key_exists('related_identifiers', $response['metadata'])) {
                $relatedIdentifiers = $response['metadata']['related_identifiers'];
            }

            if (array_key_exists('alternate_identifiers', $response['metadata'])) {
                $alternateIdentifiers = $response['metadata']['alternate_identifiers'];
            }

        }

        return array_merge($relatedIdentifiers, $alternateIdentifiers);
    }

    /**
     * @param array $hookParams
     * @return array
     */

    public static function hookLinkedDataProcessing(array $hookParams): array
    {
        $linkedIdentifiers = self::hookGetLinkedIdentifiers($hookParams);

        $data = [];
        $tmpData = [];

        /** @var array $datastes */
        foreach ($linkedIdentifiers as $linkedIdentifier) {
            $tmpData['doc_id'] = $hookParams['docId'];
            $tmpData['value'] = $linkedIdentifier['identifier'];
            $tmpData['code'] = array_key_exists('resource_type', $linkedIdentifier) ? $linkedIdentifier['resource_type'] : 'undefined';
            $tmpData['name'] = $linkedIdentifier['scheme'];
            $tmpData['link'] = !in_array($linkedIdentifier['scheme'], ['url', 'lsid'], true) ? Episciences_Paper_Dataset::$_datasetsLink[$linkedIdentifier['scheme']] . $linkedIdentifier['identifier'] : $linkedIdentifier['identifier'];
            $tmpData['source_id'] = $hookParams['repoId'];

            $data[] = $tmpData;
            $tmpData = [];
        }

        unset($tmpData);

        $affectedRows = Episciences_Paper_DatasetsManager::insert($data);
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

        $authors = [];

        $metadata = $data['metadata'];

        if (isset($metadata['license']['id']) && $metadata['license']['id'] !== '') {
            $data[Episciences_Repositories_Common::ENRICHMENT][Episciences_Repositories_Common::LICENSE_ENRICHMENT] = $metadata['license']['id'];
        }

        if (isset($metadata['creators']) && is_array($metadata['creators'])) {

            foreach ($metadata['creators'] as $author) {

                $affiliations = [];

                if (isset($author['name']) && $author['name'] !== '') {
                    $name = $author['name'];

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

        if(!empty($authors)){
            $data[Episciences_Repositories_Common::ENRICHMENT][Episciences_Repositories_Common::CONTRIB_ENRICHMENT] = $authors;
        }

        if (isset($metadata['resource_type'])){
            $data[Episciences_Repositories_Common::ENRICHMENT][Episciences_Repositories_Common::RESOURCE_TYPE_ENRICHMENT] = $metadata['resource_type'];
        }


    }


}