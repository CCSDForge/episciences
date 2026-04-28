<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Page.
 *
 * Pure-logic: id/code/uid/dates setters/getters, title/content/visibility
 * serialization, pageCode, setOptions.
 *
 * @covers Episciences_Page
 */
class Episciences_PageTest extends TestCase
{
    private Episciences_Page $page;

    protected function setUp(): void
    {
        $this->page = new Episciences_Page();
    }

    // =========================================================================
    // Default values
    // =========================================================================

    public function testDefaultIdIsZero(): void
    {
        self::assertSame(0, $this->page->getId());
    }

    public function testDefaultCodeIsEmpty(): void
    {
        self::assertSame('', $this->page->getCode());
    }

    public function testDefaultUidIsZero(): void
    {
        self::assertSame(0, $this->page->getUid());
    }

    public function testDefaultDateCreationIsEmpty(): void
    {
        self::assertSame('', $this->page->getDateCreation());
    }

    public function testDefaultPageCodeIsEmpty(): void
    {
        self::assertSame('', $this->page->getPageCode());
    }

    // =========================================================================
    // id setter/getter
    // =========================================================================

    public function testSetAndGetId(): void
    {
        $this->page->setId(5);
        self::assertSame(5, $this->page->getId());
    }

    // =========================================================================
    // code setter/getter
    // =========================================================================

    public function testSetAndGetCode(): void
    {
        $this->page->setCode('about');
        self::assertSame('about', $this->page->getCode());
    }

    // =========================================================================
    // uid setter/getter
    // =========================================================================

    public function testSetAndGetUid(): void
    {
        $this->page->setUid(42);
        self::assertSame(42, $this->page->getUid());
    }

    // =========================================================================
    // dateCreation setter/getter
    // =========================================================================

    public function testSetAndGetDateCreation(): void
    {
        $this->page->setDateCreation('2024-01-01');
        self::assertSame('2024-01-01', $this->page->getDateCreation());
    }

    public function testSetDateCreationDefaultIsEmpty(): void
    {
        $this->page->setDateCreation();
        self::assertSame('', $this->page->getDateCreation());
    }

    // =========================================================================
    // dateUpdated setter/getter
    // =========================================================================

    public function testSetAndGetDateUpdated(): void
    {
        $this->page->setDateUpdated('2024-06-15 10:00:00');
        self::assertSame('2024-06-15 10:00:00', $this->page->getDateUpdated());
    }

    // =========================================================================
    // pageCode setter/getter
    // =========================================================================

    public function testSetAndGetPageCode(): void
    {
        $this->page->setPageCode('contact');
        self::assertSame('contact', $this->page->getPageCode());
    }

    // =========================================================================
    // title — string without serialization
    // =========================================================================

    public function testSetTitleStringWithoutSerialization(): void
    {
        $this->page->setTitle('My Page', false);
        self::assertSame('My Page', $this->page->getTitle());
    }

    public function testGetTitleDeserializeWithJsonString(): void
    {
        $json = json_encode(['en' => 'About', 'fr' => 'À propos'], JSON_UNESCAPED_UNICODE);
        $this->page->setTitle($json, false);

        $result = $this->page->getTitle(true);
        self::assertIsArray($result);
        self::assertSame('About', $result['en']);
        self::assertSame('À propos', $result['fr']);
    }

    public function testSetTitleArraySerializesToJson(): void
    {
        $this->page->setTitle(['en' => 'Home', 'fr' => 'Accueil']);
        $raw = $this->page->getTitle(false);
        self::assertIsString($raw);
        $decoded = json_decode($raw, true);
        self::assertSame('Home', $decoded['en']);
        self::assertSame('Accueil', $decoded['fr']);
    }

    public function testGetTitleWithoutDeserializeReturnsStoredValue(): void
    {
        $this->page->setTitle('plain string', false);
        // false = do not deserialize
        self::assertSame('plain string', $this->page->getTitle(false));
    }

    // =========================================================================
    // visibility — string and array
    // =========================================================================

    public function testSetVisibilityString(): void
    {
        $this->page->setVisibility('public', false);
        self::assertSame('public', $this->page->getVisibility());
    }

    public function testSetVisibilityArraySerializesToJson(): void
    {
        $this->page->setVisibility(['en' => 'public', 'fr' => 'public']);
        $raw = $this->page->getVisibility(false);
        self::assertIsString($raw);
        $decoded = json_decode($raw, true);
        self::assertSame('public', $decoded['en']);
    }

    public function testGetVisibilityDeserializesJsonString(): void
    {
        $json = json_encode(['en' => 'private'], JSON_UNESCAPED_UNICODE);
        $this->page->setVisibility($json, false);
        $result = $this->page->getVisibility(true);
        self::assertIsArray($result);
        self::assertSame('private', $result['en']);
    }

    // =========================================================================
    // content — HTML→Markdown conversion
    // =========================================================================

    public function testSetContentStringPassthrough(): void
    {
        // Non-serialized string passthrough
        $this->page->setContent('some text', false);
        self::assertSame('some text', $this->page->getContent(false));
    }

    public function testSetContentArrayConvertsHtmlAndSerializes(): void
    {
        $this->page->setContent(['en' => '<p>Hello</p>']);
        $raw = $this->page->getContent(false);
        // After conversion, stored as JSON; decoded array has 'en' key
        $decoded = json_decode($raw, true);
        self::assertArrayHasKey('en', $decoded);
        // HtmlConverter strips tags or converts to Markdown
        self::assertNotEmpty($decoded['en']);
    }

    // =========================================================================
    // setOptions via constructor
    // =========================================================================

    public function testConstructorWithOptions(): void
    {
        $page = new Episciences_Page([
            'id'        => 10,
            'code'      => 'team',
            'uid'       => 3,
            'page_code' => 'team-page',
        ]);

        self::assertSame(10, $page->getId());
        self::assertSame('team', $page->getCode());
        self::assertSame(3, $page->getUid());
        self::assertSame('team-page', $page->getPageCode());
    }

    public function testConstructorWithEmptyArrayDoesNotThrow(): void
    {
        $page = new Episciences_Page([]);
        self::assertInstanceOf(Episciences_Page::class, $page);
    }
}
