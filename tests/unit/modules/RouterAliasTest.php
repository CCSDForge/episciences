<?php

declare(strict_types=1);

namespace unit\modules;

use PHPUnit\Framework\TestCase;
use Zend_Controller_Front;
use Zend_Controller_Router_Rewrite;

/**
 * Routing checks for the article URLs declared in application/configs/application.ini.
 *
 * Covers both the historical routes (non-regression) and the /articles/... aliases
 * added for the CLOCKSS transition.
 */
final class RouterAliasTest extends TestCase
{
    private const DOCID = '18732';

    /**
     * The historical routes must keep matching exactly what they used to match.
     */
    public function testLegacyPaperRoutesAreUnchanged(): void
    {
        self::assertSame(
            ['id' => self::DOCID, 'controller' => 'paper', 'action' => 'view'],
            $this->match('paper', self::DOCID)
        );

        self::assertSame(
            ['id' => self::DOCID, 'controller' => 'paper', 'action' => 'pdf'],
            $this->match('pdf', self::DOCID . '/pdf')
        );
    }

    /**
     * Regex routes are anchored (#^...$#i), so an aliased path can never be swallowed
     * by the legacy "(\d+)" route.
     */
    public function testLegacyPaperRouteDoesNotMatchAliases(): void
    {
        $this->assertNoMatch('paper', 'articles/' . self::DOCID);
        $this->assertNoMatch('paper', 'en/articles/' . self::DOCID);
        $this->assertNoMatch('pdf', 'articles/' . self::DOCID . '/download');
    }

    public function testArticlesAliasMatchesPaperView(): void
    {
        self::assertSame(
            ['id' => self::DOCID, 'controller' => 'paper', 'action' => 'view'],
            $this->match('articles', 'articles/' . self::DOCID)
        );
    }

    public function testArticlesDownloadAliasMatchesPaperPdf(): void
    {
        self::assertSame(
            ['id' => self::DOCID, 'controller' => 'paper', 'action' => 'pdf'],
            $this->match('articles_download', 'articles/' . self::DOCID . '/download')
        );
    }

    /**
     * The language prefix is mapped to the "lang" parameter, which
     * Episciences_Translation_Plugin reads through $request->getParam('lang').
     *
     * @dataProvider languagePrefixProvider
     */
    public function testLanguagePrefixedAliases(string $lang): void
    {
        self::assertSame(
            ['lang' => $lang, 'id' => self::DOCID, 'controller' => 'paper', 'action' => 'view'],
            $this->match('articles_lang', $lang . '/articles/' . self::DOCID)
        );

        self::assertSame(
            ['lang' => $lang, 'id' => self::DOCID, 'controller' => 'paper', 'action' => 'pdf'],
            $this->match('articles_lang_download', $lang . '/articles/' . self::DOCID . '/download')
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function languagePrefixProvider(): array
    {
        return [
            'english' => ['en'],
            'french' => ['fr'],
            'spanish' => ['es'],
        ];
    }

    public function testUnsupportedPathsAreRejected(): void
    {
        // no document id
        $this->assertNoMatch('articles', 'articles');
        $this->assertNoMatch('articles', 'articles/');
        // unsupported language prefix
        $this->assertNoMatch('articles_lang', 'de/articles/' . self::DOCID);
        $this->assertNoMatch('articles_lang_download', 'de/articles/' . self::DOCID . '/download');
        // a language prefix is not accepted on the legacy routes
        $this->assertNoMatch('paper', 'en/' . self::DOCID);
        // non-numeric document id
        $this->assertNoMatch('articles', 'articles/abc');
        // trailing segment
        $this->assertNoMatch('articles', 'articles/' . self::DOCID . '/download');
    }

    /**
     * assemble() is what the url() view helper calls when no route name is given:
     * it uses the currently matched route, so every alias needs a usable reverse.
     */
    public function testAliasesCanBeAssembled(): void
    {
        $params = ['controller' => 'paper', 'action' => 'view', 'id' => self::DOCID];

        self::assertSame('articles/' . self::DOCID, $this->assemble('articles', $params));
        self::assertSame(
            'articles/' . self::DOCID . '/download',
            $this->assemble('articles_download', ['id' => self::DOCID])
        );
        self::assertSame(
            'en/articles/' . self::DOCID,
            $this->assemble('articles_lang', $params + ['lang' => 'en'])
        );
        self::assertSame(
            'fr/articles/' . self::DOCID . '/download',
            $this->assemble('articles_lang_download', ['lang' => 'fr', 'id' => self::DOCID])
        );
    }

    /**
     * @return array<string, string>
     */
    private function match(string $routeName, string $path): array
    {
        $values = $this->router()->getRoute($routeName)->match($path);

        if (!is_array($values)) {
            self::fail(sprintf('Route "%s" was expected to match "%s"', $routeName, $path));
        }

        return $values;
    }

    private function assertNoMatch(string $routeName, string $path): void
    {
        self::assertFalse(
            $this->router()->getRoute($routeName)->match($path),
            sprintf('Route "%s" must not match "%s"', $routeName, $path)
        );
    }

    /**
     * @param array<string, string> $params
     */
    private function assemble(string $routeName, array $params): string
    {
        return (string)$this->router()->getRoute($routeName)->assemble($params);
    }

    private function router(): Zend_Controller_Router_Rewrite
    {
        $router = Zend_Controller_Front::getInstance()->getRouter();

        if (!$router instanceof Zend_Controller_Router_Rewrite) {
            self::fail('The front controller router is not a Zend_Controller_Router_Rewrite');
        }

        return $router;
    }
}
