<?php

class Episciences_Paper_ClassificationsManager
{

    public static function insert(array $classifications): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $values = [];
        $affectedRows = 0;

        foreach ($classifications as $classification) {
            // Ensure each item is an instance of the expected class
            if (!($classification instanceof Episciences_Paper_Classifications)) {
                $classification = new Episciences_Paper_Classifications($classification);
            }

            // Prepare the value set for the insert query
            $values[] = '(' .
                $db->quote($classification->getDocid()) . ',' .
                $db->quote($classification->getClassificationCode()) . ',' .
                $db->quote($classification->getClassificationName()) . ',' .
                $db->quote($classification->getSourceId()) .
                ')';
        }

        // SQL statement with ON DUPLICATE KEY handling
        $sql = 'INSERT INTO ' . $db->quoteIdentifier(T_PAPER_CLASSIFICATIONS) .
            ' (`docid`, `classification_code`, `classification_name`, `source_id`) VALUES ';

        if (!empty($values)) {
            try {
                // Prepare the ON DUPLICATE KEY UPDATE clause
                $onDuplicateKeySql = ' ON DUPLICATE KEY UPDATE `classification_name` = VALUES(`classification_name`), `source_id` = VALUES(`source_id`)';

                // Execute the query
                $result = $db->query($sql . implode(', ', $values) . $onDuplicateKeySql);
                $affectedRows = $result->rowCount();

            } catch (PDOException $e) {
                // Log error but don't halt the execution (ignore the error)
                error_log('Database error: ' . $e->getMessage());
            }
        }

        return $affectedRows;
    }


    public static function formatClassificationForview($paperId)
    {
        $rawInfo = self::getClassificationByDocId($paperId);
        if (!empty($rawInfo)) {
            $rawClassification = [];
            $templateClassification = "";
            foreach ($rawInfo as $value) {
                $rawClassification[$value['source_id']][] = ['classification' => htmlspecialchars($value['classification']), 'type' => $value['type']];
            }
            foreach ($rawClassification as $source_id_name => $classificationInfo) {
                $templateClassification .= "<ul class='list-unstyled'>";
                $templateClassification .= " <small class='label label-default'>" . Zend_Registry::get('Zend_Translate')->translate('Source :') . ' ' . $source_id_name . "</small>";
                foreach ($classificationInfo as $info) {
                    $templateClassification .= "<li>" . $info['type'] . "; " . $info['classification'] . "</li>";
                }
                $templateClassification .= "</ul>";

            }
            return $templateClassification;
        }
        return "";
    }

    public static function getClassificationByDocId(int $docid): array
    {
        /**
         *  /!\ Be careful if 2 classifications share an identical code
         */
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['pc' => T_PAPER_CLASSIFICATIONS])
            ->joinLeft(['pms' => T_PAPER_METADATA_SOURCES], "pc.source_id = pms.id", ["source_name" => 'pms.name'])
            ->joinLeft(['msc' => T_PAPER_CLASSIFICATION_MSC2020], "msc.code = pc.classification_code AND pc.classification_name='msc2020'", ["code.msc2020" => 'msc.code', "label.msc2020" => 'msc.label', "description.msc2020" => 'msc.description'])
            ->joinLeft(['jel' => T_PAPER_CLASSIFICATION_JEL], "jel.code = pc.classification_code AND pc.classification_name='jel'", ["code.jel" => 'jel.code', "label.jel" => 'jel.label'])
            ->where('docid = ? ', $docid)
            ->order('pc.classification_name ASC');
      //      ->order("source_id ASC");
        return $db->fetchAssoc($sql);
    }
}