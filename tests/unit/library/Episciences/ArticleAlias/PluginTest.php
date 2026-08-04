<?php

declare(strict_types=1);

namespace unit\library\Episciences\ArticleAlias;

use Episciences_ArticleAlias_Plugin;
use PHPUnit\Framework\TestCase;
use Zend_Controller_Front;
use Zend_Controller_Request_Http;
use Zend_Controller_Router_Rewrite;

/**
 * The /articles/... aliases are addressed by paper id, while PaperController expects a
 * docid: these tests cover the translation done by Episciences_ArticleAlias_Plugin.
 *
 * The database lookup is stubbed out, so only the routing side is exercised here.
 */
final class PluginTest extends TestCase
{
    private const PAPER_ID = '18700';
    private const PUBLISHED_DOC_ID = 18732;

    /**
     * @dataProvider aliasedPathProvider
     */
    public function testPaperIdIsReplacedByThePublishedDocId(string $path): void
    {
        $request = $this->route($path);

        $this->plugin()->routeShutdown($request);

        self::assertSame(self::PUBLISHED_DOC_ID, $request->getParam('id'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function aliasedPathProvider(): array
    {
        return [
            'view' => ['/articles/' . self::PAPER_ID],
            'download' => ['/articles/' . self::PAPER_ID . '/download'],
            'view, language prefix' => ['/en/articles/' . self::PAPER_ID],
            'download, language prefix' => ['/fr/articles/' . self::PAPER_ID . '/download'],
        ];
    }

    /**
     * The legacy URLs address a given version and must keep serving it.
     *
     * @dataProvider legacyPathProvider
     */
    public function testLegacyRoutesAreLeftUntouched(string $path): void
    {
        $request = $this->route($path);

        $this->plugin()->routeShutdown($request);

        self::assertSame(self::PAPER_ID, $request->getParam('id'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function legacyPathProvider(): array
    {
        return [
            'view' => ['/' . self::PAPER_ID],
            'pdf' => ['/' . self::PAPER_ID . '/pdf'],
            'csl' => ['/' . self::PAPER_ID . '/csl'],
        ];
    }

    /**
     * An id that is not the paper id of a published paper is a docid, or does not exist at
     * all: either way PaperController is left to deal with it.
     */
    public function testUnresolvedIdIsLeftUntouched(): void
    {
        $request = $this->route('/articles/' . self::PAPER_ID);

        $this->plugin(0)->routeShutdown($request);

        self::assertSame(self::PAPER_ID, $request->getParam('id'));
    }

    /**
     * @return Zend_Controller_Request_Http the routed request, as the front controller
     *                                      hands it over to the plugin
     */
    private function route(string $path): Zend_Controller_Request_Http
    {
        $request = new Zend_Controller_Request_Http('http://localhost' . $path);
        $router = Zend_Controller_Front::getInstance()->getRouter();

        if (!$router instanceof Zend_Controller_Router_Rewrite) {
            self::fail('The front controller router is not a Zend_Controller_Router_Rewrite');
        }

        $router->route($request);

        return $request;
    }

    private function plugin(int $publishedDocId = self::PUBLISHED_DOC_ID): Episciences_ArticleAlias_Plugin
    {
        return new class ($publishedDocId) extends Episciences_ArticleAlias_Plugin {
            public function __construct(private readonly int $publishedDocId)
            {
            }

            protected function getPublishedDocId(int $paperId): int
            {
                return $this->publishedDocId;
            }
        };
    }
}