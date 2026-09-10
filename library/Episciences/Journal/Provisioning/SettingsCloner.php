<?php

declare(strict_types=1);

namespace Episciences\Journal\Provisioning;

use Episciences_Review;
use Episciences_Review_DoiSettings;
use Zend_Db_Table_Abstract;

/**
 * Clones REVIEW_SETTING rows from a template journal to a new one via a direct SQL
 * INSERT ... SELECT, rather than a round-trip through Episciences_Review::setOptions()+save().
 *
 * Episciences_Review::$_settingsKeys (built in its constructor) is a partial whitelist: it
 * omits the 4 DOI settings (they go through Episciences_Review_DoiSettings instead) and
 * disableAutomaticTransfer (present in $_jsonSettings but not in $_settingsKeys). Going through
 * the object would silently drop those keys on a clone. DOI settings are cloned by default per
 * product decision — see resetDoiSettings() for the --reset-doi mitigation, and
 * JournalCreator for the warning shown when the cloned mode is "automatic".
 */
final class SettingsCloner
{
    /**
     * Editorial identity and outgoing-notification settings: never cloned from the template —
     * a fresh journal must not inherit another journal's ISSN, contacts or domains.
     *
     * @var string[]
     */
    public const EXCLUDED_SETTINGS = [
        // Editorial identity
        Episciences_Review::SETTING_ISSN,
        Episciences_Review::SETTING_ISSN_PRINT,
        Episciences_Review::SETTING_DOMAINS,
        Episciences_Review::SETTING_JOURNAL_DOI,
        Episciences_Review::SETTING_DESCRIPTION,
        Episciences_Review::SETTING_JOURNAL_DESCRIPTION,
        Episciences_Review::SETTING_JOURNAL_KEYWORDS,
        Episciences_Review::SETTING_JOURNAL_CREATION_YEAR,
        Episciences_Review::SETTING_JOURNAL_PUBLISHER,
        Episciences_Review::SETTING_JOURNAL_PUBLISHER_LOC,
        Episciences_Review::SETTING_START_STATS_AFTER_DATE,
        Episciences_Review::SETTING_SPECIAL_ISSUE_ACCESS_CODE,
        // Outgoing notifications
        Episciences_Review::SETTING_CONTACT_JOURNAL,
        Episciences_Review::SETTING_CONTACT_JOURNAL_EMAIL,
        Episciences_Review::SETTING_JOURNAL_NOTICE,
        Episciences_Review::SETTING_CONTACT_TECH_SUPPORT_EMAIL,
        Episciences_Review::SETTING_CONTACT_ERROR_MAIL,
        Episciences_Review::SETTING_SYSTEM_NOTIFICATIONS,
        Episciences_Review::SETTING_SYSTEM_CAN_NOTIFY_CHIEF_EDITORS,
        Episciences_Review::SETTING_SYSTEM_CAN_NOTIFY_ADMINISTRATORS,
        Episciences_Review::SETTING_SYSTEM_CAN_NOTIFY_SECRETARIES,
    ];

    /**
     * @return int number of REVIEW_SETTING rows written. With $ignoreExisting (--complete),
     *             an already-present key is skipped rather than overwritten (INSERT IGNORE on
     *             the (RVID, SETTING) primary key), so this is 0 once the target is complete.
     */
    public function clone(int $fromRvid, int $toRvid, bool $ignoreExisting): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $verb = $ignoreExisting ? 'INSERT IGNORE' : 'INSERT';
        $placeholders = implode(',', array_fill(0, count(self::EXCLUDED_SETTINGS), '?'));

        $sql = "$verb INTO REVIEW_SETTING (RVID, SETTING, VALUE)
                SELECT ?, SETTING, VALUE
                FROM REVIEW_SETTING
                WHERE RVID = ? AND SETTING NOT IN ($placeholders)";

        $statement = $db->query($sql, array_merge([$toRvid, $fromRvid], self::EXCLUDED_SETTINGS));

        return $statement->rowCount();
    }

    /**
     * Number of settings clone() would write for this template — used by JournalCreator's
     * --dry-run report, which must not perform the actual INSERT.
     */
    public function countCloneable(int $fromRvid): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $placeholders = implode(',', array_fill(0, count(self::EXCLUDED_SETTINGS), '?'));

        $sql = "SELECT COUNT(*) FROM REVIEW_SETTING WHERE RVID = ? AND SETTING NOT IN ($placeholders)";

        return (int)$db->fetchOne($sql, array_merge([$fromRvid], self::EXCLUDED_SETTINGS));
    }

    public function readSetting(int $rvid, string $setting): ?string
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from('REVIEW_SETTING', 'VALUE')
            ->where('RVID = ?', $rvid)
            ->where('SETTING = ?', $setting);

        $value = $db->fetchOne($select);

        return $value === false ? null : (string)$value;
    }

    /**
     * Forces the cloned DOI settings to a safe manual mode with no prefix, so a `doi:manage`
     * run on the new journal cannot register real DOIs under the template's prefix.
     */
    public function resetDoiSettings(int $rvid): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $db->query(
            'INSERT INTO REVIEW_SETTING (RVID, SETTING, VALUE) VALUES (?, ?, ?), (?, ?, ?)
             AS new_row ON DUPLICATE KEY UPDATE VALUE = new_row.VALUE',
            [
                $rvid, Episciences_Review_DoiSettings::SETTING_DOI_ASSIGN_MODE, Episciences_Review_DoiSettings::DOI_ASSIGN_MODE_MANUAL,
                $rvid, Episciences_Review_DoiSettings::SETTING_DOI_PREFIX, '',
            ]
        );
    }
}
