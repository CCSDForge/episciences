<?php

declare(strict_types=1);

namespace Episciences\Submit\Checklist;

use Closure;
use Episciences\AppRegistry;
use RuntimeException;
use Throwable;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Select;
use Zend_Db_Table_Abstract;

/**
 * Reads and writes the submission checklist of a journal: one row of REVIEW_SETTING
 * (SETTING = 'submissionChecklist', VALUE = JSON of JournalChecklist).
 *
 * The only class of the feature that talks to the database. It also assigns the keys
 * (c_xxxxxx) of the checkboxes saved for the first time.
 *
 */
final class ChecklistRepository
{
    public const SETTING = 'submissionChecklist';

    /** Stops the key loop if the generator keeps returning keys already used */
    private const MAX_KEY_ATTEMPTS = 100;

    private ?Zend_Db_Adapter_Abstract $db;

    /** @var Closure(): string */
    private Closure $keyGenerator;

    /**
     * @param ?Zend_Db_Adapter_Abstract $db defaults to the application database
     * @param ?Closure(): string $keyGenerator defaults to 'c_' + 6 random hexadecimal characters
     */
    public function __construct(?Zend_Db_Adapter_Abstract $db = null, ?Closure $keyGenerator = null)
    {
        $this->db = $db;
        $this->keyGenerator = $keyGenerator ?? static fn (): string => 'c_' . bin2hex(random_bytes(3));
    }

    /**
     * @return JournalChecklist|null null when nothing is stored or the stored JSON is invalid
     */
    public function find(int $rvid): ?JournalChecklist
    {
        $value = $this->getDb()->fetchOne($this->selectSetting($rvid));
        if (!is_string($value)) {
            return null;
        }

        $checklist = JournalChecklist::fromJson($value);
        if ($checklist === null) {
            AppRegistry::getMonoLogger()?->warning('Invalid submission checklist JSON, default checklist used', [
                'rvid' => $rvid,
            ]);
        }

        return $checklist;
    }

    /**
     * Assigns a key to every checkbox without one, then updates the row, or inserts it
     * the first time.
     *
     * @return JournalChecklist the saved checklist, every checkbox with its key
     * @throws Throwable database error (the transaction is rolled back)
     */
    public function save(int $rvid, JournalChecklist $checklist): JournalChecklist
    {
        $saved = $this->withKeys($checklist);
        $json = $saved->toJson();
        $db = $this->getDb();

        $db->beginTransaction();
        try {
            // Not INSERT ... ON DUPLICATE KEY UPDATE: MySQL only, refused by the SQLite test database.
            // Not the row count of update() either: MySQL returns 0 when the value is unchanged.
            if ($db->fetchOne($this->selectSetting($rvid)) !== false) {
                $db->update(T_REVIEW_SETTINGS, ['VALUE' => $json], [
                    'RVID = ?' => $rvid,
                    'SETTING = ?' => self::SETTING,
                ]);
            } else {
                $db->insert(T_REVIEW_SETTINGS, ['RVID' => $rvid, 'SETTING' => self::SETTING, 'VALUE' => $json]);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return $saved;
    }

    /**
     * Removes the checklist of the journal ("Back to the default list"). No row: nothing happens.
     */
    public function delete(int $rvid): void
    {
        $this->getDb()->delete(T_REVIEW_SETTINGS, [
            'RVID = ?' => $rvid,
            'SETTING = ?' => self::SETTING,
        ]);
    }

    /**
     * Same checklist, a new unique key given to every checkbox without one (existing keys kept).
     */
    private function withKeys(JournalChecklist $checklist): JournalChecklist
    {
        $usedKeys = $checklist->keys();
        $items = [];

        foreach ($checklist->items as $item) {
            if ($item->key === null) {
                $key = $this->newKey($usedKeys);
                $usedKeys[] = $key;
                $item = $item->withKey($key);
            }
            $items[] = $item;
        }

        return new JournalChecklist($items);
    }

    /**
     * @param list<string> $usedKeys
     */
    private function newKey(array $usedKeys): string
    {
        for ($attempt = 0; $attempt < self::MAX_KEY_ATTEMPTS; $attempt++) {
            $key = ($this->keyGenerator)();
            if (!in_array($key, $usedKeys, true)) {
                return $key;
            }
        }

        throw new RuntimeException('Unable to generate a unique submission checklist key');
    }

    private function selectSetting(int $rvid): Zend_Db_Select
    {
        return $this->getDb()->select()
            ->from(T_REVIEW_SETTINGS, ['VALUE'])
            ->where('RVID = ?', $rvid)
            ->where('SETTING = ?', self::SETTING);
    }

    private function getDb(): Zend_Db_Adapter_Abstract
    {
        return $this->db ??= Zend_Db_Table_Abstract::getDefaultAdapter();
    }
}
