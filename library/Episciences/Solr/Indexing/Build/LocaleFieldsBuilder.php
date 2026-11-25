<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Build;

use Episciences\Solr\Indexing\Model\SolrDocument;
use Zend_Locale;

/**
 * Port of indexTitles()/indexAbstracts() from Ccsd_Search_Solr_Indexer_Episciences:
 * fans a per-locale array out to "{locale}_paper_title_t" / "{locale}_abstract_t"
 * fields, falling back to a single non-localized field when no key is a valid
 * Zend_Locale identifier.
 */
final class LocaleFieldsBuilder
{
    /** @param array<array-key, mixed> $titles */
    public function withTitles(SolrDocument $document, array $titles): SolrDocument
    {
        return $document->withFilteredMap($this->localize($titles, 'paper_title_t'));
    }

    /** @param array<array-key, mixed> $abstracts */
    public function withAbstracts(SolrDocument $document, array $abstracts): SolrDocument
    {
        return $document->withFilteredMap($this->localize($abstracts, 'abstract_t'));
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array<string, mixed>
     */
    private function localize(array $values, string $fallbackFieldName): array
    {
        $localized = [];
        foreach ($values as $locale => $value) {
            if (Zend_Locale::isLocale((string)$locale)) {
                $localized[$locale . '_' . $fallbackFieldName] = $value;
            }
        }

        if ($localized === []) {
            $localized[$fallbackFieldName] = $values;
        }

        return $localized;
    }
}
