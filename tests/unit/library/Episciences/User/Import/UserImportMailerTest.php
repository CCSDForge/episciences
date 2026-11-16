<?php

declare(strict_types=1);

namespace unit\library\Episciences\User\Import;

use Episciences\User\Import\UserImportMailer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Episciences\User\Import\UserImportMailer
 */
final class UserImportMailerTest extends TestCase
{
    public function testMemberIsNotListedWithOtherRoles(): void
    {
        self::assertSame(['editor', 'reviewer'], UserImportMailer::displayedRoles(['member', 'editor', 'reviewer']));
    }

    public function testMemberAloneIsListed(): void
    {
        self::assertSame(['member'], UserImportMailer::displayedRoles(['member']));
    }

    public function testTheLinkValidityIsGivenInDays(): void
    {
        self::assertGreaterThanOrEqual(1, UserImportMailer::linkValidityDays());
    }
}
