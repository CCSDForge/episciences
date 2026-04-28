<?php
declare(strict_types=1);

namespace unit\library\Episciences\paper;

use Episciences_Paper_Visits;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for Episciences_Paper_Visits.
 *
 * Focuses on pure static helpers that require no DB or HTTP context:
 * - getUserAgent()
 * - countAccessMetricForDocIds() defensive path (empty input)
 * - anonymizeClientIp() (deprecated, still under test while kept)
 * - constants
 */
class Episciences_Paper_VisitsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public function testConsultTypeNoticeConstant(): void
    {
        $this->assertSame('notice', Episciences_Paper_Visits::CONSULT_TYPE_NOTICE);
    }

    public function testConsultTypeFileConstant(): void
    {
        $this->assertSame('file', Episciences_Paper_Visits::CONSULT_TYPE_FILE);
    }

    public function testPageCountMetricsNameConstant(): void
    {
        $this->assertSame('page_count', Episciences_Paper_Visits::PAGE_COUNT_METRICS_NAME);
    }

    public function testFileCountMetricsNameConstant(): void
    {
        $this->assertSame('file_count', Episciences_Paper_Visits::FILE_COUNT_METRICS_NAME);
    }

    // -------------------------------------------------------------------------
    // getUserAgent()
    // -------------------------------------------------------------------------

    protected function tearDown(): void
    {
        // Restore $_SERVER after each test
        unset($_SERVER['HTTP_USER_AGENT']);
    }

    public function testGetUserAgent_NoServerEntry_ReturnsUnknown(): void
    {
        unset($_SERVER['HTTP_USER_AGENT']);
        $this->assertSame('Unknown', Episciences_Paper_Visits::getUserAgent());
    }

    public function testGetUserAgent_EmptyString_ReturnsUnknown(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = '';
        $this->assertSame('Unknown', Episciences_Paper_Visits::getUserAgent());
    }

    public function testGetUserAgent_NormalUa_ReturnsString(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        $result = Episciences_Paper_Visits::getUserAgent();
        $this->assertStringContainsString('Mozilla', $result);
    }

    public function testGetUserAgent_TruncatesAt2000Chars(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = str_repeat('A', 3000);
        $result = Episciences_Paper_Visits::getUserAgent();
        $this->assertLessThanOrEqual(2000, strlen($result));
    }

    public function testGetUserAgent_ExactlyAt2000_NotTruncated(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = str_repeat('B', 2000);
        $result = Episciences_Paper_Visits::getUserAgent();
        $this->assertSame(2000, strlen($result));
    }

    public function testGetUserAgent_SanitizesHtmlTags(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = '<script>alert(1)</script>';
        $result = Episciences_Paper_Visits::getUserAgent();
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testGetUserAgent_ReturnsString(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'SomeAgent/1.0';
        $this->assertIsString(Episciences_Paper_Visits::getUserAgent());
    }

    // -------------------------------------------------------------------------
    // countAccessMetricForDocIds() — empty-input guard (no DB required)
    // -------------------------------------------------------------------------

    /**
     * @return array<string, int>
     */
    private function callCountAccessMetricForDocIds(array $docIds): array
    {
        $method = new ReflectionMethod(Episciences_Paper_Visits::class, 'countAccessMetricForDocIds');
        $method->setAccessible(true);
        /** @var array<string, int> */
        return $method->invoke(null, $docIds);
    }

    public function testCountAccessMetric_EmptyInput_ReturnsZeros(): void
    {
        $result = $this->callCountAccessMetricForDocIds([]);
        $this->assertSame(0, $result[Episciences_Paper_Visits::PAGE_COUNT_METRICS_NAME]);
        $this->assertSame(0, $result[Episciences_Paper_Visits::FILE_COUNT_METRICS_NAME]);
    }

    public function testCountAccessMetric_AllNegativeIds_ReturnsZeros(): void
    {
        $result = $this->callCountAccessMetricForDocIds([-1, -5, 0]);
        $this->assertSame(0, $result[Episciences_Paper_Visits::PAGE_COUNT_METRICS_NAME]);
        $this->assertSame(0, $result[Episciences_Paper_Visits::FILE_COUNT_METRICS_NAME]);
    }

    public function testCountAccessMetric_NonIntegerIds_AreFilteredOut(): void
    {
        // 'abc' → intval → 0 → filtered; '3.7' → intval → 3 → kept
        // With no DB, any valid docId will trigger the DB path and return zeros (no adapter).
        $result = $this->callCountAccessMetricForDocIds(['abc', 0, -2]);
        $this->assertSame(0, $result[Episciences_Paper_Visits::PAGE_COUNT_METRICS_NAME]);
        $this->assertSame(0, $result[Episciences_Paper_Visits::FILE_COUNT_METRICS_NAME]);
    }

    public function testCountAccessMetric_ReturnsExpectedKeys(): void
    {
        $result = $this->callCountAccessMetricForDocIds([]);
        $this->assertArrayHasKey(Episciences_Paper_Visits::PAGE_COUNT_METRICS_NAME, $result);
        $this->assertArrayHasKey(Episciences_Paper_Visits::FILE_COUNT_METRICS_NAME, $result);
    }

    public function testCountAccessMetric_ReturnTypeIsAlwaysArray(): void
    {
        $result = $this->callCountAccessMetricForDocIds([]);
        $this->assertIsArray($result);
    }

    // -------------------------------------------------------------------------
    // anonymizeClientIp() — deprecated but kept; test via reflection
    // -------------------------------------------------------------------------

    private function callAnonymizeClientIp(string $ip): string
    {
        $method = new ReflectionMethod(Episciences_Paper_Visits::class, 'anonymizeClientIp');
        $method->setAccessible(true);
        /** @var string */
        return $method->invoke(null, $ip);
    }

    public function testAnonymizeClientIp_MasksLastTwoOctets(): void
    {
        $this->assertSame('91.120.0.0', $this->callAnonymizeClientIp('91.120.10.45'));
    }

    public function testAnonymizeClientIp_InvalidIp_ReturnsFallback(): void
    {
        $this->assertSame('127.0.0.1', $this->callAnonymizeClientIp('not-an-ip'));
    }

    public function testAnonymizeClientIp_LoopbackPassedThrough(): void
    {
        // IpAnonymizer on 127.0.0.1 → '127.0.0.0' (mask applied), not '127.0.0.1' fallback
        $result = $this->callAnonymizeClientIp('127.0.0.1');
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testAnonymizeClientIp_EmptyString_ReturnsFallback(): void
    {
        $this->assertSame('127.0.0.1', $this->callAnonymizeClientIp(''));
    }
}
