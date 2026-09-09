<?php

declare(strict_types=1);

namespace Episciences\Journal\Provisioning;

use Ccsd_Website_Common;
use Episciences_Review;
use Episciences_Review_DoiSettings;
use Episciences_ReviewsManager;
use RuntimeException;
use Throwable;
use Zend_Db_Table_Abstract;
use Zend_Registry;

/**
 * Orchestrates `journal:create`: guards, then either a --dry-run report or the real
 * transactional creation.
 *
 * Ordering constraint (see CreateJournalCommand's docblock for the full picture): RVCODE,
 * REVIEW_PATH and RVID are process-wide PHP constants that can only be defined once, and
 * REVIEW_PATH is built with realpath() — which returns false (so REVIEW_PATH ends up '/')
 * if the journal's data directory does not exist yet. So this class defines those constants
 * itself, strictly after the REVIEW row exists and the data directory has been created, and
 * before anything that reads REVIEW_PATH/REVIEW_LANG_PATH/etc (the website/pages clone steps).
 * If a failure occurs after that point, those constants are left pointing at a journal whose
 * creation is about to be rolled back — harmless because the command exits immediately after,
 * but worth knowing if this class is ever changed to keep running past a failure.
 */
final class JournalCreator
{
    public function __construct(
        private readonly ReviewRowWriter $reviewRowWriter,
        private readonly SettingsCloner $settingsCloner,
        private readonly DataDirectoryProvisioner $dataDirectoryProvisioner,
        private readonly WebsiteCloner $websiteCloner,
        private readonly PagesCloner $pagesCloner,
        private readonly AdminRoleAssigner $adminRoleAssigner,
    ) {
    }

    public function create(JournalSpec $spec, bool $dryRun, bool $complete): Report
    {
        $report = new Report();

        $existingRvid = $this->guardCodeAndResolveRvid($spec->code, $complete);
        $template = $this->guardTemplateExists($spec->templateRvcode);
        $this->adminRoleAssigner->assertUserExists($spec->adminUid);

        if ($dryRun) {
            return $this->planReport($spec, $existingRvid, $template, $report);
        }

        return $this->createForReal($spec, $existingRvid, $template, $report);
    }

    private function createForReal(JournalSpec $spec, ?int $existingRvid, Episciences_Review $template, Report $report): Report
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->beginTransaction();

        $createdPaths = [];

        try {
            $rvid = $existingRvid ?? $this->reviewRowWriter->insert($spec);
            $report->add(
                'REVIEW',
                $existingRvid !== null ? "existing RVID $rvid reused (--complete)" : "RVID $rvid created"
            );

            $insertedSettings = $this->settingsCloner->clone($template->getRvid(), $rvid, $existingRvid !== null);
            $report->add('REVIEW_SETTING', "$insertedSettings setting(s) cloned from '{$spec->templateRvcode}'");

            if ($spec->resetDoi) {
                $this->settingsCloner->resetDoiSettings($rvid);
                $report->add('DOI settings', 'reset to manual mode with an empty prefix (--reset-doi)');
            } elseif (
                // readSetting() returns null when the template has no explicit doiAssignMode
                // row — which does NOT mean manual mode: Episciences_Review_DoiSettings defaults
                // $_doiAssignMode to automatic (DOI_DEFAULT_ASSIGN_MODE) whenever no row is
                // found, so a missing setting must be treated the same as an explicit
                // 'automatic' one, or a template with a real doiPrefix but no doiAssignMode row
                // would clone that prefix with no warning at all.
                ($this->settingsCloner->readSetting($rvid, Episciences_Review_DoiSettings::SETTING_DOI_ASSIGN_MODE)
                    ?? Episciences_Review_DoiSettings::DOI_DEFAULT_ASSIGN_MODE)
                === Episciences_Review_DoiSettings::DOI_ASSIGN_MODE_AUTO
            ) {
                $report->warn(
                    "Cloned DOI settings use automatic assignment: running doi:manage on '{$spec->code}' would " .
                    "register real DOIs under '{$spec->templateRvcode}''s prefix. Re-run with --reset-doi to avoid this."
                );
            }

            // Passed by reference: even if provision() throws partway through (e.g. the first
            // mkdir() of a series succeeds but a later one fails), $createdPaths already holds
            // whatever was created up to that point, since writes through a reference parameter
            // are visible to the caller regardless of how the function exits.
            $this->dataDirectoryProvisioner->provision($spec->code, $spec->templateRvcode, false, $createdPaths);
            $report->add('Data directory', count($createdPaths) . ' path(s) created under data/' . $spec->code . '/');

            $this->defineJournalConstantsFor($spec->code, $rvid);

            $languages = $this->resolveTemplateLanguages($template->getRvid());

            $websiteCloned = $this->websiteCloner->clone(
                $template->getRvid(),
                $rvid,
                $spec->templateRvcode,
                $spec->code,
                $languages,
                REVIEW_URL
            );
            $report->add('Website', $websiteCloned
                ? "menu, languages setting, styles and header cloned from '{$spec->templateRvcode}'"
                : 'skipped: target already has a menu (--complete)');

            $clonedPages = $this->pagesCloner->clone($spec->templateRvcode, $spec->code, $spec->adminUid);
            $report->add('Pages', "$clonedPages page(s) cloned");

            $this->adminRoleAssigner->assign($spec->adminUid, $rvid);
            $report->add('Admin role', "UID {$spec->adminUid} granted the administrator role");

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();

            if ($existingRvid === null) {
                // A brand-new journal: guardCodeAndResolveRvid() already established that no
                // REVIEW row existed for this code, so nothing legitimate can have been under
                // data/<code>/ either. Removing the whole tree also cleans up files WebsiteCloner
                // and PagesCloner wrote (style.css, header.<lang>.html, logo images, menu.php,
                // cloned page files) that $createdPaths never tracked, not just the directories
                // DataDirectoryProvisioner itself created.
                $this->dataDirectoryProvisioner->rollbackAll($spec->code);
            } else {
                // --complete resuming an existing journal: the data directory may already hold
                // real content from a previously successful run, so only remove the paths this
                // run is actually aware of having created.
                $this->dataDirectoryProvisioner->rollback($spec->code, $createdPaths);
            }

            throw $e;
        }

