<?php
declare(strict_types=1);

namespace Episciences\Volume;

use Episciences\AppRegistry;
use Episciences_Volume;
use Episciences_VolumesManager;
use Throwable;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Expr;
use Zend_Db_Table_Abstract;

/**
 * Deposit queue of the proceedings volume DOIs (doi_queue_volumes): assigned by the volume form,
 * requested then public through the doi:manage command (--volumes).
 */
final class DoiQueueManager
{
    /**
     * Queue entry of a volume, status "not-assigned" when there is none.
     */
    public static function findByVid(int $vid): DoiQueue
    {
        $db = self::db();
        $row = $db->fetchRow($db->select()->from(T_DOI_QUEUE_VOLUMES)->where('vid = ?', $vid));

        return is_array($row) ? DoiQueue::fromRow($row) : new DoiQueue($vid);
    }

    public static function isDoiLocked(int $vid): bool
    {
        return self::findByVid($vid)->isLocked();
    }

    /**
     * Volumes of a journal whose DOI is in the given status.
     *
     * @return list<array{volume: Episciences_Volume, doiq: DoiQueue}>
     */
    public static function findByStatus(int $rvid, string $status): array
    {
        $db = self::db();
        $select = $db->select()
            ->from(['DQ' => T_DOI_QUEUE_VOLUMES])
            ->join(['V' => T_VOLUMES], 'V.VID = DQ.vid', [])
            ->where('V.RVID = ?', $rvid)
            ->where('DQ.doi_status = ?', $status)
            ->order('DQ.vid ASC');

        $result = [];
        foreach ($db->fetchAll($select) as $row) {
            $volume = Episciences_VolumesManager::find((int)$row['vid'], $rvid);
            if ($volume instanceof Episciences_Volume) {
                $result[] = ['volume' => $volume, 'doiq' => DoiQueue::fromRow($row)];
            }
        }

        return $result;
    }

    /**
     * Queue a volume DOI, or change the status of its entry. A requested or registered DOI keeps
     * its status whatever is queued.
     *
     * @return int affected rows, 0 on error
     */
    public static function add(DoiQueue $doiQueue): int
    {
        $db = self::db();
        $table = $db->quoteIdentifier(T_DOI_QUEUE_VOLUMES);
        // The new_row alias makes an unqualified doi_status ambiguous
        $currentStatus = $table . '.`doi_status`';

        $sql = sprintf(
            'INSERT INTO %1$s (`vid`, `doi_status`, `date_init`, `date_updated`) VALUES (%2$d, %3$s, NOW(), NOW()) AS new_row'
            . ' ON DUPLICATE KEY UPDATE `doi_status` = IF(%4$s IN (%5$s), %4$s, new_row.`doi_status`), `date_updated` = NOW()',
            $table,
            $doiQueue->getVid(),
            $db->quote($doiQueue->getStatus()),
            $currentStatus,
            $db->quote(DoiQueue::LOCKED_STATUSES)
        );

        try {
            return $db->query($sql)->rowCount();
        } catch (Throwable $e) {
            self::logError('adding', $doiQueue->getVid(), $e);
            return 0;
        }
    }

    /**
     * @return int affected rows, 0 on error
     */
    public static function update(DoiQueue $doiQueue): int
    {
        try {
            return self::db()->update(
                T_DOI_QUEUE_VOLUMES,
                [
                    // Raw value: update() binds it
                    'doi_status' => $doiQueue->getStatus(),
                    'date_updated' => new Zend_Db_Expr('NOW()'),
                ],
                ['vid = ?' => $doiQueue->getVid()]
            );
        } catch (Throwable $e) {
            self::logError('updating', $doiQueue->getVid(), $e);
            return 0;
        }
    }

    /**
     * Forget a DOI that was assigned but not sent yet (DOI removed, volume no longer a proceedings).
     */
    public static function removeAssigned(int $vid): int
    {
        return self::db()->delete(T_DOI_QUEUE_VOLUMES, [
            'vid = ?' => $vid,
            'doi_status = ?' => DoiQueue::STATUS_ASSIGNED,
        ]);
    }

    private static function db(): Zend_Db_Adapter_Abstract
    {
        return Zend_Db_Table_Abstract::getDefaultAdapter();
    }

    private static function logError(string $action, int $vid, Throwable $e): void
    {
        AppRegistry::getMonoLogger()?->error(sprintf('Error %s the DOI queue entry of volume #%d: %s', $action, $vid, $e->getMessage()));
    }
}
