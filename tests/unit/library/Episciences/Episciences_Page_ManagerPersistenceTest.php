<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences_Page;
use Episciences_Page_Manager;
use PHPUnit\Framework\TestCase;
use unit\library\Episciences\support\PagesTableDoubleTrait;

require_once __DIR__ . '/support/PagesTableDoubleTrait.php';

/**
 * Episciences_Page_Manager on an in-memory `pages` table: the visibility is read from the
 * `visibility_set` column, and every write fills both `visibility_set` and the legacy JSON `visibility`
 * column.
 */
final class Episciences_Page_ManagerPersistenceTest extends TestCase
{
    use PagesTableDoubleTrait;

    private const CODE = 'unit-test-journal';

    protected function tearDown(): void
    {
        $this->uninstallPagesTable();
    }

    /**
     * @param string[] $visibility
     */
    private static function page(string $pageCode, array $visibility): Episciences_Page
    {
        $page = new Episciences_Page();
        $page->setCode(self::CODE);
        $page->setPageCode($pageCode);
        $page->setUid(7);
        $page->setTitle(['en' => 'Title']);
        $page->setContent([]);
        $page->setVisibility($visibility);

        return $page;
    }

    // =========================================================================
    // Reads
    // =========================================================================

    public function testFindByCodeAndPageCodeReadsTheVisibilitySetColumn(): void
    {
        $this->installPagesTable([self::pagesRow(3, self::CODE, 'my-page', 'editor,secretary')]);

        $page = Episciences_Page_Manager::findByCodeAndPageCode(self::CODE, 'my-page');

        self::assertSame(3, $page->getId());
        self::assertSame(['editor', 'secretary'], $page->getVisibility(true));
    }

    public function testFindByCodeAndPageCodeReturnsAnEmptyPageWhenNotFound(): void
    {
        $this->installPagesTable([]);

        self::assertSame(0, Episciences_Page_Manager::findByCodeAndPageCode(self::CODE, 'missing')->getId());
    }

    public function testFindAllByCodeReturnsThePagesOfTheJournalIndexedByPageCode(): void
    {
        $this->installPagesTable([
            self::pagesRow(1, self::CODE, 'about', 'public'),
            self::pagesRow(2, self::CODE, 'my-page', 'member'),
            self::pagesRow(3, 'other-journal', 'my-page', 'editor'),
        ]);

        $pages = Episciences_Page_Manager::findAllByCode(self::CODE);

        self::assertSame(['about', 'my-page'], array_keys($pages));
        self::assertSame(2, $pages['my-page']->getId());
        self::assertSame(['member'], $pages['my-page']->getVisibility(true));
    }

    // =========================================================================
    // Writes
    // =========================================================================

    public function testAddFillsBothVisibilityColumns(): void
    {
        $this->installPagesTable([]);

        $id = Episciences_Page_Manager::add(self::page('my-page', ['editor', 'chief_editor']));

        self::assertGreaterThan(0, $id);
        $data = $this->pagesWritesOf('insert')[0][2];
        self::assertSame('editor,chief_editor', $data['visibility_set']);
        self::assertSame('["editor","chief_editor"]', $data['visibility']);
    }

    public function testAddOfAPublicPage(): void
    {
        $this->installPagesTable([]);

        Episciences_Page_Manager::add(self::page('my-page', []));

        $data = $this->pagesWritesOf('insert')[0][2];
        self::assertSame('public', $data['visibility_set']);
        self::assertSame('["public"]', $data['visibility']);
    }

    public function testUpdateFillsBothVisibilityColumns(): void
    {
        $this->installPagesTable([self::pagesRow(5, self::CODE, 'my-page', 'public')]);
        $page = self::page('my-page', ['secretary']);
        $page->setId(5);

        self::assertSame(1, Episciences_Page_Manager::update($page));

        [, , $data, $where] = $this->pagesWritesOf('update')[0];
        self::assertSame(['code = ?' => self::CODE, 'page_code = ?' => 'my-page'], $where);
        self::assertSame('secretary', $data['visibility_set']);
        self::assertSame('["secretary"]', $data['visibility']);
    }

    /**
     * A renamed custom page keeps its row (and its id): the row is found by its former page code
     */
    public function testUpdateWithNewPageCodeRenamesTheExistingRow(): void
    {
        $this->installPagesTable([self::pagesRow(5, self::CODE, 'old-page', 'public')]);
        $page = self::page('new-page', ['editor']);
        $page->setId(5);

        self::assertSame(1, Episciences_Page_Manager::updateWithNewPageCode($page, 'old-page'));

        [, , $data, $where] = $this->pagesWritesOf('update')[0];
        self::assertSame(['code = ?' => self::CODE, 'page_code = ?' => 'old-page'], $where);
        self::assertSame('new-page', $data['page_code']);
        self::assertSame('editor', $data['visibility_set']);
        self::assertSame('["editor"]', $data['visibility']);

        $pages = Episciences_Page_Manager::findAllByCode(self::CODE);
        self::assertSame(['new-page'], array_keys($pages));
        self::assertSame(5, $pages['new-page']->getId());
    }

    public function testUpdateWithNewPageCodeChangesNothingWhenTheFormerPageDoesNotExist(): void
    {
        $this->installPagesTable([]);

        self::assertSame(0, Episciences_Page_Manager::updateWithNewPageCode(self::page('new-page', []), 'old-page'));
        self::assertSame([], Episciences_Page_Manager::findAllByCode(self::CODE));
    }
}
