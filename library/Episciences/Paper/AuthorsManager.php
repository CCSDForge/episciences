<?php


class Episciences_Paper_AuthorsManager
{
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
                    if (array_key_exists('id',$affiliation)){
                        $tmpInfoAffi = ['affiliation'=>$affiliation['name'],'url'=>$affiliation['id'][0]['id']];
                    }else{
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
                                        if ($counterDisplayedAffiAuthor === $counterAffiAuthor-1){
                                            $templateString .= "<sup>".($keyAffi+1)."</sup>";
                                        }else{
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
        if (empty(self::getAuthorByPaperId($paperId))){
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

    public static function getArrayAuthorsAffi(int $paperId) {
        $decodedauthors = [];
        foreach (self::getAuthorByPaperId($paperId) as $value){
            $decodedauthors = json_decode($value['authors'], true, 512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        return $decodedauthors;
    }

    public static function filterAuthorsAndAffiNumeric(int $paperId) {
            $allauthors = self::getArrayAuthorsAffi($paperId);
            $arrayAllAffi = [];
            foreach ($allauthors as $key => $author) {
                if (isset($author['affiliation'])){
                    foreach ($author['affiliation'] as $affiliation){
                        if (!in_array($affiliation['name'], $arrayAllAffi, true)){
                            $arrayAllAffi[] = $affiliation['name'];
                            $allauthors[$key]['idAffi'][array_key_last($arrayAllAffi)] = $arrayAllAffi[array_key_last($arrayAllAffi)];
                        } else {
                            $searching = array_search($affiliation['name'],$arrayAllAffi,true);
                            $allauthors[$key]['idAffi'][$searching] = $arrayAllAffi[$searching] ;
                        }
                        ksort($allauthors[$key]['idAffi']);
                    }
                }
            }
            return ['affiliationNumeric' => $arrayAllAffi, 'authors' => $allauthors];

    }

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

    //Format for the ROR input in paper View

    public static function formatAffiliationForInputRor(array $affiliation) {
        $affiliationFormatted = [];
        foreach ($affiliation as $value){
            $url = '';
            if (array_key_exists('id',$value)){
                $url = ' #'.$value['id'][0]['id'];
            }
            $affiliationFormatted[] = $value['name'].$url;
        }
        return $affiliationFormatted;
    }

    public static function deleteAuthorsByPaperId(int $paperId){
        if ($paperId < 1) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return ($db->delete(T_PAPER_AUTHORS, ['paperid = ?' => $paperId]) > 0);
    }
}