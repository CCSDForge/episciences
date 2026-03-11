<?php

namespace unit\library\Episciences\Repositories;

use Episciences_Repositories_Common;
use Episciences_Repositories_Dspace_Hooks;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for Episciences_Repositories_Dspace_Hooks.
 *
 * All tests are DB-free: only pure static methods tested via direct calls or ReflectionMethod.
 *
 * @covers Episciences_Repositories_Dspace_Hooks
 */
final class Episciences_Repositories_Dspace_HooksTest extends TestCase
{
    // =========================================================================
    // hookCleanIdentifiers()
    // =========================================================================

    /**
     * When the identifier contains the HDL URL prefix, the prefix is stripped
     * and the bare handle is returned under META_IDENTIFIER.
     */
    public function testHookCleanIdentifiersRemovesHdlUrl(): void
    {
        $result = Episciences_Repositories_Dspace_Hooks::hookCleanIdentifiers(
            ['id' => 'https://hdl.handle.net/1822/79894']
        );

        self::assertSame(
            [Episciences_Repositories_Common::META_IDENTIFIER => '1822/79894'],
            $result
        );
    }

    /**
     * When the identifier does not contain the HDL URL prefix, the raw value
     * is returned unchanged under META_IDENTIFIER.
     */
    public function testHookCleanIdentifiersRawId(): void
    {
        $result = Episciences_Repositories_Dspace_Hooks::hookCleanIdentifiers(
            ['id' => '1822/79894']
        );

        self::assertSame(
            [Episciences_Repositories_Common::META_IDENTIFIER => '1822/79894'],
            $result
        );
    }

    // =========================================================================
    // hookIsRequiredVersion()
    // =========================================================================

    /**
     * hookIsRequiredVersion() must return ['result' => false].
     */
    public function testHookIsRequiredVersionReturnsFalse(): void
    {
        $result = Episciences_Repositories_Dspace_Hooks::hookIsRequiredVersion();

        self::assertSame(['result' => false], $result);
    }

    // =========================================================================
    // hookIsIdentifierCommonToAllVersions()
    // =========================================================================

    /**
     * hookIsIdentifierCommonToAllVersions() must return ['result' => false].
     */
    public function testHookIsIdentifierCommonToAllVersions(): void
    {
        $result = Episciences_Repositories_Dspace_Hooks::hookIsIdentifierCommonToAllVersions();

        self::assertSame(['result' => false], $result);
    }

    // =========================================================================
    // hookVersion()
    // =========================================================================

    /**
     * When no META_IDENTIFIER key is present in params, hookVersion() returns [].
     */
    public function testHookVersionNoIdentifier(): void
    {
        $result = Episciences_Repositories_Dspace_Hooks::hookVersion([]);

        self::assertSame([], $result);
    }

    /**
     * When the identifier does not contain a dotted version segment (e.g. '1822/79894'),
     * getVersionFromIdentifier() finds no match and falls back to '1'.
     */
    public function testHookVersionWithIdentifierNoVersion(): void
    {
        $result = Episciences_Repositories_Dspace_Hooks::hookVersion(
            [Episciences_Repositories_Common::META_IDENTIFIER => '1822/79894']
        );

        self::assertSame(['version' => '1'], $result);
    }

    /**
     * When the identifier contains a dotted version segment (e.g. '1822/79894.3'),
     * getVersionFromIdentifier() extracts the part after the dot ('3').
     */
    public function testHookVersionWithIdentifierDottedVersion(): void
    {
        $result = Episciences_Repositories_Dspace_Hooks::hookVersion(
            [Episciences_Repositories_Common::META_IDENTIFIER => '1822/79894.3']
        );

        self::assertSame(['version' => '3'], $result);
    }

    // =========================================================================
    // firstXPathValue() — private static, tested via ReflectionMethod
    // =========================================================================

    /**
     * firstXPathValue() returns the string value of the first matching node.
     */
    public function testFirstXPathValueMatch(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dspace_Hooks::class, 'firstXPathValue');
        $method->setAccessible(true);

        $xml = simplexml_load_string(
            '<?xml version="1.0"?><root><child>hello</child></root>'
        );

        $result = $method->invoke(null, $xml, '//child');

