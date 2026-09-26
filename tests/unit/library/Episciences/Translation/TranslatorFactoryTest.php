<?php

namespace unit\library\Episciences\Translation;

use Episciences\Translation\TranslatorFactory;
use PHPUnit\Framework\TestCase;
use Zend_Translate;
use Zend_Translate_Exception;

/**
 * @covers \Episciences\Translation\TranslatorFactory
 */
final class TranslatorFactoryTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/data';

    // =========================================================================
    // create() — fixtures
    // =========================================================================

    public function testLaterSortedFileWinsOnDuplicateKeys(): void
    {
        $translator = TranslatorFactory::create(self::FIXTURES . '/app');

        self::assertSame('EN from b', $translator->getAdapter()->translate('shared', 'en'));
        self::assertSame('EN only in a', $translator->getAdapter()->translate('only_a', 'en'));
    }

    public function testEachLocaleOnlyGetsItsOwnDirectory(): void
    {
        $translator = TranslatorFactory::create(self::FIXTURES . '/app');

        self::assertSame(['en', 'fr'], $this->sortedList($translator));
        self::assertSame(
            ['shared' => 'FR depuis a', 'only_a' => 'FR seulement dans a'],
            $translator->getAdapter()->getMessages('fr')
        );
    }

    public function testEmailTemplatesAreNotLoaded(): void
    {
        $translator = TranslatorFactory::create(self::FIXTURES . '/app', self::FIXTURES . '/journal');

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
        $translator = TranslatorFactory::create(self::FIXTURES . '/app', self::FIXTURES . '/journal');
        $adapter = $translator->getAdapter();

        self::assertSame('EN journal override', $adapter->translate('shared', 'en'));
        self::assertSame('FR depuis a', $adapter->translate('shared', 'fr'));
        self::assertSame('EN journal only', $adapter->translate('journal_only', 'en'));
        self::assertSame('FR revue seulement', $adapter->translate('journal_only', 'fr'));
    }

    public function testMissingJournalDirectoryIsIgnored(): void
    {
        $translator = TranslatorFactory::create(self::FIXTURES . '/app', self::FIXTURES . '/does-not-exist');

        self::assertSame('EN from b', $translator->getAdapter()->translate('shared', 'en'));
    }

    /**
     * Journal translations are added later on the registered translator (OAI: Paper::getCitation(),
     * Paper_Tei): the locale must still be taken from the sub-directory name, and e-mail templates skipped.
     */
    public function testLaterDirectoryAdditionStillDetectsLocaleByDirectory(): void
    {
        $translator = TranslatorFactory::create(self::FIXTURES . '/app');
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

        TranslatorFactory::create(self::FIXTURES . '/does-not-exist');
    }

    // =========================================================================
    // create() — real application dictionaries
    // =========================================================================

    public function testApplicationDictionariesAreFullyLoadedInSortedOrder(): void
    {
        $translator = TranslatorFactory::create(PATH_TRANSLATION);

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
        $translator = TranslatorFactory::create(PATH_TRANSLATION);

        self::assertStringContainsString('fa-user-tag', $translator->getAdapter()->translate('editorial_board', 'en'));
        self::assertStringContainsString('fa-user-tag', $translator->getAdapter()->translate('editorial_board', 'fr'));
    }

    // =========================================================================
    // create() — locale
    // =========================================================================

    public function testRequestedLocaleIsApplied(): void
    {
        self::assertSame('en', (string)TranslatorFactory::create(self::FIXTURES . '/app', null, 'en')->getLocale());
        self::assertSame('fr', (string)TranslatorFactory::create(self::FIXTURES . '/app', null, 'fr')->getLocale());
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
