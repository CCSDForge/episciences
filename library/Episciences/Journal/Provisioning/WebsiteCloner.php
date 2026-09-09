<?php

declare(strict_types=1);

namespace Episciences\Journal\Provisioning;

use Ccsd_Lang_Reader;
use Ccsd_Lang_Writer;
use Ccsd_Website_Header;
use RuntimeException;
use Zend_Db_Table_Abstract;

/**
 * Clones a journal's menu (WEBSITE_NAVIGATION rows + menu.php labels), interface languages
 * (WEBSITE_SETTINGS.languages) and appearance (WEBSITE_STYLES, WEBSITE_HEADER) from a template
 * journal to a freshly created one.
 *
 * Does not go through Episciences_Website_Navigation::save(): it deletes and rewrites the
 * whole menu, renumbering every PAGEID via its own counter (processPage()), which would
 * desynchronize the copied LABEL keys (`menu-label-<PAGEID>`) from the copied menu.php entries.
 * A verbatim SQL copy keeps PAGEID, PARENT_PAGEID and LABEL untouched, so the hierarchy and
 * translations stay consistent by construction. Nor does it use
 * Episciences_Website_Navigation::load() to read the template's labels: that method hardcodes
 * REVIEW_LANG_PATH, which for the whole life of this process is bound to the *target*
 * journal's code, not the template's.
 *
 * config/navigation.json is not handled here: DataDirectoryProvisioner already copies it as
 * part of the template's config/ directory (or the running application falls back to
 * data/default/config/navigation.json at runtime if the template has none) — cloning it again
 * here would just duplicate that.
 */
final class WebsiteCloner
{
    public function hasNavigation(int $rvid): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->from('WEBSITE_NAVIGATION', 'COUNT(*)')->where('SID = ?', $rvid);

