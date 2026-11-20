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
 * (AdministratepaperController::savereviewerinvitationAction()).
 */
final class AdministratepaperOngoingInvitationTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        self::loadController('journal', 'AdministratepaperController');
    }

    private function hasOngoingInvitation(int $uid, bool $isTmpUser): bool
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getInvitations')->willReturn([
            Episciences_User_Assignment::STATUS_ACTIVE => [['UID' => '10', 'TMP_USER' => '0']],
            Episciences_User_Assignment::STATUS_PENDING => [['UID' => '20', 'TMP_USER' => '0'], ['UID' => '30', 'TMP_USER' => '1']],
            Episciences_User_Assignment::STATUS_INACTIVE => [],
            Episciences_User_Assignment::STATUS_EXPIRED => [],
            Episciences_User_Assignment::STATUS_CANCELLED => [],
        ]);

        $controller = new \AdministratepaperController($this->getRequest(), new Zend_Controller_Response_HttpTestCase());
        $method = new ReflectionMethod(\AdministratepaperController::class, 'hasOngoingReviewerInvitation');

        return (bool)$method->invoke($controller, $paper, $uid, $isTmpUser);
    }

    /**
     * @return array<string, array{int, bool, bool}>
     */
    public static function reviewerProvider(): array
    {
        return [
            'accepted invitation' => [10, false, true],
            'pending invitation' => [20, false, true],
            'pending invitation of a temporary account' => [30, true, true],
            'registered user with the id of a temporary account' => [30, false, false],
            'temporary account with the id of a registered user' => [20, true, false],
            'not invited' => [40, false, false],
        ];
    }

    #[DataProvider('reviewerProvider')]
    public function testOnlyAPendingOrAcceptedInvitationOfTheSameAccountCounts(int $uid, bool $isTmpUser, bool $expected): void
    {
        self::assertSame($expected, $this->hasOngoingInvitation($uid, $isTmpUser));
    }
}
