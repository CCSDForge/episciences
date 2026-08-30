<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Model;

/**
 * Documents the Solr field-suffix conventions declared in
 * src/solr/episciences/conf/schema.xml, so builders can express intent
 * (e.g. "this is a faceted string field") instead of hardcoding raw suffix strings.
 */
enum FieldSuffix: string
{
    case Text = '_t';
    case StringExact = '_s';
    case FacetedString = '_fs';
    case Integer = '_i';
    case Date = '_tdate';

    public function suffix(): string
    {
        return $this->value;
    }
}
