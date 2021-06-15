<?php

class Episciences_Paper_DoiQueueManager
{
    /**
     * @param int $paperId
     * @return Episciences_Paper_DoiQueue
     */
    public static function findByPaperId(int $paperId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = $db->select()
            ->from(T_DOI_QUEUE)
            ->where('paperid = ?', $paperId);

        $res = $db->fetchRow($query);
        if (empty($res)) {
            $doiQueue = null;
        } else {
            $doiQueue = $res;
        }
        return new Episciences_Paper_DoiQueue($doiQueue);
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
            if ($db->insert(T_DOI_QUEUE, $values)) {
                $resInsert = $db->lastInsertId();
            } else {
                $resInsert = 0;
            }
        } catch (Zend_Db_Adapter_Exception $exception) {
            error_log($exception->getMessage());
            error_log('Error adding DOI request queue for  for paperId ' . $doiQueue->getPaperid() . ' status ' . $doiQueue->getDoi_status());
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
        $where['id_doi_queue = ?'] = $doiQueue->getId_doi_queue();

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
        $resDelete = $db->delete(T_DOI_QUEUE, ['paperid = ?' => $paperId]);
        return $resDelete > 0;
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

        $result = [];
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = $db->query("SELECT * FROM ". T_DOI_QUEUE . " DQ, `PAPERS` P WHERE P.RVID = ? AND P.DOI !='' AND P.PAPERID = DQ.paperid AND P.STATUS = ?  AND DQ.doi_status = ? ORDER BY P.PAPERID",
            [$rvid, $paperStatus, $doiQueueStatus]);

        foreach ($query->fetchAll() as $k => $row) {
            $p = new Episciences_Paper($row);
            $q = new Episciences_Paper_DoiQueue($row);
            $result[$k]['paper'] = $p;
            $result[$k]['doiq'] = $q;
        }
        return $result;
    }

}