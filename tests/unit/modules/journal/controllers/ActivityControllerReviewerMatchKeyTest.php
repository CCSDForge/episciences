<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Behavioural tests for ActivityController::reviewerMatchKey() — the name-based
 * fallback used by buildReviewTableBlock() to group a reviewer's log entries when
 * none of them carry a uid. Pure static method (no $this, no DB), so it's invoked
 * directly by reflection.
 *
 * @covers ActivityController::reviewerMatchKey
 */
final class ActivityControllerReviewerMatchKeyTest extends TestCase
{
    private ReflectionMethod $method;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/ActivityController.php';

        $this->method = new ReflectionMethod(\ActivityController::class, 'reviewerMatchKey');
        $this->method->setAccessible(true);
    }

    private function key(string $fullName): string
    {
        return $this->method->invoke(null, $fullName);
    }

    public function testIsCaseInsensitive(): void
    {
        self::assertSame($this->key('Marie Dupont'), $this->key('MARIE DUPONT'));
    }

    public function testCollapsesRegularWhitespace(): void
    {
        self::assertSame($this->key('Marie Dupont'), $this->key('Marie   Dupont'));
    }

    public function testCollapsesNonBreakingSpaces(): void
    {
        self::assertSame($this->key('Marie Dupont'), $this->key("Marie\xC2\xA0Dupont"));
    }

    public function testIsWordOrderInsensitive(): void
    {
        self::assertSame($this->key('Marie Dupont'), $this->key('Dupont Marie'));
    }

    /**
     * Guards the fix made alongside the uid-extraction bug: this key must be
     * accent-insensitive, the same way Episciences_Reviewer_AccountResolver::normalize()
     * is for account matching — otherwise the same reviewer's name logged once with and
     * once without accents (e.g. a legacy row predating a data cleanup) would fail to
     * dedupe in the per-reviewer recap table.
     */
    public function testIsAccentInsensitive(): void
    {
        self::assertSame($this->key('Émile Zola'), $this->key('Emile Zola'));
    }

    public function testDifferentNamesProduceDifferentKeys(): void
    {
        self::assertNotSame($this->key('Marie Dupont'), $this->key('Jean Martin'));
    }
}
