<?php

class Episciences_Paper_DoiQueueManager
{
    /**
     * @param int $paperId
     * @return Episciences_Paper_DoiQueue
     */
    public static function findByPaperId(int $paperId): ?Episciences_Paper_DoiQueue
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = $db->select()
            ->from(T_DOI_QUEUE)
            ->where('paperid =?', $paperId);

        $res = $db->fetchRow($query);
        if (empty($res)) {
            return null;
        }
        return new Episciences_Paper_DoiQueue($res);
    }

    /**
     * @param Episciences_Paper_DoiQueue $doiQueue
     * @return int
     */
    public static function add(Episciences_Paper_DoiQueue $doiQueue): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $nowDb = new Zend_DB_Expr('NOW()');
        $values = [
            'paperid' => $doiQueue->getPaperid(),
            'doi_status' => $doiQueue->getDoi_status(),
            'date_init' => $nowDb,
            'date_updated' => $nowDb
        ];
        try {
            $resInsert = $db->insert(T_DOI_QUEUE, $values);
            if (!$resInsert) {
                $resInsert = 0;
            }
        } catch (Zend_Db_Adapter_Exception $exception) {
            error_log($exception->getMessage());
            error_log('Error adding DOI request queue for paperId ' . $doiQueue->getPaperid() . ' status ' . $doiQueue->getDoi_status());
            $resInsert = 0;
        }
        return $resInsert;
    }


    /**
     * @param Episciences_Paper_DoiQueue $doiQueue
     * @return int
     */
    public static function update(Episciences_Paper_DoiQueue $doiQueue): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $where = ['id_doi_queue =?' => $doiQueue->getId_doi_queue()];

        $values = [
            'paperid' => $doiQueue->getPaperid(),
            'doi_status' => $doiQueue->getDoi_status(),
            'date_updated' => new Zend_DB_Expr('NOW()')
        ];
        try {
            $resUpdate = $db->update(T_DOI_QUEUE, $values, $where);
        } catch (Zend_Db_Adapter_Exception $exception) {
            error_log($exception->getMessage());
            $resUpdate = 0;
        }
        return $resUpdate;
    }

    /**
     * @param int $paperId
     * @return bool
     */
    public static function delete(int $paperId): bool
    {
        if ($paperId < 1) {
            return false;
        }
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $resDelete = $db->delete(T_DOI_QUEUE, 'paperid =?', [$paperId]);
        return $resDelete > 0;
    }

    /**
     * @throws Zend_Db_Statement_Exception
     */
    public static function getPublicDoiToUpdate(): array
    {
        $dois = [];
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(T_REVIEW, ['RVID', 'NAME'])
            ->where('RVID != 0')
            ->where('STATUS = 1');

        foreach ($db->fetchAll($sql) as $row) {
            $dois[] = self::findDoisByStatus($row['RVID'], Episciences_Paper::STATUS_PUBLISHED, Episciences_Paper_DoiQueue::STATUS_PUBLIC);
        }

        return $dois;

    }

    /**
     * @param int $rvid
     * @param $paperStatus
     * @param $doiQueueStatus
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    public static function findDoisByStatus($rvid, $paperStatus, $doiQueueStatus): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = $db->query("SELECT * FROM " . T_DOI_QUEUE . " DQ, `PAPERS` P WHERE P.RVID =? AND P.DOI!='' AND P.PAPERID = DQ.paperid AND P.STATUS =?  AND DQ.doi_status =? ORDER BY P.PAPERID",
            [$rvid, $paperStatus, $doiQueueStatus]);
        return array_map(static function ($row) {
            return [
                'paper' => new Episciences_Paper($row),
                'doiq' => new Episciences_Paper_DoiQueue($row)
            ];
        }, $query->fetchAll());
    }


}