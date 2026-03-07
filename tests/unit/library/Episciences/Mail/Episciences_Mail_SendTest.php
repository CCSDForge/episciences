<?php

namespace unit\library\Episciences\Mail;

use Episciences_Mail_Send;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for Episciences_Mail_Send.
 *
 * getForm() is DB-dependent (PapersManager, Auth). XSS fix is verified via
 * source inspection.
 *
 * Bugs documented:
 *   S2 — getForm(): co-author fullname and email injected into htmlLabel string
 *        without htmlspecialchars() → stored XSS via user-controlled name/email fields.
 *        Fix: htmlspecialchars(..., ENT_QUOTES, 'UTF-8') around both values.
 *
 * @covers Episciences_Mail_Send
 */
final class Episciences_Mail_SendTest extends TestCase
{
    // =========================================================================
    // Security S2 — XSS in getForm() co-author htmlLabel
    // =========================================================================

    /**
     * Regression S2: getForm() injects co-author fullname and email into an HTML
     * label without escaping — allows stored XSS if a co-author's name/email
     * contains HTML special characters.
     * The fix wraps both with htmlspecialchars(..., ENT_QUOTES, 'UTF-8').
     */
    public function testGetFormEscapesCoAuthorFullName(): void
    {
        $method = new ReflectionMethod(Episciences_Mail_Send::class, 'getForm');
        $lines  = file($method->getFileName());
        $source = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        self::assertMatchesRegularExpression(
            '/htmlspecialchars\s*\(\s*\$coAuthor->getFullName\(\)/',
            $source,
            'Security S2: co-author fullname must be escaped with htmlspecialchars() in htmlLabel'
        );
    }

    public function testGetFormEscapesCoAuthorEmail(): void
    {
        $method = new ReflectionMethod(Episciences_Mail_Send::class, 'getForm');
        $lines  = file($method->getFileName());
        $source = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        self::assertMatchesRegularExpression(
            '/htmlspecialchars\s*\(\s*\$coAuthor->getEmail\(\)/',
            $source,
            'Security S2: co-author email must be escaped with htmlspecialchars() in htmlLabel'
        );
    }

    public function testGetFormUsesEntQuotesEncoding(): void
    {
        $method = new ReflectionMethod(Episciences_Mail_Send::class, 'getForm');
        $lines  = file($method->getFileName());
        $source = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        self::assertStringContainsString(
            'ENT_QUOTES',
            $source,
            'Security S2: htmlspecialchars() in getForm() must use ENT_QUOTES to prevent attribute-context XSS'
        );
    }

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
}
