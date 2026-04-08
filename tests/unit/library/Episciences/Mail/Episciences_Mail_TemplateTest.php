<?php

namespace unit\library\Episciences\Mail;

use Episciences_Mail_Template;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Episciences_Mail_Template entity.
 *
 * Framework bootstrap is available (DB + Zend_Registry) so setLocale() can run.
 *
 * @covers Episciences_Mail_Template
 */
final class Episciences_Mail_TemplateTest extends TestCase
{
    protected function setUp(): void
    {
        // setLocale() → Tools::getLanguages() → Ccsd_Locale::getLanguage() → Zend_Registry::get('Zend_Locale')
        if (!\Zend_Registry::isRegistered('Zend_Locale')) {
            \Zend_Registry::set('Zend_Locale', new \Zend_Locale('en'));
        }
        if (!\Zend_Registry::isRegistered('languages')) {
            \Zend_Registry::set('languages', ['en', 'fr']);
        }
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function testConstructWithNoArgumentsLeavesDataFieldsNull(): void
    {
        $t = new Episciences_Mail_Template();

        self::assertNull($t->getId());
        self::assertNull($t->getParentid());
        self::assertNull($t->getKey());
        self::assertNull($t->getType());
    }

    public function testConstructSetsDefaultLocale(): void
    {
        $t = new Episciences_Mail_Template();

        // The constructor calls setLocale('en') when no locale is set.
        // After framework bootstrap, 'en' must be a valid language.
        self::assertNotNull($t->getLocale());
    }

    public function testConstructWithOptionsPopulatesFields(): void
    {
        $t = new Episciences_Mail_Template(['id' => 42, 'key' => 'paper_accepted']);

        self::assertSame(42, $t->getId());
        self::assertSame('paper_accepted', $t->getKey());
    }

    // -------------------------------------------------------------------------
    // setOptions
    // -------------------------------------------------------------------------

    public function testSetOptionsReturnsSelf(): void
    {
        $t = new Episciences_Mail_Template();
        $result = $t->setOptions(['id' => 1]);

        self::assertSame($t, $result);
    }

    public function testSetOptionsIsCaseInsensitiveOnKeys(): void
    {
        $t = new Episciences_Mail_Template();
        $t->setOptions(['ID' => 10, 'KEY' => 'my_key', 'TYPE' => 'automatic']);

        self::assertSame(10, $t->getId());
        self::assertSame('my_key', $t->getKey());
        self::assertSame('automatic', $t->getType());
    }

    public function testSetOptionsIsSilentForUnknownKey(): void
    {
        $t = new Episciences_Mail_Template();

        ob_start();
        $t->setOptions(['nonexistent_key' => 'some_sensitive_value']);
        $output = ob_get_clean();

        self::assertSame('', $output, 'setOptions() must be silent for unknown keys.');
    }

    public function testSetOptionsIgnoresCompletelyUnrecognisedKeys(): void
    {
        $t = new Episciences_Mail_Template();
        $idBefore = $t->getId();
        $t->setOptions(['does_not_exist' => 'value']);

        self::assertSame($idBefore, $t->getId());
    }

    // -------------------------------------------------------------------------
    // Fluent setters
    // -------------------------------------------------------------------------

    /** @dataProvider provideFluentSetterCases */
    public function testSettersReturnSelf(string $setter, mixed $value): void
    {
        $t = new Episciences_Mail_Template();
        $result = $t->$setter($value);

        self::assertSame($t, $result);
    }

    public static function provideFluentSetterCases(): array
    {
        return [
            'setId'       => ['setId',       99],
            'setParentid' => ['setParentid',  1],
            'setRvid'     => ['setRvid',      5],
            'setRvcode'   => ['setRvcode',    'test'],
            'setKey'      => ['setKey',       'my_key'],
            'setType'     => ['setType',      'auto'],
            'setBody'     => ['setBody',      ['en' => 'body text']],
            'setName'     => ['setName',      ['en' => 'My Template']],
            'setSubject'  => ['setSubject',   ['en' => 'Subject line']],
        ];
    }

    // -------------------------------------------------------------------------
    // setRvid casts to int
    // -------------------------------------------------------------------------

    public function testSetRvidCastsToInt(): void
    {
        $t = new Episciences_Mail_Template();
        $t->setRvid('7');

        // setRvid() calls (int)$rvid
        self::assertSame(7, $t->getRvid());
    }

    // -------------------------------------------------------------------------
    // getBody / getSubject / getName — locale-based resolution
    // -------------------------------------------------------------------------

    public function testGetBodyWithExplicitLangReturnsCorrectString(): void
    {
        $t = new Episciences_Mail_Template();
        $t->setBody(['en' => 'English body', 'fr' => 'Corps français']);

        self::assertSame('English body', $t->getBody('en'));
        self::assertSame('Corps français', $t->getBody('fr'));
    }

    public function testGetBodyWithNoLangUsesLocale(): void
    {
        $t = new Episciences_Mail_Template();
        $t->setBody(['en' => 'Default body']);
        $t->setLocale('en');

        self::assertSame('Default body', $t->getBody());
    }

    public function testGetBodyReturnsNullForUnknownLang(): void
    {
        $t = new Episciences_Mail_Template();
        $t->setBody(['en' => 'Only English']);

        self::assertNull($t->getBody('de'));
    }

    public function testGetBodyReturnsNullWhenBodyNotSet(): void
    {
        $t = new Episciences_Mail_Template();

        self::assertNull($t->getBody('en'));
    }

    public function testGetSubjectWithExplicitLang(): void
    {
        $t = new Episciences_Mail_Template();
        $t->setSubject(['en' => 'My subject', 'fr' => 'Mon sujet']);
        $t->setLocale('en');

        self::assertSame('My subject', $t->getSubject('en'));
    }

    public function testGetSubjectReturnsNullWhenNotSet(): void
    {
        $t = new Episciences_Mail_Template();

        self::assertNull($t->getSubject('en'));
    }

    public function testGetNameWithExplicitLang(): void
    {
        $t = new Episciences_Mail_Template();
        $t->setName(['en' => 'Template name', 'fr' => 'Nom du template']);

        self::assertSame('Template name', $t->getName('en'));
        self::assertSame('Nom du template', $t->getName('fr'));
    }

    public function testGetNameReturnsNullForUnknownLang(): void
    {
        $t = new Episciences_Mail_Template();
        $t->setName(['en' => 'Name']);

        self::assertNull($t->getName('es'));
    }

    // -------------------------------------------------------------------------
    // isCustom()
    // -------------------------------------------------------------------------

    public function testIsCustomReturnsFalseWhenParentIdIsNull(): void
    {
        $t = new Episciences_Mail_Template();

        self::assertFalse($t->isCustom());
    }

    public function testIsCustomReturnsFalseWhenParentIdIsZero(): void
    {
        $t = new Episciences_Mail_Template();
        $t->setParentid(0);

        self::assertFalse($t->isCustom());
    }

    public function testIsCustomReturnsTrueWhenParentIdIsPositive(): void
    {
        $t = new Episciences_Mail_Template();
        $t->setParentid(5);

        self::assertTrue($t->isCustom());
    }

    // -------------------------------------------------------------------------
    // toArray()
    // -------------------------------------------------------------------------

    public function testToArrayContainsExpectedKeys(): void
    {
        $t = new Episciences_Mail_Template();
        $result = $t->toArray();

        foreach (['id', 'parentId', 'rvcode', 'key', 'type', 'subject', 'name', 'body'] as $key) {
            self::assertArrayHasKey($key, $result, "toArray() must contain key '$key'");
        }
    }

    public function testToArrayReflectsSetValues(): void
    {
        $t = new Episciences_Mail_Template();
        $t->setId(7);
        $t->setKey('user_registration');
        $t->setType('manual');
        $t->setBody(['en' => 'Hello']);

        $result = $t->toArray();

        self::assertSame(7, $result['id']);
        self::assertSame('user_registration', $result['key']);
        self::assertSame('manual', $result['type']);
        self::assertSame(['en' => 'Hello'], $result['body']);
    }

    // -------------------------------------------------------------------------
    // setLocale() — fallback logic
    // -------------------------------------------------------------------------

    /**
     * If the requested locale is available, it is stored as-is.
     * 'en' is always available after bootstrap.
     */
    public function testSetLocaleStoresAvailableLocale(): void
    {
        $t = new Episciences_Mail_Template();
        $t->setLocale('en');

        self::assertSame('en', $t->getLocale());
    }

    /**
     * If the requested locale is unknown, setLocale() falls back to the default
     * language ('en') when 'en' is available.
     */
    public function testSetLocaleReturnsDefaultLanguageForUnknownLocale(): void
    {
        $t = new Episciences_Mail_Template();
        $t->setLocale('xx_UNKNOWN');

        // Falls back to '_defaultLanguage' = 'en'.
        self::assertSame('en', $t->getLocale());
    }

    // -------------------------------------------------------------------------
    // isAutomatic / setIsAutomatic
    // -------------------------------------------------------------------------

    public function testIsAutomaticDefaultsFalse(): void
    {
        $t = new Episciences_Mail_Template();

        self::assertFalse($t->isAutomatic());
    }

    public function testSetIsAutomaticTrue(): void
    {
        $t = new Episciences_Mail_Template();
        $t->setIsAutomatic(true);

        self::assertTrue($t->isAutomatic());
    }

    // -------------------------------------------------------------------------
    // setTags / getAvailableTagsListDescription
    // -------------------------------------------------------------------------

    public function testSetTagsSortsTagsAlphabetically(): void
    {
        $t = new Episciences_Mail_Template();
        $t->setIsAutomatic(false);
        $t->setTags(['%%Z_TAG%%', '%%A_TAG%%', '%%M_TAG%%']);

        $tags = $t->getTags();

        self::assertSame(['%%A_TAG%%', '%%M_TAG%%', '%%Z_TAG%%'], $tags);
    }

    public function testSetTagsOnAutomaticTemplateRemovesSenderTags(): void
    {
        $t = new Episciences_Mail_Template();
        $t->setIsAutomatic(true);

        $input = [
            '%%SENDER_EMAIL%%',
            '%%SENDER_FULL_NAME%%',
            '%%ARTICLE_ID%%',
        ];
        $t->setTags($input);

        $tags = $t->getTags();

        self::assertNotContains('%%SENDER_EMAIL%%', $tags);
        self::assertNotContains('%%SENDER_FULL_NAME%%', $tags);
        self::assertContains('%%ARTICLE_ID%%', $tags);
    }

    public function testGetAvailableTagsListDescriptionJoinsWithSemicolon(): void
    {
        $t = new Episciences_Mail_Template();
        $t->setIsAutomatic(false);
        $t->setTags(['%%TAG_A%%', '%%TAG_B%%']);

        $description = $t->getAvailableTagsListDescription();

        self::assertStringContainsString('%%TAG_A%%', $description);
        self::assertStringContainsString('%%TAG_B%%', $description);
        self::assertStringContainsString(';', $description);
    }

    // -------------------------------------------------------------------------
    // getTranslationsFolder() — path logic
    // -------------------------------------------------------------------------

    public function testGetDefaultTranslationsFolderEndsWithLanguagesSlash(): void
    {
        $t = new Episciences_Mail_Template();

        $folder = $t->getDefaultTranslationsFolder();

        self::assertStringEndsWith('/languages/', $folder);
    }

    public function testGetTranslationsFolderReturnsDefaultWhenNotCustom(): void
    {
        $t = new Episciences_Mail_Template();
        // parentId = null → isCustom() = false

        $folder = $t->getTranslationsFolder('testjournalcode');

        self::assertSame($t->getDefaultTranslationsFolder(), $folder);
    }
}
