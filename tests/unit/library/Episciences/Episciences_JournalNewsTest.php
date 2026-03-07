<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_JournalNews.
 *
 * Pure-logic: setters/getters, content/link coercion, toArray shape.
 * DB-dependent methods (insert, update, findByLegacyId, deleteByLegacyId) excluded.
 *
 * @covers Episciences_JournalNews
 */
class Episciences_JournalNewsTest extends TestCase
{
    private Episciences_JournalNews $news;

    protected function setUp(): void
    {
        $this->news = new Episciences_JournalNews();
    }

    // =========================================================================
    // id setter/getter
    // =========================================================================

    public function testSetAndGetId(): void
    {
        $this->news->setId(42);
        self::assertSame(42, $this->news->getId());
    }

    public function testSetIdReturnsFluent(): void
    {
        self::assertInstanceOf(Episciences_JournalNews::class, $this->news->setId(1));
    }

    // =========================================================================
    // legacyId setter/getter
    // =========================================================================

    public function testSetAndGetLegacyId(): void
    {
        $this->news->setLegacyId(99);
        self::assertSame(99, $this->news->getLegacyId());
    }

    // =========================================================================
    // code setter/getter
    // =========================================================================

    public function testSetAndGetCode(): void
    {
        $this->news->setCode('epijinfo');
        self::assertSame('epijinfo', $this->news->getCode());
    }

    // =========================================================================
    // uid setter/getter
    // =========================================================================

    public function testSetAndGetUid(): void
    {
        $this->news->setUid(7);
        self::assertSame(7, $this->news->getUid());
    }

    // =========================================================================
    // dateCreation setter/getter
    // =========================================================================

    public function testSetAndGetDateCreation(): void
    {
        $this->news->setDateCreation('2024-01-15 10:00:00');
        self::assertSame('2024-01-15 10:00:00', $this->news->getDateCreation());
    }

    // =========================================================================
    // dateUpdated setter/getter (default is 'CURRENT_TIMESTAMP')
    // =========================================================================

    public function testDefaultDateUpdatedIsCurrentTimestamp(): void
    {
        self::assertSame('CURRENT_TIMESTAMP', $this->news->getDateUpdated());
    }

    public function testSetAndGetDateUpdated(): void
    {
        $this->news->setDateUpdated('2024-06-01 12:00:00');
        self::assertSame('2024-06-01 12:00:00', $this->news->getDateUpdated());
    }

    // =========================================================================
    // title setter/getter
    // =========================================================================

    public function testSetAndGetTitle(): void
    {
        $this->news->setTitle('Breaking News');
        self::assertSame('Breaking News', $this->news->getTitle());
    }

    // =========================================================================
    // content coercion
    // =========================================================================

    public function testDefaultContentCoercesToNull(): void
    {
        // _content is initialized to '' — getContent() coerces '' to null
        self::assertNull($this->news->getContent());
    }

    public function testSetContentWithNonEmptyString(): void
    {
        $this->news->setContent('<p>Hello</p>');
        self::assertSame('<p>Hello</p>', $this->news->getContent());
    }

    public function testSetContentWithEmptyStringCoercesToNull(): void
    {
        $this->news->setContent('');
        self::assertNull($this->news->getContent());
    }

    public function testSetContentWithNullCoercesToNull(): void
    {
        $this->news->setContent(null);
        self::assertNull($this->news->getContent());
    }

    public function testSetContentReturnsFluent(): void
    {
        self::assertInstanceOf(Episciences_JournalNews::class, $this->news->setContent('test'));
    }

    // =========================================================================
    // link coercion
    // =========================================================================

    public function testDefaultLinkCoercesToNull(): void
    {
        // _link is initialized to '' — getLink() coerces '' to null
        self::assertNull($this->news->getLink());
    }

    public function testSetLinkWithNonEmptyString(): void
    {
        $this->news->setLink('https://example.org');
        self::assertSame('https://example.org', $this->news->getLink());
    }

    public function testSetLinkWithEmptyStringCoercesToNull(): void
    {
        $this->news->setLink('');
        self::assertNull($this->news->getLink());
    }

    public function testSetLinkWithNullCoercesToNull(): void
    {
        $this->news->setLink(null);
        self::assertNull($this->news->getLink());
    }

    // =========================================================================
    // visibility setter/getter
    // =========================================================================

    public function testSetAndGetVisibility(): void
    {
        $this->news->setVisibility('public');
        self::assertSame('public', $this->news->getVisibility());
    }

    // =========================================================================
    // toArray
    // =========================================================================

    public function testToArrayContainsTenKeys(): void
    {
        $array = $this->buildFullNews()->toArray();
        self::assertCount(10, $array);
    }

    public function testToArrayHasExpectedKeys(): void
    {
        $array = $this->buildFullNews()->toArray();
        self::assertArrayHasKey('id', $array);
        self::assertArrayHasKey('legacyId', $array);
        self::assertArrayHasKey('code', $array);
        self::assertArrayHasKey('uid', $array);
        self::assertArrayHasKey('dateCreation', $array);
        self::assertArrayHasKey('dateUpdated', $array);
        self::assertArrayHasKey('title', $array);
        self::assertArrayHasKey('content', $array);
        self::assertArrayHasKey('link', $array);
        self::assertArrayHasKey('visiblity', $array); // Note: typo in source ('visiblity')
    }

    public function testToArrayReflectsSetValues(): void
    {
        $news = $this->buildFullNews();
        $array = $news->toArray();

        self::assertSame(5, $array['id']);
        self::assertSame('epijinfo', $array['code']);
        self::assertSame('News Title', $array['title']);
        self::assertSame('<p>Body</p>', $array['content']);
        self::assertSame('https://example.org', $array['link']);
        self::assertSame('public', $array['visiblity']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function buildFullNews(): Episciences_JournalNews
    {
        // All typed properties without defaults must be initialized before toArray()
        $news = new Episciences_JournalNews();
        $news->setId(5);
        $news->setLegacyId(1);
        $news->setCode('epijinfo');
        $news->setUid(3);
        $news->setDateCreation('2024-01-01 00:00:00');
        $news->setTitle('News Title');
        $news->setContent('<p>Body</p>');
        $news->setLink('https://example.org');
        $news->setVisibility('public');
        return $news;
    }

    // =========================================================================
    // Constructor with options
    // =========================================================================

    public function testConstructorWithNullOptionsDoesNotThrow(): void
    {
        $news = new Episciences_JournalNews(null);
        self::assertInstanceOf(Episciences_JournalNews::class, $news);
    }

    public function testConstructorWithEmptyArrayDoesNotThrow(): void
    {
        $news = new Episciences_JournalNews([]);
        self::assertInstanceOf(Episciences_JournalNews::class, $news);
    }
}
