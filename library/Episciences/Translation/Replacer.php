<?php
declare(strict_types=1);

/**
 * Pure service: applies search/replace pairs on the value side of PHP array translation files.
 *
 * Targets only the value part of  'key' => 'value'  and  "key" => "value"  entries.
 * Keys are left untouched. The original quote style of both key and value is preserved.
 */
class Episciences_Translation_Replacer
{
    /** Matches  'key' => 'value'  or  "key" => "value"  with backreferences on quote chars. */
    private const PATTERN = '/([\'"])(.*?)\1\s*=>\s*([\'"])(.*?)\3/s';

    private int $replacementCount = 0;

    /**
     * @param array<int, string> $search
     * @param array<int, string> $replace
     */
    public function __construct(
        private readonly array $search,
        private readonly array $replace,
        private readonly bool  $caseSensitive = false
    ) {}

    /**
     * Apply all search/replace pairs to $content and return the updated string.
     *
     * Resets and updates the replacement count each time it is called.
     *
     * @throws \RuntimeException when the internal regex fails.
     */
    public function replace(string $content): string
    {
        $search        = $this->search;
        $replace       = $this->replace;
        $caseSensitive = $this->caseSensitive;
        $count         = 0;

        $result = preg_replace_callback(
            self::PATTERN,
            static function (array $matches) use ($search, $replace, $caseSensitive, &$count): string {
                $keyQuote   = $matches[1];
                $key        = $matches[2];
                $valueQuote = $matches[3];
                $value      = $matches[4];

                foreach ($search as $i => $term) {
                    $updated = $caseSensitive
                        ? str_replace($term, $replace[$i], $value)
                        : str_ireplace($term, $replace[$i], $value);

                    if ($updated !== $value) {
                        $value = $updated;
                        $count++;
                    }
                }

                return "{$keyQuote}{$key}{$keyQuote} => {$valueQuote}{$value}{$valueQuote}";
            },
            $content
        );

        if ($result === null) {
            throw new \RuntimeException('Regex processing failed: ' . preg_last_error_msg());
        }

        $this->replacementCount = $count;

        return $result;
    }

    /**
     * Number of replacements made during the last call to replace().
     */
    public function getReplacementCount(): int
    {
        return $this->replacementCount;
    }

    /**
     * Count lines in $content that contain at least one 'key' => 'value' pair.
     */
    public static function countSignificantLines(string $content): int
    {
        $count = 0;
        foreach (explode(PHP_EOL, $content) as $line) {
            if (preg_match(self::PATTERN, $line) === 1) {
                $count++;
            }
        }
        return $count;
    }
}