<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Episciences_Review;
use Episciences_ReviewsManager;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use WebsiteDefaultController;

require_once APPLICATION_PATH . '/modules/common/controllers/WebsiteDefaultController.php';

/**
 * Grouping of the page types offered in the menu editor.
 */
final class WebsiteDefaultControllerPageTypesTest extends TestCase
{
    private const PAGE_TYPES = [
        'custom' => 'Episciences_Website_Navigation_Page_Custom',
        'about' => 'Episciences_Website_Navigation_Page_About',
    ];

    protected function tearDown(): void
    {
        Episciences_ReviewsManager::clearCache();
    }

    /**
     * @param Episciences_Review|false $journal
     */
    private function processPageTypes(Episciences_Review|false $journal): array
    {
        $cache = new ReflectionProperty(Episciences_ReviewsManager::class, '_cache');
        $cache->setAccessible(true);
        $cache->setValue(null, array_merge($cache->getValue(), ['rvcode_' . RVCODE => $journal]));

        // processPageTypes() does not depend on the request: the constructor is not needed
        $controller = (new ReflectionClass(WebsiteDefaultController::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(WebsiteDefaultController::class, 'processPageTypes');
        $method->setAccessible(true);

        return $method->invoke($controller, self::PAGE_TYPES);
    }

    public function testUnknownJournalGetsTheFlatList(): void
    {
        $processed = $this->processPageTypes(false);

        self::assertSame([''], array_keys($processed));
        self::assertSame(['about', 'custom'], array_values($processed['']));
    }

    public function testOldSiteGetsTheFlatList(): void
    {
        $journal = (new Episciences_Review())->setIsNewFrontSwitched('no');

        $processed = $this->processPageTypes($journal);

        self::assertSame([''], array_keys($processed));
    }

    public function testNewSiteGetsTheGroupedList(): void
    {
        $journal = (new Episciences_Review())->setIsNewFrontSwitched('yes');

        $processed = $this->processPageTypes($journal);

        self::assertSame(['About', 'Other'], array_keys($processed));
        self::assertSame(['about'], array_values($processed['About']));
        self::assertSame(['custom'], array_values($processed['Other']));
    }
}
