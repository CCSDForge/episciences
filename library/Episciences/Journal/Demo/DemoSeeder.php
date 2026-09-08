<?php

declare(strict_types=1);

namespace Episciences\Journal\Demo;

use Episciences\Journal\Provisioning\Report;
use Episciences\Paper\Import\PaperImporter;
use Episciences\Paper\Import\PublicationDateResolver;
use Episciences\Paper\Import\Row;
use Episciences\Paper\Import\VolumeSectionResolver;
use Episciences_Volume;
use Episciences_VolumesManager;
use RuntimeException;
use Throwable;

/**
 * Seeds a journal with a small, fixed set of real submissions (see
 * scripts/importSamples/demo-papers.csv) across a few volumes and sections, for manually
 * testing a journal end-to-end without touching production data.
 *
 * A thin wrapper around the same import machinery `import:papers` uses
 * (Episciences\Paper\Import\{Row,VolumeSectionResolver,PublicationDateResolver,PaperImporter}):
 * that machinery already creates volumes/sections on the fly from a title, matches existing
 * papers to avoid duplicating them on a second run, and fetches real metadata from the
 * repository (HAL/arXiv/Zenodo/...) for each identifier — so this class only adds the
 * uid/rvid rewriting (DemoCsvRewriter) and the "mark a volume as the current/special issue"
 * step VolumeSectionResolver::createVolume() cannot do (it always creates a volume with
 * current_issue = 0, special_issue = 0).
 */
final class DemoSeeder
{
    /**
     * volume_num values (scripts/importSamples/demo-papers.csv) designated as the current
     * issue and as a special issue — matched by number rather than by title text, so this
     * stays correct even if the CSV's wording changes.
     */
    private const CURRENT_ISSUE_VOLUME_NUM = '1';
    private const SPECIAL_ISSUE_VOLUME_NUM = '3';

    public function __construct(private readonly bool $dryRun)
    {
    }

    public function seed(int $rvid, int $uid, string $csvPath): Report
    {
        $report = new Report();

        $handle = fopen($csvPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Cannot read CSV file: $csvPath");
        }

        $importer = new PaperImporter($this->dryRun, new VolumeSectionResolver($this->dryRun), new PublicationDateResolver());

        $imported = 0;
        $updated = 0;
        $errors = 0;
        $lineNumber = 0;

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $lineNumber++;

            if (Row::isBlankCsvRecord($data)) {
                continue;
            }

            if ($lineNumber === 1 && strtolower($data[0]) === 'identifier') {
                continue;
            }

            $row = Row::fromCsvRow(DemoCsvRewriter::rewrite($data, $rvid, $uid));

            try {
                $result = $importer->import($row, $rvid);
                $result->wasUpdate ? $updated++ : $imported++;
                $report->add(
                    "Line $lineNumber",
                    ($result->wasUpdate ? 'updated' : 'imported') . " paper #{$result->docid} ({$row->identifier})"
                );
            } catch (Throwable $e) {
                $errors++;
                $report->warn("Line $lineNumber ({$row->identifier}): " . $e->getMessage());
            }
        }
        fclose($handle);

        $report->add('Papers', "$imported imported, $updated updated, $errors error(s)");

        if (!$this->dryRun) {
            $this->markVolumeByNum($rvid, self::CURRENT_ISSUE_VOLUME_NUM, Episciences_Volume::SETTING_CURRENT_ISSUE, $report);
            $this->markVolumeByNum($rvid, self::SPECIAL_ISSUE_VOLUME_NUM, Episciences_Volume::SETTING_SPECIAL_ISSUE, $report);
        }

        return $report;
    }

    private function markVolumeByNum(int $rvid, string $num, string $flagSetting, Report $report): void
    {
        $vid = $this->findVolumeIdByNum($rvid, $num);
        if ($vid === null) {
            return;
        }

        $volume = Episciences_VolumesManager::find($vid, $rvid);
        if ($volume === false || (string)$volume->getSetting($flagSetting) === '1') {
            return; // not found, or already set — idempotent re-run
        }

        // Episciences_Volume has no lighter-weight "update one setting" entry point: save()
        // re-derives every column (title, num, year, vol_type...) from the $data it is given,
        // so the volume's current values are read back and resubmitted alongside the one flag
        // being changed, rather than risking a partial save() call blanking them out.
        $data = [
            'status' => (string)($volume->getSetting(Episciences_Volume::SETTING_STATUS) ?: '1'),
            'current_issue' => (string)($volume->getSetting(Episciences_Volume::SETTING_CURRENT_ISSUE) ?: '0'),
            'special_issue' => (string)($volume->getSetting(Episciences_Volume::SETTING_SPECIAL_ISSUE) ?: '0'),
            'is_proceeding' => (string)($volume->getSetting(Episciences_Volume::VOLUME_IS_PROCEEDING) ?: '0'),
            'num' => $volume->getVol_num(),
            'year' => $volume->getVol_year(),
        ];
        $data[$flagSetting] = '1';

        foreach ($volume->getTitles() ?? [] as $lang => $title) {
            $data[Episciences_Volume::VOLUME_PREFIX_TITLE . $lang] = $title;
        }

        $volume->save($data, $vid);
        $report->add('Volume flag', "VID $vid marked as $flagSetting");
    }

    private function findVolumeIdByNum(int $rvid, string $num): ?int
    {
        foreach (Episciences_VolumesManager::getList(['where' => 'RVID = ' . $rvid]) as $volume) {
            if ((string)$volume->getVol_num() === $num) {
                return $volume->getVid();
            }
        }

        return null;
    }
}
