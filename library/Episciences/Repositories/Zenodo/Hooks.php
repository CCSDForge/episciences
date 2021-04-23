<?php

use GuzzleHttp\Client;
use function GuzzleHttp\json_decode as json_decodeAlias;

class Episciences_Repositories_Zenodo_Hooks implements Episciences_Repositories_HooksInterface
{
    public const API_RECORDS_URL = 'https://zenodo.org/api/records';


    public static function hookCleanXMLRecordInput(array $input): array
    {
        $search = 'xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/';
        $replace = 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/';

        if (array_key_exists('record', $input)) {
            $input['record'] = str_replace($search, $replace, $input['record']);
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

        $cHeaders = [
            'headers' => ['Content-type' => 'application/json']
        ];

        $client = new Client($cHeaders);

        try {
            $response = $client->get(self::API_RECORDS_URL . '/' . $hookParams['identifier']);

        } catch (GuzzleHttp\Exception\RequestException $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);
        }

        $arrayContent = json_decodeAlias($response->getBody()->getContents(), true);
        /** @var array $files */
        $files = $arrayContent['files'];

        /** @var array $file */

        foreach ($files as $file) {

            [$checksumType, $checksum] = explode(':', $file['checksum']);

            $tmpData['doc_id'] = $hookParams['docId'];
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

        Episciences_Paper_FilesManager::insert($data);

        return $arrayContent;

    }

    public static function hookCleanIdentifiers(array $hookParams): array
    {
        $identifier = trim($hookParams['id']);
        $search = Episciences_Repositories::getRepoDoiPrefix($hookParams['repoId']) . '/' . mb_strtolower(Episciences_Repositories::getLabel($hookParams['repoId'])) . '.';
        $identifier = str_replace($search, '', $identifier);

        return ['identifier' => $identifier];
    }
}