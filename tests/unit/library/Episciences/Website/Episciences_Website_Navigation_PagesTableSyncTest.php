<?php

declare(strict_types=1);

namespace unit\library\Episciences\Website;

use Episciences_Page_Manager;
use Episciences_Website_Navigation;
use Episciences_Website_Navigation_Page;
use Episciences_Website_Navigation_Page_About;
use Episciences_Website_Navigation_Page_Custom;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use unit\library\Episciences\support\PagesTableDoubleTrait;

require_once __DIR__ . '/../support/PagesTableDoubleTrait.php';

/**
 * Synchronisation of the menu pages with the `pages` table when the menu is saved
 * (Episciences_Website_Navigation::syncPageTitleToDatabase()), on an in-memory `pages` table.
 */
final class Episciences_Website_Navigation_PagesTableSyncTest extends TestCase
{
    use PagesTableDoubleTrait;

    private const CODE = 'unit-test-journal';

    /** Page codes without any page file under data/ */
    private const OLD = 'unit-test-old-page';
    private const NEW = 'unit-test-new-page';

    protected function tearDown(): void
    {
        $this->uninstallPagesTable();
    }

    /**
     * Saves one menu page as Episciences_Website_Navigation::save() does, with the pages of the journal
     * loaded beforehand
     */
    private function sync(Episciences_Website_Navigation_Page $page): void
    {
        $navigation = new Episciences_Website_Navigation(['sid' => 1, 'languages' => ['en']]);

        $code = new ReflectionProperty(Episciences_Website_Navigation::class, '_preloadedReviewCode');
        $code->setAccessible(true);
        $code->setValue($navigation, self::CODE);

        $pages = new ReflectionProperty(Episciences_Website_Navigation::class, '_preloadedPages');
        $pages->setAccessible(true);
        $pages->setValue($navigation, Episciences_Page_Manager::findAllByCode(self::CODE));

        $sync = new ReflectionMethod(Episciences_Website_Navigation::class, 'syncPageTitleToDatabase');
        $sync->setAccessible(true);
        $sync->invoke($navigation, $page);
    }

    /**
     * @param string[] $acl
     */
    private static function customPage(string $permalien, array $acl = []): Episciences_Website_Navigation_Page_Custom
    {
        $page = new Episciences_Website_Navigation_Page_Custom([
            'languages' => ['en'],
            'permalien' => $permalien,
            'labels' => ['en' => 'My page'],
        ]);
        $page->setAcl($acl);

        return $page;
    }

    /**
     * @return array<string, array<string, mixed>> rows of the journal by page code
     */
    private function journalRows(): array
    {
        $rows = [];
        foreach ($this->pagesRows as $row) {
            if ($row['code'] === self::CODE) {
                $rows[$row['page_code']] = $row;
            }
        }

        return $rows;
    }

    // =========================================================================
    // Custom pages
    // =========================================================================

    public function testNewCustomPageIsAddedWithItsRoles(): void
    {
        $this->installPagesTable([]);

        $this->sync(self::customPage(self::NEW, ['editor', 'secretary']));

        $rows = $this->journalRows();
        self::assertSame([self::NEW], array_keys($rows));
        self::assertSame('editor,secretary', $rows[self::NEW]['visibility_set']);
        self::assertSame('["editor","secretary"]', $rows[self::NEW]['visibility']);
    }

    public function testCustomPageWithoutRolesIsPublic(): void
    {
        $this->installPagesTable([]);

        $this->sync(self::customPage(self::NEW));

        self::assertSame('public', $this->journalRows()[self::NEW]['visibility_set']);
    }

    public function testExistingCustomPageIsUpdated(): void
    {
        $this->installPagesTable([self::pagesRow(9, self::CODE, self::NEW, 'public')]);

        $this->sync(self::customPage(self::NEW, ['chief_editor']));

        self::assertSame([], $this->pagesWritesOf('insert'));
        $rows = $this->journalRows();
        self::assertSame(9, $rows[self::NEW]['id']);
        self::assertSame('chief_editor', $rows[self::NEW]['visibility_set']);
    }

    /**
     * Changing the permalink renames the row of the page instead of adding a second one
     */
    public function testRenamedCustomPageKeepsItsRow(): void
    {
        $this->installPagesTable([self::pagesRow(9, self::CODE, self::OLD, 'public')]);
        $page = self::customPage(self::OLD, ['editor']);
        $page->setPermalien(self::NEW);

        $this->sync($page);

        self::assertSame([], $this->pagesWritesOf('insert'));
        $rows = $this->journalRows();
        self::assertSame([self::NEW], array_keys($rows));
        self::assertSame(9, $rows[self::NEW]['id']);
        self::assertSame('editor', $rows[self::NEW]['visibility_set']);
    }

    public function testRenamedCustomPageWithoutRowIsAdded(): void
    {
        $this->installPagesTable([]);
        $page = self::customPage(self::OLD);
        $page->setPermalien(self::NEW);

        $this->sync($page);

        self::assertSame([self::NEW], array_keys($this->journalRows()));
    }

    public function testPageOfAnotherJournalIsNotTouched(): void
    {
        $this->installPagesTable([self::pagesRow(9, 'other-journal', self::NEW, 'editor')]);

        $this->sync(self::customPage(self::NEW, ['secretary']));

        self::assertSame([], $this->pagesWritesOf('update'));
        self::assertSame('editor', $this->pagesRows[0]['visibility_set']);
    }

    // =========================================================================
    // Predefined pages
    // =========================================================================

    public function testPredefinedPageIsAlwaysPublic(): void
    {
        $this->installPagesTable([self::pagesRow(4, self::CODE, 'about', 'editor')]);
        $page = new Episciences_Website_Navigation_Page_About(['languages' => ['en'], 'labels' => ['en' => 'About']]);
        $page->setAcl(['editor']);

        $this->sync($page);

        $rows = $this->journalRows();
        self::assertSame(4, $rows['about']['id']);
        self::assertSame('public', $rows['about']['visibility_set']);
    }
}