        self::assertSame('hello', $result);
    }

    /**
     * firstXPathValue() returns an empty string when xpath finds no match.
     */
    public function testFirstXPathValueNoMatch(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dspace_Hooks::class, 'firstXPathValue');
        $method->setAccessible(true);

        $xml = simplexml_load_string('<?xml version="1.0"?><root></root>');

        $result = $method->invoke(null, $xml, '//nonexistent');

        self::assertSame('', $result);
    }

    // =========================================================================
    // extractLanguage() — private static, tested via ReflectionMethod
    // =========================================================================

    /**
     * extractLanguage() returns a known 2-letter language code when a valid
     * dc:language element is present.
     */
    public function testExtractLanguage2Letter(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dspace_Hooks::class, 'extractLanguage');
        $method->setAccessible(true);

        $xml = simplexml_load_string(
            '<?xml version="1.0"?>'
            . '<root xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<dc:language>en</dc:language>'
            . '</root>'
        );
        $xml->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');

        $result = $method->invoke(null, $xml);

        self::assertSame('en', $result);
    }

    /**
     * extractLanguage() falls back to 'en' when the language name is unknown
     * (convertTo2LetterCode() returns null for unrecognised language names longer than 3 chars,
     * which triggers Translations::findLanguageCodeByLanguageName() returning null).
     */
    public function testExtractLanguageUnknownFallbacksToEn(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dspace_Hooks::class, 'extractLanguage');
        $method->setAccessible(true);

        // Use a 4+ char unknown name — triggers Translations::findLanguageCodeByLanguageName()
        // which returns null for unknown names, causing convertTo2LetterCode() to return null,
        // and extractLanguage() to fall back to 'en' via ?? 'en'.
        $xml = simplexml_load_string(
            '<?xml version="1.0"?>'
            . '<root xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<dc:language>unknownlanguage</dc:language>'
            . '</root>'
        );
        $xml->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');

        $result = $method->invoke(null, $xml);

        self::assertSame('en', $result);
    }

    // =========================================================================
    // extractLicense() — private static, tested via ReflectionMethod
    // =========================================================================

    /**
     * extractLicense() returns the rightsURI attribute value when present.
     */
    public function testExtractLicenseWithUri(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dspace_Hooks::class, 'extractLicense');
        $method->setAccessible(true);

        $xmlString = '<?xml version="1.0"?>'
            . '<root xmlns:datacite="http://datacite.org/schema/kernel-4">'
            . '<datacite:rightsList>'
            . '<datacite:rights rightsURI="https://creativecommons.org/licenses/by/4.0/">CC BY 4.0</datacite:rights>'
            . '</datacite:rightsList>'
            . '</root>';

        $xml = simplexml_load_string($xmlString);
        $xml->registerXPathNamespace('datacite', 'http://datacite.org/schema/kernel-4');

        $result = $method->invoke(null, $xml);

        self::assertSame('https://creativecommons.org/licenses/by/4.0/', $result);
    }

    /**
     * extractLicense() returns an empty string when there is no datacite:rights node.
     */
    public function testExtractLicenseEmpty(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dspace_Hooks::class, 'extractLicense');
        $method->setAccessible(true);

        $xml = simplexml_load_string(
            '<?xml version="1.0"?>'
            . '<root xmlns:datacite="http://datacite.org/schema/kernel-4"></root>'
        );
        $xml->registerXPathNamespace('datacite', 'http://datacite.org/schema/kernel-4');

        $result = $method->invoke(null, $xml);

        self::assertSame('', $result);
    }

    // =========================================================================
    // buildDataForOaiDc() — private static, tested via ReflectionMethod
    // =========================================================================

    /**
     * buildDataForOaiDc() returns an array containing the expected keys.
     */
    public function testBuildDataForOaiDc(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dspace_Hooks::class, 'buildDataForOaiDc');
        $method->setAccessible(true);

        $result = $method->invoke(
            null,
            [['value' => 'Test Title', 'language' => 'en']],  // $titles
            ['Doe, John'],                                       // $creatorsDc
            [['value' => 'subject1', 'language' => 'en']],      // $subjects
            [['value' => 'A description', 'language' => 'en']], // $descriptions
            'en',                                                // $language
            'article',                                           // $dcType
            '2024-01-15',                                        // $datestamp
            ['oai:repositorium.uminho.pt:1822/79894'],           // $identifiers
            'https://creativecommons.org/licenses/by/4.0/',      // $license
            'Test Publisher'                                     // $publisher
        );

        self::assertIsArray($result);
        self::assertArrayHasKey('title', $result);
        self::assertArrayHasKey('creator', $result);
        self::assertArrayHasKey('subject', $result);
        self::assertArrayHasKey('description', $result);
        self::assertArrayHasKey('language', $result);
        self::assertArrayHasKey('type', $result);
        self::assertArrayHasKey('date', $result);
        self::assertArrayHasKey('identifier', $result);
        self::assertArrayHasKey('rights', $result);
        self::assertArrayHasKey('publisher', $result);
    }

    // =========================================================================
    // extractMetadata() — private static, tested via ReflectionMethod
    // =========================================================================

    /**
     * extractMetadata() returns [] when the XML string is invalid.
     */
    public function testExtractMetadataInvalidXml(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dspace_Hooks::class, 'extractMetadata');
        $method->setAccessible(true);

        // Suppress libxml warnings emitted by simplexml_load_string() on invalid input
        set_error_handler(static function () {
            return true;
        });

        try {
            $result = $method->invoke(null, 'not xml');
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $result);
    }

    /**
     * extractMetadata() returns a non-empty array containing TO_COMPILE_OAI_DC
     * when fed a minimal but valid OAI-PMH record with DataCite metadata.
     */
    public function testExtractMetadataValidXml(): void
    {
        $method = new ReflectionMethod(Episciences_Repositories_Dspace_Hooks::class, 'extractMetadata');
        $method->setAccessible(true);

        $xmlString = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<record xmlns="http://www.openarchives.org/OAI/2.0/"
        xmlns:oaire="http://namespace.openaire.eu/schema/oaire/"
        xmlns:datacite="http://datacite.org/schema/kernel-4"
        xmlns:dc="http://purl.org/dc/elements/1.1/">
  <header>
    <identifier>oai:repositorium.uminho.pt:1822/79894</identifier>
    <datestamp>2024-01-15</datestamp>
  </header>
  <metadata>
    <datacite:titles>
      <datacite:title xml:lang="en">Test Title</datacite:title>
    </datacite:titles>
    <datacite:creators>
      <datacite:creator>
        <datacite:creatorName>Doe, John</datacite:creatorName>
      </datacite:creator>
    </datacite:creators>
    <dc:language>en</dc:language>
    <datacite:rightsList>
      <datacite:rights rightsURI="https://creativecommons.org/licenses/by/4.0/">CC BY 4.0</datacite:rights>
    </datacite:rightsList>
  </metadata>
</record>
XML;

        $result = $method->invoke(null, $xmlString);

        self::assertIsArray($result);
        self::assertNotEmpty($result);
        self::assertArrayHasKey(Episciences_Repositories_Common::TO_COMPILE_OAI_DC, $result);
    }
}
