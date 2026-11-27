<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use PHPUnit\Framework\TestCase;
use Zend_View;

/**
 * The /rss page lists the feeds of the journal: papers (RSS and Atom) and news.
 */
final class RssIndexViewTest extends TestCase
{
    private mixed $previousTranslate = null;

    private bool $defaultRouteAdded = false;

    protected function setUp(): void
    {
        $this->previousTranslate = \Zend_Registry::isRegistered('Zend_Translate') ? \Zend_Registry::get('Zend_Translate') : null;
        \Zend_Registry::set('Zend_Translate', new \Zend_Translate(['adapter' => 'array', 'content' => ['x' => 'x'], 'locale' => 'fr']));

        // The feed links are built with the url() helper, which needs the default route
        $router = \Zend_Controller_Front::getInstance()->getRouter();
        if (!$router->hasRoute('default')) {
            $router->addDefaultRoutes();
            $this->defaultRouteAdded = true;
        }
    }

    protected function tearDown(): void
    {
        if ($this->defaultRouteAdded) {
            \Zend_Controller_Front::getInstance()->getRouter()->removeRoute('default');
        }
        if ($this->previousTranslate !== null) {
            \Zend_Registry::set('Zend_Translate', $this->previousTranslate);
        } else {
            \Zend_Registry::getInstance()->offsetUnset('Zend_Translate');
        }
    }

    public function testPageLinksToTheFeedsOfTheJournal(): void
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');

        $html = $view->render('rss/index.phtml');

        self::assertStringContainsString('href="/rss/papers"', $html);
        self::assertStringContainsString('href="/rss/atom"', $html);
        self::assertStringContainsString('href="/rss/news"', $html);
        // The icons are decorative: the link text names the feed
        self::assertSame(3, substr_count($html, '<i class="fas fa-rss" aria-hidden="true"></i>'));
    }
}
