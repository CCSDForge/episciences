<?php

declare(strict_types=1);

namespace Episciences\Paper\Record;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;

/**
 * Normalizes the whitespace of the titles of a repository metadata record (PAPERS.RECORD).
 *
 * Some repositories (e.g. arXiv, HAL) accept line breaks in document titles and expose them as is
 * in their metadata. A title is a single line: every run of whitespace (line breaks, tabs,
 * spaces) is collapsed into one space and the title is trimmed. No-break spaces are kept:
 * they are typographic choices (e.g. before a colon in French).
 *
 * Only titles are normalized: the line breaks of abstracts (dc:description) separate paragraphs.
 *
 * Titles are Dublin Core titles (dc:title) and DataCite titles (records of repositories
 * stored in DataCite format). A title without namespace is accepted too, for records whose
 * default namespace has already been stripped (@see \Episciences_PapersManager::cleanRecord()).
 */
final class TitleNormalizer
{
    public const DC_NAMESPACE = 'http://purl.org/dc/elements/1.1/';
    public const DATACITE_NAMESPACE_PREFIX = 'http://datacite.org/schema/kernel-';

    /**
     * Whitespace collapsed in titles: tab, line feed, vertical tab, form feed, carriage return,
     * space, next line, line separator and paragraph separator. U+00A0 (no-break space) is
     * deliberately left out.
     */
    private const WHITESPACE_PATTERN = '/[\x{0009}-\x{000D}\x{0020}\x{0085}\x{2028}\x{2029}]+/u';

    private const TITLE_QUERY = "//*[local-name()='title' and ("
        . "namespace-uri()='" . self::DC_NAMESPACE . "'"
        . " or starts-with(namespace-uri(), '" . self::DATACITE_NAMESPACE_PREFIX . "')"
        . " or namespace-uri()=''"
        . ')]';

    /**
     * Return the record with normalized titles.
     *
     * The record is returned unchanged, byte for byte, when no title needs normalizing or
     * when it cannot be parsed: it is only serialized again when a title has changed.
     */
    public static function normalize(string $record): string
    {
        if (trim($record) === '') {
            return $record;
        }

        $dom = self::load($record);

        if ($dom === null) {
            return $record;
        }

        $titles = (new DOMXPath($dom))->query(self::TITLE_QUERY);

        if ($titles === false || $titles->length === 0) {
            return $record;
        }

        $changed = false;

        foreach ($titles as $title) {
            if ($title instanceof DOMElement && self::normalizeTitle($title)) {
                $changed = true;
            }
        }

        if (!$changed) {
            return $record;
        }

        // Keep the prolog only when the input had one: the result is stored straight back
        // into RECORD, next to records nobody rewrote.
        $normalized = str_starts_with(ltrim($record), '<?xml')
            ? $dom->saveXML()
            : $dom->saveXML($dom->documentElement);

        return $normalized !== false ? $normalized : $record;
    }

    private static function load(string $record): ?DOMDocument
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $dom->loadXML($record, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $loaded && $dom->documentElement !== null ? $dom : null;
    }

    /**
     * Collapse the whitespace of every text node of the title, then trim its first and last
     * text nodes. Working on text nodes rather than on textContent keeps any inline markup.
     *
     * @return bool whether the title has changed
     */
    private static function normalizeTitle(DOMElement $title): bool
    {
        /** @var list<DOMText> $textNodes */
        $textNodes = [];
        self::collectTextNodes($title, $textNodes);

        if ($textNodes === []) {
            return false;
        }

        $changed = false;
        $lastIndex = count($textNodes) - 1;

        foreach ($textNodes as $index => $textNode) {
            $value = (string)preg_replace(self::WHITESPACE_PATTERN, ' ', $textNode->data);

            if ($index === 0) {
                $value = ltrim($value, ' ');
            }

            if ($index === $lastIndex) {
                $value = rtrim($value, ' ');
            }

            if ($value !== $textNode->data) {
                $textNode->data = $value;
                $changed = true;
            }
        }

        return $changed;
    }

    /**
     * @param list<DOMText> $textNodes
     */
    private static function collectTextNodes(DOMNode $node, array &$textNodes): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $textNodes[] = $child;
            } elseif ($child->hasChildNodes()) {
                self::collectTextNodes($child, $textNodes);
            }
        }
    }
}