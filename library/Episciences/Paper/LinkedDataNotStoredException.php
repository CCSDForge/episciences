<?php

declare(strict_types=1);

namespace Episciences\Paper;

/**
 * Some linked data (related identifiers) of a paper could not be stored, after the other ones were:
 * carries the number of rows stored, so that the caller still counts them and reindexes the paper.
 */
final class LinkedDataNotStoredException extends PartiallyStoredException
{
}
