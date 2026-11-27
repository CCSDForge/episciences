<?php

declare(strict_types=1);

namespace Episciences\Hal;

use DOMDocument;
use DOMElement;
use DOMException;
use DOMNode;

/**
 * Reads the TEI out of an OAI-PMH GetRecord response (metadataPrefix=xml-tei).
 *
 * The OAI-PMH server serves the TEI inside the OAI-PMH envelope, with a prefixed root (<tei:TEI>).
 * The TEI readers (licence, authors, zbjats export) walk it with SimpleXML from a <TEI> root in the
 * default namespace: the TEI is returned as such a document, the elements of other namespaces kept as they are.
 */
final class OaiTeiResponse
{
    public const TEI_NS = 'http://www.tei-c.org/ns/1.0';
    private const OAI_NS = 'http://www.openarchives.org/OAI/2.0/';

    private function __construct(
        public readonly ?string $tei,
        public readonly string $error,
    ) {
    }

    public static function parse(string $body): self
    {
        $source = new DOMDocument();

        if (trim($body) === '' || !self::loadXml($source, $body)) {
            return new self(null, 'the response is not XML');
        }

        $oaiError = $source->getElementsByTagNameNS(self::OAI_NS, 'error')->item(0);

        if ($oaiError instanceof DOMElement) {
            return new self(null, sprintf('OAI-PMH error %s: %s', $oaiError->getAttribute('code'), trim($oaiError->textContent)));
        }

        $tei = $source->getElementsByTagNameNS(self::TEI_NS, 'TEI')->item(0);

        if (!$tei instanceof DOMElement) {
            return new self(null, 'the response holds no TEI document');
        }

        try {
            return new self(self::asDefaultNamespaceDocument($tei), '');
        } catch (DOMException $e) {
            return new self(null, 'the TEI could not be copied: ' . $e->getMessage());
        }
    }

    /**
     * Load the response without raising a PHP warning: an unreadable response is reported as such by parse()
     */
    private static function loadXml(DOMDocument $document, string $xml): bool
    {
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded;
    }

    public function isTei(): bool
    {
        return $this->tei !== null;
    }

    /**
     * The TEI element, serialized as a document whose TEI elements are in the default namespace
     *
     * @throws DOMException
     */
    private static function asDefaultNamespaceDocument(DOMElement $tei): string
    {
        $target = new DOMDocument('1.0', 'UTF-8');
        self::appendCopy($tei, $target, $target);

        return (string)$target->saveXML();
    }

    /**
     * Append a copy of the node to the parent, already in the target document.
     * Each copy is attached before its children are added: ext-dom crashes when an element of another
     * namespace is imported into a detached element.
     *
     * @throws DOMException
     */
    private static function appendCopy(DOMNode $node, DOMNode $parent, DOMDocument $target): void
    {
        if (!$node instanceof DOMElement || $node->namespaceURI !== self::TEI_NS) {
            $parent->appendChild($target->importNode($node, true));
            return;
        }

        $copy = $target->createElementNS(self::TEI_NS, (string)$node->localName);
        $parent->appendChild($copy);

        foreach ($node->attributes ?? [] as $attribute) {
            $copy->setAttributeNS($attribute->namespaceURI, $attribute->nodeName, $attribute->value);
        }

        foreach ($node->childNodes as $child) {
            self::appendCopy($child, $copy, $target);
        }
    }
}
