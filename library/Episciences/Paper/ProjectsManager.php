<?php

use GuzzleHttp\Client;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class Episciences_Paper_ProjectsManager
{
    public const ONE_MONTH = 3600 * 24 * 31;
    public const UNIDENTIFIED = 'unidentified';

    public static function getProjectsByPaperIdAndSourceId(int $paperId, int $sourceId): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->from(T_PAPER_PROJECTS)->where('PAPERID = ?', $paperId)->where('source_id = ?', $sourceId); // prevent empty row
        return $db->fetchAssoc($select);
    }

    /**
     * @throws JsonException
     */
    public static function getProjectWithDuplicateRemoved($paperId)
    {
        $allProjects = self::getProjectsByPaperId($paperId);
        $rawFunding = [];
        foreach ($allProjects as $project) {
            $decodeProject = json_decode($project['funding'], true, 512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $rawFunding[] = $decodeProject;

        }
        $rawFunding = array_unique($rawFunding, SORT_REGULAR);

        //reorganise to simply the array
        $finalFundingArray = [];
        foreach ($rawFunding as $fundings) {
            foreach ($fundings as $funding) {
                $finalFundingArray[] = $funding;
                ksort($finalFundingArray[array_key_last($finalFundingArray)]); // useful for the unserialize check
            }
        }

        $finalFundingArray = array_map("unserialize", array_unique(array_map("serialize", $finalFundingArray)));

        return $finalFundingArray;
    }

    public static function getProjectsByPaperId(int $paperId): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->from(["project" => T_PAPER_PROJECTS])->joinLeft(["source_paper" => T_PAPER_METADATA_SOURCES], "project.source_id = source_paper.id", ["source_id_name" => 'source_paper.name'])->where('PAPERID = ?', $paperId); // prevent empty row
        return $db->fetchAssoc($select);
    }

    /**
     * @param $paperId
     * @return string|string[]
     * @throws JsonException
     * @throws Zend_Exception
     */
    public static function formatProjectsForview($paperId)
    {
        $rawInfo = self::getProjectsByPaperId($paperId);
        $translator = Zend_Registry::get('Zend_Translate');
        if (!empty($rawInfo)) {
            $rawFunding = [];
            $templateProject = "";
            foreach ($rawInfo as $value) {
                $rawFunding[$value['source_id_name']][] = json_decode($value['funding'], true, 512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            }
            foreach ($rawFunding as $source_id_name => $fundingInfo) {
                $templateProject .= "<ul class='list-unstyled'>";
                $templateProject .= " <small class='label label-default'>" . $translator->translate('Source :') . ' ' . $source_id_name . "</small>";
                foreach ($fundingInfo as $counter => $funding) {
                    foreach ($funding as $kf => $vfunding) {
                        if (isset($vfunding['projectTitle']) && $vfunding['projectTitle'] !== self::UNIDENTIFIED) {
                            $templateProject .= '<li><em>' . htmlspecialchars($vfunding['projectTitle']) . "</em>";
                            if (isset($vfunding['funderName']) && $vfunding['funderName'] !== self::UNIDENTIFIED) {
                                $templateProject .= "; " . $translator->translate("Funder") . ": " . htmlspecialchars($vfunding['funderName']);
                            }
                        } elseif (isset($vfunding['funderName']) && $vfunding['funderName'] !== self::UNIDENTIFIED) {
                            $templateProject .= "<li>" . $translator->translate("Funder") . ": " . htmlspecialchars($vfunding['funderName']);
                        }
                        if (
                            isset($vfunding['code']) && $vfunding['code'] !== self::UNIDENTIFIED &&
                            (
                                (isset($vfunding['funderName']) && $vfunding['funderName'] !== self::UNIDENTIFIED) ||
                                (isset($vfunding['projectTitle']) && $vfunding['projectTitle'] !== self::UNIDENTIFIED)
                            )
                        ) {
                            $templateProject .= "; Code: " . htmlspecialchars($vfunding['code']);
                        }
                        if (isset($vfunding['callId']) && $vfunding['callId'] !== self::UNIDENTIFIED) {
                            $templateProject .= "; " . $translator->translate("callId") . ": " . htmlspecialchars($vfunding['callId']);
                        }
                        if (isset($vfunding['projectFinancing']) && $vfunding['projectFinancing'] !== self::UNIDENTIFIED) {
                            $templateProject .= "; " . $translator->translate("projectFinancing") . ": " . htmlspecialchars($vfunding['projectFinancing']);
                        }

                        if (isset($vfunding['url']) && $vfunding['url'] !== ''){
                            $templateProject .= "; ";
                            $templateProject .= '<a href="' . $vfunding['url'] . '">';
                            $templateProject .= $vfunding['url'];
                            $templateProject.= '</a>';
                        }
                        $templateProject .= "</li>";
                    }

                }
                $templateProject .= "</ul>";
            }
            return ['funding' => $templateProject];

        }
        return "";
    }

    /**
     * @param $fileFound
     * @param array $fundingArray
     * @param array $globalfundingArray
     * @return array
     */
    public static function formatFundingOAForDB($fileFound, array $fundingArray, array $globalfundingArray): array
    {
        foreach ($fileFound as $openAireKey => $valueOpenAire) {
            if (array_key_exists('to', $valueOpenAire) && array_key_exists('@type', $valueOpenAire['to']) && $valueOpenAire['to']['@type'] === "project") {
                if (array_key_exists('title', $valueOpenAire)) {
                    $fundingArray['projectTitle'] = $valueOpenAire['title']['$'];
                }
                if (array_key_exists('acronym', $valueOpenAire)) {
                    $fundingArray['acronym'] = $valueOpenAire['acronym']['$'];
                }
                if (array_key_exists('funder', $valueOpenAire['funding'])) {
                    $fundingArray['funderName'] = $valueOpenAire['funding']['funder']['@name'];
                }
                if (array_key_exists('code', $valueOpenAire)) {
                    $fundingArray['code'] = $valueOpenAire['code']['$'];
                }

                $globalfundingArray[] = $fundingArray;

            }
        }
        return $globalfundingArray;
    }

    /**
     * @param array $globalfundingArray
     * @param array $rowInDBGraph
     * @param int $paperId
     * @return int
     * @throws JsonException
     */
    public static function insertOrUpdateFundingOA(array $globalfundingArray, array $rowInDBGraph, int $paperId): int
    {
        if (!empty($globalfundingArray) && empty($rowInDBGraph)) {
            if (PHP_SAPI === 'cli') {
                echo ('Project Found ' . json_encode($globalfundingArray, JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)) . PHP_EOL;
            }
            return self::insert(
                [
                    'funding' => json_encode($globalfundingArray, JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'paperId' => $paperId,
                    'source_id' => Episciences_Repositories::GRAPH_OPENAIRE_ID
                ]
            );

        }

        if (!empty($globalfundingArray) && !empty($rowInDBGraph)) {
            return self::update(
                [
                    'funding' => json_encode($globalfundingArray, JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'paperId' => $paperId,
                    'source_id' => Episciences_Repositories::GRAPH_OPENAIRE_ID
                ]
            );
        }
        return 0;
    }

    /**
     * @param $projects
     * @return int
     */

    public static function insert($projects): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $values = [];

        $affectedRows = 0;


        if (!($projects instanceof Episciences_Paper_Projects)) {

            $projects = new Episciences_Paper_Projects($projects);
        }

        $values[] = '(' . $db->quote($projects->getFunding()) . ',' . $db->quote($projects->getPaperid()) . ' , ' . $db->quote($projects->getSourceId()) . ')';

        $sql = 'INSERT INTO ' . $db->quoteIdentifier(T_PAPER_PROJECTS) . ' (`funding`,`paperid`, `source_id`) VALUES ';

        if (!empty($values)) {
            try {
                //Prepares and executes an SQL
                /** @var Zend_Db_Statement_Interface $result */
                $result = $db->query($sql . implode(', ', $values) . ' ON DUPLICATE KEY UPDATE funding=VALUES(funding)');
                $affectedRows = $result->rowCount();
            } catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_ERROR);
            }
        }
        return $affectedRows;

    }

    public static function update($projects): int
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!($projects instanceof Episciences_Paper_Projects)) {
            $projects = new Episciences_Paper_Projects($projects);
        }
        $where['paperid = ?'] = $projects->getPaperId();
        $where['source_id = ?'] = $projects->getSourceId();
        $values = [
            'funding' => $projects->getFunding()
        ];
        try {
            $resUpdate = $db->update(T_PAPER_PROJECTS, $values, $where);
        } catch (Zend_Db_Adapter_Exception $exception) {
            $resUpdate = 0;
            trigger_error($exception->getMessage(), E_USER_ERROR);
        }
        return $resUpdate;

    }

    /**
     * @param array $rowInDbHal
     * @param array $mergeArrayANREU
     * @param int $paperId
     * @return int
     * @throws JsonException
     */
    public static function insertOrUpdateHalFunding(array $rowInDbHal, array $mergeArrayANREU, int $paperId): int
    {
        if (!empty($rowInDbHal) && !empty($mergeArrayANREU)) {
            if (PHP_SAPI === 'cli') {
                echo 'HAL PROJECT UPDATED' . PHP_EOL;
            }
            return self::update(
                [
                    'funding' => json_encode($mergeArrayANREU, JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'paperId' => $paperId,
                    'source_id' => Episciences_Repositories::HAL_REPO_ID
                ]
            );


        }

        if (!empty($mergeArrayANREU) && empty($rowInDbHal)) {
            if (PHP_SAPI === 'cli') {
                echo 'NEW HAL PROJECT INSERTED' . PHP_EOL;
            }
            return self::insert(
                [
                    'funding' => json_encode($mergeArrayANREU, JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'paperId' => $paperId,
                    'source_id' => Episciences_Repositories::HAL_REPO_ID
                ]
            );

        }
        return 0;
    }

    public static function CallHAlApiForIdEuAndAnrFunding($identifier, $version)
    {
        $client = new Client();
        $halCallArrayResp = '';
        $url = "https://api.archives-ouvertes.fr/search/?q=((halId_s:" . $identifier . " OR halIdSameAs_s:" . $identifier . ") AND version_i:" . $version . ")&fl=europeanProjectId_i,anrProjectId_i";
        try {
            return $client->get($url, [
                'headers' => [
                    'User-Agent' => 'CCSD Episciences support@episciences.org',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]
            ])->getBody()->getContents();

        } catch (GuzzleException $e) {

            trigger_error($e->getMessage());

        }
        return $halCallArrayResp;
    }

    /**
     * @param $rawArray
     * @param string $identifier
     * @param array $globalArrayJson
     * @return array
     * @throws JsonException
     */
    public static function FormatFundingANREuToArray($rawArray, string $identifier, array $globalArrayJson): array
    {
        $cache = new FilesystemAdapter('enrichmentFunding', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
        foreach ($rawArray as $halValue) {
            if (isset($halValue['europeanProjectId_i'])) {
                foreach ($halValue['europeanProjectId_i'] as $idEuro) {
                    if (PHP_SAPI === "cli") {
                        echo 'European Project found on HAL ' . $idEuro . PHP_EOL;
                    }
                    $fileNameEuro = $identifier . '_' . $idEuro . "_EU_funding.json";
                    $setsEU = $cache->getItem($fileNameEuro);
                    $setsEU->expiresAfter(self::ONE_MONTH);
                    if (!$setsEU->isHit()) {
                        $halEuroResp = self::CallHAlApiForEuroProject($idEuro);
                        $setsEU->set($halEuroResp);
                        $cache->save($setsEU);
                        $globalArrayJson[] = self::formatEuHalResp(json_decode($halEuroResp, true, 512, JSON_THROW_ON_ERROR));
                    } else {
                        $globalArrayJson[] = self::formatEuHalResp(json_decode($setsEU->get(), true, 512, JSON_THROW_ON_ERROR));
                    }
                }
            }
            if (isset($halValue['anrProjectId_i'])) {
                foreach ($halValue['anrProjectId_i'] as $idAnr) {
                    if (PHP_SAPI === "cli") {
                        echo 'ANR Project found on HAL ' . $idAnr . PHP_EOL;
                    }
                    $fileNameAnr = $identifier . '_' . $idAnr . "_ANR_funding.json";
                    $setsANR = $cache->getItem($fileNameAnr);
                    $setsANR->expiresAfter(self::ONE_MONTH);
                    if (!$setsANR->isHit()) {
                        $halAnrResp = self::CallHAlApiForAnrProject($idAnr);
                        if (PHP_SAPI === "cli") {
                            echo 'ANR Project found on HAL ' . $idAnr . PHP_EOL;
                        }
                        $setsANR->set($halAnrResp);
                        $cache->save($setsANR);
                        $globalArrayJson[] = self::formatAnrHalResp(json_decode($halAnrResp, true, 512, JSON_THROW_ON_ERROR));
                    } else {
                        if (PHP_SAPI === "cli") {
                            echo 'ANR Project found in CACHE' . PHP_EOL;
                        }
                        $globalArrayJson[] = self::formatAnrHalResp(json_decode($setsANR->get(), true, 512, JSON_THROW_ON_ERROR));
                    }
                }
            }
        }
        return $globalArrayJson;
    }

    public static function CallHAlApiForEuroProject($halDocId)
    {


        $client = new Client();
        $halCallArrayResp = '';
        $url = "https://api.archives-ouvertes.fr/ref/europeanproject/?q=docid:" . $halDocId . "&fl=projectTitle:title_s,acronym:acronym_s,code:reference_s,callId:callId_s,projectFinancing:financing_s";
        try {
            return $client->get($url, [
                'headers' => [
                    'User-Agent' => 'CCSD Episciences support@episciences.org',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]
            ])->getBody()->getContents();

        } catch (GuzzleException $e) {

            trigger_error($e->getMessage());

        }
        return $halCallArrayResp;
    }

    public static function formatEuHalResp($respEuHAl)
    {

        $arrayAllValuesExpected = [
            'projectTitle' => self::UNIDENTIFIED,
            'acronym' => self::UNIDENTIFIED,
            'funderName' => 'European Commission',
            'code' => self::UNIDENTIFIED,
            'callId' => self::UNIDENTIFIED,
            'projectFinancing' => self::UNIDENTIFIED
        ];
        $arrayEuropean = [];
        if (!empty($respEuHAl['response']['docs'])) {
            $i = 0;
            foreach ($respEuHAl['response']['docs'] as $key => $value) {
                $arrayEuropean[$key] = $value;
                //add unidentified to all key not found
                if (!empty(array_diff_key($arrayAllValuesExpected, $value))) {
                    foreach (array_diff_key($arrayAllValuesExpected, $value) as $keyDiff => $valueDiff) {
                        $arrayEuropean[$i][$keyDiff] = $valueDiff;
                    }
                }
                $i++;
            }
        }
        return $arrayEuropean;
    }

    public static function CallHAlApiForAnrProject($halDocId)
    {


        $client = new Client();
        $halCallArrayResp = '';
        $url = "https://api.archives-ouvertes.fr/ref/anrproject/?q=docid:" . $halDocId . "&fl=projectTitle:title_s,acronym:acronym_s,code:reference_s";
        try {
            return $client->get($url, [
                'headers' => [
                    'User-Agent' => 'CCSD Episciences support@episciences.org',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]
            ])->getBody()->getContents();

        } catch (GuzzleException $e) {

            trigger_error($e->getMessage());

        }
        return $halCallArrayResp;
    }

    public static function formatAnrHalResp($respAnrHAl)
    {

        $arrayAllValuesExpected = [
            'projectTitle' => self::UNIDENTIFIED,
            'acronym' => self::UNIDENTIFIED,
            'funderName' => 'French National Research Agency (ANR)',
            'code' => self::UNIDENTIFIED,
        ];
        $arrayAnr = [];
        if (!empty($respAnrHAl['response']['docs'])) {
            $i = 0;
            foreach ($respAnrHAl['response']['docs'] as $key => $value) {
                $arrayAnr[$key] = $value;
                //add unidentified to all key not found
                if (!empty(array_diff_key($arrayAllValuesExpected, $value))) {
                    foreach (array_diff_key($arrayAllValuesExpected, $value) as $keyDiff => $valueDiff) {
                        $arrayAnr[$i][$keyDiff] = $valueDiff;
                    }
                }
                $i++;
            }
        }
        return $arrayAnr;
    }
}