<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Checks that the account created from a reviewer invitation only receives
 * whitelisted form fields (source-pattern analysis, as in the sibling tests).
 */
final class ReviewerControllerAccountCreationTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(
            APPLICATION_PATH . '/modules/journal/controllers/ReviewerController.php'
        );
    }

    public function testRequestDataIsFilteredBeforeBuildingTheReviewer(): void
    {
        $start = strpos($this->source, 'function createNewReviewerWithoutAccountProcessing(');
        self::assertNotFalse($start);
        $end = strpos($this->source, 'private function', (int) $start + 1);
        $method = substr($this->source, (int) $start, (int) $end - (int) $start);

        $filterPos = strpos($method, 'ACCOUNT_CREATION_FIELDS');
        $buildPos = strpos($method, 'new Episciences_Reviewer(');
        self::assertNotFalse($filterPos);
        self::assertNotFalse($buildPos);
        self::assertLessThan($buildPos, $filterPos);
        self::assertStringContainsString('getUid()', $method);
    }

    public function testWhitelistDoesNotContainPrivilegedFields(): void
    {
        self::assertSame(1, preg_match('/ACCOUNT_CREATION_FIELDS = \[(.*?)\];/s', $this->source, $m));

        foreach (['UID', 'VALID', 'ROLE', 'TIME_REGISTERED', 'UUID'] as $forbidden) {
            self::assertStringNotContainsString("'$forbidden'", $m[1]);
        }
        foreach (['EMAIL', 'FIRSTNAME', 'LASTNAME', 'PASSWORD'] as $expected) {
            self::assertStringContainsString("'$expected'", $m[1]);
        }
    }
}
