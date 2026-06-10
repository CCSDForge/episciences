<?php

namespace unit\library\Episciences;

use PHPUnit\Framework\TestCase;

/**
 * Behavioural rule for Episciences_User::saveUserRoles(): it persists only the roles
 * the current user is allowed to assign (its "editable roles").
 *
 * saveUserRoles() hits the database (DELETE/INSERT), so it cannot be exercised
 * end-to-end without a real connection. Instead we:
 *   1. assert, by source analysis, that the submitted roles are intersected with the
 *      caller's editable roles before the INSERT, and
 *   2. exercise that exact expression to document the resulting behaviour.
 *
 * @coversNothing
 */
final class Episciences_UserSaveRolesFilterTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(
            realpath(__DIR__ . '/../../../../library/Episciences/User.php')
        );
    }

    private function extractSaveUserRoles(): string
    {
        $start = strpos($this->source, 'function saveUserRoles(');
        self::assertNotFalse($start, 'saveUserRoles() not found in Episciences_User');
        $end = strpos($this->source, "\n    public function ", $start + 1);
        return substr($this->source, $start, ($end === false ? strlen($this->source) : $end) - $start);
    }

    // -----------------------------------------------------------------------
    // Source analysis: the filter is wired before persistence
    // -----------------------------------------------------------------------

    public function testRolesAreIntersectedWithEditableRoles(): void
    {
        $method = $this->extractSaveUserRoles();
        self::assertMatchesRegularExpression(
            '/array_intersect\(.*\$roles.*array_keys\(\$editableRoles\)\)/s',
            $method,
            'saveUserRoles() must intersect the submitted roles with the editable roles'
        );
    }

    public function testFilterRunsBeforeTheInsert(): void
    {
        $method = $this->extractSaveUserRoles();
        $filterPos = strpos($method, 'array_intersect');
        $insertPos = stripos($method, 'INSERT IGNORE');
        self::assertNotFalse($filterPos, 'saveUserRoles() must filter the roles');
        self::assertNotFalse($insertPos, 'saveUserRoles() must still perform the INSERT');
        self::assertLessThan($insertPos, $filterPos,
            'the role filter must run before the INSERT');
    }

    // -----------------------------------------------------------------------
    // Behavioural: the filtering expression itself (mirrors the source)
    // -----------------------------------------------------------------------

    /**
     * Replicates the expression used in saveUserRoles():
     *   array_values(array_intersect((array)$roles, array_keys($editableRoles)))
     *
     * @param list<string> $submitted
     * @param array<string,string> $editable role => role
     * @param list<string> $expected
     * @dataProvider filteringCases
     */
    public function testFilteringExpressionKeepsOnlyEditableRoles(array $submitted, array $editable, array $expected): void
    {
        $filtered = array_values(array_intersect($submitted, array_keys($editable)));
        self::assertSame($expected, $filtered);
    }

    /**
     * @return iterable<string, array{list<string>, array<string,string>, list<string>}>
     */
    public static function filteringCases(): iterable
    {
        // getEditableRoles() returns [reviewer => reviewer] when the caller may only
        // manage reviewers.
        $reviewerOnly = ['reviewer' => 'reviewer'];

        yield 'roles outside the editable set are dropped' => [
            ['chief_editor', 'administrator', 'secretary'], $reviewerOnly, [],
        ];
        yield 'only the editable role is kept from a mixed payload' => [
            ['reviewer', 'epiadmin'], $reviewerOnly, ['reviewer'],
        ];
        yield 'an editable role is kept' => [
            ['reviewer'], $reviewerOnly, ['reviewer'],
        ];

        // A wider editable set keeps the corresponding roles untouched.
        $wide = [
            'epiadmin' => 'epiadmin', 'chief_editor' => 'chief_editor',
            'administrator' => 'administrator', 'secretary' => 'secretary',
            'editor' => 'editor', 'guest_editor' => 'guest_editor', 'reviewer' => 'reviewer',
        ];
        yield 'a role within a wider editable set is kept' => [
            ['administrator'], $wide, ['administrator'],
        ];
        yield 'a multi-role payload within the editable set is preserved' => [
            ['editor', 'reviewer'], $wide, ['editor', 'reviewer'],
        ];
    }
}
