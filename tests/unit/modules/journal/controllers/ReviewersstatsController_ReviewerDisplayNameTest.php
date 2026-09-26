<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReviewersstatsController;

/**
 * @covers \ReviewersstatsController
 */
final class ReviewersstatsController_ReviewerDisplayNameTest extends TestCase
{
    private ReflectionMethod $method;

    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/ReviewersstatsController.php';
    }

    protected function setUp(): void
    {
        $this->method = new ReflectionMethod(ReviewersstatsController::class, 'reviewerDisplayName');
        $this->method->setAccessible(true);
    }

    /**
     * @param array<string, mixed>|null $profile
     * @param array<int, array<string, mixed>> $rows
     */
    private function displayName(?array $profile, array $rows, ?string $email): string
    {
        return $this->method->invoke(null, $profile, $rows, $email, 1, 'Deleted account');
    }

    public function testNameComesFromTheVisibleReviewersProfile(): void
    {
        $rows = [['uid' => 5, 'tmp_user' => 0]];

        self::assertSame('Jane Doe', $this->displayName(['uid' => 5, 'SCREEN_NAME' => 'Jane Doe'], $rows, 'jane@x.com'));
    }

    public function testRowsWithoutALoadableAccountShowTheDeletedLabel(): void
    {
        self::assertSame('Deleted account', $this->displayName(null, [['uid' => 5, 'tmp_user' => 0]], null));
    }

    public function testNoVisibleRowOnlyEchoesTheSearchedEmail(): void
    {
        self::assertSame('jane@x.com', $this->displayName(null, [], 'jane@x.com'));
        self::assertSame('', $this->displayName(null, [], null));
    }

}
