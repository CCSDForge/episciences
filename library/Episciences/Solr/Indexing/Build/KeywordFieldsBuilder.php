<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Build;

use Episciences\Solr\Indexing\Model\SolrDocument;

/**
 * Port of indexKeywords() from Ccsd_Search_Solr_Indexer_Episciences: fans the
 * paper's "subjects" metadata out to the multivalued keyword_t field, whether
 * subjects is a flat list of keywords or a list of keyword-groups.
 */
final class KeywordFieldsBuilder
{
    public function build(SolrDocument $document, mixed $subjects): SolrDocument
    {
        if (!is_array($subjects)) {
            return $document;
        }

        foreach ($subjects as $keyword) {
            if (is_array($keyword)) {
                foreach ($keyword as $oneKeyword) {
                    $document = $document->withRawField('keyword_t', $oneKeyword);
                }
            } else {
                $document = $document->withRawField('keyword_t', $keyword);
            }
        }

        return $document;
    }
}
