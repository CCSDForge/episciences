<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReviewController;

/**
 * ReviewController::licensesAction (journal licenses page).
 *
 * Strategy: source-code pattern analysis — no database or HTTP dispatch needed.
 */
final class ReviewControllerLicensesTest extends TestCase
{
    private static function licensesActionSource(): string
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/ReviewController.php';

        $method = new ReflectionMethod(ReviewController::class, 'licensesAction');
        $lines = file($method->getFileName());

        return implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
    }

    /**
     * Bug: setOptions() always replaces the DOI settings of the review — the instance cached
     * by Episciences_ReviewsManager — with settings built from the license keys only (empty).
     */
    public function testLicensesActionDoesNotUseSetOptions(): void
    {
        $source = self::licensesActionSource();

        self::assertStringNotContainsString(
            '->setOptions(',
            $source,
            'BUG: setOptions() resets the DOI settings of the cached review; use setSetting()'
        );
        self::assertStringContainsString('setSetting(Episciences_Review::SETTING_LICENSE_DEFAULT', $source);
        self::assertStringContainsString('Episciences_Review::SETTING_LICENSES_ALLOWED,', $source);
    }

    /**
     * Episciences_ReviewsManager::find() may return false: it must be checked before loadSettings().
     */
    public function testLicensesActionChecksReviewBeforeLoadingSettings(): void
    {
        $source = self::licensesActionSource();

        $guardPos = strpos($source, '!$review instanceof Episciences_Review');
        $loadPos = strpos($source, '$review->loadSettings()');

        self::assertNotFalse($guardPos, 'missing review guard in licensesAction');
        self::assertNotFalse($loadPos, 'loadSettings() call not found in licensesAction');
        self::assertLessThan($loadPos, $guardPos, 'the review must be checked before loadSettings()');
        self::assertStringContainsString('setHttpResponseCode(404)', $source);
    }
}
