<?php

use Episciences\Repositories\CommonHooksInterface;
use Episciences\Repositories\InputSanitizerInterface;

class Episciences_Repositories_CryptologyePrint_Hooks implements CommonHooksInterface, InputSanitizerInterface
{
    public const SELF_URL = 'https://eprint.iacr.org/';
    public const UPDATE_DATETIME = 'update'; // to point a specific version (not managed by the OAI) of paper

    /**
     * @param array $hookParams
     * @return array
     * @throws Ccsd_Error
     */

    public static function hookApiRecords(array $hookParams): array
    {
        //https://eprint.iacr.org/oai?verb=GetRecord&metadataPrefix=oai_dc&identifier=oai:eprint.iacr.org:1996/002

        if (!isset($hookParams['identifier'], $hookParams['repoId'])) {
            return [];
        }

        // Pour vérifier la version actuelle, car l'OAI renvoi la dernière version.
        // Quelle que soit la version demandée, les métadonnées récupérées sont celles de la dernière version ;
        // je me suis assuré que si une version est spécifiée, les liens pointent vers cette version
        // https://eprint.iacr.org/2026/192 : dernière version. PDF : https://eprint.iacr.org/2026/192.pdf
        // https://eprint.iacr.org/archive/2026/192/20260205:224930 : version spécifique. PDF : https://eprint.iacr.org/archive/2026/192/1770331770.pdf :

        $extractedDateTime = Episciences_Repositories_Common::getDateTimePattern($hookParams['identifier']);
        // Le format de l'identifiant dans la base de données est "YYYY/XXX/dateTime" ; il n'est pas pris en charge par l'appel OAI ; d'où la nécessité de le nettoyer.
        $identifier = rtrim(Episciences_Repositories_Common::removeDateTimePattern($hookParams['identifier']), '/');
        $oaiIdentifier = Episciences_Repositories::getIdentifier($hookParams['repoId'], $identifier);
        $record = Episciences_Repositories_Common::getRecord(Episciences_Repositories::getBaseUrl($hookParams['repoId']), $oaiIdentifier);

        $xml = simplexml_load_string($record);

        if ($xml === false) {
            return [];
        }

        $header = $xml->header;
        $isoDate = (string)$header->datestamp;

        $data['record'] = $record;

        try {
            $date = new DateTime($isoDate);

            $formatted = $date->format('Ymd:His'); // is used to target a specific version of a document
            $data[self::UPDATE_DATETIME] = $extractedDateTime !== '' ? $extractedDateTime : $formatted; // Sinon, la mise à jour des métadonnées entraînera la mise à jour de la version (y compris le PDF).
        } catch (Exception $e) {
            Episciences_View_Helper_Log::log($e->getMessage());
        }

        $type = Episciences_Tools::xpath($record, '//dc:type');

        if (!empty($type)) {
            $data[Episciences_Repositories_Common::ENRICHMENT][Episciences_Repositories_Common::RESOURCE_TYPE_ENRICHMENT] = $type;
        }

        $data['conceptrecid'] = $identifier; // identique pour toutes les versions ; renvoie à la dernière version

        return $data;
    }

    public static function hookCleanIdentifiers(array $hookParams): array
    {
        $identifier = rtrim(str_replace(array(self::SELF_URL, 'archive/'), '', $hookParams['id']), '/');

        return [
            Episciences_Repositories_Common::META_IDENTIFIER => $identifier
        ];
    }

    public static function hookVersion(array $hookParams): array
    {
        return [];
    }

    public static function hookIsRequiredVersion(): array
    {
        return ['result' => false];
    }

    public static function hookIsIdentifierCommonToAllVersions(): array
    {
        return ['result' => true];
    }
}