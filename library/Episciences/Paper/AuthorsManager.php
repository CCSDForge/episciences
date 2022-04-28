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

    /**
     * @param Episciences_Paper_Authors $authors
     * @return int
     */
    public static function update(Episciences_Paper_Authors $authors): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $where['idauthors = ?'] = $authors->getAuthorId();
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
}