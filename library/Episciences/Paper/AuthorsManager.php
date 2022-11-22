<?php

use GuzzleHttp\Client as guzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class Episciences_Paper_AuthorsManager
{
    public const ONE_MONTH = 3600 * 24 * 31;

    /**
     * @param array $authors
     * @return int
     */

    public static function insert(array $authors): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $values = [];

        $affectedRows = 0;


        foreach ($authors as $author) {

            if (!($author instanceof Episciences_Paper_Authors)) {

                $author = new Episciences_Paper_Authors($author);
            }

            $values[] = '(' . $db->quote($author->getAuthors()) . ',' . $db->quote($author->getPaperId()) . ')';

        }

        $sql = 'INSERT INTO ' . $db->quoteIdentifier(T_PAPER_AUTHORS) . ' (`authors`,`paperId`) VALUES ';

        if (!empty($values)) {

            try {
                //Prepares and executes an SQL
                /** @var Zend_Db_Statement_Interface $result */
                $result = $db->query($sql . implode(', ', $values));

                $affectedRows = $result->rowCount();

            } catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_ERROR);
            }
        }

        return $affectedRows;
    }

    public static function getAuthorByPaperId($paperId): array {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->from(T_PAPER_AUTHORS)->where('PAPERID = ?',$paperId); // prevent empty row
        return $db->fetchAssoc($select);
    }

    public static function formatAuthorEnrichmentForViewByPaper($paperId): array {
        $decodedauthor = [];
        foreach (self::getAuthorByPaperId($paperId) as $value){
            $decodedauthor = json_decode($value['authors'], true);
        }
        $templateString = "";
        $sizeArr = count($decodedauthor);
        //create TMP text to save orcid for view popup Jquery
        $orcidText = "";
        // for display
        $tmpArrayAffiliation = [];
        $tmpArrayAfUrl = [];
        //pre clean affi to avoid duplicate
        $unUniqueAffi = [];
        $stringListAffi = '';
        //authorList we needed it for the select affiliation but can be in case we need all author for other things
        $authorsList = '';
        $uniqueAffi = [];
        if (!empty($decodedauthor)) {
            foreach ($decodedauthor as $value) {
                if (array_key_exists('affiliation',$value)) {
                    $tmpArrayAffiliation[] = $value['affiliation'];
                }
            }
            foreach ($tmpArrayAffiliation as $affiliationInfo) {
                foreach ($affiliationInfo as  $affiliation) {
                    if (array_key_exists('id', $affiliation)) {
                        $tmpInfoAffi = ['affiliation'=>$affiliation['name'],'url'=>$affiliation['id'][0]['id']];
                    } else {
                        $tmpInfoAffi = ['affiliation'=>$affiliation['name']];
                    }
                    $tmpArrayAfUrl[] = $tmpInfoAffi;
                }
            }
            // make unique array of url and affiliation and reindex it
            $tmpArrayAfUrl =  array_map("unserialize", array_unique(array_map("serialize", $tmpArrayAfUrl)));
            $i = 0;
            foreach ($tmpArrayAfUrl as $key => $value){
                $uniqueAffi[$i] = $value;
                $i++;
            }
            foreach ($decodedauthor as $key => $value) {
                $fullname = htmlspecialchars($value['fullname']);
                $authorsList .= $fullname;
                //search orcid to display logo and url in paper page
                if (array_key_exists('orcid', $value)) {
                    $orcid = htmlspecialchars($value['orcid']);
                    $orcidUrl = "https://orcid.org/" . $orcid;
                    $templateString .= $fullname . ' <a rel="noopener" href=' . $orcidUrl . ' data-toggle="tooltip" data-placement="bottom" data-original-title=' . $orcid . ' target="_blank" ><img src="/img/ORCID-iD.png" alt="ORCID-iD" height="16px"/></a>';
                    $orcidText .= $orcid;
                } else {
                    $templateString .= ' ' . $fullname . ' ';
                    $orcidText .= "NULL";
                }
                //search affiliation in author looping
                if (array_key_exists('affiliation', $value)) {
                    // count affiliation to display ',' correctly
                    $counterAffiAuthor = count($value['affiliation']);
                    $counterDisplayedAffiAuthor = 0;
                    foreach ($value['affiliation'] as $affiliationAuthor){
                        foreach ($uniqueAffi as $keyAffi => $affi) {
                            // check if affiliation looped exist for the author and url corresponding to the right ROR looped in order to avoid some duplications
                            // at this point the json in DB is monodimensional so we can point exactly with no other loop
                            if (in_array($affi['affiliation'], $affiliationAuthor, true)) {
                                if (array_key_exists('url',$affi) &&  array_key_exists('id',$affiliationAuthor)){
                                    //check if right url to avoid duplicate because we can have same name ROR but not same url
                                    //case of affiliation finded with url
                                    if ($affi['url'] === $affiliationAuthor['id'][0]['id']) {
                                        if ($counterDisplayedAffiAuthor === $counterAffiAuthor-1){
                                            $templateString .= "<sup>".($keyAffi+1)."</sup>";
                                        }else{
                                            $templateString .= "<sup>".($keyAffi+1).",</sup>";
                                        }
                                        $counterDisplayedAffiAuthor++;
                                    }
                                } elseif((in_array($affi['affiliation'], $affiliationAuthor, true) && !isset($affiliationAuthor['id'])) && !isset($affi['url'])) {
                                        //this case is for affiliation which have good name but no url ROR
                                        if ($counterDisplayedAffiAuthor === $counterAffiAuthor-1) {
                                            $templateString .= "<sup>".($keyAffi+1)."</sup>";
                                        } else {
                                            $templateString .= "<sup>".($keyAffi+1).",</sup>";
                                        }
                                        $counterDisplayedAffiAuthor++;
                                }
                            }
                        }
                    }
                }
                if ($key !== $sizeArr - 1) {
                    $authorsList.=';';
                    $templateString .= '; ';
                    $orcidText .= "##";
                }

            }
            //List of affiliation

            $stringListAffi .='<ul class="list-unstyled">';
            foreach ($uniqueAffi as $index => $affi) {
                if (isset($affi['url'])){
                    $stringListAffi .= '<li class="affiliation"><span class="label label-default">'.($index+1).'</span> <a href='.$affi['url'].' target="_blank">'.htmlspecialchars($affi['affiliation']).'</a></li>';
                }else{
                    $stringListAffi .= '<li class="affiliation"><span class="label label-default">'.($index+1).'</span> '.htmlspecialchars($affi['affiliation']).'</li>';
                }
            }
            $stringListAffi .= '</ul>';
        }
        return ['template'=>$templateString,'orcid'=>$orcidText,'listAffi'=> $stringListAffi,'authorsList'=>$authorsList];
    }
    /**
     * @param Episciences_Paper_Authors $authors
     * @return int
     */
    public static function update(Episciences_Paper_Authors $authors): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $authorId = $authors->getAuthorId();
        if ($authorId !== null) {
            $where['idauthors = ?'] = $authors->getAuthorId();
        }
        $where['paperid = ?'] = $authors->getPaperId();

        $values = [
                'authors' => $authors->getAuthors()
        ];
        try {
            $resUpdate = $db->update(T_PAPER_AUTHORS, $values, $where);
        } catch (Zend_Db_Adapter_Exception $exception) {
            $resUpdate = 0;
            trigger_error($exception->getMessage(), E_USER_ERROR);
        }
        return $resUpdate;
    }

    /**
     * @param $paper
     * @param $paperId
     * @return void
     */
    public static function InsertAuthorsFromPapers($paper, $paperId): void
    {
        if (empty(self::getAuthorByPaperId($paperId))) {
            $authors = $paper->getMetadata('authors');
            foreach ($authors as $author) {
                $authorsFormatted = Episciences_Tools::reformatOaiDcAuthor($author);

                $exploded = explode(', ', $author);

                $arrayAuthors[] = [
                    'fullname' => $authorsFormatted,
                    'given' => $exploded[1] ?? null,
                    'family' => $exploded[0] ?? null
                ];
            }

            Episciences_Paper_AuthorsManager::insert([
                [
                    'authors' => json_encode($arrayAuthors, JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'paperId' => $paperId
                ]
            ]);
            unset($arrayAuthors);
        }
    }

    /**
     * @param int $paperId
     * @return array|mixed
     * @throws JsonException
     */
    public static function getArrayAuthorsAffi(int $paperId) {
        $decodedauthors = [];
        foreach (self::getAuthorByPaperId($paperId) as $value){
            $decodedauthors = json_decode($value['authors'], true, 512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        return $decodedauthors;
    }

    /**
     * @param int $paperId
     * @return array
     * @throws JsonException
     */
    public static function filterAuthorsAndAffiNumeric(int $paperId) {
            $allauthors = self::getArrayAuthorsAffi($paperId);
            $arrayAllAffi = [];
            foreach ($allauthors as $key => $author) {
                if (isset($author['affiliation'])) {
                    foreach ($author['affiliation'] as $affiliation) {
                        // we need to distinct all affiliations because we can't know which is which even if is the same string affiliation
                        // it might be not the same affiliation
                        $md5nameKey = $affiliation['name'];
                        if (isset($affiliation['id'])) {
                            $md5nameKey .= $affiliation['id'][0]['id'].$affiliation['id'][0]['id-type'];
                        }
                        if (array_key_exists(md5($md5nameKey), $arrayAllAffi)) {
                            $allauthors[$key]['idAffi'][md5($md5nameKey)] = $arrayAllAffi[md5($md5nameKey)];
                        } else {
                            $arrayAllAffi[md5($md5nameKey)] = [
                                "name"=> $affiliation['name'],
                            ];
                            if (isset($affiliation['id'])) {
                                $arrayAllAffi[array_key_last($arrayAllAffi)]['url'] = $affiliation['id'][0]['id'];
                                $arrayAllAffi[array_key_last($arrayAllAffi)]['type'] = $affiliation['id'][0]['id-type'];
                            }
                            $allauthors[$key]['idAffi'][array_key_last($arrayAllAffi)] = $arrayAllAffi[array_key_last($arrayAllAffi)];
                        }
                        ksort($allauthors[$key]['idAffi']);
                    }
                }
            }
            ksort($arrayAllAffi);
            return ['affiliationNumeric' => $arrayAllAffi, 'authors' => $allauthors];

    }

    /**
     * @param int $paperId
     * @param int $idAuthorInJson
     * @return mixed|string
     * @throws JsonException
     */
    public static function findAffiliationsOneAuthorByPaperId(int $paperId, int $idAuthorInJson) {

        $authors = self::getAuthorByPaperId($paperId);
        foreach ($authors as $value) {
            $jsonAuthorDecoded =  json_decode($value['authors'], true, 512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }
        if (array_key_exists('affiliation',$jsonAuthorDecoded[$idAuthorInJson])){
            return $jsonAuthorDecoded[$idAuthorInJson]['affiliation'];
        }
        return "";
    }



    /**
     * Format for the ROR input in paper View
     * @param array $affiliation
     * @return array
     */
    public static function formatAffiliationForInputRor(array $affiliation) {
        $affiliationFormatted = [];
        foreach ($affiliation as $value){
            $url = '';
            if (array_key_exists('id',$value)) {
                $url = ' #'.$value['id'][0]['id'];
            }
            $affiliationFormatted[] = $value['name'].$url;
        }
        return $affiliationFormatted;
    }

    /**
     * @param int $paperId
     * @return bool
     */
    public static function deleteAuthorsByPaperId(int $paperId){
        if ($paperId < 1) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return ($db->delete(T_PAPER_AUTHORS, ['paperid = ?' => $paperId]) > 0);
    }

    /**
     * @param string $orcid
     * @return string
     */
    public static function cleanLowerCaseOrcid(string $orcid): string
    {
        $orcidReg = '/\d{4}-\d{4}-\d{4}-\d{3}x+$/'; //wrong pattern for orcid
        preg_match($orcidReg, $orcid, $matches);
        if (!empty($matches)) {
            $orcid = str_replace('x', 'X', $orcid);
        }
        return $orcid;
    }

    /**
     * @param string $identifier
     * @param int $version
     * @return string
     */
    public static function getTeiHalByIdentifier(string $identifier, int $version): string
    {
        $client = new guzzleClient();
        $url = "https://api.archives-ouvertes.fr/search/?q=((halId_s:" . $identifier . " OR halIdSameAs_s:" . $identifier . ") AND version_i:" . $version . ")&wt=xml-tei";
        $teiHalResp = '';
        $timeOut = 3;
        if (PHP_SAPI === 'cli') {
            $timeOut = 40;
        }
        try {
            return $client->get($url, [
                'headers' => [
                    'User-Agent' => 'CCSD Episciences support@episciences.org',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'timeout' => $timeOut
            ])->getBody()->getContents();

        } catch (GuzzleException $e) {

            trigger_error($e->getMessage());

        }
        return $teiHalResp;
    }

    /**
     * @param string $identifier
     * @param int $version
     * @return bool
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function getHalTei(string $identifier, int $version) : bool
    {
        $fileTeiHal = 'hal-tei-'.$identifier.'-'.$version. ".xml";
        $cacheTei = new FilesystemAdapter('halTei', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
        $setsGlobalTei = $cacheTei->getItem($fileTeiHal);
        $setsGlobalTei->expiresAfter(self::ONE_MONTH);
        if (!$setsGlobalTei->isHit()) {
            $getTei = self::getTeiHalByIdentifier($identifier, $version);
            $setsGlobalTei->set($getTei);
            $cacheTei->save($setsGlobalTei);
            return true;
        }
        return false;
    }

    /**
     * @param string $identifier
     * @param int $version
     * @return string
     * @throws \Psr\Cache\InvalidArgumentException
     */

    public static function getHalTeiCache(string $identifier, int $version) : string
    {
        $fileTeiHal = 'hal-tei-'.$identifier.'-'.$version. ".xml";
        $cacheTei = new FilesystemAdapter('halTei', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
        $setsGlobalTei = $cacheTei->getItem($fileTeiHal);
        $setsGlobalTei->expiresAfter(self::ONE_MONTH);
        if (!$setsGlobalTei->isHit()) {
            return '';
        }
        return $setsGlobalTei->get();
    }

    /**
     * @param simpleXMLElement $xmlString
     * @return array
     */

    public static function getAuthorsFromHalTei(simpleXMLElement $xmlString): array
    {
        if (!isset($xmlString->text->body->listBibl->biblFull->titleStmt->author)) {
            return [];
        }
        $authors = $xmlString->text->body->listBibl->biblFull->titleStmt->author;
        $globalAuthorArray = [];
        foreach ($authors as $author) {
            foreach ($author->persName as $infoName) {
                //NAME
                $globalAuthorArray = self::getAuthorInfoFromXmlTei($infoName, $globalAuthorArray);
            }
            //AFFI
            if (isset($author->affiliation)) {
                $globalAuthorArray = self::getAuthorStructureFromXmlTei($author, $globalAuthorArray);
            }
            //ORCID
            if (isset($author->idno)) {
                $globalAuthorArray = self::getOrcidAuthorFromXmlTei($author, $globalAuthorArray);
            }
        }
        return $globalAuthorArray;
    }

    /**
     * @param simpleXMLElement $xmlString
     * @return array
     */
    public static function getAffiFromHalTei(simpleXMLElement $xmlString): array {
        $back = $xmlString->text->back;
        $orgInfo = [];
        if (isset($back->listOrg)) {
            foreach ($back->listOrg->org as $org) {
                $orgInfo[(string)$org->attributes('xml', true)[0]]['name'] = trim((string)$org->orgName);
                if ($org->idno) {
                    foreach ($org->idno as $orgIdno) {
                        if ((string)$orgIdno->attributes()->type ==='ROR') {
                            $orgInfo[(string)$org->attributes('xml', true)[0]]['ROR'] = trim("https://ror.org/".$orgIdno);
                        }
                    }
                }
            }
        }
        return $orgInfo;
    }

    /**
     * @param array $authorTei
     * @param array $affiliationTei
     * @return array
     */
    public static function mergeAuthorInfoAndAffiTei(array $authorTei, array $affiliationTei): array {
        foreach ($authorTei as $index => $author) {
            if (isset($author["affiliations"])) {
                foreach ($author["affiliations"] as $indexAffi => $affiliationStruct) {
                    $authorTei[$index]['affiliations'][$indexAffi] = ['name' => $affiliationTei[$affiliationStruct]['name']];
                    if (array_key_exists('ROR', $affiliationTei[$affiliationStruct])) {
                        $authorTei[$index]['affiliations'][$indexAffi]['ROR'] = $affiliationTei[$affiliationStruct]['ROR'];
                    }
                }
            }
        }
        return $authorTei;
    }
    /**
     * @param array $authorDb
     * @param array $authorTei
     * @return array
     */
    public static function mergeInfoDbAndInfoTei(array $authorDb, array $authorTei): array
    {

        foreach ($authorDb as $indexAuthor => $authorInfoDb) {
            foreach ($authorTei as $indexAuthorTei => $authorInfoTei) {
                if (($authorInfoDb['fullname'] === $authorInfoTei['fullname'])
                    || (Episciences_Tools::replace_accents($authorInfoTei['fullname']) === Episciences_Tools::replace_accents($authorInfoDb['fullname']))) {
                    if (array_key_exists('orcid', $authorInfoTei) && !array_key_exists('orcid', $authorInfoDb)) {
                        $authorDb[$indexAuthor]['orcid'] = $authorInfoTei['orcid'];
                        if (PHP_SAPI === 'cli') {
                            echo "Orcid Added for ".$authorDb[$indexAuthor]['fullname'];
                            self::logInfoMessage("Orcid Added for ".$authorDb[$indexAuthor]['fullname']);
                        }
                    }
                    if (array_key_exists('affiliation', $authorInfoDb) && array_key_exists('affiliations', $authorInfoTei)) {
                        foreach ($authorInfoTei['affiliations'] as $affiliation) {
                            if (!in_array($affiliation['name'],array_column($authorInfoDb['affiliation'],'name'), true)) {
                                if (array_key_exists('ROR', $affiliation)) {
                                    $authorDb[$indexAuthor]['affiliation'][] = self::putAffiliationWithRORinArray($affiliation);
                                } else {
                                    $authorDb[$indexAuthor]['affiliation'][] = self::putOnlyNameAffiliation($affiliation['name']);
                                }

                                if (PHP_SAPI === 'cli') {
                                    echo PHP_EOL."Affiliation Added for ".$authorDb[$indexAuthor]['fullname'].PHP_EOL;
                                    self::logInfoMessage( "Affiliation Added with ROR for ".$authorDb[$indexAuthor]['fullname']);
                                }
                            } elseif (in_array($affiliation['name'],array_column($authorInfoDb['affiliation'],'name'))
                                && array_key_exists('ROR', $affiliation)
                                && self::affiliationRorExistbyAffi($authorInfoDb['affiliation'][key($authorDb[$indexAuthor]['affiliation'])]) === false) {
                                $authorDb[$indexAuthor]['affiliation'][key($authorDb[$indexAuthor]['affiliation'])]['id'] = self::putOnlyRORAffiliation($affiliation['ROR']);
                                if (PHP_SAPI === 'cli') {
                                    echo PHP_EOL."ROR to Affiliation Added for ".$authorDb[$indexAuthor]['fullname']." - ".$authorDb[$indexAuthor]['affiliation'][key($authorDb[$indexAuthor]['affiliation'])].PHP_EOL;
                                    self::logInfoMessage("ROR to Affiliation Added for ".$authorDb[$indexAuthor]['fullname']." - ".$authorDb[$indexAuthor]['affiliation'][key($authorDb[$indexAuthor]['affiliation'])]);
                                }
                            }
                        }
                    } elseif (array_key_exists('affiliations', $authorInfoTei) && !array_key_exists('affiliation', $authorInfoDb)) {
                        foreach ($authorInfoTei['affiliations'] as $affiliation) {
                            if (array_key_exists('ROR', $affiliation)) {
                                $authorDb[$indexAuthor]['affiliation'][] = self::putAffiliationWithRORinArray($affiliation);
                                if (PHP_SAPI === 'cli') {
                                    echo PHP_EOL.'New Affiliation with ROR Added for '.$authorDb[$indexAuthor]['fullname']." - ".$affiliation['name'].PHP_EOL;
                                    self::logInfoMessage('New Affiliation with ROR Added for '.$authorDb[$indexAuthor]['fullname']." - ".$affiliation['name']);
                                }
                            } else {
                                $authorDb[$indexAuthor]['affiliation'][] = self::putOnlyNameAffiliation($affiliation['name']);
                                if (PHP_SAPI === 'cli') {
                                    echo PHP_EOL.'New Affiliation without ROR founded, Added for '.$authorDb[$indexAuthor]['fullname']." - ".$affiliation['name'].PHP_EOL;
                                    self::logInfoMessage('New Affiliation without ROR founded, Added for '.$authorDb[$indexAuthor]['fullname']." - ".$affiliation['name']);
                                }
                            }
                        }
                    }
                }
            }
        }
        return $authorDb;
    }

    /**
     * @param array $affiliationOfAuthor
     * @return bool
     */
    private static function affiliationRorExistbyAffi(array $affiliationOfAuthor): bool {
        if (isset($affiliationOfAuthor['id'])) {
            foreach ($affiliationOfAuthor['id'] as $affiliation) {
                if ($affiliation['id-type'] === "ROR") {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param string $msg
     * @return void
     * @throws Exception
     */
    public static function logInfoMessage(string $msg): void
    {
        $logger = new Logger('AuthorsManager');
        $logger->pushHandler(new StreamHandler(EPISCIENCES_LOG_PATH . 'getcreatordata_' . date('Y-m-d') . '.log', Logger::INFO));
        $logger->info($msg);
    }

    /**
     * @param SimpleXMLElement|null $author
     * @param array $globalAuthorArray
     * @return array
     */
    public static function getOrcidAuthorFromXmlTei(?SimpleXMLElement $author, array $globalAuthorArray): array
    {
        foreach ($author->idno as $idno) {
            if ((string)$idno->attributes()->type === "ORCID") {
                $filterOrcid = substr((string)$idno, strrpos((string)$idno, '/') + 1);
                $filterOrcid = str_replace('/', '', $filterOrcid);
                $globalAuthorArray[array_key_last($globalAuthorArray)]['orcid'] = trim($filterOrcid);
            }
        }
        return $globalAuthorArray;
    }

    /**
     * @param SimpleXMLElement|null $infoName
     * @param array $globalAuthorArray
     * @return array
     */
    public static function getAuthorInfoFromXmlTei(?SimpleXMLElement $infoName, array $globalAuthorArray): array
    {
        $globalAuthorArray[] = [
            'given_name' => (string)$infoName->forename,
            'family' => (string)$infoName->surname,
            'fullname' => rtrim($infoName->forename . " " . $infoName->surname),
        ];
        return $globalAuthorArray;
    }

    /**
     * @param SimpleXMLElement|null $author
     * @param array $globalAuthorArray
     * @return array
     */
    public static function getAuthorStructureFromXmlTei(?SimpleXMLElement $author, array $globalAuthorArray): array
    {
        $i = 0;
        foreach ($author->affiliation as $aff) {
            $globalAuthorArray[array_key_last($globalAuthorArray)]['affiliations'][$i] = (string)str_replace("#", '', $aff->attributes()->ref);
            $i++;
        }
        return $globalAuthorArray;
    }

    /**
     * Try to catch information related to author like ORCID or AFFILIATION structure with or without ROR From HAL TEI when paper is an HAL paper
     * @param int $repoId
     * @param int $paperId
     * @param string $identifier
     * @param int $version
     * @return void
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function enrichAffiOrcidFromTeiHalInDB(int $repoId, int $paperId, string $identifier, int $version): void 
    {
        if ((string) $repoId === Episciences_Repositories::HAL_REPO_ID) {
            $decodeAuthor = '';
            $selectAuthor = self::getAuthorByPaperId($paperId);
            foreach ($selectAuthor as $authorsDb){
                $decodeAuthor = json_decode($authorsDb['authors'], true, 512, JSON_THROW_ON_ERROR);
            }
            self::getHalTei($identifier, $version);
            $cacheTeiHal = self::getHalTeiCache($identifier, $version);
            if ($cacheTeiHal !== '') {
                $xmlString = simplexml_load_string($cacheTeiHal);
                if (is_object($xmlString) && $xmlString->count() > 0) {
                    $authorTei = self::getAuthorsFromHalTei($xmlString);
                    $affiInfo = self::getAffiFromHalTei($xmlString);
                    $authorTei = self::mergeAuthorInfoAndAffiTei($authorTei, $affiInfo);
                    $FormattedAuthorsForDb = self::mergeInfoDbAndInfoTei($decodeAuthor,$authorTei);
                    $newAuthorInfos = new Episciences_Paper_Authors();
                    $newAuthorInfos->setAuthors(json_encode($FormattedAuthorsForDb, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT));
                    $newAuthorInfos->setPaperId($paperId);
                    self::update($newAuthorInfos);
                }
            }
        }
    }

    /**
     * @param array $affiliation
     * @return array
     */
    public static function putAffiliationWithRORinArray(array $affiliation): array
    {
        return [
            "name" => $affiliation['name'],
            "id" => [
                [
                    'id' => $affiliation['ROR'],
                    'id-type' => 'ROR'
                ]
            ]
        ];
    }

    /**
     * @param string $ror
     * @return array[]
     */
    public static function putOnlyRORAffiliation(string $ror): array
    {
        return [
            [
                'id' => $ror,
                'id-type' => 'ROR'
            ]
        ];
    }

    /**
     * @param string $name
     * @return array
     */
    public static function putOnlyNameAffiliation(string $name): array
    {
        return [
            "name" => $name
        ];
    }
}