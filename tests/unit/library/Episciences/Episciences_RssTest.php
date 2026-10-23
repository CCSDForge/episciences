<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences_Rss;
use Laminas\Feed\Writer\Feed;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
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

            return [[
                'TITLE' => 'title_1',
                'CONTENT' => 'content_1',
                'DATE_POST' => '2026-10-01 10:00:00',
            ]];
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

        $translator = new Zend_Translate('array', ['title_1' => 'Online news', 'content_1' => 'News content'], 'en');
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
