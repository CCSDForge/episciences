<?php

namespace unit\library\Episciences\Repositories;

use PHPUnit\Framework\TestCase;

class Episciences_Repositories_ARCHE_HooksTest extends TestCase
{
    /**
     * Test 1: hookIsRequiredVersion() returns ['result' => false]
     */
    public function testHookIsRequiredVersionReturnsFalse(): void
    {
        $result = \Episciences_Repositories_ARCHE_Hooks::hookIsRequiredVersion();
        $this->assertSame(['result' => false], $result);
    }

    /**
     * Test 2: hookIsIdentifierCommonToAllVersions() returns ['result' => false]
     */
    public function testHookIsIdentifierCommonToAllVersionsReturnsFalse(): void
    {
        $result = \Episciences_Repositories_ARCHE_Hooks::hookIsIdentifierCommonToAllVersions();
        $this->assertSame(['result' => false], $result);
    }

    /**
     * Test 3: extractRelatedIdentifiers() with no 'related_identifiers' key returns []
     */
    public function testExtractRelatedIdentifiersNoKey(): void
    {
        $result = \Episciences_Repositories_ARCHE_Hooks::extractRelatedIdentifiers(['metadata' => []]);
        $this->assertSame([], $result);
    }

    /**
     * Test 4: extractRelatedIdentifiers() returns the array when key is present
     */
    public function testExtractRelatedIdentifiersWithKey(): void
    {
        $input = ['metadata' => ['related_identifiers' => [['id' => 'test']]]];
        $result = \Episciences_Repositories_ARCHE_Hooks::extractRelatedIdentifiers($input);
        $this->assertSame([['id' => 'test']], $result);
    }

