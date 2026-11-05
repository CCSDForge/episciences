<?php

namespace unit\library\Episciences\Mail;

use Episciences_Mail_Send;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for Episciences_Mail_Send.
 *
 * getForm() is DB-dependent (PapersManager, Auth). The co-authors data of the form is
 * JSON in hidden inputs (Episciences\Mail\RecipientSuggestions::fromUser()), escaped by the
 * form view helpers: no HTML is built there anymore.
 *
 * @covers Episciences_Mail_Send
 */
final class Episciences_Mail_SendTest extends TestCase
{
    // =========================================================================
    // Escaping logic (pure, no DB)
    // =========================================================================

    /**
     * Verify that htmlspecialchars with ENT_QUOTES neutralises common XSS payloads.
     */
    public function testHtmlspecialcharsNeutralisesScriptTag(): void
    {
        $input    = '<script>alert("xss")</script>';
        $escaped  = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        self::assertStringNotContainsString('<script>', $escaped);
        self::assertStringContainsString('&lt;script&gt;', $escaped);
    }

    public function testHtmlspecialcharsNeutralisesDoubleQuoteInAttribute(): void
    {
        $input   = 'foo" onmouseover="alert(1)';
        $escaped = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        self::assertStringNotContainsString('"', $escaped);
        self::assertStringContainsString('&quot;', $escaped);
    }

    public function testHtmlspecialcharsNeutralisesSingleQuoteInAttribute(): void
    {
        $input   = "foo' onmouseover='alert(1)";
        $escaped = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        self::assertStringNotContainsString("'", $escaped);
        self::assertStringContainsString('&#039;', $escaped);
    }

    public function testHtmlspecialcharsPreservesNormalEmail(): void
    {
        $email   = 'user@example.com';
        $escaped = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

        self::assertSame($email, $escaped);
    }

    // =========================================================================
    // getMailDisplayCode() — the code shown in the default subject of the manual
    // mail-sending form: the review's custom mail display code if set, RVCODE otherwise.
    // =========================================================================

    private function invokeGetMailDisplayCode(): string
    {
        $method = new ReflectionMethod(Episciences_Mail_Send::class, 'getMailDisplayCode');
        $method->setAccessible(true);
        return $method->invoke(null);
    }

    /**
     * Primes Episciences_ReviewsManager's intra-request cache so find(RVCODE) resolves
     * to $review (or `false`, when null) without a DB round-trip.
     */
    private function primeReviewsManagerCache(?\Episciences_Review $review): void
    {
        $property = new \ReflectionProperty(\Episciences_ReviewsManager::class, '_cache');
        $property->setAccessible(true);
        $cache = $property->getValue();
        $cache['rvcode_' . RVCODE] = $review ?? false;
        $property->setValue(null, $cache);
    }

    protected function tearDown(): void
    {
        // Leave no journal in the cache: a primed `false` would make find(RVCODE) fail in the next tests
        \Episciences_ReviewsManager::clearCache();
        parent::tearDown();
    }

    private function reviewWithSetting(?string $mailDisplayCode): \Episciences_Review
    {
        $review = new \Episciences_Review();
        $review->setCode(RVCODE);
        if ($mailDisplayCode !== null) {
            $review->setSetting(\Episciences_Review::SETTING_MAIL_DISPLAY_CODE, $mailDisplayCode);
        }
        return $review;
    }

    public function testGetMailDisplayCodeFallsBackToRvcodeWhenSettingUnset(): void
    {
        $this->primeReviewsManagerCache($this->reviewWithSetting(null));

        self::assertSame(RVCODE, $this->invokeGetMailDisplayCode());
    }

    public function testGetMailDisplayCodeFallsBackToRvcodeWhenSettingBlank(): void
    {
        $this->primeReviewsManagerCache($this->reviewWithSetting('   '));

        self::assertSame(RVCODE, $this->invokeGetMailDisplayCode());
    }

    public function testGetMailDisplayCodeUsesCustomSettingWhenSet(): void
    {
        $this->primeReviewsManagerCache($this->reviewWithSetting('custom-label'));

        self::assertSame('custom-label', $this->invokeGetMailDisplayCode());
    }

    public function testGetMailDisplayCodeTrimsCustomSetting(): void
    {
        $this->primeReviewsManagerCache($this->reviewWithSetting('  custom-label  '));

        self::assertSame('custom-label', $this->invokeGetMailDisplayCode());
    }

    public function testGetMailDisplayCodeFallsBackToRvcodeWhenReviewNotFound(): void
    {
        $this->primeReviewsManagerCache(null);

        self::assertSame(RVCODE, $this->invokeGetMailDisplayCode());
    }

    // =========================================================================
    // sendMailFromReview() — %%PERMANENT_ARTICLE_ID%% must not regress to a
    // per-call DB lookup. $paper is already loaded here (it's how
    // $paper->getDocid() is obtained); passing $paper->getPaperid() lets
    // setDocid() resolve the tag without a query. This method is the shared
    // backbone for most application mails (COI, editor assignment, etc.), not
    // just the reminders cron.
    // =========================================================================

    public function testSendMailFromReviewPassesPaperIdToSetDocid(): void
    {
        $method = new ReflectionMethod(Episciences_Mail_Send::class, 'sendMailFromReview');
        $lines  = file($method->getFileName());
        $source = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        self::assertMatchesRegularExpression(
            '/setDocid\(\s*\$paper->getDocid\(\)\s*,\s*\$paper->getPaperid\(\)\s*\)/',
            $source,
            'BUG: setDocid() must be given $paper->getPaperid() to avoid a DB lookup '
            . 'for %%PERMANENT_ARTICLE_ID%% in sendMailFromReview()'
        );
    }
}
