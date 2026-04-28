<?php

namespace unit\library\Ccsd;

use Ccsd_DOMDocument;
use DOMDocument;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Ccsd_DOMDocument
 *
 * Tests: createElement() sanitisation (xmlSafe + stripCtrlChars),
 *        XmlToArray() and XmlToGroupedArray() structure.
 */
class Ccsd_DOMDocumentTest extends TestCase
{
    private Ccsd_DOMDocument $dom;

    protected function setUp(): void
    {
        $this->dom = new Ccsd_DOMDocument('1.0', 'UTF-8');
    }

    // ------------------------------------------------------------------
    // createElement — basic behaviour
    // ------------------------------------------------------------------

    public function testCreateElementPlainText(): void
    {
        $el = $this->dom->createElement('title', 'Hello World');
        $this->assertSame('Hello World', $el->nodeValue);
    }

    public function testCreateElementNullValue(): void
    {
        // null passed as value — xmlSafe(null) must not crash
        $el = $this->dom->createElement('empty', null);
        $this->assertNotNull($el);
        $this->assertSame('empty', $el->tagName);
    }

    public function testCreateElementStripsControlChars(): void
    {
        // Control character \x01 must be stripped by stripCtrlChars
        $el = $this->dom->createElement('ctrl', "hello\x01world");
        $this->assertSame('helloworld', $el->nodeValue);
    }

    public function testCreateElementAmpersandNodeValue(): void
    {
        // xmlSafe converts & → &amp; before passing to DOMDocument::createElement().
        // PHP DOM parses entity references in the element value constructor, so
        // nodeValue always returns the decoded text ('&'), not the entity form ('&amp;').
        $el = $this->dom->createElement('text', 'a & b');
        $this->assertSame('a & b', $el->nodeValue);
    }

    public function testCreateElementReturnsAppendableNode(): void
    {
        $root = $this->dom->createElement('root');
        $child = $this->dom->createElement('child', 'value');
        $this->dom->appendChild($root);
        $root->appendChild($child);

        $this->assertSame('value', $this->dom->getElementsByTagName('child')->item(0)->nodeValue);
    }

    // ------------------------------------------------------------------
    // XmlToArray
    // ------------------------------------------------------------------

    private function loadSimpleXml(): void
    {
        $this->dom->loadXML('<root><child>Hello</child><item attr="val">World</item></root>');
    }

    public function testXmlToArrayRootName(): void
    {
        $this->loadSimpleXml();
        $result = $this->dom->XmlToArray($this->dom->documentElement);
        $this->assertSame('root', $result['name']);
    }

    public function testXmlToArrayHasChildren(): void
    {
        $this->loadSimpleXml();
        $result = $this->dom->XmlToArray($this->dom->documentElement);
        $this->assertArrayHasKey('children', $result);
        $this->assertNotEmpty($result['children']);
    }

    public function testXmlToArrayTextNodeContent(): void
    {
        $this->dom->loadXML('<root><child>Hello</child></root>');
        $result = $this->dom->XmlToArray($this->dom->documentElement);
        // First child of root is <child>, which has a text node
        $child = $result['children'][0];
        $this->assertSame('child', $child['name']);
        $this->assertArrayHasKey('children', $child);
        $textNode = $child['children'][0];
        $this->assertSame('text', $textNode['name']);
        $this->assertSame('Hello', $textNode['content']);
    }

    public function testXmlToArrayAttributesPresent(): void
    {
        $this->dom->loadXML('<root><item attr="val">World</item></root>');
        $result = $this->dom->XmlToArray($this->dom->documentElement);
        $item = $result['children'][0];
        $this->assertSame('item', $item['name']);
        $this->assertArrayHasKey('attributes', $item);
        $this->assertSame('val', $item['attributes']['attr']);
    }

    public function testXmlToArrayEmptyDocReturnsEmptyArray(): void
    {
        // Load an element with no children and no text
        $this->dom->loadXML('<root/>');
        $result = $this->dom->XmlToArray($this->dom->documentElement);
        $this->assertSame('root', $result['name']);
        $this->assertArrayNotHasKey('children', $result);
    }

    // ------------------------------------------------------------------
    // XmlToGroupedArray
    // ------------------------------------------------------------------

    public function testXmlToGroupedArrayRootName(): void
    {
        $this->dom->loadXML('<root><item>A</item><item>B</item></root>');
        $result = $this->dom->XmlToGroupedArray($this->dom->documentElement);
        $this->assertSame('root', $result['name']);
    }

    public function testXmlToGroupedArrayGroupsSameNameChildren(): void
    {
        $this->dom->loadXML('<root><item>A</item><item>B</item></root>');
        $result = $this->dom->XmlToGroupedArray($this->dom->documentElement);
        // Two <item> nodes should be grouped under a single 'item' entry
        $this->assertArrayHasKey('children', $result);
        $names = array_column($result['children'], 'name');
        // Only one unique 'item' key after grouping
        $this->assertCount(1, array_unique($names));
        $this->assertContains('item', $names);
    }

    public function testXmlToGroupedArrayDifferentChildrenPreserved(): void
    {
        $this->dom->loadXML('<root><title>T</title><body>B</body></root>');
        $result = $this->dom->XmlToGroupedArray($this->dom->documentElement);
        $names = array_column($result['children'], 'name');
        $this->assertContains('title', $names);
        $this->assertContains('body', $names);
    }
}
