<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use DOMDocument;
use DOMXPath;
use Episciences_Rss;
use Episciences_Website_Navigation_Page_Rss;
use Laminas\Feed\Writer\Feed;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Select;
use Zend_Db_Table_Abstract;
use Zend_Registry;
use Zend_Translate;

/**
 * News feed of a journal.
 *
 * The database adapter is a partial mock: the news query is built for real and captured,
 * nothing is executed.
 *
 * @covers Episciences_Rss
 */
final class Episciences_RssTest extends TestCase
{
    private Zend_Db_Adapter_Abstract $previousAdapter;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $adapter;

    /** @var list<string> SQL of the news queries sent to the adapter */
    private array $newsQueries = [];

    /** @var list<array<string, mixed>> news rows returned by the adapter */
    private array $newsRows = [[
        'NEWSID' => 1,
        'TITLE' => 'title_1',
        'CONTENT' => 'content_1',
        'DATE_POST' => '2026-10-01 10:00:00',
    ]];

    private bool $languagesRegistered = false;

    protected function setUp(): void
    {
        // Read by the Episciences_News constructor
        if (!Zend_Registry::isRegistered('languages')) {
            Zend_Registry::set('languages', ['en', 'fr']);
            $this->languagesRegistered = true;
        }

        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();

        $this->adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchAll'])
            ->getMock();
        Zend_Db_Table_Abstract::setDefaultAdapter($this->adapter);

        $this->adapter->method('fetchAll')->willReturnCallback(function ($select) {
            $this->newsQueries[] = $select instanceof Zend_Db_Select ? $select->assemble() : (string)$select;

            return $this->newsRows;
        });
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);

