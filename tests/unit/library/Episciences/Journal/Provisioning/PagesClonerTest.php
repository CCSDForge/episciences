<?php

declare(strict_types=1);

namespace unit\library\Episciences\Journal\Provisioning;

use Episciences\Journal\Provisioning\PagesCloner;
use PHPUnit\Framework\TestCase;
use unit\library\Episciences\support\PagesTableDoubleTrait;

require_once __DIR__ . '/../../support/PagesTableDoubleTrait.php';

/**
 * PagesCloner::clone() on an in-memory `pages` table: the visibility of the cloned pages is a valid value
 * of the `visibility_set` column, and the legacy JSON column is rebuilt from it.
 */
final class PagesClonerTest extends TestCase
{
    use PagesTableDoubleTrait;

    /** Journal codes without any page file under data/ */
    private const FROM = 'unit-test-pages-from';
    private const TO = 'unit-test-pages-to';

    protected function tearDown(): void
    {
        $this->uninstallPagesTable();
    }

    public function testClonedPageKeepsTheVisibilityOfTheSourcePage(): void
    {
        $this->installPagesTable([self::pagesRow(1, self::FROM, 'my-page', 'editor,chief_editor')]);

        $cloned = (new PagesCloner())->clone(self::FROM, self::TO, 42);

        self::assertSame(1, $cloned);
        $inserts = $this->pagesWritesOf('insert');
        self::assertCount(1, $inserts);
        $data = $inserts[0][2];
        self::assertSame(self::TO, $data['code']);
        self::assertSame('my-page', $data['page_code']);
        self::assertSame(42, $data['uid']);
        self::assertSame('editor,chief_editor', $data['visibility_set']);
        self::assertSame('["editor","chief_editor"]', $data['visibility']);
    }

    public function testClonedPageKeepsTitleAndContentAsStored(): void
    {
        $this->installPagesTable([self::pagesRow(1, self::FROM, 'about', 'public')]);

        (new PagesCloner())->clone(self::FROM, self::TO, 42);

        $data = $this->pagesWritesOf('insert')[0][2];
        self::assertSame('{"en":"Title"}', $data['title']);
        self::assertSame('{"en":"Content"}', $data['content']);
        self::assertSame('public', $data['visibility_set']);
    }

    public function testPageAlreadyOnTheTargetJournalIsLeftUntouched(): void
    {
        $this->installPagesTable([
            self::pagesRow(1, self::FROM, 'about', 'public'),
            self::pagesRow(2, self::TO, 'about', 'editor'),
        ]);

        $cloned = (new PagesCloner())->clone(self::FROM, self::TO, 42);

        self::assertSame(0, $cloned);
        self::assertSame([], $this->pagesWrites);
    }
}
