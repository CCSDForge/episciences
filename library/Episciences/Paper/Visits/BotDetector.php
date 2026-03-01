<?php
declare(strict_types=1);

namespace Episciences\Paper\Visits;

/**
 * UA-based bot detection using a COUNTER Robots list file.
 *
 * The list is a plain-text file where each non-blank, non-comment line
 * is a regex pattern. Lines starting with '#' are ignored.
 *
 * @see UpdateCounterRobotsListCommand for downloading the list.
 */
class BotDetector
{
    /** @var string|null Compiled combined regex, shared across all instances. */
    private static ?string $compiledPattern = null;

    public function __construct(private readonly string $patternsFilePath) {}

    /**
     * Returns true if the given User-Agent string looks like a bot.
     * Empty or very short UAs are considered bots.
     * When the patterns file does not exist, only the length check applies.
     */
    public function isBot(string $userAgent): bool
    {
        if (strlen(trim($userAgent)) <= 1) {
            return true;
        }

        $pattern = $this->getCompiledPattern();
        if ($pattern === null) {
            return false;
        }

        return (bool) preg_match($pattern, $userAgent);
    }

    /**
     * Build and cache the combined regex from the patterns file.
     * Returns null when the file is missing or contains no valid patterns.
     */
    private function getCompiledPattern(): ?string
    {
        if (self::$compiledPattern !== null) {
            return self::$compiledPattern;
        }

        if (!file_exists($this->patternsFilePath)) {
            return null;
        }

        $lines = file($this->patternsFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return null;
        }

        $parts = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            // Validate pattern individually before adding to combined regex
            if (@preg_match('/' . $line . '/i', '') !== false) {
                $parts[] = '(?:' . $line . ')';
            }
        }

        if (empty($parts)) {
            return null;
        }

        self::$compiledPattern = '/' . implode('|', $parts) . '/i';
        return self::$compiledPattern;
    }

    /**
     * Reset static pattern cache — for testing only.
     */
    public static function resetCache(): void
    {
        self::$compiledPattern = null;
    }
}
