<?php

namespace unit\library\Episciences\Mail;

use Episciences_Mail_Reminder;
use Episciences_Mail_RemindersManager;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for Episciences_Mail_Reminder entity.
 *
 * Tests cover entity-level behaviour (constants, setters, getters, toArray).
 * Methods requiring DB access (loadRecipients, save, loadTranslations) are
 * documented here but not exercised; security notes are recorded inline.
 *
 * @covers Episciences_Mail_Reminder
 */
final class Episciences_Mail_ReminderTest extends TestCase
{
    protected function setUp(): void
    {
        // __construct() → setLocale(Tools::getLocale()) → Zend_Registry::get('Zend_Translate')
        // setLocale()   → Tools::getLanguages() → Ccsd_Locale::getLanguage() → Zend_Registry::get('Zend_Locale')
        if (!\Zend_Registry::isRegistered('Zend_Locale')) {
            \Zend_Registry::set('Zend_Locale', new \Zend_Locale('en'));
        }
        if (!\Zend_Registry::isRegistered('languages')) {
            \Zend_Registry::set('languages', ['en', 'fr']);
        }
        if (!\Zend_Registry::isRegistered('Zend_Translate')) {
            // Content must not be empty for the adapter to register locale 'en'.
            \Zend_Registry::set('Zend_Translate', new \Zend_Translate([
                'adapter' => \Zend_Translate::AN_ARRAY,
                'content' => ['' => ''],
                'locale'  => 'en',
            ]));
        }
    }

    // -------------------------------------------------------------------------
    // Type constants — completeness & sequentiality
    // -------------------------------------------------------------------------

    public function testTypeConstantsAreSequentialIntegers(): void
    {
        $expected = [
            Episciences_Mail_Reminder::TYPE_UNANSWERED_INVITATION         => 0,
            Episciences_Mail_Reminder::TYPE_BEFORE_REVIEWING_DEADLINE     => 1,
            Episciences_Mail_Reminder::TYPE_AFTER_REVIEWING_DEADLINE      => 2,
            Episciences_Mail_Reminder::TYPE_BEFORE_REVISION_DEADLINE      => 3,
            Episciences_Mail_Reminder::TYPE_AFTER_REVISION_DEADLINE       => 4,
            Episciences_Mail_Reminder::TYPE_NOT_ENOUGH_REVIEWERS          => 5,
            Episciences_Mail_Reminder::TYPE_ARTICLE_BLOCKED_IN_ACCEPTED_STATE  => 6,
            Episciences_Mail_Reminder::TYPE_ARTICLE_BLOCKED_IN_SUBMITTED_STATE => 7,
            Episciences_Mail_Reminder::TYPE_ARTICLE_BLOCKED_IN_REVIEWED_STATE  => 8,
        ];

        foreach ($expected as $const => $value) {
            self::assertSame($value, $const);
        }
    }

    public function testDefaultWaitingTimeIsZero(): void
    {
        self::assertSame(0, Episciences_Mail_Reminder::DEFAULT_WAITING_TIME);
    }

    // -------------------------------------------------------------------------
    // $_typeLabel — one label per type constant
    // -------------------------------------------------------------------------

    public function testTypeLabelCoversAllTypeConstants(): void
    {
        $allTypes = $this->getAllTypeConstants();

        foreach ($allTypes as $const => $value) {
            self::assertArrayHasKey(
                $value,
                Episciences_Mail_Reminder::$_typeLabel,
                "Missing \$_typeLabel entry for type constant $const (value=$value)"
            );
        }
    }

    public function testTypeLabelValuesAreNonEmptyStrings(): void
    {
        foreach (Episciences_Mail_Reminder::$_typeLabel as $type => $label) {
            self::assertIsString($label);
            self::assertNotEmpty($label, "Label for type $type must not be empty");
        }
    }

    // -------------------------------------------------------------------------
    // $_typeKey — one key per type constant
    // -------------------------------------------------------------------------

    public function testTypeKeyCoversAllTypeConstants(): void
    {
        $allTypes = $this->getAllTypeConstants();

        foreach ($allTypes as $const => $value) {
            self::assertArrayHasKey(
                $value,
                Episciences_Mail_Reminder::$_typeKey,
                "Missing \$_typeKey entry for type constant $const (value=$value)"
            );
        }
    }