        Episciences_ReviewsManager::clearCache();
        $report->add('RVID', (string)$rvid);

        return $report;
    }

    private function planReport(JournalSpec $spec, ?int $existingRvid, Episciences_Review $template, Report $report): Report
    {
        $report->add(
            'REVIEW',
            $existingRvid !== null
                ? "existing RVID $existingRvid would be reused (--complete)"
                : "a new REVIEW row would be created for code '{$spec->code}'"
        );

        $countCloneable = $this->settingsCloner->countCloneable($template->getRvid());
        $report->add('REVIEW_SETTING', "$countCloneable setting(s) would be cloned from '{$spec->templateRvcode}'");

        if ($spec->resetDoi) {
            $report->add('DOI settings', 'would be reset to manual mode with an empty prefix (--reset-doi)');
        }

        $plannedPaths = $this->dataDirectoryProvisioner->provision($spec->code, $spec->templateRvcode, true);
        $report->add('Data directory', count($plannedPaths) . ' path(s) would be created under data/' . $spec->code . '/');

        $report->add('Website', "menu, languages setting, styles and header would be cloned from '{$spec->templateRvcode}'");
        $report->add('Pages', "custom pages would be cloned from '{$spec->templateRvcode}'");
        $report->add('Admin role', "UID {$spec->adminUid} would be granted the administrator role");

        return $report;
    }

    private function guardCodeAndResolveRvid(string $code, bool $complete): ?int
    {
        if (!Episciences_Review::exist($code)) {
            return null;
        }

        if (!$complete) {
            throw new RuntimeException("A journal with code '$code' already exists. Use --complete to finish provisioning it.");
        }

        $review = Episciences_ReviewsManager::findByRvcode($code);
        if ($review === false) {
            throw new RuntimeException("Journal '$code' exists but could not be loaded.");
        }

        return $review->getRvid();
    }

    private function guardTemplateExists(string $templateRvcode): Episciences_Review
    {
        $template = Episciences_ReviewsManager::findByRvcode($templateRvcode);
        if ($template === false) {
            throw new RuntimeException("Template journal '$templateRvcode' does not exist.");
        }

        return $template;
    }

    /**
     * @return string[]
     */
    private function resolveTemplateLanguages(int $templateRvid): array
    {
        $languages = (new Ccsd_Website_Common($templateRvid, []))->getLanguages();

        return $languages !== [] ? $languages : ['en'];
    }

    /**
     * Defines RVCODE/RVID/REVIEW_PATH/etc for the rest of this process, once — and only once —
     * the REVIEW row and its data directory both exist, so realpath() resolves REVIEW_PATH
     * correctly instead of falling back to '/'. Mirrors ImportPapersCommand::lockJournal(),
     * minus the parts specific to paper import.
     */
    private function defineJournalConstantsFor(string $code, int $rvid): void
    {
        defineJournalConstants($code);

        if (!defined('RVID')) {
            define('RVID', $rvid);
        }

        $review = Episciences_ReviewsManager::findByRvid($rvid);
        if ($review !== false) {
            $review->loadSettings();
            Zend_Registry::set('reviewSettingsDoi', $review->getDoiSettings());
        }

        if (is_dir(REVIEW_PATH . 'languages') && count(scandir(REVIEW_PATH . 'languages') ?: []) > 2) {
            Zend_Registry::get('Zend_Translate')->addTranslation(REVIEW_PATH . 'languages');
        }
    }
}
