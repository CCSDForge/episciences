<?php

namespace unit\library\Episciences\Translation;

use Episciences_Translation_Plugin;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Zend_Controller_Request_Http;
use Zend_Registry;
use Zend_Translate;

/**
 * Unit tests for Episciences_Translation_Plugin::resolveLocale().
 *
 * preDispatch() is only tested for its early return: a full run sets a cookie and reads the journal languages from the DB.
 *
 * @covers Episciences_Translation_Plugin
 */
final class Episciences_Translation_PluginTest extends TestCase
{
    // =========================================================================
    // preDispatch() — internal forwards
    // =========================================================================

    /**
     * An internal forward (journal index -> page) re-runs preDispatch(): the translator must not be
     * rebuilt and the language cookie must not be sent twice.
     */
    public function testPreDispatchIsSkippedOnceTheTranslatorIsRegistered(): void
    {
        $registered = Zend_Registry::isRegistered('Zend_Translate') ? Zend_Registry::get('Zend_Translate') : null;
        $translator = new Zend_Translate(['adapter' => Zend_Translate::AN_ARRAY, 'content' => ['k' => 'v'], 'locale' => 'en']);
        Zend_Registry::set('Zend_Translate', $translator);

        $plugin = new Episciences_Translation_Plugin();
        (new ReflectionProperty($plugin, 'translatorRegistered'))->setValue($plugin, true);

        try {
            $plugin->preDispatch(new Zend_Controller_Request_Http());
            self::assertSame($translator, Zend_Registry::get('Zend_Translate'));
        } finally {
            Zend_Registry::set('Zend_Translate', $registered);
        }
    }

    // =========================================================================
    // resolveLocale()
    // =========================================================================

    /**
     * @dataProvider resolveLocaleProvider
     * @param string[] $allowed
     */
    public function testResolveLocale(?string $url, ?string $cookie, ?string $account, ?string $browser, array $allowed, string $expected): void
    {
        self::assertSame($expected, Episciences_Translation_Plugin::resolveLocale($url, $cookie, $account, $browser, $allowed));
    }

    /**
     * @return array<string, array{?string, ?string, ?string, ?string, string[], string}>
     */
    public static function resolveLocaleProvider(): array
    {
        return [
            'URL wins over everything' => ['en', 'fr', 'fr', 'fr', ['en', 'fr'], 'en'],
            'invalid URL falls through to cookie' => ['de', 'en', 'fr', 'fr', ['en', 'fr'], 'en'],
            'cookie (explicit switch) wins over account' => [null, 'en', 'fr', 'fr', ['en', 'fr'], 'en'],
            'account wins over browser' => [null, null, 'en', 'fr', ['en', 'fr'], 'en'],
            'invalid cookie falls through to account' => [null, 'xx', 'en', 'fr', ['en', 'fr'], 'en'],
            'unsupported account falls through to browser' => [null, null, 'de', 'en', ['en', 'fr'], 'en'],
            'anonymous: browser' => [null, null, null, 'en', ['en', 'fr'], 'en'],
            'unsupported browser falls back to French' => [null, null, null, 'de', ['en', 'fr'], 'fr'],
            'nothing falls back to French' => [null, null, null, null, ['en', 'fr'], 'fr'],
            'English-only journal never gets French' => ['fr', 'fr', 'fr', 'fr', ['en'], 'en'],
            'English-only journal default' => [null, null, null, null, ['en'], 'en'],
            'extra journal language is accepted' => ['es', null, null, null, ['fr', 'en', 'es'], 'es'],
        ];
    }
}