        return (int)$db->fetchOne($select) > 0;
    }

    /**
     * @param string[] $languages interface languages, e.g. ['fr', 'en']
     * @return bool false when skipped because the target already has a menu (--complete on an
     *              already-provisioned journal), true when it actually cloned everything
     */
    public function clone(
        int $fromRvid,
        int $toRvid,
        string $fromRvcode,
        string $toRvcode,
        array $languages,
        string $targetPublicUrl
    ): bool {
        if ($this->hasNavigation($toRvid)) {
            return false;
        }

        $this->cloneNavigationRows($fromRvid, $toRvid);
        $this->cloneMenuLabels($fromRvcode, $toRvcode, $languages);
        $this->cloneLanguagesSetting($fromRvid, $toRvid);
        $this->cloneStyles($fromRvid, $toRvid, $fromRvcode, $toRvcode, $targetPublicUrl);
        $this->cloneHeader($fromRvid, $toRvid, $fromRvcode, $toRvcode, $languages, $targetPublicUrl);

        return true;
    }

    private function cloneNavigationRows(int $fromRvid, int $toRvid): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $db->query(
            'INSERT INTO WEBSITE_NAVIGATION (SID, PAGEID, TYPE_PAGE, CONTROLLER, ACTION, LABEL, PARENT_PAGEID, PARAMS)
             SELECT ?, PAGEID, TYPE_PAGE, CONTROLLER, ACTION, LABEL, PARENT_PAGEID, PARAMS
             FROM WEBSITE_NAVIGATION
             WHERE SID = ?
             ORDER BY NAVIGATIONID ASC',
            [$toRvid, $fromRvid]
        );
    }

    /**
     * @param string[] $languages
     */
    private function cloneMenuLabels(string $fromRvcode, string $toRvcode, array $languages): void
    {
        if ($languages === []) {
            return;
        }

        $templateLangPath = DataDirectoryProvisioner::pathFor($fromRvcode) . 'languages/';
        $targetLangPath = DataDirectoryProvisioner::pathFor($toRvcode) . 'languages/';

        if (!is_dir($templateLangPath)) {
            return;
        }

        $reader = new Ccsd_Lang_Reader('menu', $templateLangPath, $languages, true);
        $writer = new Ccsd_Lang_Writer($reader->get());
        $writer->add($targetLangPath, 'menu');
    }

    private function cloneLanguagesSetting(int $fromRvid, int $toRvid): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $db->query(
            "INSERT IGNORE INTO WEBSITE_SETTINGS (SID, SETTING, VALUE)
             SELECT ?, SETTING, VALUE
             FROM WEBSITE_SETTINGS
             WHERE SID = ? AND SETTING = 'languages'",
            [$toRvid, $fromRvid]
        );
    }

    private function cloneStyles(int $fromRvid, int $toRvid, string $fromRvcode, string $toRvcode, string $targetPublicUrl): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->from('WEBSITE_STYLES', ['SETTING', 'VALUE'])->where('RVID = ?', $fromRvid);
        $styles = $db->fetchPairs($select);

        if ($styles === []) {
            return;
        }

        $targetPublicDir = DataDirectoryProvisioner::pathFor($toRvcode) . 'public/';

        $target = new ClonableWebsiteStyle($toRvid, $targetPublicDir, $targetPublicUrl);
        // save() deletes any existing WEBSITE_STYLES rows for $toRvid (none yet) then rewrites
        // style.css on disk from the cloned tags, so the new journal gets correctly
        // domain-qualified asset URLs rather than the template's.
        $target->save($styles);

        // save() only copies an uploaded file's tmp path via the settings form (getFileName()),
        // which is null here since $styles comes straight from the database, not a form
        // submission — so the background image referenced by the cloned bg_img_file row would
        // otherwise never actually reach the new journal's public/, leaving style.css pointing
        // at a file that doesn't exist. Reuses copyLogoFile()'s same bare-filename validation.
        if (!empty($styles['bg_img_file'])) {
            $this->copyLogoFile(
                DataDirectoryProvisioner::pathFor($fromRvcode) . 'public/',
                $targetPublicDir,
                (string)$styles['bg_img_file']
            );
        }
    }

    /**
     * @param string[] $languages
     */
    /**
     * @param string[] $languages
     */
    private function cloneHeader(
        int $fromRvid,
        int $toRvid,
        string $fromRvcode,
        string $toRvcode,
        array $languages,
        string $targetPublicUrl
    ): void {
        $source = new ClonableWebsiteHeader($fromRvid);
        $source->load();

        if ($source->_logos === []) {
            return;
        }

        $sourcePublicDir = DataDirectoryProvisioner::pathFor($fromRvcode) . 'public/';
        $targetPublicDir = DataDirectoryProvisioner::pathFor($toRvcode) . 'public/';

        // Each loaded logo carries the source row's own 'rvid' key (lower-cased column name).
        // Ccsd_Website_Header::save() seeds its insert with [$_fieldSID => $this->_sid] and then
        // lets the logo's own keys overwrite it — so an un-stripped 'rvid' key would silently
        // re-point every cloned row back at the *template*'s RVID instead of the new journal's.
        $logos = $source->_logos;
        foreach ($logos as &$logo) {
            unset($logo['rvid'], $logo['sid']);

            // Only the specific logo image file is copied — not the whole public/ directory,
            // which on a real journal also holds per-volume generated exports (DOAJ XML,
            // merged PDFs) that have nothing to do with the header.
            if (($logo['type'] ?? null) === Ccsd_Website_Header::LOGO_IMG && !empty($logo['img'])) {
                $this->copyLogoFile($sourcePublicDir, $targetPublicDir, (string)$logo['img']);
            }
        }
        unset($logo);

        $target = new ClonableWebsiteHeader(
            $toRvid,
            $targetPublicDir,
            $targetPublicUrl,
            DataDirectoryProvisioner::pathFor($toRvcode) . 'layout/'
        );
        $target->setLanguages($languages);
        $target->_logos = $logos;
        // save([], []) skips setHeader() (no form submission to apply) but still writes the
        // logos we just assigned and regenerates layout/header.<lang>.html for the new journal.
        $target->save([], []);
    }

    private function copyLogoFile(string $sourcePublicDir, string $targetPublicDir, string $filename): void
    {
        // A logo referencing a built-in asset (e.g. "/img/...") lives outside the journal's
        // public/ directory entirely — nothing to copy.
        if ($filename === '' || str_starts_with($filename, '/img')) {
            return;
        }

        // WEBSITE_HEADER.img is expected to be a bare file name; reject anything that isn't,
        // since Ccsd_File::renameFile() (which produced it on the template journal) does not
        // itself forbid path separators — a stray one here would let the concatenation below
        // escape the journal's public/ directory.
        $safeName = basename($filename);
        if ($safeName === '' || $safeName !== $filename) {
            return;
        }

        $sourceFile = $sourcePublicDir . $safeName;
        $targetFile = $targetPublicDir . $safeName;

        if (!is_file($sourceFile) || file_exists($targetFile)) {
            return;
        }

        if (!is_dir($targetPublicDir) && !mkdir($targetPublicDir, 0770, true) && !is_dir($targetPublicDir)) {
            throw new RuntimeException("Directory \"$targetPublicDir\" was not created");
        }

        if (!copy($sourceFile, $targetFile)) {
            throw new RuntimeException("Failed to copy logo file \"$sourceFile\" to \"$targetFile\"");
        }
        chmod($targetFile, 0644);
    }
}
