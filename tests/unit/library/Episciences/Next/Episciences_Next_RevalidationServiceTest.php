<?php

namespace unit\library\Episciences\Next;

use Episciences\Next\RevalidationService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences\Next\RevalidationService.
 *
 * HTTP behaviour (postRevalidation with a live server) and queue persistence
 * (enqueueTag → DB) are excluded — they require integration test setup.
 *
 * @covers \Episciences\Next\RevalidationService
 */
class Episciences_Next_RevalidationServiceTest extends TestCase
{
    private const TEST_RVCODE = 'phpunit-next-revalidation-test';

    private static string $testConfigDir;
    private static string $testConfigFile;

    // =========================================================================
    // Setup / teardown
    // =========================================================================

    public static function setUpBeforeClass(): void
    {
        self::$testConfigDir = APPLICATION_PATH . '/../data/' . self::TEST_RVCODE . '/config';
        self::$testConfigFile = self::$testConfigDir . '/pwd.json';
    }

    protected function tearDown(): void
    {
        if (file_exists(self::$testConfigFile)) {
            unlink(self::$testConfigFile);
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::removeDirectory(APPLICATION_PATH . '/../data/' . self::TEST_RVCODE);
    }

    // =========================================================================
    // resolveToken() — no journal config file
    // =========================================================================

    public function testResolveToken_NoFile_ReturnsEmptyString(): void
    {
        // No data/{rvcode}/config/pwd.json exists for this rvcode
        $token = RevalidationService::resolveToken(self::TEST_RVCODE);
        self::assertSame('', $token);
    }

    // =========================================================================
    // resolveToken() — with journal config file
    // =========================================================================

    public function testResolveToken_ValidToken_ReturnsToken(): void
    {
        $this->writeConfig(['NEXT_REVALIDATION_TOKEN' => 'secret-abc-123']);

        $token = RevalidationService::resolveToken(self::TEST_RVCODE);

        self::assertSame('secret-abc-123', $token);
    }

    public function testResolveToken_EmptyTokenInFile_ReturnsEmptyString(): void
    {
        $this->writeConfig(['NEXT_REVALIDATION_TOKEN' => '']);

        $token = RevalidationService::resolveToken(self::TEST_RVCODE);

        // Empty string is treated as absent → falls back to global constant (undefined → '')
        self::assertSame('', $token);
    }

    public function testResolveToken_MissingKeyInFile_ReturnsEmptyString(): void
    {
        $this->writeConfig(['OTHER_KEY' => 'irrelevant']);

        $token = RevalidationService::resolveToken(self::TEST_RVCODE);

        self::assertSame('', $token);
    }

    public function testResolveToken_MalformedJson_ReturnsEmptyString(): void
    {
        if (!is_dir(self::$testConfigDir)) {
            mkdir(self::$testConfigDir, 0755, true);
        }
        file_put_contents(self::$testConfigFile, '{not valid json}');

        $token = RevalidationService::resolveToken(self::TEST_RVCODE);

        self::assertSame('', $token);
    }

    // =========================================================================
    // isEnabled() — tested via public API (EPISCIENCES_ENABLE_NEXT_FRONT unset)
    // =========================================================================

    public function testEnqueueTag_FeatureFlagOff_DoesNotThrow(): void
    {
        // EPISCIENCES_ENABLE_NEXT_FRONT is not defined in the test environment → no-op
        RevalidationService::enqueueTag('epijinfo', 'article-42');
        self::assertTrue(true); // reached without exception or DB call
    }

    public function testEnqueuTags_FeatureFlagOff_DoesNotThrow(): void
    {
        RevalidationService::enqueueTags('epijinfo', ['article-42', 'articles-epijinfo']);
        self::assertTrue(true);
    }

    public function testRevalidateOrEnqueue_FeatureFlagOff_DoesNotThrow(): void
    {
        RevalidationService::revalidateOrEnqueue('epijinfo', 'about-epijinfo');
        self::assertTrue(true);
    }

    // =========================================================================
    // postRevalidation() — NEXT_BASE_URL not defined
    // =========================================================================

    public function testPostRevalidation_NoBaseUrl_ReturnsZero(): void
    {
        // NEXT_BASE_URL is not defined in the test environment
        $status = RevalidationService::postRevalidation('epijinfo', 'article-42');
        self::assertSame(0, $status);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function writeConfig(array $data): void
    {
        if (!is_dir(self::$testConfigDir)) {
            mkdir(self::$testConfigDir, 0755, true);
        }
        file_put_contents(self::$testConfigFile, json_encode($data, JSON_THROW_ON_ERROR));
    }

    private static function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (array_diff(scandir($path), ['.', '..']) as $entry) {
            $full = $path . '/' . $entry;
            is_dir($full) ? self::removeDirectory($full) : unlink($full);
        }
        rmdir($path);
    }
}
