<?php

class Episciences_Page_Manager
{
    /** Page codes that have no corresponding Next.js fetch tag — skip revalidation. */
    private const NEXT_SKIP_PAGE_CODES = ['editorial-workflow', 'ethical-charter', 'prepare-submission'];

    /** Direct mapping from page_code to tag template (placeholder {rvcode} resolved at runtime). */
    private const NEXT_PAGE_CODE_TAGS = [
        'about'                     => 'about',
        'indexing'                  => 'indexing',
        'indexation-metrics'        => 'indexation',
        'credits'                   => 'credits',
        'for-reviewers'             => 'for-reviewers',
        'for-conference-organisers' => 'for-conference-organisers',
        'proposing-special-issues'  => 'proposing-special-issues',
        'acknowledgements'          => 'acknowledgements',
    ];

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

        if ($resInsert > 0) {
            $tag = self::resolvePageTag($page->getPageCode(), $page->getCode());
            if ($tag !== null) {
                \Episciences\Next\RevalidationService::revalidateOrEnqueue($page->getCode(), $tag);
            }
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

        try {
            $resUpdate = $db->update(T_PAGES, $values, $where);
        } catch (Zend_Db_Adapter_Exception $exception) {
            error_log($exception->getMessage());
            $resUpdate = 0;
        }

        if ($resUpdate > 0) {
            $tag = self::resolvePageTag($page->getPageCode(), $page->getCode());
            if ($tag !== null) {
                \Episciences\Next\RevalidationService::revalidateOrEnqueue($page->getCode(), $tag);
            }
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

        if ($resDelete > 0) {
            $tag = self::resolvePageTag($page_code, $code);
            if ($tag !== null) {
                \Episciences\Next\RevalidationService::revalidateOrEnqueue($code, $tag);
            }
        }

        return $resDelete > 0;
    }

    /**
     * Resolve the Next.js cache tag for a given page_code and journal rvcode.
     * Returns null for pages that have no corresponding Next.js fetch tag.
     */
    private static function resolvePageTag(string $pageCode, string $rvcode): ?string
    {
        if (in_array($pageCode, self::NEXT_SKIP_PAGE_CODES, true)) {
            return null;
        }

        if (isset(self::NEXT_PAGE_CODE_TAGS[$pageCode])) {
            return self::NEXT_PAGE_CODE_TAGS[$pageCode] . '-' . $rvcode;
        }

        return 'page-' . $pageCode . '-' . $rvcode;
    }
}