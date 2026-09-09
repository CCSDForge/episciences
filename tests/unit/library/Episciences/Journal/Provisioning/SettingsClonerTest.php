<?php

namespace unit\library\Episciences\Journal\Provisioning;

use Episciences\Journal\Provisioning\SettingsCloner;
use Episciences_Review;
use Episciences_Review_DoiSettings;
use PHPUnit\Framework\TestCase;

/**
 * clone()/countCloneable()/resetDoiSettings() need a database — only the EXCLUDED_SETTINGS
 * whitelist itself is unit-tested here (its exact SQL shape is verified end-to-end, per
 * docs/journal-provisioning.md).
 */
class SettingsClonerTest extends TestCase
{
    public function testExcludedSettingsHasNoDuplicates(): void
    {
        $this->assertCount(
            count(array_unique(SettingsCloner::EXCLUDED_SETTINGS)),
            SettingsCloner::EXCLUDED_SETTINGS
        );
    }

    public function testExcludedSettingsHasNoEmptyValues(): void
    {
        foreach (SettingsCloner::EXCLUDED_SETTINGS as $setting) {
            $this->assertNotSame('', $setting);
        }
    }

    /**
     * Every excluded key must still resolve to a real Episciences_Review::SETTING_* constant —
     * guards against a stale key surviving a rename in Review.php.
     *
     * @return array<string, array{string}>
     */
    public static function identityAndNotificationSettingProvider(): array
    {
        return [
            'ISSN' => [Episciences_Review::SETTING_ISSN],
            'ISSN_PRINT' => [Episciences_Review::SETTING_ISSN_PRINT],
            'domains' => [Episciences_Review::SETTING_DOMAINS],
            'journalAssignedDoi' => [Episciences_Review::SETTING_JOURNAL_DOI],
            'description' => [Episciences_Review::SETTING_DESCRIPTION],
            'journalDescription' => [Episciences_Review::SETTING_JOURNAL_DESCRIPTION],
            'journalKeywords' => [Episciences_Review::SETTING_JOURNAL_KEYWORDS],
            'journalCreationYear' => [Episciences_Review::SETTING_JOURNAL_CREATION_YEAR],
            'journalPublisher' => [Episciences_Review::SETTING_JOURNAL_PUBLISHER],
            'journalPublisherLoc' => [Episciences_Review::SETTING_JOURNAL_PUBLISHER_LOC],
            'startStatsAfterDate' => [Episciences_Review::SETTING_START_STATS_AFTER_DATE],
            'specialIssueAccessCode' => [Episciences_Review::SETTING_SPECIAL_ISSUE_ACCESS_CODE],
            'contactJournal' => [Episciences_Review::SETTING_CONTACT_JOURNAL],
            'contactJournalEmail' => [Episciences_Review::SETTING_CONTACT_JOURNAL_EMAIL],
            'contactJournalNotice' => [Episciences_Review::SETTING_JOURNAL_NOTICE],
            'contactTechSupportEmail' => [Episciences_Review::SETTING_CONTACT_TECH_SUPPORT_EMAIL],
            'contactErrorMail' => [Episciences_Review::SETTING_CONTACT_ERROR_MAIL],
            'systemNotifications' => [Episciences_Review::SETTING_SYSTEM_NOTIFICATIONS],
            'systemCanNotifyChiefEditors' => [Episciences_Review::SETTING_SYSTEM_CAN_NOTIFY_CHIEF_EDITORS],
            'systemCanNotifyAdministrator' => [Episciences_Review::SETTING_SYSTEM_CAN_NOTIFY_ADMINISTRATORS],
            'systemCanNotifySecretaries' => [Episciences_Review::SETTING_SYSTEM_CAN_NOTIFY_SECRETARIES],
        ];
    }

    /** @dataProvider identityAndNotificationSettingProvider */
    public function testExpectedSettingIsExcluded(string $setting): void
    {
        $this->assertContains($setting, SettingsCloner::EXCLUDED_SETTINGS);
    }

    public function testExcludedSettingsCountMatchesTheKnownIdentityAndNotificationList(): void
    {
        $this->assertCount(count(self::identityAndNotificationSettingProvider()), SettingsCloner::EXCLUDED_SETTINGS);
    }

    /**
     * DOI settings are cloned by default (a product decision, mitigated by --reset-doi), so
     * they must never be added to EXCLUDED_SETTINGS.
     */
    public function testDoiSettingsAreNotExcluded(): void
    {
        $doiSettings = [
            Episciences_Review_DoiSettings::SETTING_DOI_PREFIX,
            Episciences_Review_DoiSettings::SETTING_DOI_FORMAT,
            Episciences_Review_DoiSettings::SETTING_DOI_REGISTRATION_AGENCY,
            Episciences_Review_DoiSettings::SETTING_DOI_ASSIGN_MODE,
        ];

        foreach ($doiSettings as $setting) {
            $this->assertNotContains($setting, SettingsCloner::EXCLUDED_SETTINGS);
        }
    }
}
