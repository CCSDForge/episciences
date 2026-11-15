<?php
declare(strict_types=1);

namespace Episciences\Volume;

use Episciences\AppRegistry;
use Throwable;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Journal-wide MySQL named lock on the proceedings DOIs. The volume form holds it from the DOI
 * status read to the queue update, the deposit while it claims a DOI: a DOI cannot change
 * between the claim and the deposit, nor be given to two volumes by concurrent saves.
 *
 * MySQL releases the lock when the connection closes, so an aborted request never keeps it.
 */
final class ProceedingsDoiLock
{
    private const TIMEOUT_SECONDS = 10;

    private bool $released = false;

    private function __construct(
        private readonly Zend_Db_Adapter_Abstract $db,
        private readonly string $name
    ) {
    }

    /**
     * @return self|null null when the lock is still held by another process after the timeout
     */
    public static function acquire(int $rvid, ?Zend_Db_Adapter_Abstract $db = null): ?self
    {
        $db ??= Zend_Db_Table_Abstract::getDefaultAdapter();
        $name = sprintf('episciences_volume_doi_%d', $rvid);

        try {
            $acquired = (string)$db->fetchOne('SELECT GET_LOCK(?, ?)', [$name, self::TIMEOUT_SECONDS]) === '1';
        } catch (Throwable $e) {
            AppRegistry::getMonoLogger()?->error(sprintf('Proceedings DOI lock of journal #%d not acquired: %s', $rvid, $e->getMessage()));
            return null;
        }

        return $acquired ? new self($db, $name) : null;
    }

    public function release(): void
    {
        if ($this->released) {
            return;
        }

        $this->released = true;

        try {
            $this->db->fetchOne('SELECT RELEASE_LOCK(?)', [$this->name]);
        } catch (Throwable $e) {
            AppRegistry::getMonoLogger()?->warning(sprintf('Proceedings DOI lock %s not released: %s', $this->name, $e->getMessage()));
        }
    }
}
