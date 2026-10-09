<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences_User;
use PHPUnit\Framework\TestCase;

/**
 * Roles removed by a roles save are limited to the roles the caller may edit.
 *
 * @covers \Episciences_User::computeRemovedRoles
 */
final class Episciences_UserComputeRemovedRolesTest extends TestCase
{
    public function testRolesOutsideTheEditableSetAreNeverRemoved(): void
    {
        self::assertSame([], Episciences_User::computeRemovedRoles(['chief_editor', 'editor'], [], ['reviewer']));
    }

    public function testNoEditableRolesRemovesNothing(): void
    {
        self::assertSame([], Episciences_User::computeRemovedRoles(['editor', 'reviewer'], [], []));
    }

    public function testEditableRoleNoLongerSubmittedIsRemoved(): void
    {
        self::assertSame(['reviewer'], Episciences_User::computeRemovedRoles(['chief_editor', 'reviewer'], [], ['reviewer']));
    }

    public function testKeptRoleIsNotRemoved(): void
    {
        self::assertSame(['editor'], Episciences_User::computeRemovedRoles(['editor', 'reviewer'], ['reviewer'], ['editor', 'reviewer']));
    }
}
