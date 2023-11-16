<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class Episciences_Paper_CitationsManager
{
    public const NUMBER_OF_AUTHORS_WANTED_VIEWS = 5;
    public static function insert(array $citations): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $values = [];

        $affectedRows = 0;

        foreach ($citations as $citation) {

            if (!($citation instanceof Episciences_Paper_Citations)) {

                $citation = new Episciences_Paper_Citations($citation);
            }

            $values[] = '(' . $db->quote($citation->getCitation()) . ',' . $db->quote($citation->getDocId()) . ',' . $db->quote($citation->getSourceId()) . ')';

        }
        $sql = 'INSERT INTO ' . $db->quoteIdentifier(T_PAPER_CITATIONS) . ' (`citation`,`docid`,`source_id`) VALUES ';

        if (!empty($values)) {
            try {
                //Prepares and executes an SQL
                /** @var Zend_Db_Statement_Interface $result */
                $result = $db->query($sql . implode(', ', $values) . ' ON DUPLICATE KEY UPDATE citation=VALUES(citation)');
                $affectedRows = $result->rowCount();

            } catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_ERROR);
            }
        }
        return $affectedRows;
    }

    public static function getCitationByDocId($docId){

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(["citation" => T_PAPER_CITATIONS])->joinLeft(['source_paper'=>T_PAPER_METADATA_SOURCES],"citation.source_id = source_paper.id",["source_id_name"=>'source_paper.name'])->where('docid = ? ', $docId)->order("source_id");
        return $db->fetchAssoc($sql);
    }

    public static function formatCitationsForViewPaper($docId){
        $allCitation = self::getCitationByDocId($docId);
        $templateCitation = "";
        $counterCitations = 0;
        $doiOrgDomain = 'https://doi.org/';
        foreach ($allCitation as $value) {
            $decodeCitations = json_decode($value['citation'], true, 512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $counterCitations += count($decodeCitations);
            $decodeCitations = self::sortAuthorAndYear($decodeCitations);
            foreach ($decodeCitations as $citationMetadataArray){
                $templateCitation.="<ul class='list-unstyled'>";
                $templateCitation.="<li>";
                $citationMetadataArray = array_map('strip_tags',$citationMetadataArray);
                if ($citationMetadataArray['type'] === 'book-chapter'){
                    $citationMetadataArray = self::reorganizeForBookChapter($citationMetadataArray);
                } elseif ($citationMetadataArray['type'] === 'proceedings-article'){
                    $citationMetadataArray = self::reorganizeForProceedingsArticle($citationMetadataArray);
                }
                foreach ($citationMetadataArray as $keyMetadata => $metadata) {
                    if ($metadata !== ""){
                        if ($keyMetadata === 'source_title') {
                            $templateCitation.= "<i>".htmlspecialchars($metadata).'</i>';
                        } elseif ($keyMetadata === 'type') {
                            continue;
                        } elseif ($keyMetadata === 'author') {
                            $metadata = self::reduceAuthorsView(htmlspecialchars($metadata));
                            $templateCitation.= "<b>".self::formatAuthors(htmlspecialchars($metadata)).'</b>';
                        } elseif ($keyMetadata === 'page') {
                            $templateCitation.= "pp.&nbsp".trim(htmlspecialchars($metadata));
                        } elseif ($keyMetadata === 'doi') {
                            $templateCitation.= "<a rel='noopener' target='_blank' href=".$doiOrgDomain.$metadata.">".htmlspecialchars($metadata)."</a>";
                        } elseif ($keyMetadata === 'oa_link' && $citationMetadataArray['doi'] !== $metadata){
                            $templateCitation.= "<i class='fas fa-lock-open'></i>"." <a rel='noopener' target='_blank' href=".htmlspecialchars($metadata).">".htmlspecialchars($metadata)."</a>";
                        } elseif ($keyMetadata === 'oa_link' && $citationMetadataArray['doi'] === $metadata){
                            continue;
                        } else {
                            $templateCitation.= htmlspecialchars($metadata);
                        }
                        $templateCitation.= ', ';
                    }
                }
                $templateCitation = substr_replace($templateCitation,".",-2);
                $templateCitation.= "</li>";

            }
            $templateCitation.="</ul>";
            $templateCitation .= "<small class='label label-default'>".Zend_Registry::get('Zend_Translate')->translate('Sources :') . ' ' . "OpenCitations, OpenAlex & Crossref" ."</small>";
            $templateCitation.="<br>";
        }
        return ['template'=>$templateCitation,'counterCitations'=>$counterCitations];
    }

    /**
     * @param array $arrayMetadata
     * @return array
     */
    public static function sortAuthorAndYear(array $arrayMetadata = []) : array {
        usort($arrayMetadata, static function($a, $b) {
            return strcmp($a['author'], $b['author']);
        });
        usort($arrayMetadata, static function($a, $b) {
            return $b['year'] - $a['year'];
        });
        return $arrayMetadata;
    }

    public static function formatAuthors($author){

        $getAllAuthorRow = explode(";",$author);
        $getAllAuthorRow = array_map('trim',$getAllAuthorRow);
        $orcidReg = '/, \d{4}-\d{4}-\d{4}-\d{3}(?:\d|X)/'; //regex with comma
        foreach ($getAllAuthorRow as $value){
            preg_match($orcidReg, $value, $matches);
            if (!empty($matches)){
                $author = str_replace($value,preg_replace($orcidReg, self::createOrcidStringForView($matches[0]), $value),$author);
            }

        }
        return rtrim($author);
    }

    public static function reduceAuthorsView($author){
        $getAllAuthorRow = explode(";",$author);
        $getAllAuthorRow = array_map('trim',$getAllAuthorRow);
        if (count($getAllAuthorRow) > self::NUMBER_OF_AUTHORS_WANTED_VIEWS){
            $getAllAuthorRow = array_slice($getAllAuthorRow,0,self::NUMBER_OF_AUTHORS_WANTED_VIEWS, true);
            $getAllAuthorRow[] = "et al.";
        }
        $strAuthorsReduced = implode(';', $getAllAuthorRow);
        return rtrim($strAuthorsReduced);
    }


    public static function createOrcidStringForView($orcid)
    {
        $orcid = ltrim(htmlspecialchars($orcid),',');
        $orcid = ltrim(htmlspecialchars($orcid));
        return '<small style="margin-left: 4px;"><a rel="noopener" href="https://orcid.org/' . htmlspecialchars($orcid) . '" data-toggle=tooltip data-placement="bottom" data-original-title=' . htmlspecialchars($orcid) . ' target="_blank"><img srcset="/img/orcid_id.svg" src="/img/ORCID-iD.png" height="16px" alt="ORCID"/></a></small>';
    }

    /**
     * @param array $citation
     * @return string[]
     */
    public static function reorganizeForBookChapter(array $citation) : array
    {
        $arrayForBc = [
            'author' => "",
            'source_title'=> "",
            'title'=> "",
            'volume'=> "",
            'issue'=> "",
            'page'=> "",
            'year'=> "",
            'doi'=> "",
            'oa_link'=> ""
        ];
        foreach ($citation as $key => $val) {
            $arrayForBc[$key] = $val;
        }
        return $arrayForBc;
    }

    /**
     * @param array $citation
     * @return string[]
     */
    public static function reorganizeForProceedingsArticle(array $citation) : array
    {
        $arrayForPa = [
            'author' => "",
            'source_title'=> "",
            'title'=> "",
            'volume'=> "",
            'page'=> "",
            'issue'=> "",
            'year'=> "",
            'event_place' => "",
            'doi'=> "",
            'oa_link'=> ""
        ];
        foreach ($citation as $key => $val) {
            $arrayForPa[$key] = $val;
        }
        return $arrayForPa;
    }

    /**
     * @param array $metadataInfoCitation
     * @param array $globalInfoMetadata
     * @param int $i
     * @param string $doiWhoCite
     * @return array|mixed
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function getAllCitationInfoAndFormat(array $metadataInfoCitation, array $globalInfoMetadata, int $i, string $doiWhoCite) : array
    {
        $globalInfoMetadata[$i]['type'] = $metadataInfoCitation['type_crossref'];
        $globalInfoMetadata[$i]['author'] = Episciences_OpenalexTools::getAuthors($metadataInfoCitation['authorships']);
        $globalInfoMetadata[$i]['year'] = $metadataInfoCitation['publication_year'];
        $globalInfoMetadata[$i]['title'] = $metadataInfoCitation['title'];
        $getBestOpenAccessInfo = Episciences_OpenalexTools::getBestOaInfo(
            $metadataInfoCitation['primary_location'],
            $metadataInfoCitation['locations'],
            $metadataInfoCitation['best_oa_location']
        );
        $getLocationFromCr = Episciences_CrossrefTools::getLocationFromCrossref($getBestOpenAccessInfo, $doiWhoCite);
        $globalInfoMetadata = Episciences_CrossrefTools::addLocationEvent($metadataInfoCitation['type_crossref'], $doiWhoCite, $globalInfoMetadata, $i);
        if ($getLocationFromCr === "" && $getBestOpenAccessInfo === "") {
            $globalInfoMetadata[$i]['source_title'] = "";
        } else {
            $globalInfoMetadata[$i]['source_title'] = ($getLocationFromCr === "") ? $getBestOpenAccessInfo['source_title'] : $getLocationFromCr;
        }
        $globalInfoMetadata[$i]['volume'] = $metadataInfoCitation['biblio']['volume'] ?? "";
        $globalInfoMetadata[$i]['issue'] = $metadataInfoCitation['biblio']['issue'] ?? "";
        $globalInfoMetadata[$i]['page'] = Episciences_OpenalexTools::getPages($metadataInfoCitation['biblio']['first_page'], $metadataInfoCitation['biblio']['last_page']);
        $globalInfoMetadata[$i]['doi'] = $doiWhoCite;
        if ($getLocationFromCr === "" && $getBestOpenAccessInfo === "") {
            $globalInfoMetadata[$i]['oa_link'] = "";
        } else {
            $globalInfoMetadata[$i]['oa_link'] = ($getLocationFromCr === "" && !is_null($getBestOpenAccessInfo['oa_link'])) ? $getBestOpenAccessInfo['oa_link'] : "";
        }

        return $globalInfoMetadata;
    }

    /**
     * @param string $doiWhoCite
     * @param array $globalInfoMetadata
     * @param int $i
     * @return array
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function processCitationsByDoiCited(string $doiWhoCite, array $globalInfoMetadata, int $i): array
    {
        $setsMetadata = Episciences_OpenalexTools::getMetadataOpenAlexByDoi($doiWhoCite);
        if (PHP_SAPI === 'cli') {
            echo PHP_EOL .'METADATA FOUND IN CACHE ' . $doiWhoCite . PHP_EOL;
        }
        self::logInfoMessage('METADATA FOUND IN CACHE ' . $doiWhoCite);
        $metadataInfoCitation = json_decode($setsMetadata->get(), true, 512, JSON_THROW_ON_ERROR);
        if (reset($metadataInfoCitation) !== "") {
            $globalInfoMetadata = self::getAllCitationInfoAndFormat($metadataInfoCitation, $globalInfoMetadata, $i, $doiWhoCite);
        }
        return $globalInfoMetadata;
    }

    /**
     * @param array $globalInfoMetadata
     * @param int $docId
     * @return void
     * @throws JsonException
     */
    public static function insertOrUpdateCitationsByDocId(array $globalInfoMetadata, int $docId): void
    {
        if (!empty($globalInfoMetadata)) {
            $globalInfoMetaAsJson = json_encode($globalInfoMetadata,
                JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            if (PHP_SAPI === 'cli') {
                echo PHP_EOL . $globalInfoMetaAsJson . PHP_EOL;
            }
            self::logInfoMessage($globalInfoMetaAsJson);
            $citationObject = new Episciences_Paper_Citations();
            $citationObject->setCitation($globalInfoMetaAsJson);
            $citationObject->setDocId($docId);
            $citationObject->setSourceId(Episciences_Repositories::OPENCITATIONS_ID);
            if (self::insert([$citationObject]) >= 1) {
                if (PHP_SAPI === 'cli') {
                    echo PHP_EOL .'CITATION INSERTED FOR ' . $docId . PHP_EOL;
                }
                self::logInfoMessage('CITATION INSERTED FOR ' . $docId);
            } else {
                if (PHP_SAPI === 'cli') {
                    echo PHP_EOL .'NO CHANGING CITATIONS FOR ' . $docId . PHP_EOL;
                }
                self::logInfoMessage('NO CHANGING CITATIONS FOR ' . $docId );
            }
        }
    }


    /**
     * @param $apiCallCitationCache
     * @param $docId
     * @return void
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function extractCitationsAndInsertInDb($apiCallCitationCache, $docId): void
    {
        $globalArrayCiteDOI = Episciences_OpencitationsTools::cleanDoisCitingFound($apiCallCitationCache);
        $globalInfoMetadata = [];
        $i = 0;
        foreach ($globalArrayCiteDOI as $doiWhoCite) {
            $globalInfoMetadata = self::processCitationsByDoiCited($doiWhoCite, $globalInfoMetadata, $i);
            $i++;
        }
        self::insertOrUpdateCitationsByDocId($globalInfoMetadata, $docId);
    }

    /**
     * @param string $msg
     * @return void
     * @throws Exception
     */
    public static function logInfoMessage(string $msg): void
    {
        $logger = new Logger('CitationsManager');
        $logger->pushHandler(new StreamHandler(EPISCIENCES_LOG_PATH . 'getcitationsdata_' . date('Y-m-d') . '.log', Logger::INFO));
        $logger->info($msg);
    }


}

