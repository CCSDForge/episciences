<?php

declare(strict_types=1);

use Episciences\Reviewer\StatsQuery;

class MyreviewstatsController extends Zend_Controller_Action
{
    public function indexAction(): void
    {
        $rvid = (int)RVID;
        $currentUserId = (int)Episciences_Auth::getUid();

        $queryService = new StatsQuery();
        // Personal stats only: isPersonal = true (no GDPR rolling cap), isRestricted = false.
        $results = $queryService->getReviewersGlobalStats(
            $rvid,
            false,
            $currentUserId,
            null,
            null,
            false,
            false,
            false,
            true,
            1,
            0
        );

        $stats = $results['data'][0] ?? null;
        $totalReminders = 0;

        if ($stats !== null && !empty($stats['EMAIL'])) {
            $itemIds = [];
            if (!empty($stats['item_ids'])) {
                foreach (explode(',', (string)$stats['item_ids']) as $itemId) {
                    $itemIds[] = (int)$itemId;
                }
            }

            $reminders = $queryService->getReminderCounts($rvid, $itemIds, null, [(string)$stats['EMAIL']]);
            $totalReminders = $reminders[strtolower((string)$stats['EMAIL'])] ?? 0;
        }

        $this->view->stats = $stats;
        $this->view->totalReminders = $totalReminders;
    }
}
