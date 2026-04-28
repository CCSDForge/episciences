<?php

namespace unit\library\Episciences\Repositories;

use Episciences_Repositories_Common;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SimpleXMLElement;

/**
 * Unit tests for Episciences_Repositories_Common.
 *
 * All tests are DB-free: only pure static methods are tested.
 *
 * Bugs documented/fixed:
 *   C1 — formatReferences(): $tmp uninitialized when doi is empty → undefined variable ErrorException
 *   C2 — getVersionFromIdentifier(): return 1 (int) violates string return type → return '1'
 *   C3 — convertTo2LetterCode(null): strlen(null) deprecated in PHP 8.1 → null guard added
 *   C4 — processPerson(): xpath()[0] without empty-check → TypeError on empty array
 *
 * @covers Episciences_Repositories_Common
 */
final class Episciences_Repositories_CommonTest extends TestCase
{
    // =========================================================================
    // formatReferences()
    // =========================================================================

    public function testFormatReferencesEmpty(): void
    {
        self::assertSame([], Episciences_Repositories_Common::formatReferences([]));
    }

    public function testFormatReferencesWithDoi(): void
    {
        $result = Episciences_Repositories_Common::formatReferences([
            'doi'   => '10.1/x',
            'title' => 'T',
            'year'  => '2020',
        ]);

        self::assertArrayHasKey('doi', $result);
        self::assertArrayHasKey('raw_reference', $result);
        self::assertSame('10.1/x', $result['doi']);
    }

    /**
     * Bug C1: $tmp was never initialized, so when doi is absent the line
     * `$tmp['raw_reference'] = $rawReference` triggers "Undefined variable $tmp".
     * After the fix ($tmp = [] before the first if), this must pass without error.
     */
    public function testFormatReferencesWithoutDoi(): void
    {
        $result = Episciences_Repositories_Common::formatReferences([
            'title' => 'T',
            'year'  => '2020',
        ]);

        self::assertArrayHasKey('raw_reference', $result);
        self::assertArrayNotHasKey('doi', $result);
    }

    // =========================================================================
    // getVersionFromIdentifier()
    // =========================================================================

    public function testGetVersionFromIdentifierWithVersion(): void
    {
        $version = Episciences_Repositories_Common::getVersionFromIdentifier('1822/79894.3');
        self::assertSame('3', $version);
    }

    /**
     * Bug C2: before fix, `return 1` (int) would cause a TypeError in strict mode
     * and assertSame('1', 1) would fail. After fix `return '1'` (string), passes.
     */
    public function testGetVersionFromIdentifierNoMatch(): void
    {
        $version = Episciences_Repositories_Common::getVersionFromIdentifier('1822/79894');
        self::assertSame('1', $version);
    }

    // =========================================================================
    // convertTo2LetterCode()
    // =========================================================================

    /**
     * Bug C3: strlen(null) is deprecated in PHP 8.1 and would emit a warning/error.
     * After fix (null guard), must return null cleanly.
     */
    public function testConvertTo2LetterCodeNull(): void
    {
        self::assertNull(Episciences_Repositories_Common::convertTo2LetterCode(null));
    }

    public function testConvertTo2LetterCode2Letters(): void
    {
        self::assertSame('fr', Episciences_Repositories_Common::convertTo2LetterCode('fr'));
    }

    // =========================================================================
    // isOpenAccessRight()
    // =========================================================================

    public function testIsOpenAccessRightWithPattern(): void
    {
        $record = '<metadata><dc:rights>info:eu-repo/semantics/openAccess</dc:rights></metadata>';
        $result = Episciences_Repositories_Common::isOpenAccessRight(['record' => $record]);

        self::assertSame(['isOpenAccessRight' => true], $result);
    }

    public function testIsOpenAccessRightWithoutPattern(): void
    {
        $record = '<metadata><dc:rights>closedAccess</dc:rights></metadata>';
        $result = Episciences_Repositories_Common::isOpenAccessRight(['record' => $record]);

        self::assertSame(['isOpenAccessRight' => false], $result);
    }

    public function testIsOpenAccessRightNoRecord(): void
    {
        $result = Episciences_Repositories_Common::isOpenAccessRight([]);
        self::assertSame(['isOpenAccessRight' => false], $result);
    }

    // =========================================================================
    // isRequiredVersion()
    // =========================================================================

