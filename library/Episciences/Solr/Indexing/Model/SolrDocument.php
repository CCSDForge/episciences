<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Model;

use Ccsd_Search_Solr;
use Ccsd_Tools_String;
use Solarium\QueryType\Update\Query\Document;

/**
 * Immutable field map, built up field-by-field, then converted to a Solarium
 * Document only at the very last step. This is the seam that makes every field
 * builder in Build/ testable without a Solarium client: builders return a
 * SolrDocument, not a mutated Solarium\Document.
 *
 * Reproduces two distinct field-writing behaviours found in the legacy
 * Ccsd_Search_Solr_Indexer(_Episciences) so a field-level diff against the legacy
 * builder stays meaningful:
 *  - "filtered" fields (legacy: routed through addArrayOfMetaToDoc()/addMetaToDoc())
 *    are trimmed/control-char-stripped and silently skipped when empty,
 *    "0000-00-00", or equal to the facet separator.
 *  - "raw" fields (legacy: direct $document->addField() calls for author/volume/
 *    section/keyword fields) are appended as-is, no sanitization, no dedup.
 */
final class SolrDocument
{
    /** @var array<string, list<bool|int|float|string>> */
    private readonly array $fields;

    /** @param array<string, list<bool|int|float|string>> $fields */
    private function __construct(array $fields)
    {
        $this->fields = $fields;
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * NB: accepts bool because Ccsd_Tools::xpath() (used for language_s) returns
     * false — not a filtered-out value here (only '', '0000-00-00' and the facet
     * separator are), so legacy would pass it straight through to addField() too.
     */
    public function withFilteredField(string $name, bool|int|float|string|null $value): self
    {
        $name = trim($name);

        if (is_string($value)) {
            $value = Ccsd_Tools_String::stripCtrlChars($value);
            $value = trim($value);
        }

        if ($value === null || $value === '' || $value === '0000-00-00' || $value === Ccsd_Search_Solr::SOLR_FACET_SEPARATOR) {
            return $this;
        }

        return $this->appendRaw($name, $value);
    }

    public function withRawField(string $name, int|float|string $value): self
    {
        return $this->appendRaw($name, $value);
    }

    /**
     * Mirrors Ccsd_Search_Solr_Indexer::addArrayOfMetaToDoc(): applies
     * withFilteredField() to every entry, de-duplicating (array_unique) any entry
     * whose value is itself a list of values.
     *
     * @param array<string, bool|int|float|string|null|list<bool|int|float|string|null>> $fields
     */
    public function withFilteredMap(array $fields): self
    {
        $document = $this;

        foreach ($fields as $name => $value) {
            if (is_array($value)) {
                foreach (array_unique($value, SORT_REGULAR) as $oneValue) {
                    $document = $document->withFilteredField($name, $oneValue);
                }
            } else {
                $document = $document->withFilteredField($name, $value);
            }
        }

        return $document;
    }

    private function appendRaw(string $name, bool|int|float|string $value): self
    {
        $fields = $this->fields;
        $fields[$name][] = $value;

        return new self($fields);
    }

    /** @return array<string, list<bool|int|float|string>> */
    public function toArray(): array
    {
        return $this->fields;
    }

    public function toSolariumDocument(Document $document): Document
    {
        foreach ($this->fields as $name => $values) {
            foreach ($values as $value) {
                $document->addField($name, $value);
            }
        }

        return $document;
    }
}
