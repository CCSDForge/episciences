<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Regression guards: user lookups must never expose credential columns.
 *
 * T_UTILISATEURS holds the account credentials next to the identity data. Lookups feeding
 * autocomplete endpoints must name the columns they return instead of selecting whole rows.
 * Source-analysis tests (ZF1 controllers and static DB helpers are not instantiable in isolation).
 */
final class UserLookupColumnScopeTest extends TestCase
{
    private function extractMethod(string $file, string $methodName): string
    {
        $source = (string) file_get_contents($file);
        $start = strpos($source, 'function ' . $methodName . '(');
        self::assertNotFalse($start, "Method $methodName not found in " . basename($file));

        $candidates = array_filter([
            strpos($source, "\n    public function ", $start + 1),
            strpos($source, "\n    public static function ", $start + 1),
            strpos($source, "\n    protected function ", $start + 1),
            strpos($source, "\n    private function ", $start + 1),
        ], static fn($v) => $v !== false);
        $stop = $candidates ? min($candidates) : strlen($source);

        return substr($source, $start, $stop - $start);
    }

    public function testFilterUsersNamesItsColumns(): void
    {
        $method = $this->extractMethod(dirname(APPLICATION_PATH) . '/library/Episciences/User.php', 'filterUsers');

        self::assertMatchesRegularExpression('/from\(T_CAS_USERS,\s*self::FILTER_USERS_COLUMNS\)/', $method);
        self::assertDoesNotMatchRegularExpression('/from\(T_CAS_USERS\)/', $method);
    }

    public function testFilterUsersColumnsExcludeCredentials(): void
    {
        $constant = (new \ReflectionClass(\Episciences_User::class))->getReflectionConstant('FILTER_USERS_COLUMNS');
        self::assertNotFalse($constant);

        $columns = array_map('strtoupper', $constant->getValue());
        self::assertNotContains('PASSWORD', $columns);
        self::assertNotContains('*', $columns);
    }

    public function testGetmailsReturnsOnlyAutocompleteFields(): void
    {
        $method = $this->extractMethod(
            APPLICATION_PATH . '/modules/common/controllers/UserDefaultController.php',
            'getmailsAction'
        );

        self::assertStringNotContainsString('as &$user', $method, 'whole rows must not be echoed back');
        self::assertStringContainsString("'mail' =>", $method);
        self::assertStringNotContainsString('PASSWORD', $method);
    }
}
