<?php
declare(strict_types=1);

namespace Episciences\Volume\Import;

use Episciences_Volume;
use RuntimeException;

/**
 * Creates a journal volume from one CSV row.
 *
 * Builds flat 'title_{lang}' / 'description_{lang}' keys: Episciences_Volume::save()
 * only picks up titles/descriptions from those (via
 * Episciences_VolumesManager::revertVolumeTitleToTextArray() /
 * revertVolumeDescriptionToTextareaArray()), never from a nested array.
 */
final class Importer
{
    public function __construct(private readonly bool $dryRun)
    {
    }

    /**
     * @throws RuntimeException on failure
     */
    public function import(Row $row): void
    {
        $data = [
            'status' => $row->status ?? 0,
            'current_issue' => $row->currentIssue ?? 0,
            'special_issue' => $row->specialIssue ?? 0,
        ];

        foreach ($row->titles() as $lang => $title) {
            $data[Episciences_Volume::VOLUME_PREFIX_TITLE . $lang] = $title;
        }
        foreach ($row->descriptions() as $lang => $description) {
            $data[Episciences_Volume::VOLUME_PREFIX_DESCRIPTION . $lang] = $description;
        }
        if ($row->bibReference !== null) {
            $data['bib_reference'] = $row->bibReference;
        }
        if ($row->num !== null) {
            $data['num'] = $row->num;
        }
        if ($row->year !== null) {
            $data['year'] = $row->year;
        }

        if ($this->dryRun) {
            return;
        }

        $volume = new Episciences_Volume();
        if (!$volume->save($data)) {
            throw new RuntimeException('Failed to save volume');
        }
    }
}
