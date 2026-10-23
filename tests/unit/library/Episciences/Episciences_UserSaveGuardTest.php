<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use PHPUnit\Framework\TestCase;

/**
 * Structural regression guard for Episciences_User::save() (D7): a failed CAS
 * write (Ccsd_User_Models_UserMapper::save() returning false) must abort
 * before touching T_USERS, instead of silently continuing with a stale/wrong
 * UID.
 *
 * This is a structural guard, not a behavioral one: exercising the actual
 * failure path requires the auth database, which this suite does not have.
 * It only proves the early-return is present and precedes the T_USERS writes.
 *
 * @covers \Episciences_User
 */
final class Episciences_UserSaveGuardTest extends TestCase
{
    private string $method;

    protected function setUp(): void
    {
        $source = (string)file_get_contents(
            APPLICATION_PATH . '/../library/Episciences/User.php'
        );

        $start = strpos($source, 'public function save(');
        self::assertNotFalse($start, 'Episciences_User::save() not found');

        $end = strpos($source, "\n    public function ", $start + 1);
        self::assertNotFalse($end, 'could not find the end of Episciences_User::save()');

        $this->method = substr($source, $start, $end - $start);
    }

    public function testSaveChecksForCasWriteFailure(): void
    {
        self::assertMatchesRegularExpression('/\$casId\s*===\s*false/', $this->method,
            'save() must check for a failed CAS write ($casId === false)');
    }

    public function testSaveAbortsBeforeWritingLocalDataOnCasFailure(): void
    {
        $guardPos = preg_match('/\$casId\s*===\s*false/', $this->method, $matches, PREG_OFFSET_CAPTURE)
            ? $matches[0][1]
            : false;
        $insertPos = strpos($this->method, 'insert(T_USERS');
        $updatePos = strpos($this->method, 'update(T_USERS');

        self::assertNotFalse($guardPos, 'save() must check for a failed CAS write');
        self::assertNotFalse($insertPos, 'save() must insert into T_USERS on account creation');
        self::assertNotFalse($updatePos, 'save() must update T_USERS on account modification');
        self::assertLessThan($insertPos, $guardPos,
            'the CAS-failure guard must run before the T_USERS insert (D7)');
        self::assertLessThan($updatePos, $guardPos,
            'the CAS-failure guard must run before the T_USERS update (D7)');
    }

    public function testSaveReturnsFalseOnCasWriteFailure(): void
    {
        $guardPos = strpos($this->method, '$casId === false');
        self::assertNotFalse($guardPos, 'save() must check for a failed CAS write');

        $afterGuard = substr($this->method, $guardPos, 220);
        self::assertStringContainsString('return false;', $afterGuard,
            'save() must return false immediately when the CAS write failed (D7)');
    }
}
