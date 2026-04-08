<?php

declare(strict_types=1);

namespace unit\library\Episciences\notify;

use Episciences\Notify\PreprintUrlParser;
use PHPUnit\Framework\TestCase;

class PreprintUrlParserTest extends TestCase
{
    private PreprintUrlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new PreprintUrlParser();
    }

    // -------------------------------------------------------------------------
    // parseUrl — normal cases
    // -------------------------------------------------------------------------

    public function testParseUrlWithVersionExtractsIdentifierAndVersion(): void
    {
        $result = $this->parser->parseUrl('https://hal.science/hal-03697346v3');

        self::assertSame('hal-03697346', $result['identifier']);
        self::assertSame(3, $result['version']);
    }

    public function testParseUrlVersion1ExtractsCorrectly(): void
    {
        $result = $this->parser->parseUrl('https://hal.science/hal-03697346v1');

        self::assertSame('hal-03697346', $result['identifier']);
        self::assertSame(1, $result['version']);
    }

    public function testParseUrlWithHighVersionNumber(): void
    {
        $result = $this->parser->parseUrl('https://hal.science/hal-03697346v12');

        self::assertSame('hal-03697346', $result['identifier']);
        self::assertSame(12, $result['version']);
    }

    // -------------------------------------------------------------------------
    // parseUrl — missing or empty version
    // -------------------------------------------------------------------------

    public function testParseUrlWithoutVersionDefaultsToOne(): void
    {
        $result = $this->parser->parseUrl('https://hal.science/hal-03697346');

        self::assertSame('hal-03697346', $result['identifier']);
        self::assertSame(1, $result['version']);
    }

    public function testParseUrlWithVersionMarkerButNoNumberDefaultsToOne(): void
    {
        // 'v' present but no digit after it
        $result = $this->parser->parseUrl('https://hal.science/hal-03697346v');

        self::assertSame('hal-03697346', $result['identifier']);
        self::assertSame(1, $result['version']);
    }

    // -------------------------------------------------------------------------
    // parseUrl — edge cases
    // -------------------------------------------------------------------------

    public function testParseEmptyUrlReturnsDefaults(): void
    {
        $result = $this->parser->parseUrl('');

        self::assertSame('', $result['identifier']);
        self::assertSame(1, $result['version']);
    }

    public function testParseUrlReturnsArray(): void
    {
        self::assertIsArray($this->parser->parseUrl('https://hal.science/hal-03697346v3'));
        self::assertIsArray($this->parser->parseUrl(''));
    }

    public function testParseUrlWithPathSlashesStripped(): void
    {
        // Slashes in the path are stripped to build the identifier
        $result = $this->parser->parseUrl('https://hal.science/hal-03697346v2');

        self::assertStringNotContainsString('/', $result['identifier']);
    }

    // -------------------------------------------------------------------------
    // extractRvCode — normal cases
    // -------------------------------------------------------------------------

    public function testExtractRvCodeFromStandardJournalUrl(): void
    {
        $code = $this->parser->extractRvCode('https://revue-test.episciences.org', 'episciences.org');

        self::assertSame('revue-test', $code);
    }

    public function testExtractRvCodeFromJournalUrlWithPath(): void
    {
        $code = $this->parser->extractRvCode('https://revue-test.episciences.org/paper/view?id=123', 'episciences.org');

        self::assertSame('revue-test', $code);
    }

    public function testExtractRvCodeWithHyphenatedJournalCode(): void
    {
        $code = $this->parser->extractRvCode('https://my-journal-name.episciences.org', 'episciences.org');

        self::assertSame('my-journal-name', $code);
    }

    // -------------------------------------------------------------------------
    // extractRvCode — edge cases
    // -------------------------------------------------------------------------

    public function testExtractRvCodeFromEmptyUrlReturnsEmpty(): void
    {
        $code = $this->parser->extractRvCode('', 'episciences.org');

        self::assertSame('', $code);
    }

    public function testExtractRvCodeFromDifferentDomainReturnsEmpty(): void
    {
        $code = $this->parser->extractRvCode('https://revue-test.other-platform.org', 'episciences.org');

        self::assertSame('', $code);
    }

    public function testExtractRvCodeFromRootDomainReturnsEmpty(): void
    {
        // URL is the domain itself (no subdomain)
        $code = $this->parser->extractRvCode('https://episciences.org', 'episciences.org');

        self::assertSame('', $code);
    }

    public function testExtractRvCodeFromInvalidUrlReturnsEmpty(): void
    {
        $code = $this->parser->extractRvCode('not-a-url', 'episciences.org');

        self::assertSame('', $code);
    }

    public function testExtractRvCodePreservesPreprodDomain(): void
    {
        // The domain suffix must match exactly
        $code = $this->parser->extractRvCode('https://revue-test.episciences.org', 'episciences.org');

        self::assertSame('revue-test', $code);
    }
}
