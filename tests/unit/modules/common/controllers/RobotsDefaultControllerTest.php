<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use RobotsController;
use RobotsDefaultController;
use unit\modules\ControllerTestCase;
use unit\modules\support\DispatchResult;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;

/**
 * robots.txt of the journals (RobotsDefaultController) and of the portal.
 */
final class RobotsDefaultControllerTest extends ControllerTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::loadController('common', 'RobotsDefaultController');
    }

    public function testRobotsAreKeptOutsideProduction(): void
    {
        $result = $this->dispatch(RobotsDefaultController::class, 'index', $this->getRequest());

        self::assertSame("# Episciences robots.txt\nUser-agent: *\nDisallow: /\n", $result->body());
        self::assertTextPlain($result);
        self::assertSame([], $this->db->queries(), 'the journal is not looked up outside production');
    }

    public function testProductionJournalIsOpenToRobotsExceptItsPrivatePaths(): void
    {
        $this->db->respondTo('/is_new_front_switched/', [['is_new_front_switched' => 'no']]);

        $result = $this->dispatch($this->productionController(), 'index', $this->getRequest());
        $lines = explode("\n", $result->body());

        self::assertSame(['# Episciences robots.txt', 'User-agent: *'], array_slice($lines, 0, 2));
        self::assertContains('Disallow: /user', $lines);
        self::assertContains('Disallow: */json', $lines);
        self::assertSame(
            ['User-agent: barkrowler', 'Disallow: /'],
            array_slice($lines, array_search('User-agent: barkrowler', $lines, true), 2),
            'the whole site is only closed to barkrowler'
        );
        self::assertSame(1, substr_count($result->body(), "Disallow: /\n"));
        self::assertContains('Sitemap: ' . rtrim(APPLICATION_URL, '/') . '/sitemap', $lines);
        self::assertTextPlain($result);
    }

    public function testProductionJournalServedByTheNewFrontIsClosedToRobots(): void
    {
        $this->db->respondTo('/is_new_front_switched/', [['is_new_front_switched' => 'yes']]);

        $result = $this->dispatch($this->productionController(), 'index', $this->getRequest());

        self::assertSame("# Episciences robots.txt\nUser-agent: *\nDisallow: /\n", $result->body());
    }

    /**
     * The portal controller has the same class name as the journal one: it is loaded in its own process.
     */
    #[RunInSeparateProcess]
    public function testPortalIsClosedToRobotsEvenInProduction(): void
    {
        self::loadController('portal', 'RobotsController');
        $class = get_class(new class (new Zend_Controller_Request_HttpTestCase(), new Zend_Controller_Response_HttpTestCase()) extends RobotsController {
            protected function isProductionEnvironment(): bool
            {
                return true;
            }
        });

        $result = $this->dispatch($class, 'index', $this->getRequest());

        self::assertSame("# Episciences robots.txt\nUser-agent: *\nDisallow: /\n", $result->body());
        self::assertTextPlain($result);
    }

    /**
     * @return class-string<RobotsDefaultController>
     */
    private function productionController(): string
    {
        return get_class(new class (new Zend_Controller_Request_HttpTestCase(), new Zend_Controller_Response_HttpTestCase()) extends RobotsDefaultController {
            protected function isProductionEnvironment(): bool
            {
                return true;
            }
        });
    }

    private static function assertTextPlain(DispatchResult $result): void
    {
        self::assertSame('text/plain; charset=utf-8', $result->header('Content-Type'));
    }
}
