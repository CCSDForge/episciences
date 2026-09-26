<?php

namespace unit\library\Episciences\Translation;

use Episciences_Translation_Plugin;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Zend_Controller_Request_Http;
use Zend_Registry;
use Zend_Translate;
use Zend_Translate_Exception;

/**
 * Unit tests for Episciences_Translation_Plugin::createTranslator() and ::resolveLocale().
 *
 * preDispatch() is only tested for its early return: a full run sets a cookie and reads the journal languages from the DB.
 *
 * @covers Episciences_Translation_Plugin
 */
final class Episciences_Translation_PluginTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/data';

    // =========================================================================
    // createTranslator() — fixtures
    // =========================================================================

    public function testLaterSortedFileWinsOnDuplicateKeys(): void
    {
        $translator = Episciences_Translation_Plugin::createTranslator(self::FIXTURES . '/app');

        self::assertSame('EN from b', $translator->getAdapter()->translate('shared', 'en'));
        self::assertSame('EN only in a', $translator->getAdapter()->translate('only_a', 'en'));
    }

    public function testEachLocaleOnlyGetsItsOwnDirectory(): void
    {
        $translator = Episciences_Translation_Plugin::createTranslator(self::FIXTURES . '/app');

        self::assertSame(['en', 'fr'], $this->sortedList($translator));
        self::assertSame(
            ['shared' => 'FR depuis a', 'only_a' => 'FR seulement dans a'],
            $translator->getAdapter()->getMessages('fr')
        );
    }

    public function testEmailTemplatesAreNotLoaded(): void
    {
        $translator = Episciences_Translation_Plugin::createTranslator(self::FIXTURES . '/app', self::FIXTURES . '/journal');

        foreach (['en', 'fr'] as $lang) {
            foreach ($translator->getAdapter()->getMessages($lang) as $key => $value) {
                self::assertStringNotContainsString('template', (string)$key);
                self::assertStringNotContainsStringIgnoringCase('template', (string)$value);
                self::assertStringNotContainsString('gabarit', (string)$value);
            }
        }
    }

    public function testJournalDictionariesOverrideApplicationOnes(): void
    {
        $translator = Episciences_Translation_Plugin::createTranslator(self::FIXTURES . '/app', self::FIXTURES . '/journal');
        $adapter = $translator->getAdapter();

        self::assertSame('EN journal override', $adapter->translate('shared', 'en'));
        self::assertSame('FR depuis a', $adapter->translate('shared', 'fr'));
        self::assertSame('EN journal only', $adapter->translate('journal_only', 'en'));
        self::assertSame('FR revue seulement', $adapter->translate('journal_only', 'fr'));
    }

    public function testMissingJournalDirectoryIsIgnored(): void
    {
        $translator = Episciences_Translation_Plugin::createTranslator(self::FIXTURES . '/app', self::FIXTURES . '/does-not-exist');

        self::assertSame('EN from b', $translator->getAdapter()->translate('shared', 'en'));
    }

    /**
     * Journal translations are added later on the registered translator (OAI: Paper::getCitation(),
     * Paper_Tei): the locale must still be taken from the sub-directory name, and e-mail templates skipped.
     */
    public function testLaterDirectoryAdditionStillDetectsLocaleByDirectory(): void
    {
        $translator = Episciences_Translation_Plugin::createTranslator(self::FIXTURES . '/app');
        $translator->setLocale('en');

        $translator->addTranslation(self::FIXTURES . '/journal');
        $adapter = $translator->getAdapter();

        self::assertSame('FR revue seulement', $adapter->translate('journal_only', 'fr'));
        self::assertSame('EN journal only', $adapter->translate('journal_only', 'en'));
        self::assertSame('FR depuis a', $adapter->translate('shared', 'fr'));
        self::assertNotContains('Custom journal template.', $adapter->getMessages('en'));
        self::assertSame('en', (string)$translator->getLocale());
    }

    public function testThrowsWhenNoDictionaryIsFound(): void
    {
        $this->expectException(Zend_Translate_Exception::class);

        Episciences_Translation_Plugin::createTranslator(self::FIXTURES . '/does-not-exist');
    }

    // =========================================================================
    // createTranslator() — real application dictionaries
    // =========================================================================

    public function testApplicationDictionariesAreFullyLoadedInSortedOrder(): void
    {
        $translator = Episciences_Translation_Plugin::createTranslator(PATH_TRANSLATION);

        self::assertSame(['en', 'fr'], $this->sortedList($translator));

        foreach (['en', 'fr'] as $lang) {
            $files = glob(PATH_TRANSLATION . '/' . $lang . '/*.php');
            sort($files, SORT_STRING);
            $expected = [];
            foreach ($files as $file) {
                $expected = array_replace($expected, include $file);
            }

            self::assertEquals($expected, $translator->getAdapter()->getMessages($lang), "Dictionary mismatch for '$lang'");
        }
    }

    /**
     * views.php (loaded after js.php) wins: role labels keep their icon on server-rendered pages.
     */
    public function testViewsDictionaryWinsOverJsDictionary(): void
    {
        $translator = Episciences_Translation_Plugin::createTranslator(PATH_TRANSLATION);

        self::assertStringContainsString('fa-user-tag', $translator->getAdapter()->translate('editorial_board', 'en'));
        self::assertStringContainsString('fa-user-tag', $translator->getAdapter()->translate('editorial_board', 'fr'));
    }

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
    public function testResolveLocale(?string $url, ?string $cookie, ?string $browser, array $allowed, string $expected): void
    {
        self::assertSame($expected, Episciences_Translation_Plugin::resolveLocale($url, $cookie, $browser, $allowed));
    }

    /**
     * @return array<string, array{?string, ?string, ?string, string[], string}>
     */
    public static function resolveLocaleProvider(): array
    {
        return [
            'URL wins over cookie and browser' => ['en', 'fr', 'fr', ['en', 'fr'], 'en'],
            'invalid URL falls through to cookie' => ['de', 'en', 'fr', ['en', 'fr'], 'en'],
            'cookie wins over browser' => [null, 'en', 'fr', ['en', 'fr'], 'en'],
            'invalid cookie falls through to browser' => [null, 'xx', 'en', ['en', 'fr'], 'en'],
            'unsupported browser falls back to French' => [null, null, 'de', ['en', 'fr'], 'fr'],
            'nothing falls back to French' => [null, null, null, ['en', 'fr'], 'fr'],
            'English-only journal never gets French' => ['fr', 'fr', 'fr', ['en'], 'en'],
            'English-only journal default' => [null, null, null, ['en'], 'en'],
            'extra journal language is accepted' => ['es', null, null, ['fr', 'en', 'es'], 'es'],
        ];
    }

    /**
     * @return string[]
     */
    private function sortedList(Zend_Translate $translator): array
    {
        $list = array_values($translator->getAdapter()->getList() ?? []);
        sort($list);
        return $list;
    }
}
