<?php

class Episciences_Page_Manager
{
    /**
     * Convert visibility SET value to JSON format for legacy column synchronization
     * Example: "editor,chief_editor" -> '["editor","chief_editor"]'
     */
    private static function visibilityToJson(string $visibility): string
    {
        if (empty($visibility)) {
            return '["public"]';
        }
        $values = explode(',', $visibility);
        return json_encode($values, JSON_UNESCAPED_UNICODE);
    }
    /**
     * Find all pages for a given review code
     *
     * @param string $code Review code
     * @return array<string, Episciences_Page> Indexed by page_code
     */
    public static function findAllByCode(string $code): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = $db->select()
            ->from(T_PAGES, [
                'id', 'code', 'uid', 'date_creation', 'date_updated',
                'title', 'content', 'page_code',
                'visibility' => 'visibility_set'
            ])
            ->where('code = ?', $code);

        $rows = $db->fetchAll($query);
        $pages = [];

        foreach ($rows as $row) {
            $page = new Episciences_Page($row);
            $pages[$page->getPageCode()] = $page;
        }

        return $pages;
    }

    public static function findByCodeAndPageCode(string $code, string $page_code): Episciences_Page
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = $db->select()
            ->from(T_PAGES, [
                'id', 'code', 'uid', 'date_creation', 'date_updated',
                'title', 'content', 'page_code',
                'visibility' => 'visibility_set'
            ])
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
        $visibilityValue = $page->getVisibility();
        $values = [
            'id' => $page->getId(),
            'code' => $page->getCode(),
            'uid' => $page->getUid(),
            'date_creation' => $nowDb,
            'date_updated' => $nowDb,
            'title' => $page->getTitle(),
            'content' => $page->getContent(),
            'visibility_set' => $visibilityValue,
            'visibility' => self::visibilityToJson($visibilityValue),
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
        $visibilityValue = $page->getVisibility();

        $values = [
            'id' => $page->getId(),
            'code' => $page->getCode(),
            'uid' => $page->getUid(),
            'date_updated' => new Zend_DB_Expr('NOW()'),
            'title' => $page->getTitle(),
            'content' => $page->getContent(),
            'visibility_set' => $visibilityValue,
            'visibility' => self::visibilityToJson($visibilityValue),
            'page_code' => $page->getPageCode()
        ];

        try {
            $resUpdate = $db->update(T_PAGES, $values, $where);
        } catch (Zend_Db_Adapter_Exception $exception) {
            error_log($exception->getMessage());
            $resUpdate = 0;
        }

        return $resUpdate;
    }

    /**
     * Update a page with a new page_code (permalien change)
     * Uses the old page_code in where clause to find the entry
     */
    public static function updateWithNewPageCode(Episciences_Page $page, string $oldPageCode): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $where = ['code = ?' => $page->getCode(), 'page_code = ?' => $oldPageCode];
        $visibilityValue = $page->getVisibility();

        $values = [
            'uid' => $page->getUid(),
            'date_updated' => new Zend_DB_Expr('NOW()'),
            'title' => $page->getTitle(),
            'content' => $page->getContent(),
            'visibility_set' => $visibilityValue,
            'visibility' => self::visibilityToJson($visibilityValue),
            'page_code' => $page->getPageCode() // New page_code
        ];

        try {
            $resUpdate = $db->update(T_PAGES, $values, $where);
        } catch (Zend_Db_Adapter_Exception $exception) {
            error_log($exception->getMessage());
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
