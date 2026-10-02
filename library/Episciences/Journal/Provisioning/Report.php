<?php

declare(strict_types=1);

namespace Episciences\Journal\Provisioning;

/**
 * Collects what a journal:create run did (or, under --dry-run, would do), for
 * CreateJournalCommand to display as a table plus any warnings.
 */
final class Report
{
    /** @var array<int, array{0: string, 1: string}> */
    private array $rows = [];

    /** @var string[] */
    private array $warnings = [];

    public function add(string $step, string $detail): void
    {
        $this->rows[] = [$step, $detail];
    }

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    public function toRows(): array
    {
        return $this->rows;
    }

    /**
     * @return string[]
     */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
