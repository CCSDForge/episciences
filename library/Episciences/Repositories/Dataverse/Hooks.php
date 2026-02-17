<?php


use Episciences\Repositories\CommonHooksInterface;
use Episciences\Repositories\FilesEnrichmentInterface;
use Episciences\Repositories\InputSanitizerInterface;
use GuzzleHttp\Exception\GuzzleException;

class Episciences_Repositories_Dataverse_Hooks implements CommonHooksInterface, InputSanitizerInterface, FilesEnrichmentInterface
{
    public const IDENTIFIER_PREFIX = 'doi:';
    public const DATAVERSE_IDENTIFIER_EXEMPLE = '(DOI) 10.15454/GXXVJW / doi:10.15454/GXXVJW';
    public const SUCCESS_CODE = 'ok';
    public const ERROR_CODE = 'error';
    public const TO_COMPILE_OAI_DC = 'toCompileOaiDc';
    public const VERSION_MINOR_NUMBER = 0;

    public static function hookFilesProcessing(array $hookParams): array
    {

        $files = $hookParams['files'];
        $docId = $hookParams['docId'];
        $repoId = $hookParams['repoId'];

        $files = array_map(static function ($file) use ($docId, $repoId) {
            $file['doc_id'] = $docId;
            $file['source'] = $repoId;
            return $file;

        }, $files);


        $hookParams['affectedRows'] = Episciences_Paper_FilesManager::insert($files);

        return $hookParams;
    }

    /**
     * @param array $hookParams
     * @return array
     * @throws Ccsd_Error
     */
    public static function hookApiRecords(array $hookParams): array
    {
        $options = [];

        if (!isset($hookParams['identifier'])) {
            return [];
        }

        $url = Episciences_Repositories::getRepositories()[$hookParams['repoId']][Episciences_Repositories::REPO_API_URL];

        $url .= 'datasets/:persistentId/versions/';
        $url .= (int)$hookParams['version'];
        $url .= '/?persistentId=';
        $url .= $hookParams['identifier'];

        try {
            $response = Episciences_Tools::callApi($url, $options);

            if (false === $response || !isset($response['data'])) {
                throw new Ccsd_Error(Ccsd_Error::ID_DOES_NOT_EXIST_CODE);
            }


        } catch (GuzzleException $e) {
            throw new Ccsd_Error($e->getMessage());
        }

        $status = $response['status'] ?? null;

        if (

            $status &&
            mb_strtolower($status) !== self::SUCCESS_CODE

        ) {


            $result = $response['message'] ?? '"Empty record';

            return ['error' => $result, 'record' => null];
        }


        $processedData = $response['data'];

        self::dataProcess($processedData, $hookParams);

        $elements = [
            'headers' => $processedData[self::TO_COMPILE_OAI_DC]['headers'],
            'body' => [
                'title' => $processedData[self::TO_COMPILE_OAI_DC]['titles'][0] ?? '',
                'creator' => $processedData[self::TO_COMPILE_OAI_DC]['creators'] ?? [],
                'subject' => $processedData[self::TO_COMPILE_OAI_DC]['subjects'] ?? [],
                'description' => $processedData[self::TO_COMPILE_OAI_DC]['dsDescriptions'] ?? '',
                'date' => $processedData[self::TO_COMPILE_OAI_DC]['date'],
                'type' => $processedData[self::TO_COMPILE_OAI_DC]['kindOfDatas'][0] ?? '',
                'identifier' => [$processedData[self::TO_COMPILE_OAI_DC]['headers']['identifier']],
                'language' => $processedData[self::TO_COMPILE_OAI_DC]['languages'] ?? ['en']
            ]
        ];


        if (isset($processedData[self::TO_COMPILE_OAI_DC]['license'])) {
            $elements['body']['rights'] = $processedData[self::TO_COMPILE_OAI_DC]['license'];
        }

        $result = ['record' => Episciences_Repositories_Common::toDublinCore($elements)];

        if ($processedData[Episciences_Repositories_Common::ENRICHMENT]) {
            $result[Episciences_Repositories_Common::ENRICHMENT] = $processedData[Episciences_Repositories_Common::ENRICHMENT];

        }

        return $result;

    }

    public static function hookCleanIdentifiers(array $hookParams): array
    {
        return Episciences_Repositories_Common::cleanAndPrepare($hookParams);
    }

