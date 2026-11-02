<?php

declare(strict_types=1);

namespace unit\library\Episciences\Website;

use Episciences_Page;
use Episciences_Website_Navigation_Page_Custom;
use PHPUnit\Framework\TestCase;
use unit\library\Episciences\support\PagesTableDoubleTrait;

require_once __DIR__ . '/../support/PagesTableDoubleTrait.php';

/**
 * Custom menu page: former permalink kept for the save of the menu, visibility read from the `pages`
 * table when the menu is loaded.
 */
final class Episciences_Website_Navigation_Page_CustomTest extends TestCase
{
    use PagesTableDoubleTrait;

    /** Page codes without any page file under data/ */
    private const FIRST = 'unit-test-first-page';
    private const SECOND = 'unit-test-second-page';
    private const THIRD = 'unit-test-third-page';

    protected function tearDown(): void
    {
        $this->uninstallPagesTable();
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function customPage(array $options = []): Episciences_Website_Navigation_Page_Custom
    {
        return new Episciences_Website_Navigation_Page_Custom($options + ['languages' => ['en']]);
    }

    /**
     * A page as read from the `pages` table, with its `visibility_set` value
     */
    private static function storedPage(string $visibilitySet): Episciences_Page
    {
        $page = new Episciences_Page();
        $page->setId(3);
        $page->setVisibility($visibilitySet, false);

        return $page;
    }

    // =========================================================================
    // Former permalink
    // =========================================================================

    public function testNewPageHasNoFormerPermalink(): void
    {
        $page = self::customPage(['permalien' => self::FIRST]);

        self::assertNull($page->getPreviousPermalien());
    }

    public function testSettingTheSamePermalinkKeepsNoFormerPermalink(): void
    {
        $page = self::customPage(['permalien' => self::FIRST]);
        $page->setPermalien(self::FIRST);

        self::assertNull($page->getPreviousPermalien());
    }

    public function testChangedPermalinkKeepsTheFormerOne(): void
    {
        $page = self::customPage(['permalien' => self::FIRST]);
        $page->setPermalien(self::SECOND);

        self::assertSame(self::SECOND, $page->getPermalien());
        self::assertSame(self::FIRST, $page->getPreviousPermalien());
    }

    /**
     * Until the menu is saved, the row of the page in the `pages` table still has the first permalink
     */
    public function testPermalinkChangedTwiceKeepsTheFirstOne(): void
    {
        $page = self::customPage(['permalien' => self::FIRST]);
        $page->setPermalien(self::SECOND);
        $page->setPermalien(self::THIRD);

        self::assertSame(self::FIRST, $page->getPreviousPermalien());
    }

    // =========================================================================
    // Visibility loaded from the pages table
    // =========================================================================

    public function testLoadTakesTheRolesOfThePreloadedPage(): void
    {
        $this->installPagesTable([]);
        $page = self::customPage(['permalien' => self::FIRST, 'preloadedPage' => self::storedPage('editor,secretary')]);

        $page->load();

        self::assertSame(['editor', 'secretary'], $page->getAcl());
    }

    public function testLoadOfAPublicPreloadedPageClearsTheRoles(): void
    {
        $this->installPagesTable([]);
        $page = self::customPage(['permalien' => self::FIRST, 'preloadedPage' => self::storedPage('public')]);
        $page->setAcl(['editor']);

        $page->load();

        self::assertSame([], $page->getAcl());
    }

    public function testLoadWithoutPreloadedPageReadsThePagesTable(): void
    {
        $row = self::pagesRow(3, (string)RVCODE, self::FIRST, 'chief_editor');
        // As stored before the migration, to check the SET column is the one read
        $row['visibility'] = '["public"]';
        $this->installPagesTable([$row]);
        $page = self::customPage(['permalien' => self::FIRST]);

        $page->load();

        self::assertSame(['chief_editor'], $page->getAcl());
    }

    public function testLoadKeepsTheRolesOfTheMenuWhenThePageIsNotInThePagesTable(): void
    {
        $this->installPagesTable([]);
        $page = self::customPage(['permalien' => self::FIRST]);
        $page->setAcl(['editor']);

        $page->load();

        self::assertSame(['editor'], $page->getAcl());
    }
}
