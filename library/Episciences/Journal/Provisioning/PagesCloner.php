<?php

declare(strict_types=1);

namespace Episciences\Journal\Provisioning;

use Episciences_Page;
use Episciences_Page_Manager;
use RuntimeException;
use Zend_Db_Table_Abstract;

/**
 * Clones custom CMS pages (the `pages` table + their rendered HTML files under
 * data/<rvcode>/pages/, read by the legacy ZF1-rendered front end) from a template journal to
 * a new one.
 *
 * Episciences_Page_Manager has no bulk enumeration method (only findByCodeAndPageCode()), so
 * this reads the source rows directly. title/content/visibility are passed to Episciences_Page's
 * setters with $serialize = false: the source row already holds the serialized JSON string
 * Episciences_Page itself would produce, so re-serializing it would double-encode it. The
 * $serialize flag is also what keeps setContent() from running its HTML-to-Markdown conversion —
 * that conversion only triggers when given an array (raw form input), never a string.
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

            $newId = Episciences_Page_Manager::add($newPage);
            if ($newId <= 0) {
                throw new RuntimeException("Failed to insert cloned page '$pageCode' for journal '$toCode'.");
            }

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

            if (!is_dir($targetDir) && !mkdir($targetDir, 0770, true) && !is_dir($targetDir)) {
                throw new RuntimeException("Directory \"$targetDir\" was not created");
            }

            if (!copy($sourceFile, $targetFile)) {
                throw new RuntimeException("Failed to copy page file \"$sourceFile\" to \"$targetFile\"");
            }
            chmod($targetFile, 0644);
        }
    }
}