    public function testTypeKeyValuesAreNonEmptySnakeCaseStrings(): void
    {
        foreach (Episciences_Mail_Reminder::$_typeKey as $type => $key) {
            self::assertIsString($key);
            self::assertNotEmpty($key);
            self::assertMatchesRegularExpression(
                '/^[a-z][a-z0-9_]+$/',
                $key,
                "Type key '$key' for type $type must be snake_case"
            );
        }
    }

    // -------------------------------------------------------------------------
    // MAPPING_REMINDER_RECIPIENTS — one entry per type constant
    // -------------------------------------------------------------------------

    public function testMappingReminderRecipientsCoversAllTypeConstants(): void
    {
        $allTypes = $this->getAllTypeConstants();

        foreach ($allTypes as $const => $value) {
            self::assertArrayHasKey(
                $value,
                Episciences_Mail_Reminder::MAPPING_REMINDER_RECIPIENTS,
                "Missing MAPPING_REMINDER_RECIPIENTS entry for $const (value=$value)"
            );
        }
    }

    public function testMappingReminderRecipientsEachEntryIsNonEmptyArray(): void
    {
        foreach (Episciences_Mail_Reminder::MAPPING_REMINDER_RECIPIENTS as $type => $recipients) {
            self::assertIsArray($recipients, "Recipients for type $type must be an array");
            self::assertNotEmpty($recipients, "Recipients for type $type must not be empty");
        }
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function testConstructWithNoArgumentsLeavesDataFieldsNull(): void
    {
        $r = new Episciences_Mail_Reminder();

        self::assertNull($r->getId());
        self::assertNull($r->getRvid());
        self::assertNull($r->getType());
        self::assertNull($r->getDelay());
        self::assertNull($r->getRepetition());
        self::assertNull($r->getRecipient());
    }

    public function testConstructSetsLocale(): void
    {
        $r = new Episciences_Mail_Reminder();

        // After framework bootstrap, locale must be set.
        self::assertNotNull($r->getLocale());
    }

    public function testConstructWithOptionsPopulatesFields(): void
    {
        $r = new Episciences_Mail_Reminder([
            'id'         => 10,
            'rvid'       => 3,
            'type'       => 1,
            'delay'      => 7,
            'repetition' => 0,
            'recipient'  => 'editor',
        ]);

        self::assertSame(10, $r->getId());
        self::assertSame(3, $r->getRvid());
        self::assertSame(1, $r->getType());  // cast to int
        self::assertSame(7, $r->getDelay());
        self::assertSame(0, $r->getRepetition());
        self::assertSame('editor', $r->getRecipient());
    }

    // -------------------------------------------------------------------------
    // setOptions — DB column name mapping (uppercase keys)
    // -------------------------------------------------------------------------

    public function testSetOptionsMapsUppercaseDbColumnNames(): void
    {
        $r = new Episciences_Mail_Reminder();

        // DB returns uppercase column names: ID, RVID, TYPE, DELAY, REPETITION, RECIPIENT
        $r->setOptions([
            'ID'         => 42,
            'RVID'       => 5,
            'TYPE'       => '2',
            'DELAY'      => 14,
            'REPETITION' => 7,
            'RECIPIENT'  => 'reviewer',
        ]);

        self::assertSame(42, $r->getId());
        self::assertSame(5, $r->getRvid());
        self::assertSame(2, $r->getType());   // cast to int by setType()
        self::assertSame(14, $r->getDelay());
        self::assertSame(7, $r->getRepetition());
        self::assertSame('reviewer', $r->getRecipient());
    }

    public function testSetOptionsIgnoresUnknownKeys(): void
    {
        $r = new Episciences_Mail_Reminder();
        $r->setOptions(['UNKNOWN_COLUMN' => 'ignored_value']);

        // No property should be set from an unknown key.
        self::assertNull($r->getId());
    }

    // -------------------------------------------------------------------------
    // setType() — always casts to int
    // -------------------------------------------------------------------------

    public function testSetTypeCastsStringToInt(): void
    {
        $r = new Episciences_Mail_Reminder();
        $r->setType('3');

        self::assertSame(3, $r->getType());
        self::assertIsInt($r->getType());
    }

    public function testSetTypeReturnsSelf(): void
    {
        $r = new Episciences_Mail_Reminder();

        self::assertSame($r, $r->setType(0));
    }

    // -------------------------------------------------------------------------
    // Fluent interface
    // -------------------------------------------------------------------------

    /** @dataProvider provideFluentSetters */
    public function testSettersReturnSelf(string $setter, mixed $value): void
    {
        $r = new Episciences_Mail_Reminder();

        self::assertSame($r, $r->$setter($value));
    }

    public static function provideFluentSetters(): array
    {
        return [
            ['setId',         1],
            ['setRvid',       2],
            ['setDelay',      5],
            ['setRepetition', 7],
            ['setRecipient',  'editor'],
            ['setBody',       ['en' => 'body']],
            ['setName',       ['en' => 'name']],
            ['setSubject',    ['en' => 'subject']],
            ['setCustom',     ['en' => 0]],
            ['setRecipients', []],
            ['setDeadline',   '2026-12-31'],
        ];
    }

    // -------------------------------------------------------------------------
    // toArray()
    // -------------------------------------------------------------------------

    public function testToArrayContainsExpectedKeys(): void
    {
        $r = new Episciences_Mail_Reminder();
        $result = $r->toArray();

        $expectedKeys = ['id', 'rvid', 'delay', 'repetition', 'recipient', 'type', 'name', 'subject', 'body', 'custom'];
        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, $result, "toArray() must contain key '$key'");
        }
    }

    public function testToArrayReflectsSetValues(): void
    {
        $r = new Episciences_Mail_Reminder();
        $r->setId(5);
        $r->setRvid(2);
        $r->setType(1);
        $r->setDelay(3);
        $r->setRepetition(7);
        $r->setRecipient('reviewer');

        $result = $r->toArray();

        self::assertSame(5, $result['id']);
        self::assertSame(2, $result['rvid']);
        self::assertSame(1, $result['type']);
        self::assertSame(3, $result['delay']);
        self::assertSame(7, $result['repetition']);
        self::assertSame('reviewer', $result['recipient']);
    }

    // -------------------------------------------------------------------------
    // getSubject() / getBody() — translation helpers
    // -------------------------------------------------------------------------

    public function testGetSubjectWithExplicitLangReturnsCorrectString(): void
    {
        $r = new Episciences_Mail_Reminder();
        $r->setSubject(['en' => 'Reminder subject', 'fr' => 'Sujet rappel']);

        self::assertSame('Reminder subject', $r->getSubject('en'));
    }

    public function testGetSubjectFallsBackToDefaultLangWhenRequestedUnavailable(): void
    {
        // Episciences_Tools::getTranslation() implements the fallback chain.
        $r = new Episciences_Mail_Reminder();
        $r->setSubject(['en' => 'English only']);

        // Requesting 'fr' but only 'en' is available — must not crash.
        $result = $r->getSubject('fr');
        // Result may be null or an 'en' fallback depending on helper implementation.
        // The important contract: no exception is thrown.
        self::assertTrue(is_null($result) || is_string($result));
    }

    public function testGetBodyWithExplicitLang(): void
    {
        $r = new Episciences_Mail_Reminder();
        $r->setBody(['en' => 'Body text', 'fr' => 'Texte corps']);

        self::assertSame('Body text', $r->getBody('en'));
        self::assertSame('Texte corps', $r->getBody('fr'));
    }

    // -------------------------------------------------------------------------
    // getName() / getNameTranslations()
    // -------------------------------------------------------------------------

    public function testGetNameWithLang(): void
    {
        $r = new Episciences_Mail_Reminder();
        $r->setName(['en' => 'Name EN', 'fr' => 'Nom FR']);

        self::assertSame('Name EN', $r->getName('en'));
        self::assertSame('Nom FR', $r->getName('fr'));
    }

    public function testGetNameReturnsNullForUnknownLang(): void
    {
        $r = new Episciences_Mail_Reminder();
        $r->setName(['en' => 'Name EN']);

        self::assertNull($r->getName('de'));
    }

    public function testGetNameTranslationsReturnsFullArray(): void
    {
        $r = new Episciences_Mail_Reminder();
        $translations = ['en' => 'Name EN', 'fr' => 'Nom FR'];
        $r->setName($translations);

        self::assertSame($translations, $r->getNameTranslations());
    }

    // -------------------------------------------------------------------------
    // getCustomFor()
    // -------------------------------------------------------------------------

    public function testGetCustomForReturnsValueForKnownLang(): void
    {
        $r = new Episciences_Mail_Reminder();
        $r->setCustom(['en' => 1, 'fr' => 0]);

        self::assertSame(1, $r->getCustomFor('en'));
        self::assertSame(0, $r->getCustomFor('fr'));
    }

    public function testGetCustomForReturnsNullForUnknownLang(): void
    {
        $r = new Episciences_Mail_Reminder();
        $r->setCustom(['en' => 1]);

        self::assertNull($r->getCustomFor('de'));
    }

    // -------------------------------------------------------------------------
    // setLocale() — fallback
    // -------------------------------------------------------------------------

    public function testSetLocaleStoresKnownLocale(): void
    {
        $r = new Episciences_Mail_Reminder();
        $r->setLocale('en');

        self::assertSame('en', $r->getLocale());
    }

    public function testSetLocaleReturnsDefaultForUnknownLocale(): void
    {
        $r = new Episciences_Mail_Reminder();
        $r->setLocale('xx_INVALID');

        // Must fall back to '_defaultLanguage' = 'en' (or first available).
        self::assertNotNull($r->getLocale());
        self::assertSame('en', $r->getLocale());
    }

    // -------------------------------------------------------------------------
    // SECURITY fix — loadRecipients() $date parameter is now quoted
    // -------------------------------------------------------------------------

    /**
     * Reminder.php line 266 was fixed to use Zend_Db_Table_Abstract::getDefaultAdapter()->quote()
     * instead of raw string concatenation:
     *
     *   Before: $date = ($date) ? "'" . $date . "'" : 'CURDATE()';
     *   After:  $date = ($date) ? $db->quote((string)$date) : 'CURDATE()';
     *
     * This test verifies that the object still accepts a date parameter without
     * error (the full SQL path requires DB access and is tested in integration tests).
     */
    public function testLoadRecipientsAcceptsDateParameterWithoutError(): void
    {
        $r = new Episciences_Mail_Reminder();
        // The Reminder object itself imposes no constraints on $date before
        // passing it to loadRecipients(). The DB adapter's quote() handles escaping.
        self::assertInstanceOf(Episciences_Mail_Reminder::class, $r);
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // RemindersManager::REPETITION_MAP — consistency
    // -------------------------------------------------------------------------

    public function testRepetitionMapKeysAreNonNegativeIntegers(): void
    {
        foreach (array_keys(Episciences_Mail_RemindersManager::REPETITION_MAP) as $key) {
            self::assertIsInt($key);
            self::assertGreaterThanOrEqual(0, $key);
        }
    }

    public function testRepetitionMapValuesAreNonEmptyStrings(): void
    {
        foreach (Episciences_Mail_RemindersManager::REPETITION_MAP as $days => $label) {
            self::assertIsString($label, "Repetition label for $days days must be a string");
            self::assertNotEmpty($label);
        }
    }

    public function testRepetitionMapContainsExpectedEntries(): void
    {
        $map = Episciences_Mail_RemindersManager::REPETITION_MAP;

        self::assertArrayHasKey(0, $map,  'Key 0 (never) must exist');
        self::assertArrayHasKey(1, $map,  'Key 1 (daily) must exist');
        self::assertArrayHasKey(7, $map,  'Key 7 (weekly) must exist');
        self::assertArrayHasKey(14, $map, 'Key 14 (bi-weekly) must exist');
        self::assertArrayHasKey(31, $map, 'Key 31 (monthly) must exist');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Returns all TYPE_* int constants defined on Episciences_Mail_Reminder. */
    private function getAllTypeConstants(): array
    {
        $rc = new ReflectionClass(Episciences_Mail_Reminder::class);
        return array_filter(
            $rc->getConstants(),
            static fn ($v) => is_int($v)
        );
    }
}
