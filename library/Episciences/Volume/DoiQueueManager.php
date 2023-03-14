<?php

class Episciences_Volume_DoiQueueManager
{
    /**
     * @param int $vid
     * @return Episciences_Volume_DoiQueue
     */
    public static function findByVolumesId(int $vid)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = $db->select()
            ->from(T_DOI_QUEUE_VOLUMES)
            ->where('vid = ?', $vid);

        $res = $db->fetchRow($query);
        if (empty($res)) {
            $doiQueueVolumes = null;
        } else {
            $doiQueueVolumes = $res;
        }
        return new Episciences_Volume_DoiQueue($doiQueueVolumes);
    }

    /**
     * @param Episciences_Volume_DoiQueue $doiQueueVolume
     * @return int
     */
    public static function add(Episciences_Volume_DoiQueue $doiQueueVolume): int
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $nowDb = new Zend_DB_Expr('NOW()');
        $values = [
            'vid' => $doiQueueVolume->getVid(),
            'doi_status' => $db->quote($doiQueueVolume->getDoi_status()),
            'date_init' => $nowDb,
            'date_updated' => $nowDb
        ];
        if (!empty($values)) {
            try {
                $sql = 'INSERT INTO ' . $db->quoteIdentifier(T_DOI_QUEUE_VOLUMES) . ' (`vid`,`doi_status`,`date_init`,`date_updated`) VALUES (';
                //Prepares and executes an SQL
                /** @var Zend_Db_Statement_Interface $result */
                $result = $db->query($sql . implode(', ', $values) . ') ON DUPLICATE KEY UPDATE doi_status=VALUES(doi_status), date_updated=NOW()');
                $resInsert = $result->rowCount();
            } catch (Exception $e) {
                error_log($e->getMessage());
                error_log('Error adding DOI request queue for VolumeId ' . $doiQueueVolume->getVolumeId() . ' status ' . $doiQueueVolume->getDoi_status());
                $resInsert = 0;
            }
        }
        return $resInsert;
    }

    /**
     * @param Episciences_Volume_DoiQueue $doiQueueVolumes
     * @return int
     */
    public static function update(Episciences_Volume_DoiQueue $doiQueueVolumes): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $where['id = ?'] = $doiQueueVolumes->getId();

        $values = [
            'vid' => $doiQueueVolumes->getVid(),
            'doi_status' => $db->quote($doiQueueVolumes->getDoi_status()),
            'date_updated' => new Zend_DB_Expr('NOW()')
        ];
        try {
            $resUpdate = $db->update(T_DOI_QUEUE_VOLUMES, $values, $where);
        } catch (Zend_Db_Adapter_Exception $exception) {
            error_log($exception->getMessage());
            $resUpdate = 0;
        }
        return $resUpdate;
    }

}