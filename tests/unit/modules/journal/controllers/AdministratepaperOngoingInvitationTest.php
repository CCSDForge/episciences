<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Paper;
use Episciences_User_Assignment;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use unit\modules\ControllerTestCase;
use Zend_Controller_Response_HttpTestCase;

/**
 * A reviewer with a pending or accepted invitation for a paper is not invited again
 * (AdministratepaperController::savereviewerinvitationAction()), nor offered by the invitation page
 * (AdministratepaperController::invitereviewerAction()).
 */
final class AdministratepaperOngoingInvitationTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        self::loadController('journal', 'AdministratepaperController');
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private static function invitations(): array
    {
        return [
            Episciences_User_Assignment::STATUS_ACTIVE => [
                ['UID' => '10', 'TMP_USER' => '0', 'reviewer' => ['email' => 'accepted@example.org']],
            ],
            Episciences_User_Assignment::STATUS_PENDING => [
                ['UID' => '20', 'TMP_USER' => '0', 'reviewer' => ['email' => 'pending@example.org']],
                ['UID' => '30', 'TMP_USER' => '1', 'reviewer' => ['email' => 'tmp@example.org']],
            ],
            Episciences_User_Assignment::STATUS_INACTIVE => [],
            Episciences_User_Assignment::STATUS_EXPIRED => [],
            Episciences_User_Assignment::STATUS_CANCELLED => [],
        ];
    }

    private function invokePrivate(string $name, mixed ...$args): mixed
    {
        $controller = new \AdministratepaperController($this->getRequest(), new Zend_Controller_Response_HttpTestCase());
        $method = new ReflectionMethod(\AdministratepaperController::class, $name);

        return $method->invoke($controller, ...$args);
    }

    private function hasOngoingInvitation(int $uid, bool $isTmpUser, string $email): bool
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getInvitations')->willReturn(self::invitations());

        return (bool)$this->invokePrivate('hasOngoingReviewerInvitation', $paper, $uid, $isTmpUser, $email);
    }

    /**
     * @return array<string, array{int, bool, string, bool}>
     */
    public static function reviewerProvider(): array
    {
        return [
            'accepted invitation' => [10, false, '', true],
            'pending invitation' => [20, false, '', true],
            'pending invitation of a temporary account' => [30, true, '', true],
            'registered user with the id of a temporary account' => [30, false, '', false],
            'temporary account with the id of a registered user' => [20, true, '', false],
            'not invited' => [40, false, '', false],
            'new temporary account with the address of an invited temporary account' => [0, true, ' TMP@Example.org ', true],
            'new temporary account with the address of an invited user' => [0, true, 'pending@example.org', true],
            'new temporary account with another address' => [0, true, 'other@example.org', false],
            'new temporary account without address' => [0, true, '', false],
            'no id and not a temporary account' => [0, false, 'pending@example.org', false],
        ];
    }

    #[DataProvider('reviewerProvider')]
    public function testOnlyAPendingOrAcceptedInvitationOfTheSameReviewerCounts(int $uid, bool $isTmpUser, string $email, bool $expected): void
    {
        self::assertSame($expected, $this->hasOngoingInvitation($uid, $isTmpUser, $email));
    }

    public function testTheIgnoreListHoldsTheUserIdsOfTheOngoingInvitationsOnly(): void
    {
        self::assertSame([10, 20], $this->invokePrivate('ongoingInvitationUserIds', self::invitations()));
    }

    public function testTheIgnoreListIsEmptyWithoutInvitation(): void
    {
        self::assertSame([], $this->invokePrivate('ongoingInvitationUserIds', []));
    }
}
