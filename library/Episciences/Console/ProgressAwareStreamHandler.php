<?php
declare(strict_types=1);

namespace Episciences\Console;

use Monolog\Handler\StreamHandler;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Stream handler that keeps log lines from garbling an active Symfony ProgressBar.
 *
 * ProgressBar::clear()/display() only coordinate cleanly when the output is ANSI-decorated
 * (a real interactive terminal): clear() erases the bar via cursor-movement escape codes,
 * which is a no-op otherwise — e.g. under `sudo`/cron/non-tty pipes, which is how this
 * runs in practice. In that case the bar's own redraw ends without a trailing newline
 * (Symfony's non-decorated fallback prepends a newline to each redraw instead of
 * overwriting in place), so a plain log write lands right after it, mid-line. Forcing a
 * leading newline on every write while a bar is active fixes that unconditionally; calling
 * clear()/display() around it still gives a clean in-place overwrite when decoration
 * is available.
 */
class ProgressAwareStreamHandler extends StreamHandler
{
    private ?ProgressBar $progressBar = null;

    public function setProgressBar(?ProgressBar $progressBar): void
    {
        $this->progressBar = $progressBar;
    }

    /**
     * @param array<string, mixed> $record
     */
    protected function write(array $record): void
    {
        if ($this->progressBar === null) {
            parent::write($record);
            return;
        }

        $this->progressBar->clear();
        $record['formatted'] = PHP_EOL . ($record['formatted'] ?? '');
        parent::write($record);
        $this->progressBar->display();
    }
}
