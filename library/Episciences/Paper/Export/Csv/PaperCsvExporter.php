<?php
declare(strict_types=1);

namespace Episciences\Paper\Export\Csv;

use Episciences\Paper\Import\Row;
use Episciences_PapersManager;
use Episciences_SectionsManager;
use Episciences_VolumesManager;
use Zend_Db_Table_Abstract;

/**
 * Exports the papers matching a Filters criteria set to a semicolon-separated CSV, in the exact
 * same 19-column format import:papers reads — writing one row at a time (DOCIDs are fetched
 * first, then each paper is hydrated and streamed) so memory stays bounded on a large export.
 */
final class PaperCsvExporter
{
    /**
     * The same header import:papers expects (see Episciences\Paper\Import\Row).
     */
    private const HEADER = [
        'identifier', 'repoid', 'version', 'status',
        'volume_id', 'volume_title_fr', 'volume_title_en', 'volume_num', 'volume_year',
        'section_id', 'section_title_fr', 'section_title_en',
        'uid', 'publication_date', 'editors', 'doi', 'docid', 'rvid', 'submission_date',
    ];

    /**
     * Papers are hydrated in batches of this size via Episciences_PapersManager::getByDocIds()
     * (one query per batch, no N+1) rather than all at once, to keep memory bounded on a large
     * export — mirrors papers:update-document's --buffer.
     */
    private const BATCH_SIZE = 500;

    public function __construct(private readonly Filters $filters)
    {
    }

    /**
     * @param resource $handle writable stream, e.g. fopen($csvFile, 'wb')
     * @return int number of paper rows written (header not included)
     */
    public function export($handle): int
    {
        fputcsv($handle, self::HEADER, ';');

        $volumes = $this->indexById(Episciences_VolumesManager::getList(['where' => 'RVID = ' . $this->filters->rvid]), 'getVid');
        $sections = $this->indexById(Episciences_SectionsManager::getList(['where' => 'RVID = ' . $this->filters->rvid]), 'getSid');

        $count = 0;
        foreach (array_chunk($this->matchingDocids(), self::BATCH_SIZE) as $docidBatch) {
            $papers = Episciences_PapersManager::getByDocIds($docidBatch);

            foreach ($docidBatch as $docid) {
                $paper = $papers[$docid] ?? null;
                if (!$paper) {
                    continue;
                }

                $row = RowBuilder::build($paper, $volumes[$paper->getVid()] ?? null, $sections[$paper->getSid()] ?? null);
                fputcsv($handle, $row->toCsvArray(), ';');
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<int, object> $objects
     * @return array<int, object>
     */
    private function indexById(array $objects, string $getIdMethod): array
    {
        $indexed = [];
        foreach ($objects as $object) {
            $indexed[$object->$getIdMethod()] = $object;
        }

        return $indexed;
    }

    /**
     * @return int[]
     */
    private function matchingDocids(): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $is = ['rvid' => $this->filters->rvid];
        if ($this->filters->volumeId !== null) {
            $is['vid'] = $this->filters->volumeId;
        }
        if ($this->filters->sectionId !== null) {
            $is['sid'] = $this->filters->sectionId;
        }
        if ($this->filters->docids !== []) {
            $is['docid'] = $this->filters->docids;
        }
        if ($this->filters->statuses !== []) {
            $is['status'] = $this->filters->statuses;
        }
        if ($this->filters->repoid !== null) {
            $is['repoid'] = $this->filters->repoid;
        }
        if ($this->filters->uid !== null) {
            $is['uid'] = $this->filters->uid;
        }

        $select = Episciences_PapersManager::getListQuery(
            ['is' => $is, 'order' => 'DOCID ASC'],
            false,
            false,
            ['DOCID']
        );

        if ($this->filters->year !== null) {
            $select->where('YEAR(PUBLICATION_DATE) = ?', $this->filters->year);
        }

        if ($this->filters->identifier !== null) {
            $select->where('IDENTIFIER LIKE ?', $this->filters->identifier);
            if ($this->filters->version !== null) {
                $select->where('VERSION = ?', (float)$this->filters->version);
            }
        }

        // Trusted input only — passed as-is to the query, same escape hatch as
        // papers:update-document's --sqlwhere.
        if ($this->filters->sqlWhere !== null) {
            $select->where($this->filters->sqlWhere);
        }

        return array_map('intval', $db->fetchCol($select));
    }
}
