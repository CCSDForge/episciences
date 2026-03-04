<?php

namespace unit\library\Episciences\Repositories;

use Episciences_Repositories_Common;
use Episciences_Repositories_CryptologyePrint_Hooks;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Repositories_CryptologyePrint_Hooks.
 *
 * All tests are DB-free: only pure static methods are tested.
 *
 * Key constants:
 *   Episciences_Repositories_CryptologyePrint_Hooks::SELF_URL = 'https://eprint.iacr.org/'
 *   Episciences_Repositories_CryptologyePrint_Hooks::SHORT_URL = 'https://ia.cr/'
 *   Episciences_Repositories_Common::META_IDENTIFIER           = 'identifier'
 *
 * @covers Episciences_Repositories_CryptologyePrint_Hooks
 */
class Episciences_Repositories_CryptologyePrint_HooksTest extends TestCase
{
    // =========================================================================
    // hookCleanIdentifiers()
    // =========================================================================

    /**
     * Test 1: Long URL (https://eprint.iacr.org/) prefix is stripped.
     */
    public function testHookCleanIdentifiersStripsLongUrl(): void
    {
        $result = Episciences_Repositories_CryptologyePrint_Hooks::hookCleanIdentifiers([
            'id' => 'https://eprint.iacr.org/2024/123',
        ]);

        self::assertSame(
            [Episciences_Repositories_Common::META_IDENTIFIER => '2024/123'],
            $result
        );
    }

    /**
     * Test 2: Short URL (https://ia.cr/) prefix is stripped.
     */
    public function testHookCleanIdentifiersStripsShortUrl(): void
    {
        $result = Episciences_Repositories_CryptologyePrint_Hooks::hookCleanIdentifiers([
            'id' => 'https://ia.cr/2024/123',
        ]);

        self::assertSame(
            [Episciences_Repositories_Common::META_IDENTIFIER => '2024/123'],
            $result
        );
    }

    /**
     * Test 3: A raw identifier (no URL prefix) is returned as-is.
     */
    public function testHookCleanIdentifiersRawId(): void
    {
        $result = Episciences_Repositories_CryptologyePrint_Hooks::hookCleanIdentifiers([
            'id' => '2024/123',
        ]);

        self::assertSame(
            [Episciences_Repositories_Common::META_IDENTIFIER => '2024/123'],
            $result
        );
    }

    /**
     * Test 4: Trailing slash is stripped by rtrim after URL prefix removal.
     */
    public function testHookCleanIdentifiersTrailingSlash(): void
    {
        $result = Episciences_Repositories_CryptologyePrint_Hooks::hookCleanIdentifiers([
            'id' => 'https://eprint.iacr.org/2024/123/',
        ]);

        self::assertSame(
            [Episciences_Repositories_Common::META_IDENTIFIER => '2024/123'],
            $result
        );
    }

    /**
     * Test 10: hookCleanIdentifiers only strips the URL prefix, NOT the datetime pattern.
     * The datetime stripping happens in hookApiRecords, not hookCleanIdentifiers.
     */
    public function testCleanIdentifierWithDateTimePattern(): void
    {
        $result = Episciences_Repositories_CryptologyePrint_Hooks::hookCleanIdentifiers([
            'id' => 'https://eprint.iacr.org/2024/192/20260205:224930',
        ]);

        self::assertSame(
            [Episciences_Repositories_Common::META_IDENTIFIER => '2024/192/20260205:224930'],
            $result
        );
    }

    // =========================================================================
    // hookVersion()
    // =========================================================================

    /**
     * Test 5: hookVersion() always returns an empty array.
     */
    public function testHookVersionReturnsEmpty(): void
    {
        $result = Episciences_Repositories_CryptologyePrint_Hooks::hookVersion([]);

        self::assertSame([], $result);
    }

    // =========================================================================
    // hookIsRequiredVersion()
    // =========================================================================

    /**
     * Test 6: hookIsRequiredVersion() returns ['result' => false].
     */
    public function testHookIsRequiredVersionReturnsFalse(): void
    {
        $result = Episciences_Repositories_CryptologyePrint_Hooks::hookIsRequiredVersion();

        self::assertSame(['result' => false], $result);
    }

    // =========================================================================
    // hookIsIdentifierCommonToAllVersions()
    // =========================================================================

    /**
     * Test 7: hookIsIdentifierCommonToAllVersions() returns ['result' => true].
     */
    public function testHookIsIdentifierCommonToAllVersionsReturnsTrue(): void
    {
        $result = Episciences_Repositories_CryptologyePrint_Hooks::hookIsIdentifierCommonToAllVersions();

        self::assertSame(['result' => true], $result);
    }

    // =========================================================================
    // Interaction with Episciences_Repositories_Common::getDateTimePattern()
    // =========================================================================

    /**
     * Test 8: Documents how hookApiRecords uses Common::getDateTimePattern() to extract
     * the datetime portion from an identifier of the form YYYY/XXX/YYYYMMDD:HHMMSS.
     */
    public function testGetDateTimePatternInteraction(): void
    {
        $result = Episciences_Repositories_Common::getDateTimePattern('2024/192/20260205:224930');

        self::assertSame('20260205:224930', $result);
    }

    // =========================================================================
    // Interaction with Episciences_Repositories_Common::removeDateTimePattern()
    // =========================================================================

    /**
     * Test 9: Documents how hookApiRecords uses Common::removeDateTimePattern() to clean
     * the identifier before passing it to the OAI endpoint.
     * After removal, rtrim(..., '/') would produce '2024/192'.
     */
    public function testRemoveDateTimePatternInteraction(): void
    {
        $result = Episciences_Repositories_Common::removeDateTimePattern('2024/192/20260205:224930');

        self::assertSame('2024/192/', $result);
    }
}
