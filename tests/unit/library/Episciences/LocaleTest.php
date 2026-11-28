<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences\Locale;
use PHPUnit\Framework\TestCase;
use Zend_Locale;
use Zend_Registry;

/**
 * Unit tests for Episciences\Locale.
 */
final class LocaleTest extends TestCase
{
    protected function tearDown(): void
    {
        if (Zend_Registry::isRegistered('Zend_Locale')) {
            Zend_Registry::getInstance()->offsetUnset('Zend_Locale');
        }

        parent::tearDown();
    }

    public function testGetLanguageNameInEnglish(): void
    {
        self::assertSame('French', Locale::getLanguageName('fr', 'en'));
    }

    public function testGetLanguageNameInFrench(): void
    {
        self::assertSame('français', Locale::getLanguageName('fr', 'fr'));
        self::assertSame('anglais', Locale::getLanguageName('en', 'fr'));
    }

    public function testGetLanguageNameUnknownCodeReturnsCode(): void
    {
        self::assertSame('xx', Locale::getLanguageName('xx', 'en'));
    }

    public function testGetLanguageNameIsCaseInsensitive(): void
    {
        self::assertSame('French', Locale::getLanguageName('FR', 'en'));
    }

    public function testGetLanguageNameEmptyCodeReturnsEmptyString(): void
    {
        self::assertSame('', Locale::getLanguageName(''));
    }

    public function testGetLanguageNameDefaultsToEnglishWithoutRegistry(): void
    {
        self::assertSame('French', Locale::getLanguageName('fr'));
    }

    public function testGetLanguageNameUsesZendLocaleObjectFromRegistry(): void
    {
        Zend_Registry::set('Zend_Locale', new Zend_Locale('fr'));
        self::assertSame('anglais', Locale::getLanguageName('en'));
    }

    public function testGetLanguageNameUsesStringLocaleFromRegistry(): void
    {
        Zend_Registry::set('Zend_Locale', 'fr');
        self::assertSame('anglais', Locale::getLanguageName('en'));
    }

    public function testGetLanguageNamesInEnglish(): void
    {
        $names = Locale::getLanguageNames('en');
        self::assertSame('English', $names['en']);
        self::assertSame('French', $names['fr']);
    }

    public function testGetLanguageNamesUsesRegistryLocale(): void
    {
        Zend_Registry::set('Zend_Locale', new Zend_Locale('fr'));
        $names = Locale::getLanguageNames();
        self::assertSame('anglais', $names['en']);
        self::assertSame('français', $names['fr']);
    }

    public function testGetLanguageNamesFallsBackToEnglishForUnknownLocale(): void
    {
        $names = Locale::getLanguageNames('zz_ZZ');
        self::assertSame('English', $names['en']);
        self::assertSame('French', $names['fr']);
    }
}
