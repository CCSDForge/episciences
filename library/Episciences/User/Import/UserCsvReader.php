<?php

declare(strict_types=1);

namespace Episciences\User\Import;

use InvalidArgumentException;

/**
 * Reads and validates a user import CSV file: semicolon separated, UTF-8 (BOM tolerated), with a header row naming
 * the columns firstname, lastname, email (required), role and lang (optional).
 */
final class UserCsvReader
{
    public const array REQUIRED_COLUMNS = ['firstname', 'lastname', 'email'];
    public const array OPTIONAL_COLUMNS = ['role', 'lang'];
    private const string SEPARATOR = ';';
    private const string UTF8_BOM = "\xEF\xBB\xBF";

    /**
     * @param list<string> $allowedRoles
     * @param list<string> $allowedLangs
     */
    public function __construct(private readonly array $allowedRoles, private readonly array $allowedLangs)
    {
    }

    /**
     * Every line is checked: the file is imported only when there is no error at all.
     *
     * @return array{rows: list<UserImportRow>, errors: list<string>}
     */
    public function read(string $path): array
    {
        $handle = is_file($path) && is_readable($path) ? fopen($path, 'rb') : false;
        if ($handle === false) {
            return ['rows' => [], 'errors' => [sprintf('cannot read the file "%s"', $path)]];
        }

        try {
            return $this->readHandle($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param resource $handle
     * @return array{rows: list<UserImportRow>, errors: list<string>}
     */
    private function readHandle($handle): array
    {
        $header = fgetcsv($handle, 0, self::SEPARATOR, '"', '');
        $columns = is_array($header) ? $this->parseHeader($header) : null;
        if ($columns === null) {
            return ['rows' => [], 'errors' => [$this->headerError()]];
        }

        $rows = [];
        $errors = [];
        $lineOfEmail = [];
        $line = 1;

        while (($values = fgetcsv($handle, 0, self::SEPARATOR, '"', '')) !== false) {
            $line++;
            if ($this->isBlank($values)) {
                continue;
            }

            try {
                $row = UserImportRow::fromRecord($line, $this->combine($columns, $values), $this->allowedRoles, $this->allowedLangs);
            } catch (InvalidArgumentException $e) {
                $errors[] = sprintf('line %d: %s', $line, $e->getMessage());
                continue;
            }

            // the accounts table compares addresses without case
            $key = mb_strtolower($row->email);
            if (isset($lineOfEmail[$key])) {
                $errors[] = sprintf('line %d: email "%s" already on line %d', $line, $row->email, $lineOfEmail[$key]);
                continue;
            }

            $lineOfEmail[$key] = $line;
            $rows[] = $row;
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * @param array<int, string|null> $header
     * @return array<int, string>|null column names indexed by position, null when a required column is missing
     */
    private function parseHeader(array $header): ?array
    {
        $header[0] = str_starts_with((string)$header[0], self::UTF8_BOM)
            ? substr((string)$header[0], strlen(self::UTF8_BOM))
            : $header[0];

        $columns = array_map(static fn(?string $name): string => strtolower(trim((string)$name)), $header);

        return array_diff(self::REQUIRED_COLUMNS, $columns) === [] ? $columns : null;
    }

    private function headerError(): string
    {
        return sprintf(
            'line 1: the header must name the columns %s (optional: %s), separated by "%s"',
            implode(', ', self::REQUIRED_COLUMNS),
            implode(', ', self::OPTIONAL_COLUMNS),
            self::SEPARATOR
        );
    }

    /**
     * @param array<int, string> $columns
     * @param array<int, string|null> $values
     * @return array<string, string>
     */
    private function combine(array $columns, array $values): array
    {
        $record = [];
        foreach ($columns as $position => $name) {
            $record[$name] = (string)($values[$position] ?? '');
        }

        return $record;
    }

    /**
     * @param array<int, string|null> $values
     */
    private function isBlank(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string)$value) !== '') {
                return false;
            }
        }

        return true;
    }
}
