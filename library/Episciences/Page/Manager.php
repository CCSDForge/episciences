<?php

class Episciences_Page_Manager
{
    public static function findByCodeAndPageCode(string $code, string $page_code): Episciences_Page
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = $db->select()
            ->from(T_PAGES)
            ->where('code = ?', $code)
            ->where('page_code = ?', $page_code);

        $res = $db->fetchRow($query);
        if (!$res) {
            $res = [];
        }
        return new Episciences_Page($res ?? null);
    }

    public static function add(Episciences_Page $page): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $nowDb = new Zend_DB_Expr('NOW()');
        $values = [
            'id' => $page->getId(),
            'code' => $page->getCode(),
            'uid' => $page->getUid(),
            'date_creation' => $nowDb,
            'date_updated' => $nowDb,
            'title' => $page->getTitle(),
            'content' => $page->getContent(),
            'visibility' => $page->getVisibility(),
            'page_code' => $page->getPageCode()
        ];

        try {
            $resInsert = $db->insert(T_PAGES, $values) ? $db->lastInsertId() : 0;
        } catch (Zend_Db_Adapter_Exception $exception) {
            error_log($exception->getMessage());
            $resInsert = 0;
        }

        return $resInsert;
    }

    public static function update(Episciences_Page $page): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $where = ['code = ?' => $page->getCode(), 'page_code = ?' => $page->getPageCode()];

        $values = [
            'id' => $page->getId(),
            'code' => $page->getCode(),
            'uid' => $page->getUid(),
            'date_updated' => new Zend_DB_Expr('NOW()'),
            'title' => $page->getTitle(),
            'content' => $page->getContent(),
            'visibility' => $page->getVisibility(),
            'page_code' => $page->getPageCode()
        ];

        // Debug log
        $logFile = REVIEW_PATH . 'tmp/sync_debug.log';
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - UPDATE T_PAGES=" . T_PAGES . "\n", FILE_APPEND);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - UPDATE values title=" . $values['title'] . "\n", FILE_APPEND);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - UPDATE where=" . json_encode($where) . "\n", FILE_APPEND);

        try {
            $resUpdate = $db->update(T_PAGES, $values, $where);
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - UPDATE affected rows=" . $resUpdate . "\n", FILE_APPEND);
        } catch (Zend_Db_Adapter_Exception $exception) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - UPDATE ERROR=" . $exception->getMessage() . "\n", FILE_APPEND);
            $resUpdate = 0;
        }

        return $resUpdate;
    }

    public static function delete(string $page_code, string $code): bool
    {
        if (!$page_code || !$code) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $where = ['code = ?' => $code, 'page_code = ?' => $page_code];
        try {
            $resDelete = $db->delete(T_PAGES, $where);
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            $resDelete = 0;
        }


        return $resDelete > 0;
    }
}
