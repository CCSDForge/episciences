<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use PHPUnit\Framework\TestCase;

/**
 * Titles, abstracts and author names shown on the public listing pages come from
 * repository records indexed in Solr: they must be escaped before reaching the page
 * (source-pattern analysis).
 */
final class PublicViewsOutputEscapingTest extends TestCase
{
    private function source(string $relativePath): string
    {
        return (string) file_get_contents(
            APPLICATION_PATH . '/modules/journal/views/scripts/' . $relativePath
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function authorListViewProvider(): array
    {
        return [
            'section' => ['section/view.phtml'],
            'volume' => ['volume/volume-indexed-papers.phtml'],
            'search results' => ['partials/search_results.phtml'],
            'latest publications' => ['browse/latest.phtml'],
        ];
    }

    /**
     * @dataProvider authorListViewProvider
     */
    public function testAuthorLinksAreEscaped(string $view): void
    {
        $source = $this->source($view);

        self::assertStringContainsString(
            'sprintf($outputFormat, $this->escape($url), $this->escape($authorName))',
            $source
        );
        self::assertStringNotContainsString('sprintf($outputFormat, $url, $authorName)', $source);
    }

    public function testSectionPageEscapesTitleAndAbstract(): void
    {
        $source = $this->source('section/view.phtml');

        self::assertStringContainsString(
            '<?php echo htmlspecialchars(Episciences_Tools::decodeLatex($title)) ?>',
            $source
        );
        self::assertStringContainsString('Ccsd_Tools_String::truncate(htmlspecialchars($abstract)', $source);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function volumeSectionViewProvider(): array
    {
        return [
            'search results' => ['partials/search_results.phtml'],
            'latest publications' => ['browse/latest.phtml'],
        ];
    }

    /**
     * @dataProvider volumeSectionViewProvider
     */
    public function testVolumeAndSectionNamesAreEscaped(string $view): void
    {
        $source = $this->source($view);

        self::assertStringNotContainsString('<?php echo $volume; ?>', $source);
        self::assertStringNotContainsString('<?php echo $section; ?>', $source);
        self::assertStringContainsString('<?php echo $this->escape($volume); ?>', $source);
        self::assertStringContainsString('<?php echo $this->escape($section); ?>', $source);
    }
}
