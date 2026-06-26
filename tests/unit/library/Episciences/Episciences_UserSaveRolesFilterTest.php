<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use PHPUnit\Framework\TestCase;

/**
 * Behavioural rule: only the roles the current user is allowed to assign
 * ("editable roles") may reach the database.
 *
 * History:
 *   - The filter lived in saveUserRoles() until commit 7c585452, which removed
 *     it because getEditableRoles() returns [] for non-secretary callers, causing
 *     programmatic calls (e.g. invitation acceptance) to silently drop roles.
 *   - The filter was moved to saverolesAction() — the only untrusted entry point
 *     where form-submitted roles need to be constrained.
 *
 * Tests 1 & 2: source analysis — the filter is wired in the controller action.
 * Test 3: behavioural — the filtering expression itself is correct.
 *
 * @coversNothing
 */
final class Episciences_UserSaveRolesFilterTest extends TestCase
{
    private string $controllerSource;

    protected function setUp(): void
    {
        $this->controllerSource = (string) file_get_contents(
            realpath(__DIR__ . '/../../../../application/modules/common/controllers/UserDefaultController.php')
        );
    }

    private function extractSaverolesAction(): string
    {
        $start = strpos($this->controllerSource, 'function saverolesAction(');
        self::assertNotFalse($start, 'saverolesAction() not found in UserDefaultController');
        $end = strpos($this->controllerSource, "\n    public function ", $start + 1);
        return substr(
            $this->controllerSource,
            $start,
            ($end === false ? strlen($this->controllerSource) : $end) - $start
        );
    }

    // -----------------------------------------------------------------------
    // Source analysis: the filter is wired before persistence
    // -----------------------------------------------------------------------

    public function testRolesAreIntersectedWithEditableRoles(): void
    {
        $method = $this->extractSaverolesAction();
        self::assertMatchesRegularExpression(
            '/array_intersect\(.*\$roles.*array_keys\(\$editableRoles\)\)/s',
            $method,
            'saverolesAction() must intersect the submitted roles with the editable roles'
        );
    }

    public function testFilterRunsBeforeTheInsert(): void
    {
        $method = $this->extractSaverolesAction();
        $filterPos = strpos($method, 'array_intersect');
        $savePos   = strpos($method, 'saveUserRoles(');
        self::assertNotFalse($filterPos, 'saverolesAction() must filter the roles');
        self::assertNotFalse($savePos,   'saverolesAction() must still call saveUserRoles()');
        self::assertLessThan($savePos, $filterPos,
            'the role filter must run before saveUserRoles()');
    }

    // -----------------------------------------------------------------------
    // Behavioural: the filtering expression itself (mirrors the source)
    // -----------------------------------------------------------------------

    /**
     * Replicates the expression used in saverolesAction():
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