    /**
     * Test 5 (Bug A1): enrichmentProcess() throws \InvalidArgumentException (built-in)
     * for invalid XML — NOT \http\Exception\InvalidArgumentException (PECL).
     */
    public function testEnrichmentProcessThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $method = new \ReflectionMethod(\Episciences_Repositories_ARCHE_Hooks::class, 'enrichmentProcess');
        $method->setAccessible(true);
        $method->invoke(null, 'not xml');
    }

    /**
     * Test 6 (Bug A2): enrichmentProcess() with a valid OAI-PMH + DataCite XML string
     * does not throw a TypeError — safeDateFormat() handles the date safely.
     */
    public function testEnrichmentProcessWithValidXml(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/" xmlns:oai="http://www.openarchives.org/OAI/2.0/" xmlns:datacite="http://datacite.org/schema/kernel-3">
  <GetRecord>
    <record>
      <header xmlns:oai="http://www.openarchives.org/OAI/2.0/">
        <oai:identifier>oai:test:1</oai:identifier>
        <oai:datestamp>2024-01-15</oai:datestamp>
      </header>
      <metadata>
        <oai_datacite xmlns:datacite="http://datacite.org/schema/kernel-3">
          <datacite:creators>
            <datacite:creator>
              <datacite:creatorName>Doe, John</datacite:creatorName>
            </datacite:creator>
          </datacite:creators>
          <datacite:titles>
            <datacite:title>Test Title</datacite:title>
          </datacite:titles>
          <datacite:language>en</datacite:language>
        </oai_datacite>
      </metadata>
    </record>
  </GetRecord>
</OAI-PMH>
XML;

        $method = new \ReflectionMethod(\Episciences_Repositories_ARCHE_Hooks::class, 'enrichmentProcess');
        $method->setAccessible(true);
        $result = $method->invoke(null, $xml);

        $this->assertIsArray($result);
    }

    /**
     * Test 7: extractDescriptions() returns an array when given a SimpleXMLElement
     * with at least one datacite:description node.
     */
    public function testExtractDescriptionsReflection(): void
    {
        $xmlString = <<<'XML'
<?xml version="1.0"?>
<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/" xmlns:datacite="http://datacite.org/schema/kernel-3">
  <GetRecord><record><metadata>
    <resource xmlns:datacite="http://datacite.org/schema/kernel-3">
      <datacite:descriptions>
        <datacite:description xml:lang="en" descriptionType="Abstract">Test description</datacite:description>
      </datacite:descriptions>
    </resource>
  </metadata></record></GetRecord>
</OAI-PMH>
XML;

        $metadata = simplexml_load_string($xmlString);
        $this->assertInstanceOf(\SimpleXMLElement::class, $metadata);

        $metadata->registerXPathNamespace('oai', 'http://www.openarchives.org/OAI/2.0/');
        $metadata->registerXPathNamespace('datacite', 'http://datacite.org/schema/kernel-3');

        $method = new \ReflectionMethod(\Episciences_Repositories_ARCHE_Hooks::class, 'extractDescriptions');
        $method->setAccessible(true);
        $result = $method->invoke(null, $metadata, 'en');

        $this->assertIsArray($result);
    }

    /**
     * Test 8 (Bug A2): enrichmentProcess() with an invalid datestamp does not throw TypeError.
     * After A2 fix, safeDateFormat() returns '' for invalid dates instead of crashing.
     */
    public function testEnrichmentProcessWithInvalidDateDoesNotThrow(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/" xmlns:oai="http://www.openarchives.org/OAI/2.0/" xmlns:datacite="http://datacite.org/schema/kernel-3">
  <GetRecord>
    <record>
      <header xmlns:oai="http://www.openarchives.org/OAI/2.0/">
        <oai:identifier>oai:test:2</oai:identifier>
        <oai:datestamp>not-a-date</oai:datestamp>
      </header>
      <metadata>
        <oai_datacite xmlns:datacite="http://datacite.org/schema/kernel-3">
          <datacite:creators>
            <datacite:creator>
              <datacite:creatorName>Doe, Jane</datacite:creatorName>
            </datacite:creator>
          </datacite:creators>
          <datacite:titles>
            <datacite:title>Another Title</datacite:title>
          </datacite:titles>
          <datacite:language>en</datacite:language>
        </oai_datacite>
      </metadata>
    </record>
  </GetRecord>
</OAI-PMH>
XML;

        $method = new \ReflectionMethod(\Episciences_Repositories_ARCHE_Hooks::class, 'enrichmentProcess');
        $method->setAccessible(true);

        // After fix A2, no TypeError should be thrown
        $result = $method->invoke(null, $xml);

        $this->assertIsArray($result);
    }

    // =========================================================================
    // extractDescriptions() — language fallback and empty filtering
    // =========================================================================

    /**
     * When a description node has no xml:lang attribute, the document language is used.
     */
    public function testExtractDescriptionsFallbackToDocumentLanguage(): void
    {
        $xmlStr = <<<'XML'
<?xml version="1.0"?>
<root xmlns:datacite="http://datacite.org/schema/kernel-3">
  <datacite:descriptions>
    <datacite:description descriptionType="Abstract">Abstract without lang</datacite:description>
  </datacite:descriptions>
</root>
XML;
        $metadata = simplexml_load_string($xmlStr);
        $this->assertInstanceOf(\SimpleXMLElement::class, $metadata);
        $metadata->registerXPathNamespace('datacite', 'http://datacite.org/schema/kernel-3');

        $method = new \ReflectionMethod(\Episciences_Repositories_ARCHE_Hooks::class, 'extractDescriptions');
        $method->setAccessible(true);
        $result = $method->invoke(null, $metadata, 'de');

        $this->assertNotEmpty($result);
        $this->assertSame('de', $result[0]['language']);
    }

    /**
     * Empty description nodes (after trim) must be filtered out.
     */
    public function testExtractDescriptionsFiltersEmpty(): void
    {
        $xmlStr = <<<'XML'
<?xml version="1.0"?>
<root xmlns:datacite="http://datacite.org/schema/kernel-3">
  <datacite:descriptions>
    <datacite:description descriptionType="Abstract"></datacite:description>
    <datacite:description descriptionType="Abstract" xml:lang="en">Real abstract</datacite:description>
  </datacite:descriptions>
</root>
XML;
        $metadata = simplexml_load_string($xmlStr);
        $this->assertInstanceOf(\SimpleXMLElement::class, $metadata);
        $metadata->registerXPathNamespace('datacite', 'http://datacite.org/schema/kernel-3');

        $method = new \ReflectionMethod(\Episciences_Repositories_ARCHE_Hooks::class, 'extractDescriptions');
        $method->setAccessible(true);
        $result = $method->invoke(null, $metadata, 'en');

        $this->assertCount(1, $result);
        $this->assertSame('Real abstract', $result[0]['value']);
    }

    /**
     * Multiple descriptions with explicit xml:lang attribute are all returned
     * with their respective language codes.
     */
    public function testExtractDescriptionsMultipleLanguages(): void
    {
        $xmlStr = <<<'XML'
<?xml version="1.0"?>
<root xmlns:datacite="http://datacite.org/schema/kernel-3">
  <datacite:descriptions>
    <datacite:description descriptionType="Abstract" xml:lang="en">English abstract</datacite:description>
    <datacite:description descriptionType="Abstract" xml:lang="fr">Résumé français</datacite:description>
  </datacite:descriptions>
</root>
XML;
        $metadata = simplexml_load_string($xmlStr);
        $this->assertInstanceOf(\SimpleXMLElement::class, $metadata);
        $metadata->registerXPathNamespace('datacite', 'http://datacite.org/schema/kernel-3');

        $method = new \ReflectionMethod(\Episciences_Repositories_ARCHE_Hooks::class, 'extractDescriptions');
        $method->setAccessible(true);
        $result = $method->invoke(null, $metadata, 'en');

        $this->assertCount(2, $result);
        $languages = array_column($result, 'language');
        $this->assertContains('en', $languages);
        $this->assertContains('fr', $languages);
    }

    /**
     * enrichmentProcess() returns an array with 'title' set from datacite:titles.
     */
    public function testEnrichmentProcessPopulatesTitle(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/" xmlns:datacite="http://datacite.org/schema/kernel-3">
  <GetRecord>
    <record>
      <header>
        <identifier>oai:test:3</identifier>
        <datestamp>2024-03-01</datestamp>
      </header>
      <metadata>
        <oai_datacite>
          <datacite:titles>
            <datacite:title>My ARCHE Paper</datacite:title>
          </datacite:titles>
          <datacite:language>en</datacite:language>
        </oai_datacite>
      </metadata>
    </record>
  </GetRecord>
</OAI-PMH>
XML;
        $method = new \ReflectionMethod(\Episciences_Repositories_ARCHE_Hooks::class, 'enrichmentProcess');
        $method->setAccessible(true);
        $result = $method->invoke(null, $xml);

        $this->assertIsArray($result);
        $this->assertSame('My ARCHE Paper', $result['title'] ?? '');
    }
}
