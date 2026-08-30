<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Model;

/**
 * Mirrors the legacy Ccsd_Search_Solr_Indexer::O_UPDATE / O_DELETE string constants,
 * but as a real type: an invalid origin is now a compile-time/type error instead of
 * being silently coerced to UPDATE by Ccsd_Search_Solr_Indexer::setOrigin().
 */
enum IndexOrigin: string
{
    case Update = 'UPDATE';
    case Delete = 'DELETE';
}
