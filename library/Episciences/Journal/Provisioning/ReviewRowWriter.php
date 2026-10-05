<?php

declare(strict_types=1);

namespace Episciences\Journal\Provisioning;

use Zend_Db_Table_Abstract;

/**
 * Inserts the REVIEW row itself — the piece Episciences_Review::save() never does. save() only
 * writes REVIEW_SETTING rows; nothing in the codebase creates a REVIEW row before this class.
 */
final class ReviewRowWriter
{
    public function insert(JournalSpec $spec): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $db->insert('REVIEW', [
            'CODE' => $spec->code,
            'NAME' => $spec->name,
            'subtitle' => $spec->subtitle,
            'STATUS' => $spec->status,
            'CREATION' => date('Y-m-d H:i:s'),
            'PIWIKID' => $spec->piwikId,
            'is_new_front_switched' => $spec->isNewFrontSwitched ? 'yes' : 'no',
        ]);

        return (int)$db->lastInsertId();
    }
}
