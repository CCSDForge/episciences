<?php

declare(strict_types=1);

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shared --docid / --sqlwhere / --file resolution, used by solr:index to
 * select one or more papers to (re)index.
 */
trait ResolvesDocIdOptions
{
    /** @return list<int>|null null means resolution failed and an error was already printed */
    private function resolveDocIds(InputInterface $input, SymfonyStyle $io): ?array
    {
        $docId = $input->getOption('docid');
        $sqlWhere = $input->getOption('sqlwhere');
        $file = $input->getOption('file');

        $provided = array_filter([$docId !== null, $sqlWhere !== null, $file !== null]);

        if (count($provided) > 1) {
            $io->error('Options --docid, --sqlwhere and --file are mutually exclusive.');

            return null;
        }

        if ($provided === []) {
            $io->error('One of --docid, --sqlwhere or --file is required.');

            return null;
        }

        if ($docId !== null) {
            if (!ctype_digit((string)$docId) || (int)$docId <= 0) {
                $io->error(sprintf('Invalid --docid: %s', $docId));

                return null;
            }

            return [(int)$docId];
        }

        if ($file !== null) {
            if (!is_readable((string)$file)) {
                $io->error(sprintf('File not readable: %s', $file));

                return null;
            }

            $lines = file((string)$file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $docIds = array_values(array_filter(
                array_map('intval', $lines ?: []),
                static fn (int $id): bool => $id > 0
            ));

            if ($docIds === []) {
                $io->error(sprintf('No valid DOCID found in file: %s', $file));

                return null;
            }

            return $docIds;
        }

        // Only published papers belong in the Solr index (matches legacy
        // Ccsd_Search_Solr_Indexer_Episciences::selectIds(), which always
        // ANDed STATUS = STATUS_PUBLISHED under any caller-supplied clause).
        // IndexPaperMessageHandler enforces this too, but filtering here
        // avoids enqueuing (and burning worker time on) docids that will
        // just be skipped.
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from('PAPERS', 'DOCID')
            ->where('STATUS = ?', Episciences_Paper::STATUS_PUBLISHED)
            ->where((string)$sqlWhere);

        return array_map('intval', $db->fetchCol($select));
    }
}
