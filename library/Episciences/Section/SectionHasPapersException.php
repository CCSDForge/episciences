<?php

declare(strict_types=1);

namespace Episciences\Section;

use RuntimeException;

/**
 * A section cannot be deleted while papers are still attached to it.
 */
class SectionHasPapersException extends RuntimeException
{
    /**
     * @param int $sid
     * @param list<int> $paperIds ids of the papers attached to the section
     */
    public function __construct(int $sid, private readonly array $paperIds)
    {
        parent::__construct(sprintf('Section %d still has %d paper(s)', $sid, count($paperIds)));
    }

    /**
     * @return list<int>
     */
    public function getPaperIds(): array
    {
        return $this->paperIds;
    }
}
