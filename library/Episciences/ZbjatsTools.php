<?php

use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class Episciences_ZbjatsTools
{
    /**
     * @param array $jsonRefBib
     * @return array
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function jsonToZbjatBibRef(array $jsonRefBib): array
    {
        $refsInfo = [];
        foreach ($jsonRefBib as $refBib) {
            $doiInfo = '';
            $dir = CACHE_PATH_METADATA . 'zbjatRefBib/';
            if (!is_dir($dir) && !mkdir($dir, 0776, true) && !is_dir($dir)) {
                trigger_error(sprintf('Upload file failed: directory "%s" was not created', $dir));
                continue;
            }
            if (isset($refBib['doi']) && $refBib['doi'] !== '') {
                $cacheZbjatJsonRefBib = new FilesystemAdapter('zbjatRefBib', 0, CACHE_PATH_METADATA);
                $file = $cacheZbjatJsonRefBib->getItem(md5($refBib['doi']) . '.json');
                if (!$file->isHit()) {
                    if (PHP_SAPI === "cli") {
                        echo 'CALL API TO GET BIBLIOGRAPHICAL REFERENCES ' . $refBib['doi'] . PHP_EOL;
                    }
                    if (Episciences_tools::isDoi($refBib['doi'])) {
                        $doiInfo = Episciences_DoiTools::getMetadataFromDoi($refBib['doi']);
                    } elseif (Episciences_Tools::isArxiv($refBib['doi'])) {
                        $doiInfo = Episciences_DoiTools::getMetadataFromDoi(Episciences_Repositories::getRepoDoiPrefix(Episciences_Repositories::ARXIV_REPO_ID) . '/' . "arXiv." . $refBib['doi']);
                    }
                    if ($doiInfo !== "") {
                        $file->set($doiInfo);
                        $cacheZbjatJsonRefBib->save($file);
                    }
                } else {
                    if (PHP_SAPI === "cli") {
                        echo 'GET BIBLIOGRAPHICAL REFERENCES FROM CACHE ' . $refBib['doi'] . PHP_EOL;
                    }
                    $doiInfo = $file->get();
                }
                // get tei hal
                if (Episciences_Tools::isHal($refBib['doi'])
                    || !empty(Episciences_Tools::getHalIdAndVer($refBib['doi']))) {
                    $doiInfo = self::getTeiHalForZbjatExport($refBib['doi']);
                }
                if ($doiInfo !== '') {
                    if (Episciences_Tools::isHal($refBib['doi'])
                        || !empty(Episciences_Tools::getHalIdAndVer($refBib['doi']))) {
                        $refsInfo[] = $doiInfo;
                    } else {
                        $refsInfo[] = self::cslToJats($doiInfo);
                    }
                }
            } elseif (array_key_exists('csl',$refBib) && !isset($refBib['doi'])){
                $csl = json_decode($refBib['csl'],true)['csl'];
                $csl['published'] = $csl['issued'];
                unset($csl['issued']);
                $removeLayer = json_encode($csl);
                $refsInfo[] = self::cslToJats($removeLayer);
            } elseif (!array_key_exists('doi',$refBib) &&
                !array_key_exists('csl',$refBib) &&
                array_key_exists('unstructured_citation',$refBib)){
                $refsInfo[]['mixed-citation'] = $refBib['unstructured_citation'];
            }
        }
        return $refsInfo;
    }

    /**
     * @param string $cslJsonRefBib
     * @return array
     * @throws JsonException
     */
    public static function cslToJats(string $cslJsonRefBib): array
    {
        $refToJatsFormat = [];
        $cslJsonRefBib = json_decode($cslJsonRefBib, true, 512, JSON_THROW_ON_ERROR);
        if (isset($cslJsonRefBib['type'])) {
            $refToJatsFormat['publication-type'] = $cslJsonRefBib['type'];
        }
        $refAuthors = [];
        if (isset($cslJsonRefBib['author'])) {
            $indexAuthors = 0;
            foreach ($cslJsonRefBib['author'] as $author) {
                if (isset($author['family'])) {
                    $refAuthors[$indexAuthors]['surname'] = $author['family'];
                }
                if (isset($author['given'])) {
                    $refAuthors[$indexAuthors]['given-names'] = $author['given'];
                }
                $indexAuthors++;
            }
            $refToJatsFormat['authors'] = $refAuthors;
        }
        if ($cslJsonRefBib['type'] !== "article-journal" && $cslJsonRefBib['type'] !== "journal-article") {
            if (isset($cslJsonRefBib["container-title"])
                && !is_array($cslJsonRefBib["container-title"])
                && $cslJsonRefBib["container-title"] !== "") {
                $refToJatsFormat['source'] = $cslJsonRefBib["container-title"];
            }
            if (isset($cslJsonRefBib["title"])){
                $refToJatsFormat['source'] = $cslJsonRefBib["title"];
            }
//            elseif(isset($cslJsonRefBib["publisher"])) {
//                $refToJatsFormat['source'] = $cslJsonRefBib["publisher"];
//            }
        } else {
            if (isset($cslJsonRefBib["container-title"])){
                $refToJatsFormat['source'] = $cslJsonRefBib["container-title"];
            }
            if (!is_array($cslJsonRefBib["title"])) {
                $refToJatsFormat['article-title'] = $cslJsonRefBib["title"];
            }
        }
        if (isset($cslJsonRefBib['DOI'])) {
            $refToJatsFormat['doi'] = $cslJsonRefBib['DOI'];
        }
        if (isset($cslJsonRefBib['ISSN'])) {
            $refToJatsFormat['issn'] = $cslJsonRefBib['ISSN'][0];
        }
        if (isset($cslJsonRefBib['published'])) {
            $refToJatsFormat['year'] = $cslJsonRefBib['published']['date-parts'][0][0];
        }
        if (isset($cslJsonRefBib['volume'])) {
            $refToJatsFormat['volume'] = $cslJsonRefBib["volume"];
        }
        if (isset($cslJsonRefBib['issue'])) {
            $refToJatsFormat['issue'] = $cslJsonRefBib["issue"];
        }
        if (isset($cslJsonRefBib['page'])) {
            $getFirstAndLast = explode('-', $cslJsonRefBib['page']);
            $refToJatsFormat['fpage'] = $getFirstAndLast[0];
            if (count($getFirstAndLast) > 1) {
                $refToJatsFormat['lpage'] = $getFirstAndLast[1];
            }
        }
        return $refToJatsFormat;
    }

    /**
     * @param string $halId
     * @return array
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function getTeiHalForZbjatExport(string $halId): array
    {
        $refToJatsFormat = [];
        $halIdMatches = Episciences_Tools::getHalIdAndVer($halId);
        if (isset($halIdMatches[2])) {
            $tei = Episciences_Paper_AuthorsManager::getHalTei(str_replace($halIdMatches[2], '', $halIdMatches[1]),
                (int)str_replace('v', '', $halIdMatches[2]));
            $cacheTeiHal = Episciences_Paper_AuthorsManager::getHalTeiCache(
                str_replace($halIdMatches[2], '', $halIdMatches[1]),
                (int)str_replace('v', '', $halIdMatches[2]));
        } else {
            $tei = Episciences_Paper_AuthorsManager::getHalTei($halIdMatches[1]);
            $cacheTeiHal = Episciences_Paper_AuthorsManager::getHalTeiCache($halIdMatches[1]);
        }
        if ($cacheTeiHal !== '') {
            $xmlString = simplexml_load_string($cacheTeiHal);
            if (is_object($xmlString) && $xmlString->count() > 0) {
                $authorTei = Episciences_Paper_AuthorsManager::getAuthorsFromHalTei($xmlString);
                foreach ($authorTei as $key => $author) {
                    if ($author['family'] !== '') {
                        $refToJatsFormat['authors'][$key]['surname'] = $author['family'];
                    }
                    if ($author['given_name'] !== '') {
                        $refToJatsFormat['authors'][$key]['given-names'] = $author['given_name'];
                    }
                }
                $docType = self::getDocTypeFromTeiHal($xmlString);
                if ($docType !== "") {
                    $refToJatsFormat['publication-type'] = $docType;
                    $refToJatsFormat = self::getTitleAndSource($docType, $xmlString, $refToJatsFormat);
                    $refToJatsFormat = self::getDoiIfExist($xmlString, $refToJatsFormat);
                    $refToJatsFormat = self::getIssnsIfExists($xmlString, $refToJatsFormat);
                    $refToJatsFormat = self::getIsbnIfExists($xmlString, $refToJatsFormat);
                    if (isset($xmlString->text->body->listBibl->biblFull->sourceDesc->biblStruct->monogr->imprint)) {
                        $imprint = $xmlString->text->body->listBibl->biblFull->sourceDesc->biblStruct->monogr->imprint;
                        $refToJatsFormat = self::getVolumesInfos($imprint, $refToJatsFormat);
                        $refToJatsFormat = self::getYear($xmlString, $refToJatsFormat);
                    }
                }
            }
        }
        return $refToJatsFormat;
    }

    /**
     * @param $xmlString
     * @return string
     */
    public static function getDocTypeFromTeiHal($xmlString): string
    {
        $docType = "";
        if (isset($xmlString->text->body->listBibl->biblFull->profileDesc)) {
            $classCode = $xmlString->text->body->listBibl->biblFull->profileDesc->textClass->classCode;
            foreach ($classCode as $domain) {
                if ((string)$domain->attributes()->scheme === 'halTypology') {
                    return (string)$domain->attributes()->n;
                }
            }
        }
        return $docType;
    }

    /**
     * @param $xmlString
     * @param array $refToJatsFormat
     * @return array
     */
    public static function getDoiIfExist($xmlString, array $refToJatsFormat): array
    {
        if (isset($xmlString->text->body->listBibl->biblFull->sourceDesc->biblStruct->idno)) {
            $idno = $xmlString->text->body->listBibl->biblFull->sourceDesc->biblStruct->idno;
            if ($idno !== null) {
                foreach ($idno as $info) {
                    if ((string)$info->attributes()->type === 'doi') {
                        $refToJatsFormat['doi'] = (string)$info;
                        break;
                    }
                }
            }
        }
        return $refToJatsFormat;
    }

    /**
     * @param SimpleXMLElement $imprint
     * @param array $refToJatsFormat
     * @return array
     */
    public static function getVolumesInfos(SimpleXMLElement $imprint, array $refToJatsFormat): array
    {
        if (isset($imprint->biblScope)) {
            foreach ($imprint->biblScope as $biblScope) {
                if ((string)$biblScope->attributes()->unit === 'volume') {
                    $refToJatsFormat['volume'] = (string)$biblScope;
                }
                if ((string)$biblScope->attributes()->unit === 'issue') {
                    $refToJatsFormat['issue'] = (string)$biblScope;
                }
                if ((string)$biblScope->attributes()->unit === 'pp') {
                    $getFirstAndLast = explode('-', (string)$biblScope);
                    $refToJatsFormat['fpage'] = $getFirstAndLast[0];
                    if (count($getFirstAndLast) > 1) {
                        $refToJatsFormat['lpage'] = $getFirstAndLast[1];
                    }
                }
            }
        }
        return $refToJatsFormat;
    }

    /**
     * @param $xmlString
     * @param array $refToJatsFormat
     * @return array
     */
    public static function getYear($xmlString, array $refToJatsFormat): array
    {
        if (isset($xmlString->text->body->listBibl->biblFull->editionStmt->edition->date)) {
            foreach ($xmlString->text->body->listBibl->biblFull->editionStmt->edition->date as $date) {
                if (((string)$date->attributes()->type === 'whenProduced')
                    && preg_match("~\d{4}~", $date, $matches)) {
                    $refToJatsFormat['year'] = $matches[0];
                    break;
                }
            }
        }
        return $refToJatsFormat;
    }

    /**
     * @param $xmlString
     * @param array $refToJatsFormat
     * @return array
     */
    public static function getIssnsIfExists($xmlString, array $refToJatsFormat): array
    {
        if (isset($xmlString->text->body->listBibl->biblFull->sourceDesc->biblStruct->monogr,
            $xmlString->text->body->listBibl->biblFull->sourceDesc->biblStruct->monogr->idno)) {
            $idno = $xmlString->text->body->listBibl->biblFull->sourceDesc->biblStruct->monogr->idno;
            foreach ($idno as $infoId) {
                if ((string)$infoId->attributes()->type === 'issn') {
                    $refToJatsFormat['issn'] = (string)$infoId;
                }
                if ((string)$infoId->attributes()->type === 'eissn') {
                    $refToJatsFormat['eissn'] = (string)$infoId;
                }

            }
        }
        return $refToJatsFormat;
    }

    public static function getIsbnIfExists($xmlString, array $refToJatsFormat): array
    {
        if (isset($xmlString->text->body->listBibl->biblFull->sourceDesc->biblStruct->monogr,
            $xmlString->text->body->listBibl->biblFull->sourceDesc->biblStruct->monogr->idno)) {
            $idno = $xmlString->text->body->listBibl->biblFull->sourceDesc->biblStruct->monogr->idno;
            foreach ($idno as $infoId) {
                if ((string)$infoId->attributes()->type === 'isbn') {
                    $refToJatsFormat['isbn'] = (string)$infoId;
                }

            }
        }
        return $refToJatsFormat;
    }

    /**
     * @param string $docType
     * @param $xmlString
     * @param array $refToJatsFormat
     * @return array
     */
    public static function getTitleAndSource(string $docType, $xmlString, array $refToJatsFormat): array
    {

        if ($docType === 'ART') {
            if (isset($xmlString->text->body->listBibl->biblFull->titleStmt->title)) {
                $refToJatsFormat['article-title'] =
                    (string)$xmlString->text->body->listBibl->biblFull->titleStmt->title[0];
            }
            $refToJatsFormat['source'] = (string)$xmlString->text->body->listBibl->biblFull->sourceDesc->biblStruct->monogr->title;
        } else {
            if (isset($xmlString->text->body->listBibl->biblFull->titleStmt->title)) {
                $refToJatsFormat['source'] = (string)$xmlString->text->body->listBibl->biblFull->titleStmt->title[0];
            }
        }
        $refToJatsFormat['lang-article'] = (string) $xmlString->text->body->listBibl->biblFull->titleStmt->title[0]->attributes('xml', TRUE)['lang'];
        return $refToJatsFormat;
    }
}