    public function testIsRequiredVersionDefault(): void
    {
        self::assertTrue(Episciences_Repositories_Common::isRequiredVersion());
    }

    public function testIsRequiredVersionFalse(): void
    {
        self::assertFalse(Episciences_Repositories_Common::isRequiredVersion(false));
    }

    // =========================================================================
    // cleanAndPrepare()
    // =========================================================================

    public function testCleanAndPrepareNoId(): void
    {
        self::assertSame([], Episciences_Repositories_Common::cleanAndPrepare([]));
    }

    public function testCleanAndPrepareWithId(): void
    {
        $result = Episciences_Repositories_Common::cleanAndPrepare(['id' => ' foo ']);
        self::assertSame(['identifier' => 'foo'], $result);
    }

    // =========================================================================
    // checkAndCleanRecord()
    // =========================================================================

    public function testCheckAndCleanRecordAddsXmlns(): void
    {
        $input    = 'xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/ rest';
        $output   = Episciences_Repositories_Common::checkAndCleanRecord($input);

        self::assertStringContainsString('xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"', $output);
        self::assertStringContainsString('xsi:schemaLocation=', $output);
    }

    public function testCheckAndCleanRecordNoChange(): void
    {
        $input  = '<record>no schema location here</record>';
        $output = Episciences_Repositories_Common::checkAndCleanRecord($input);

        self::assertSame($input, $output);
    }

    // =========================================================================
    // replaceYMDHMSWithTimestamp()
    // =========================================================================

    public function testReplaceYMDHMSWithTimestampValid(): void
    {
        $result = Episciences_Repositories_Common::replaceYMDHMSWithTimestamp('20200115:120000');
        self::assertIsNumeric($result);
    }

    public function testReplaceYMDHMSWithTimestampInvalid(): void
    {
        $input  = 'notadate';
        $result = Episciences_Repositories_Common::replaceYMDHMSWithTimestamp($input);
        self::assertSame($input, $result);
    }

    // =========================================================================
    // removeDateTimePattern() / getDateTimePattern()
    // =========================================================================

    public function testRemoveDateTimePattern(): void
    {
        $result = Episciences_Repositories_Common::removeDateTimePattern('2024/192/20260205:224930');
        self::assertSame('2024/192/', $result);
    }

    public function testGetDateTimePattern(): void
    {
        $result = Episciences_Repositories_Common::getDateTimePattern('2024/192/20260205:224930');
        self::assertSame('20260205:224930', $result);
    }

    public function testGetDateTimePatternNoMatch(): void
    {
        $result = Episciences_Repositories_Common::getDateTimePattern('no match');
        self::assertSame('', $result);
    }

    // =========================================================================
    // getConceptIdentifierFromString()
    // =========================================================================

    public function testGetConceptIdentifierFromString(): void
    {
        $result = Episciences_Repositories_Common::getConceptIdentifierFromString('1822/79894.3');
        self::assertSame('1822/79894', $result);
    }

    public function testGetConceptIdentifierNoMatch(): void
    {
        $result = Episciences_Repositories_Common::getConceptIdentifierFromString('nodigits');
        self::assertSame('nodigits', $result);
    }

    // =========================================================================
    // getType()
    // =========================================================================

    public function testGetTypeMimeValid(): void
    {
        self::assertSame('pdf', Episciences_Repositories_Common::getType('application/pdf'));
    }

    public function testGetTypeWithParameter(): void
    {
        self::assertSame('html', Episciences_Repositories_Common::getType('text/html; charset=UTF-8'));
    }

    public function testGetTypeInvalid(): void
    {
        self::assertNull(Episciences_Repositories_Common::getType('notvalid'));
    }

    // =========================================================================
    // extractMultilingualContent()
    // =========================================================================

    public function testExtractMultilingualContent(): void
    {
        $xmlStr = <<<'XML'
<resource xmlns:datacite="http://datacite.org/schema/kernel-4">
    <datacite:titles>
        <datacite:title xml:lang="en">My Title</datacite:title>
        <datacite:title xml:lang="fr">Mon Titre</datacite:title>
    </datacite:titles>
</resource>
XML;
        $metadata = new SimpleXMLElement($xmlStr);
        $metadata->registerXPathNamespace('datacite', 'http://datacite.org/schema/kernel-4');

        $result = Episciences_Repositories_Common::extractMultilingualContent(
            $metadata,
            'datacite:titles/datacite:title',
            'en'
        );

        self::assertIsArray($result);
        self::assertNotEmpty($result);
        self::assertArrayHasKey('value', $result[0]);
        self::assertArrayHasKey('language', $result[0]);
    }

