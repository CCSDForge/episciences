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
        if (!empty($decodedauthor)) {
            foreach ($decodedauthor as $key => $value) {
                $fullname = htmlspecialchars($value['fullname']);
                if (array_key_exists('orcid',$value)) {
                    $orcid = htmlspecialchars($value['orcid']);
                    $orcidUrl = "https://orcid.org/".$orcid;
                    $templateString .= $fullname.' <a rel="noopener" href='.$orcidUrl.' data-toggle="tooltip" data-placement="bottom" data-original-title='.$orcid.' target="_blank" ><img src="/img/ORCID-iD.png" alt="ORCID-iD" height="16px"/></a>';
                    $orcidText .= $orcid;
                }else{
                    $templateString .= ' '. $fullname.' ';
                    $orcidText .= "NULL";
                }
                if ($key !== $sizeArr-1) {
                    $templateString .= '; ';
                    $orcidText.= "##";
                }
            }
        }
        return ['template'=>$templateString,'orcid'=>$orcidText];
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
        $authors = $paper->getMetadata('authors');
        foreach ($authors as $author) {
            $authorsFormatted = Episciences_Tools::reformatOaiDcAuthor($author);
            [$familyName, $givenName] = explode(', ', $author);
            $arrayAuthors[] = [
                'fullname' => $authorsFormatted,
                'given' => $givenName,
                'family' => $familyName
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

    public static function deleteAuthorsByPaperId(int $paperId){
        if ($paperId < 1) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return ($db->delete(T_PAPER_AUTHORS, ['paperid = ?' => $paperId]) > 0);
    }
}