<?php

/**
 * Database layer for paper citations.
 * All SQL operations related to T_PAPER_CITATIONS are centralized here.
 */
class Episciences_Paper_Citations_Repository
{
    /**
     * Insert or update citation records (upsert).
     * Uses the MySQL 8.0+ alias syntax for ON DUPLICATE KEY UPDATE.
     *
     * @param array $citations array of Episciences_Paper_Citations objects or associative arrays
     * @return int number of affected rows
     */
    public static function insert(array $citations): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $values = [];
        $affectedRows = 0;

        foreach ($citations as $citation) {
            if (!($citation instanceof Episciences_Paper_Citations)) {
                $citation = new Episciences_Paper_Citations($citation);
            }

            $values[] = '(' . $db->quote($citation->getCitation()) . ','
                . $db->quote($citation->getDocId()) . ','
                . $db->quote($citation->getSourceId()) . ')';
        }

        if ($values === []) {
            return $affectedRows;
        }

        // MySQL 8.0.20+ alias syntax replaces the deprecated VALUES() function
        $sql = 'INSERT INTO ' . $db->quoteIdentifier(T_PAPER_CITATIONS)
            . ' (`citation`, `docid`, `source_id`) VALUES '
            . implode(', ', $values)
            . ' AS new_citation ON DUPLICATE KEY UPDATE citation = new_citation.citation';

        try {
            /** @var Zend_Db_Statement_Interface $result */
            $result = $db->query($sql);
            $affectedRows = $result->rowCount();
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);
        }

        return $affectedRows;
    }

    /**
     * Retrieve all citation records for a given document, joined with source metadata.
     *
     * @return array associative result rows, keyed by primary key
     */
    public static function findByDocId(int $docId): array
    {
        if ($docId <= 0) {
            return [];
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sql = $db->select()
            ->from(['citation' => T_PAPER_CITATIONS])
            ->joinLeft(
                ['source_paper' => T_PAPER_METADATA_SOURCES],
                'citation.source_id = source_paper.id',
                ['source_id_name' => 'source_paper.name']
            )
            ->where('docid = ?', $docId)
            ->order('source_id');

        return $db->fetchAssoc($sql);
    }
}