    // =========================================================================
    // extractRelatedIdentifiersFromMetadata()
    // =========================================================================

    public function testExtractRelatedIdentifiers(): void
    {
        $xmlStr = <<<'XML'
<resource xmlns:datacite="http://datacite.org/schema/kernel-4">
    <datacite:relatedIdentifiers>
        <datacite:relatedIdentifier relationType="IsSupplementTo" relatedIdentifierType="DOI">10.1234/example</datacite:relatedIdentifier>
    </datacite:relatedIdentifiers>
</resource>
XML;
        $metadata = new SimpleXMLElement($xmlStr);
        $metadata->registerXPathNamespace('datacite', 'http://datacite.org/schema/kernel-4');

        $result = Episciences_Repositories_Common::extractRelatedIdentifiersFromMetadata($metadata);

        self::assertIsArray($result);
        self::assertNotEmpty($result);
        self::assertArrayHasKey('identifier', $result[0]);
        self::assertArrayHasKey('relation', $result[0]);
        self::assertArrayHasKey('resource_type', $result[0]);
        self::assertArrayHasKey('scheme', $result[0]);
    }

    // =========================================================================
    // assembleData()
    // =========================================================================

    public function testAssembleDataPartial(): void
    {
        $assembled = [];
        Episciences_Repositories_Common::assembleData(
            ['title' => ['My paper']],
            [],
            $assembled
        );

        self::assertArrayHasKey(Episciences_Repositories_Common::TO_COMPILE_OAI_DC, $assembled);
    }

    public function testAssembleDataWithContrib(): void
    {
        $assembled = [];
        $enrichment = [
            Episciences_Repositories_Common::CONTRIB_ENRICHMENT => [
                ['fullname' => 'John Doe', 'family' => 'Doe', 'given' => 'John'],
            ],
        ];

        Episciences_Repositories_Common::assembleData([], $enrichment, $assembled);

        self::assertArrayHasKey(
            Episciences_Repositories_Common::CONTRIB_ENRICHMENT,
            $assembled[Episciences_Repositories_Common::ENRICHMENT]
        );
    }

    // =========================================================================
    // processPerson() — private, tested via ReflectionMethod (Bug C4)
    // =========================================================================

    /**
     * Bug C4: before fix, $person->xpath($nameField)[0] on an empty result array
     * triggered a TypeError. After fix (empty check guard), the method must return []
     * gracefully without any error.
     */
    public function testProcessPersonWithEmptyXpath(): void
    {
        // Build a <creator> node with no child element matching any xpath
        $xmlStr = <<<'XML'
<datacite:creators xmlns:datacite="http://datacite.org/schema/kernel-4">
    <datacite:creator>
    </datacite:creator>
</datacite:creators>
XML;
        $xml     = new SimpleXMLElement($xmlStr);
        $xml->registerXPathNamespace('datacite', 'http://datacite.org/schema/kernel-4');
        $creators = $xml->xpath('//datacite:creator');
        self::assertNotEmpty($creators);
        $creator = $creators[0];

        $method = new ReflectionMethod(Episciences_Repositories_Common::class, 'processPerson');
        $method->setAccessible(true);

        $creatorsDc = [];
        $seenNames  = [];

        // nameField that does not exist in the XML node → xpath returns [] → was TypeError before fix
        // ReflectionMethod::invokeArgs requires references to be passed via array + reference wrapper
        $result = $method->invokeArgs(null, [$creator, 'datacite:nonExistentField', &$creatorsDc, &$seenNames]);

        self::assertIsArray($result);
    }

    // =========================================================================
    // safeDateFormat() — new method
    // =========================================================================

    public function testSafeDateFormatValid(): void
    {
        self::assertSame('2024-01-15', Episciences_Repositories_Common::safeDateFormat('2024-01-15'));
    }

    public function testSafeDateFormatEmpty(): void
    {
        self::assertSame('', Episciences_Repositories_Common::safeDateFormat(''));
    }

    public function testSafeDateFormatInvalid(): void
    {
        self::assertSame('', Episciences_Repositories_Common::safeDateFormat('not-a-date'));
    }
}
