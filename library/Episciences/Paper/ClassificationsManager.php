<?php

class Episciences_Paper_ClassificationsManager
{

    public static function insert(array $classifications): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $values = [];

        $affectedRows = 0;

        foreach ($classifications as $classification) {

            if (!($classification instanceof Episciences_Paper_Classifications)) {

                $classification = new Episciences_Paper_Classifications($classification);
            }

            $values[] = '(' . $db->quote($classification->getPaperId()) . ',' . $db->quote($classification->getClassification()) .  ',' . $db->quote($classification->getType()) . ',' . $db->quote($classification->getSourceId()) . ')';

        }
        $sql = 'INSERT INTO ' . $db->quoteIdentifier(T_PAPER_CLASSIFICATIONS) . ' (`paperid`,`classification`,`type`,`source_id`) VALUES ';

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

    public static function getClassificationByPaperId($paperId){

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['paper_classification'=>T_PAPER_CLASSIFICATIONS])->joinLeft(['source_paper'=>T_PAPER_METADATA_SOURCES],"paper_classification.source_id = source_paper.id",["source_id_name"=>'source_paper.name'])->where('paperId = ? ', $paperId)->order("source_id");
        return $db->fetchAssoc($sql);
    }

    public static function formatClassificationForview($paperId)
    {
        $rawInfo = self::getClassificationByPaperId($paperId);
        if (!empty($rawInfo)) {
            $rawClassification = [];
            $templateClassification = "";
            foreach ($rawInfo as $value) {
                $rawClassification[$value['source_id_name']][] = ['classification' => htmlspecialchars($value['classification']),'type'=>$value['type']];
            }
            foreach ($rawClassification as $source_id_name => $classificationInfo) {
                $templateClassification .= "<ul class='list-unstyled'>";
                $templateClassification .= " <small class='label label-info'>" . Zend_Registry::get('Zend_Translate')->translate('Source :') . ' ' . $source_id_name . "</small>";
                foreach ($classificationInfo as $info){
                    $templateClassification .= "<li>".$info['type']."; ".$info['classification']."</li>";
                }
                $templateClassification .= "</ul>";

            }
            return $templateClassification;
        }
        return "";
    }
}