<?php

declare(strict_types=1);

namespace unit\library\Episciences\Reviewer;

use Episciences\Reviewer\InvitationEligibility;
use Episciences_Paper;
use PHPUnit\Framework\Attributes\DataProvider;
use unit\modules\ControllerTestCase;

/**
 * Whether a person may be invited to review a version, and why not (git #1387).
 *
 * Extends ControllerTestCase for its database stub.
 */
final class InvitationEligibilityTest extends ControllerTestCase
{
    private const AUTHOR = 99;
    private const LOGGED = 42;

    private static function eligibility(): InvitationEligibility
    {
        return new InvitationEligibility(self::AUTHOR, self::LOGGED, [7], [8], ['Invited@Example.org']);
    }

    /**
     * @return array<string, array{int|null, string, string|null}>
     */
    public static function personProvider(): array
    {
        return [
            'author' => [self::AUTHOR, '', InvitationEligibility::REASON_AUTHOR],
            'logged user' => [self::LOGGED, '', InvitationEligibility::REASON_SELF],
            'conflict of interest declared' => [7, '', InvitationEligibility::REASON_CONFLICT],
            'account already invited' => [8, '', InvitationEligibility::REASON_ALREADY_INVITED],
            'account whose address is already invited' => [9, ' invited@example.org', InvitationEligibility::REASON_ALREADY_INVITED],
            'person without an account already invited' => [null, 'INVITED@example.org', InvitationEligibility::REASON_ALREADY_INVITED],
            'account that may be invited' => [9, 'other@example.org', null],
            'person without an account that may be invited' => [null, 'new@example.org', null],
        ];
    }

    #[DataProvider('personProvider')]
    public function testWhyAPersonMayNotBeInvited(?int $uid, string $email, ?string $expected): void
    {
        self::assertSame($expected, self::eligibility()->refusalFor($uid, $email));
    }

    public function testTheInvitationsInProgressOnTheVersionAreRead(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getDocid')->willReturn(30);
        $paper->method('getUid')->willReturn(self::AUTHOR);
        $this->db->respondTo('/FROM `USER_ASSIGNMENT`/', [
            ['UID' => 8, 'TMP_USER' => 0, 'EMAIL' => 'eight@example.org'],
            // a person without an account: their id is not an account
            ['UID' => 9, 'TMP_USER' => 1, 'EMAIL' => 'tmp@example.org'],
        ]);

        $eligibility = InvitationEligibility::forVersion($paper, self::LOGGED, []);

        self::assertSame(InvitationEligibility::REASON_ALREADY_INVITED, $eligibility->refusalFor(8));
        self::assertNull($eligibility->refusalFor(9, 'nine@example.org'));
        self::assertSame(InvitationEligibility::REASON_ALREADY_INVITED, $eligibility->refusalFor(null, 'tmp@example.org'));
        self::assertSame(InvitationEligibility::REASON_AUTHOR, $eligibility->refusalFor(self::AUTHOR));

        $sql = $this->db->selects()[0];
        self::assertStringContainsString('ITEMID = 30', $sql);
        self::assertStringContainsString("a.STATUS IN ('pending', 'active')", $sql);
    }
}