    public static function hookVersion(array $hookParams): array
    {
        $version = $hookParams['response'][Episciences_Repositories_Common::ENRICHMENT]['version'] ?? $hookParams['version'] ?? 1;
        return ['version' => $version];
    }

    public static function hookIsRequiredVersion(): array
    {
        return ['result' => Episciences_Repositories_Common::isRequiredVersion()];
    }

    private static function dataProcess(array &$data, array $hookParams = []): void
    {

        $result = [];
        $urlIdentifier = '';
        $license = '';
        $creators = [];

        $version = $data['versionNumber'] ?? 1;

        if (isset($data['versionMinorNumber'])) {
            $version = sprintf('%s.%s', $version, $data['versionMinorNumber']);
        }

        $result[Episciences_Repositories_Common::ENRICHMENT]['version'] = $version; // version form reposository

        $fieldsToBeProcessed = [
            'title',
            'author',
            'language',
            'dsDescription',
            'subject',
            'keyword',
            'kindOfData',
            'publication',
            'project',
        ];

        $extractedOaiDcFields = ['titles', 'languages', 'dsDescriptions', 'subjects', 'keywords', 'kindOfDatas'];

        if (isset($data['datasetPersistentId'])) {

            $urlIdentifier = 'https://doi.org/';
            $urlIdentifier .= mb_substr(
                $data['datasetPersistentId'], strlen(Episciences_Repositories_Dataverse_Hooks::IDENTIFIER_PREFIX)
            );
        }

        if ($urlIdentifier === '') {
            $message = 'toDublinCore Error @ Episciences_Repositories_Dataverse::hookApiRecords: ';
            $message .= 'Undefined identifier';
            trigger_error($message);
        }

        $headers = [
            'identifier' => $urlIdentifier,
        ];

        $result[self::TO_COMPILE_OAI_DC]['headers'] = $headers;
        $result[self::TO_COMPILE_OAI_DC]['date'] = isset($data['releaseTime']) ? date_create($data['releaseTime'])->format('Y-m-d') : '';

        if (isset($data['license']['uri'])) {
            $license = $data['license']['uri'];

        } elseif (isset($data['termsOfUse'])) {
            // @see entrepot.recherche.data.gouv responses
            /**
             * Exemple 1 #doi:10.15454/JB2WCE
             * <img src=\"https://licensebuttons.net/l/by-sa/3.0/88x31.png\" alt=\"CC BY-SA\" height=\"100\">\r\n<br>\r\n
             * <a href=\"https://creativecommons.org/licenses/by-sa/4.0/\">
             * Attribution - Partage dans les mêmes conditions / Attribution - Share alike licence version 4.0</a>
             *
             */

            /**
             * Exemple 2 #doi:10.57745/3K4BYX
             * <a rel="license" href="http://creativecommons.org/licenses/by-sa/4.0/"><img alt="Licence Creative Commons"
             * style="border-width:0" src="https://i.creativecommons.org/l/by-sa/4.0/88x31.png" /></a><br />
             * Ce(tte) œuvre est mise à disposition selon les termes de la <a rel="license"
             * href="http://creativecommons.org/licenses/by-sa/4.0/">
             * Licence Creative Commons Attribution -  Partage dans les Mêmes Conditions 4.0 International</a>.
             */


            $explodedTermsOfUse = explode("<a", $data['termsOfUse']);
            $processed = trim(str_replace('"', '', stripslashes(explode(">", $explodedTermsOfUse[array_key_last($explodedTermsOfUse)])[0])));

            if (str_contains($processed, 'href=')) {
                $license = mb_substr($processed, 5); //5 => size of (href=)
                $pattern = '~<a.*?(http|https)://[\w]+\.[\w/-]+[\d.]+[\d]?/\\\?~';
                $isMatched = preg_match_all($pattern, $data['termsOfUse'], $matches, PREG_SET_ORDER, 0);
                if ($isMatched) {
                    $license = explode('href="', stripslashes($matches[0][0]))[1];
                }
            } else {
                $license = $processed; // (eg. darus doi:10.18419/DARUS-4228)
            }

        }

        $metadata = $data['metadataBlocks'];

        $citationFields = $metadata['citation']['fields'] ?? [];

        foreach ($citationFields as $field) {

            if (!isset($field['typeName'], $field['value']) || !in_array($field['typeName'], $fieldsToBeProcessed, true)) {
                continue;
            }

            $isMultipleField = isset($field['multiple']) && $field['multiple'];

            $key = $field['typeName'] . 's';
            $$key = [];

            if ($isMultipleField) {

                $fieldValues = $field['value'];

                foreach ($fieldValues as $val) {

                    $tmp = [];
                    $currentAuthorAffiliations = [];

                    if (!is_array($val)) {
                        $val = (array)$val;
                    }

                    foreach ($val as $cVal) {

                        $isMultipleValue = isset($cVal['multiple']) && $cVal['multiple'];

                        if (!$isMultipleValue) {

                            $extractedValue = $cVal['value'] ?? $cVal;

                            if ($key === 'authors') {

                                if (isset($cVal['typeName'])) {


                                    if ($cVal['typeName'] !== 'authorName' && $cVal['typeName'] !== 'authorAffiliation') {
                                        continue;
                                    }

                                    if ($cVal['typeName'] === 'authorName') {

                                        $currentAuthor = $extractedValue;
                                        $creators[] = $currentAuthor;
                                        $explodedAuthor = explode(', ', $currentAuthor);

                                        /**
                                         *
                                         * {
                                         * "fullname": "xxxxxxxx",
                                         * "given": "xxxxxxxx",
                                         * "family": "",
                                         * "orcid": "0000-0000-0000-0000",
                                         * "affiliation": {
                                         * "0": {
                                         * "name": "xxxxxxxx",
                                         * }
                                         * }
                                         * }
                                         */


                                        $tmp['fullname'] = $currentAuthor;
                                        $tmp['given'] = isset($explodedAuthor[1]) ? trim($explodedAuthor[1]) : '';
                                        $tmp['family'] = isset($explodedAuthor[0]) ? trim($explodedAuthor[0]) : '';
                                        if (
                                            isset($val['authorIdentifierScheme']['value']) &&
                                            $val['authorIdentifierScheme']['value'] === 'ORCID'
                                        ) {
                                            $tmp['orcid'] = Episciences_Paper_AuthorsManager::normalizeOrcid($val['authorIdentifier']['value']);
                                        }

                                    } else {
                                        $currentAuthorAffiliations[] = ['name' => $extractedValue];
                                        $tmp['affiliation'] = $currentAuthorAffiliations;
                                    }

                                }


                            } elseif ($key === 'dsDescriptions') {

                                if (
                                    $cVal['typeName'] !== 'dsDescriptionValue' &&
                                    $cVal['typeName'] !== 'dsDescriptionDate' &&
                                    $cVal['typeName'] !== 'dsDescriptionLanguage'
                                ) {
                                    continue;
                                }

                                if ($cVal['typeName'] === 'dsDescriptionValue') {
                                    $tmp['value'] = strip_tags($extractedValue);
                                } elseif ($cVal['typeName'] === 'dsDescriptionLanguage') {
                                    $tmp['language'] = lcfirst(mb_substr($extractedValue, 0, 2));
                                } elseif ($cVal['typeName'] === 'dsDescriptionDate') {
                                    $tmp['date'] = $extractedValue;
                                }


                            } elseif ($key === 'subjects' || $key === 'keywords' || $key === 'languages' || $key === 'kindOfDatas') {
                                $tmp = ($key !== 'languages') ? $extractedValue : lcfirst(mb_substr($extractedValue, 0, 2));

                            } elseif ($key === 'publications') { // citations > The article or report using the dataset

                                /**
                                 * {
                                 * "0": {
                                 * "author": "xxx, xxxxx",
                                 * "year": "2019",
                                 * "title": "xxxxxx",
                                 * "source_title": "xxxxxxxx"
                                 * "volume": "27",
                                 * "issue": "2",
                                 * "page": "187",
                                 * "doi": "10.123/xx-2019-0014",
                                 * "oa_link": ""
                                 * }
                                 * }
                                 */

                                if ($cVal['typeName'] === 'publicationCitation') {
                                    $tmp['title'] = $extractedValue;
                                } elseif ($cVal['typeName'] === 'publicationIDType') {
                                    $tmp['source_title'] = $extractedValue;
                                } elseif ($cVal['typeName'] === 'publicationIDNumber') {
                                    $tmp['IDNumber'] = $extractedValue;
                                } elseif ($cVal['typeName'] === 'publicationURL') {
                                    $tmp['oa_link'] = $extractedValue;
                                }


                            } elseif ($key === 'projects') {

                                /**
                                 * project
                                 *
                                 *
                                 * {
                                 * "0": {
                                 * "projectTitle": "xxxxxxxx",
                                 * "acronym": "XXXXX",
                                 * "funderName": "xxxxxxxxxx",
                                 * "code": "658162"
                                 * }
                                 * }
                                 */


                                if ($cVal['typeName'] === 'projectName') {
                                    $projectTitle = $extractedValue;
                                } elseif ($cVal['typeName'] === 'projectTitle') {
                                    $projectTitle = $extractedValue;
                                } elseif ($cVal['typeName'] === 'projectAcronym') {

                                    $tmp['acronym'] = $extractedValue;

                                } elseif ($cVal['typeName'] === 'projectURL') {
                                    $tmp['url'] = $extractedValue;
                                }

                                $tmp['projectTitle'] = $projectTitle ?? Episciences_Paper_ProjectsManager::UNIDENTIFIED;
                                $tmp['acronym'] = $tmp['acronym'] ?? Episciences_Paper_ProjectsManager::UNIDENTIFIED;
                                $tmp['funderName'] = $tmp['funderName'] ?? Episciences_Paper_ProjectsManager::UNIDENTIFIED;
                                $tmp['code'] = $tmp['code'] ?? Episciences_Paper_ProjectsManager::UNIDENTIFIED;

                            }

                        }

                    }

                    if (!empty($tmp)) {
                        $$key[] = $tmp;
                    }
                }

            } else {
                $$key[] = $field['value'];
            }

            if (in_array($key, $extractedOaiDcFields, true)) {
                $result[self::TO_COMPILE_OAI_DC][$key] = $$key;

                if ($key === 'kindOfDatas') {
                    $result[Episciences_Repositories_Common::ENRICHMENT][Episciences_Repositories_Common::RESOURCE_TYPE_ENRICHMENT] = $$key;
                }

            } elseif ($key === 'publications') {
                $result[Episciences_Repositories_Common::ENRICHMENT][Episciences_Repositories_Common::CITATIONS] = $$key;
            } else {
                $result[Episciences_Repositories_Common::ENRICHMENT][$key] = $$key;
            }


        }

        $result[self::TO_COMPILE_OAI_DC]['creators'] = $creators;
        $result[self::TO_COMPILE_OAI_DC]['license'] = $license;

        $files = $data['files'] ?? [];

        $result[Episciences_Repositories_Common::ENRICHMENT]['files'] = self::processFiles($files, $hookParams);

        $data = $result;
    }

