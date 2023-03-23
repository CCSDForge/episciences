<?php

class Episciences_Paper_CitationsManager
{
    CONST NUMBER_OF_AUTHORS_WANTED_VIEWS = 5;
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
            $templateCitation .= "<small class='label label-info'>".Zend_Registry::get('Zend_Translate')->translate('Source :') . ' ' . htmlspecialchars($value['source_id_name']) ."</small>";
            $decodeCitations = json_decode($value['citation'], true, 512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $counterCitations += count($decodeCitations);
            $decodeCitations = self::sortAuthorAndYear($decodeCitations);
            foreach ($decodeCitations as $citationMetadataArray){
                $templateCitation.="<ul class='list-unstyled'>";
                $templateCitation.="<li>";
                $citationMetadataArray = array_map('strip_tags',$citationMetadataArray);
                foreach ($citationMetadataArray as $keyMetadata => $metadata) {
                    if ($metadata !== ""){
                        if ($keyMetadata === 'source_title') {
                            $templateCitation.= "<i>".htmlspecialchars($metadata).'</i>';
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
            $templateCitation.="<br>";
        }
        return ['template'=>$templateCitation,'counterCitations'=>$counterCitations];
    }

    public static function sortAuthorAndYear($arrayMetadata) {
        $arrayAuthor = [];
        $arrayYear = [];
        foreach ($arrayMetadata as $value) {
            if ($value['author']!=="") {
                $arrayAuthor[] = $value;
            } else {
                $arrayYear[] = $value;
            }
        }
        array_multisort(array_column($arrayAuthor,'author'),SORT_ASC,SORT_NATURAL|SORT_FLAG_CASE,$arrayAuthor);
        array_multisort(array_column($arrayYear,'year'),SORT_DESC,$arrayYear);
        return array_merge($arrayYear,$arrayAuthor);
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
        return '<small style="margin-left: 4px;"><a rel="noopener" href="https://orcid.org/' . htmlspecialchars($orcid) . '" data-toggle=tooltip data-placement="bottom" data-original-title=' . htmlspecialchars($orcid) . ' target="_blank"><img src="/img/ORCID-iD.png" alt="ORCID-iD" height="16px"/></a></small>';
    }
}