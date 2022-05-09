<?php

class Episciences_Paper_LicenceManager
{
    /**
     * @param array $licencesk
     * @return int
     */

    public static function insert(array $licences): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $values = [];

        $affectedRows = 0;

        foreach ($licences as $licence) {

            if (!($licence instanceof Episciences_Paper_Licence)) {

                $licence = new Episciences_Paper_Licence($licence);
            }

            $values[] = '(' . $db->quote($licence->getLicence()) . ',' . $db->quote($licence->getDocId()) . ',' . $db->quote($licence->getSourceId()) . ')';

        }
        $sql = 'INSERT INTO ' . $db->quoteIdentifier(T_PAPER_LICENCES) . ' (`licence`,`docid`,`source_id`) VALUES ';


        if (!empty($values)) {
            try {
                //Prepares and executes an SQL
                /** @var Zend_Db_Statement_Interface $result */
                $result = $db->query($sql . implode(', ', $values) . ' ON DUPLICATE KEY UPDATE licence=VALUES(licence)');
                $affectedRows = $result->rowCount();

            } catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_ERROR);
            }
        }

        return $affectedRows;

    }
}