    private static function processFiles(array $files = [], $hookParams = []): array
    {

        if (empty($files)) {
            return $files;
        }

        $processedFiles = [];

        foreach ($files as $val) {

            $tmp = [];
            $dataFile = $val['dataFile'];

            $fileName = $dataFile['filename'] ?? $dataFile['label'];
            $explodedFileName = explode('.', $fileName);

            $tmp['file_name'] = $fileName;
            $tmp['file_type'] = $explodedFileName[array_key_last($explodedFileName)] ?? 'undefined';
            $tmp['file_size'] = $dataFile['filesize'];
            $tmp['checksum'] = $dataFile['checksum']['value'];
            $tmp['checksum_type'] = $dataFile['checksum']['type'];
            $tmp['self_link'] = self::getAssembledLink($val, $hookParams['repoId']);
            $processedFiles[] = $tmp;
        }

        return $processedFiles;

    }

    private static function getAssembledLink(array $values, ?int $repoId = null): string
    {

        if (isset($values['dataFile']['pidURL'])) {
            return $values['dataFile']['pidURL'];
        }

        if ($repoId && isset($values['dataFile']['id'])) {
            $assembledLink = preg_replace('#api/v\d/#', '', Episciences_Repositories::getApiUrl($repoId));
            $assembledLink .= 'file.xhtml?fileId=';
            $assembledLink .= $values['dataFile']['id'];
            if (isset($values['version'])) {
                $assembledLink .= sprintf('&version=%s', $values['version']);
            }

            return $assembledLink;

        }

        return '#';

    }

    public static function hookIsIdentifierCommonToAllVersions(): array
    {
        return ['result' => false];
    }
}