        if ($this->languagesRegistered) {
            Zend_Registry::getInstance()->offsetUnset('languages');
        }
    }

    /**
     * The constructor looks the journal up and renders the feed at once: build the object
     * without it and give it what listNews() needs.
     */
    private function newsFeed(): Episciences_Rss
    {
        $reflection = new ReflectionClass(Episciences_Rss::class);
        /** @var Episciences_Rss $rss */
        $rss = $reflection->newInstanceWithoutConstructor();

        $translator = new Zend_Translate('array', [
            'title_1' => 'Online news',
            'content_1' => 'News content',
            'title_2' => 'Second news',
            'content_2' => "Line <b>one</b> & \"quoted\"\nLine two",
        ], 'en');
        $reflection->getProperty('_translator')->setValue($rss, $translator);

        $feed = new Feed();
        $feed->setTitle('Journal - News');
        $feed->setLink('https://journal.example.org');
        $feed->setDescription('News');
        $feed->setDateModified(time());
        $rss->setFeed($feed);

        return $rss;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function renderNews(array $settings): string
    {
        ob_start();
        try {
            $this->newsFeed()->listNews($settings);
        } finally {
            $output = (string)ob_get_clean();
        }

        return $output;
    }

    public function testNewsFeedOnlyListsTheOnlineNewsOfTheJournal(): void
    {
        $output = $this->renderNews(['max' => '10']);

        self::assertCount(1, $this->newsQueries);
        self::assertStringContainsString('ONLINE = 1', $this->newsQueries[0]);
        self::assertMatchesRegularExpression('/\bRVID = ' . RVID . '\b/', $this->newsQueries[0]);
        self::assertMatchesRegularExpression('/LIMIT 10\b/', $this->newsQueries[0]);
        self::assertStringContainsString('Online news', $output);
    }

    public function testNewsFeedSizeIsBounded(): void
    {
        $this->renderNews(['max' => '100000']);

        self::assertMatchesRegularExpression('/LIMIT ' . Episciences_Rss::MAX_ENTRIES . '\b/', $this->newsQueries[0]);
    }

    private static function xpath(string $output): DOMXPath
    {
        $dom = new DOMDocument();
        self::assertTrue($dom->loadXML($output));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('content', 'http://purl.org/rss/1.0/modules/content/');

        return $xpath;
    }

    public function testEachNewsHasItsOwnLinkAndGuid(): void
    {
        $this->newsRows[] = [
            'NEWSID' => 2,
            'TITLE' => 'title_2',
            'CONTENT' => 'content_2',
            'DATE_POST' => '2026-10-02 10:00:00',
        ];

        $xpath = self::xpath($this->renderNews([]));

        $guids = [];
        foreach ($xpath->query('/rss/channel/item') ?: [] as $item) {
            $guids[] = $xpath->evaluate('string(guid)', $item);
            self::assertSame($xpath->evaluate('string(link)', $item), $xpath->evaluate('string(guid)', $item));
        }

        self::assertSame([APPLICATION_URL . '/news/#news-1', APPLICATION_URL . '/news/#news-2'], $guids);
    }

    public function testNewsContentIsRenderedAsPlainTextLikeOnTheNewsPage(): void
    {
        $this->newsRows = [[
            'NEWSID' => 2,
            'TITLE' => 'title_2',
            'CONTENT' => 'content_2',
            'DATE_POST' => '2026-10-02 10:00:00',
        ]];

        $xpath = self::xpath($this->renderNews([]));

        $expected = "Line &lt;b&gt;one&lt;/b&gt; &amp; &quot;quoted&quot;<br />\nLine two";
        self::assertSame($expected, $xpath->evaluate('string(/rss/channel/item/description)'));
        self::assertSame($expected, $xpath->evaluate('string(/rss/channel/item/content:encoded)'));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function channelProvider(): array
    {
        return [
            'papers' => ['papers', '/rss/papers', 'Derniers articles'],
            'news' => ['news', '/rss/news', 'Dernières actualités'],
        ];
    }

    /**
     * @dataProvider channelProvider
     */
    public function testChannelDescribesTheFeedOfTheAction(string $action, string $feedLink, string $description): void
    {
        $rss = $this->newsFeed();
        $feed = (new ReflectionMethod(Episciences_Rss::class, 'createFeed'))->invoke($rss, 'Journal', $action);

        self::assertSame(['rss' => APPLICATION_URL . $feedLink], $feed->getFeedLinks());
        self::assertSame($description, $feed->getDescription());
    }

    /**
     * @return array<string, array{mixed, ?int}>
     */
    public static function parseMaxEntriesProvider(): array
    {
        return [
            'null' => [null, null],
            'numeric string' => ['15', 15],
            'above the bound' => ['5000', Episciences_Rss::MAX_ENTRIES],
            // is_numeric() but invalid: the journal setting applies
            'zero' => ['0', null],
            'negative' => ['-3', null],
            'decimal' => ['1.5', null],
            'exponent' => ['1e9', null],
            'array' => [['10'], null],
        ];
    }

    /**
     * @dataProvider parseMaxEntriesProvider
     */
    public function testParseMaxEntries(mixed $max, ?int $expected): void
    {
        self::assertSame($expected, Episciences_Rss::parseMaxEntries($max));
    }

    public function testInvalidJournalSettingFallsBackToTheFeedDefault(): void
    {
        $page = new Episciences_Website_Navigation_Page_Rss();
        $page->setNbResults('none');

        self::assertSame(Episciences_Rss::DEFAULT_MAX_ENTRIES, $page->getNbResults());
    }

    /**
     * @return array<string, array{array<string, mixed>, int}>
     */
    public static function maxEntriesProvider(): array
    {
        return [
            'missing' => [[], Episciences_Rss::DEFAULT_MAX_ENTRIES],
            'null (no feed page settings)' => [['max' => null], Episciences_Rss::DEFAULT_MAX_ENTRIES],
            'numeric string' => [['max' => '15'], 15],
            'integer' => [['max' => 25], 25],
            'above the bound' => [['max' => '5000'], Episciences_Rss::MAX_ENTRIES],
            'zero' => [['max' => '0'], Episciences_Rss::DEFAULT_MAX_ENTRIES],
            'negative' => [['max' => '-3'], Episciences_Rss::DEFAULT_MAX_ENTRIES],
            'decimal' => [['max' => '1.5'], Episciences_Rss::DEFAULT_MAX_ENTRIES],
            'exponent' => [['max' => '1e9'], Episciences_Rss::DEFAULT_MAX_ENTRIES],
            'not a number' => [['max' => 'all'], Episciences_Rss::DEFAULT_MAX_ENTRIES],
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @dataProvider maxEntriesProvider
     */
    public function testMaxEntries(array $settings, int $expected): void
    {
        self::assertSame($expected, Episciences_Rss::getMaxEntries($settings));
    }
}
