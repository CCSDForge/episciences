<?php

declare(strict_types=1);

namespace Episciences\Journal\Provisioning;

use Episciences_Page;
use Episciences_Page_Manager;
use Zend_Db_Table_Abstract;

/**
 * Clones custom CMS pages (the `pages` table + their rendered HTML files under
 * data/<rvcode>/pages/, read by the legacy ZF1-rendered front end) from a template journal to
 * a new one.
 *
 * Episciences_Page_Manager has no bulk enumeration method (only findByCodeAndPageCode()), so
 * this reads the source rows directly. title/content/visibility are copied as the
 * already-serialized JSON strings the source row holds, rather than re-encoded through
 * Episciences_Page's setters: setContent() converts HTML to Markdown on every call, and the
 * stored value is already Markdown — running it through that conversion again would corrupt it.
 */
final class PagesCloner
{
    /**
     * @return int number of pages cloned. A page_code already present on the target is left
     *             untouched (not updated), which makes this safe to re-run under --complete.
     */
    public function clone(string $fromCode, string $toCode, int $uid): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->from('pages')->where('code = ?', $fromCode);
        $sourceRows = $db->fetchAll($select);

        $cloned = 0;

        foreach ($sourceRows as $row) {
            $pageCode = (string)$row['page_code'];

            $existing = Episciences_Page_Manager::findByCodeAndPageCode($toCode, $pageCode);
            if ($existing->getId() > 0) {
                continue;
            }

            $newPage = new Episciences_Page();
            $newPage->setCode($toCode);
            $newPage->setPageCode($pageCode);
            $newPage->setUid($uid);
            $newPage->setTitle((string)$row['title'], false);
            $newPage->setContent((string)$row['content'], false);
            $newPage->setVisibility((string)$row['visibility'], false);

            Episciences_Page_Manager::add($newPage);
            $this->clonePageFiles($fromCode, $toCode, $pageCode);
            $cloned++;
        }

        return $cloned;
    }

    private function clonePageFiles(string $fromCode, string $toCode, string $pageCode): void
    {
        $sourceDir = DataDirectoryProvisioner::pathFor($fromCode) . 'pages/';
        $targetDir = DataDirectoryProvisioner::pathFor($toCode) . 'pages/';

        foreach (glob($sourceDir . $pageCode . '.*.html') ?: [] as $sourceFile) {
            $targetFile = $targetDir . basename($sourceFile);
            if (file_exists($targetFile)) {
                continue;
            }

            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0770, true);
            }
            copy($sourceFile, $targetFile);
            chmod($targetFile, 0644);
        }
    }
}
