<?php

namespace unit\library\Episciences;

use Episciences_UserManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_UserManager
 *
 * Only pure guard-clause paths (no DB call) are exercised here.
 * Methods that hit the database are not testable without a real DB connection.
 *
 * @covers Episciences_UserManager
 */
class Episciences_UserManagerTest extends TestCase
{
    // =========================================================================
    // getCorrespondingUidFromUuid() — empty uuid guard
    // =========================================================================

    public function testGetCorrespondingUidFromUuidReturnsZeroForEmptyUuid(): void
    {
        // Guard: if (empty($uuid)) return 0; — no DB access
        $result = Episciences_UserManager::getCorrespondingUidFromUuid('');
        $this->assertSame(0, $result);
    }

    public function testGetCorrespondingUidFromUuidReturnsZeroForDefaultParam(): void
    {
        // Default $uuid = '' → same guard
        $result = Episciences_UserManager::getCorrespondingUidFromUuid();
        $this->assertSame(0, $result);
    }

    // =========================================================================
    // getUuidFromUid() — empty uid guard
    // =========================================================================

    public function testGetUuidFromUidReturnsNullForZeroUid(): void
    {
        // Guard: if (empty($uid)) return null; — no DB access
        $result = Episciences_UserManager::getUuidFromUid(0);
        $this->assertNull($result);
    }
}
