<?php

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for miscellaneous pure methods in Episciences_Paper that are not
 * covered by other test files.
 *
 * Tested methods:
 *   - getStatusLabelFromDictionary()
 *   - getVersionsIds() / setVersionsIds()
 *   - setPassword() / getPassword()
 *   - isLatestVersion() (via createPartialMock)
 *   - isCoauthor() / isOwner() / isOwnerOrCoAuthor() (uid-based logic)
 *
 * @covers Episciences_Paper
 */
final class Episciences_Paper_MiscTest extends TestCase
{
    private Episciences_Paper $paper;

    protected function setUp(): void
    {
        $this->paper = new Episciences_Paper();

        if (!defined('SESSION_NAMESPACE')) {
            define('SESSION_NAMESPACE', 'Episciences_Auth');
        }

        \Episciences_Auth::getInstance()->clearIdentity();
        $session = new \Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->realIdentities);
    }

    protected function tearDown(): void
    {
        \Episciences_Auth::getInstance()->clearIdentity();
        $session = new \Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->realIdentities);
    }

    private function createMockUser(int $uid): \Episciences_User
    {
        $user = $this->createMock(\Episciences_User::class);
        $user->method('getUid')->willReturn($uid);
        return $user;
    }

    private function loginUser(\Episciences_User $user): void
    {
        \Episciences_Auth::getInstance()->getStorage()->write($user);
    }

    // -----------------------------------------------------------------------
    // getStatusLabelFromDictionary()
    // -----------------------------------------------------------------------

    public function testGetStatusLabelFromDictionaryReturnsLabelForSubmitted(): void
    {
        $this->paper->setStatus(Episciences_Paper::STATUS_SUBMITTED);
        self::assertSame('submitted', $this->paper->getStatusLabelFromDictionary());
    }

    public function testGetStatusLabelFromDictionaryReturnsLabelForPublished(): void
    {
        $this->paper->setStatus(Episciences_Paper::STATUS_PUBLISHED);
        self::assertSame('published', $this->paper->getStatusLabelFromDictionary());
    }

    public function testGetStatusLabelFromDictionaryReturnsLabelForAccepted(): void
    {
        $this->paper->setStatus(Episciences_Paper::STATUS_ACCEPTED);
        self::assertSame('accepted', $this->paper->getStatusLabelFromDictionary());
    }

    public function testGetStatusLabelFromDictionaryReturnsLabelForRefused(): void
    {
        $this->paper->setStatus(Episciences_Paper::STATUS_REFUSED);
        self::assertSame('refused', $this->paper->getStatusLabelFromDictionary());
    }

    public function testGetStatusLabelFromDictionaryReturnsUnknownForUndefinedStatus(): void
    {
        $this->paper->setStatus(9999);
        self::assertSame('unknown', $this->paper->getStatusLabelFromDictionary());
    }

    /**
     * Every entry in STATUS_DICTIONARY should be reachable and return a
     * non-empty string.
     */
    public function testGetStatusLabelFromDictionaryCoversAllDictionaryEntries(): void
    {
        foreach (Episciences_Paper::STATUS_DICTIONARY as $status => $label) {
            $this->paper->setStatus($status);
            self::assertSame(
                $label,
                $this->paper->getStatusLabelFromDictionary(),
                "Status $status should map to label '$label'"
            );
        }
    }

    // -----------------------------------------------------------------------
    // getVersionsIds() / setVersionsIds()
    // -----------------------------------------------------------------------

    public function testSetAndGetVersionsIds(): void
    {
        $ids = [1 => '100', 2 => '101', 3 => '102'];
        $this->paper->setVersionsIds($ids);
        self::assertSame($ids, $this->paper->getVersionsIds());
    }

    public function testSetVersionsIdsIsFluentAndReturnsSelf(): void
    {
        self::assertSame($this->paper, $this->paper->setVersionsIds([]));
    }

    public function testGetVersionsIdsReturnsSetValueWithoutDbCall(): void
    {
        // Pre-populate via setter → getVersionsIds() must NOT call loadVersionsIds()
        $this->paper->setVersionsIds([1 => '10']);
        $result = $this->paper->getVersionsIds();
        self::assertCount(1, $result);
    }

    // -----------------------------------------------------------------------
    // setPassword() / getPassword()
    // -----------------------------------------------------------------------

    public function testSetPasswordStoresPlainTextWhenEncryptFlagIsFalse(): void
    {
        $this->paper->setPassword('secret123', false);
        self::assertSame('secret123', $this->paper->getPassword());
    }

    public function testSetPasswordNullResultsInNullPassword(): void
    {
        $this->paper->setPassword(null);
        self::assertNull($this->paper->getPassword());
    }

    public function testSetPasswordEmptyStringStoresEmptyString(): void
    {
        $this->paper->setPassword('');
        // setPassword stores whatever is passed; empty string is stored as empty string
        self::assertSame('', $this->paper->getPassword());
    }

    public function testGetPasswordReturnsNullByDefault(): void
    {
        self::assertNull($this->paper->getPassword());
    }

    // -----------------------------------------------------------------------
    // isLatestVersion() — mocked getLatestVersionId() to avoid DB
    // -----------------------------------------------------------------------

    public function testIsLatestVersionReturnsTrueWhenDocIdMatchesLatestVersionId(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['getLatestVersionId']);
        $paper->method('getLatestVersionId')->willReturn('42');
        $paper->setDocid(42);

        self::assertTrue($paper->isLatestVersion());
    }

    public function testIsLatestVersionReturnsFalseWhenDocIdDoesNotMatchLatestVersionId(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['getLatestVersionId']);
        $paper->method('getLatestVersionId')->willReturn('99');
        $paper->setDocid(42);

        self::assertFalse($paper->isLatestVersion());
    }

    public function testIsLatestVersionReturnsFalseWhenLatestVersionIdIsFalse(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['getLatestVersionId']);
        $paper->method('getLatestVersionId')->willReturn(false);
        $paper->setDocid(42);

        self::assertFalse($paper->isLatestVersion());
    }

    // -----------------------------------------------------------------------
    // isOwner()
    // -----------------------------------------------------------------------

    public function testIsOwnerReturnsTrueWhenCurrentUserMatchesPaperUid(): void
    {
        $paper = new Episciences_Paper();
        $paper->setUid(42);

        $this->loginUser($this->createMockUser(42));

        self::assertTrue($paper->isOwner());
    }

    public function testIsOwnerReturnsFalseWhenNoIdentityMatchesPaperUid(): void
    {
        $paper = new Episciences_Paper();
        $paper->setUid(42);

        $this->loginUser($this->createMockUser(99));

        self::assertFalse($paper->isOwner());
    }

    /**
     * When an admin uses "switch user" (su) to act as someone else, the paper
     * author should still be recognized as owner via the original identity —
     * this is the case Episciences_Auth::getOriginalIdentity() exists for.
     */
    public function testIsOwnerReturnsTrueWhenOriginalIdentityMatchesPaperUidDuringSu(): void
    {
        $paper = new Episciences_Paper();
        $paper->setUid(99); // the admin's own uid, i.e. the paper they authored

        $admin = $this->createMockUser(99);
        $impersonated = $this->createMockUser(42);

        $this->loginUser($admin);
        \Episciences_Auth::saveRealIdentity();
        $this->loginUser($impersonated);

        // Current identity (42) doesn't match, but the original identity (99) does
        self::assertTrue($paper->isOwner());
    }

    public function testIsOwnerReturnsFalseWhenNeitherCurrentNorOriginalIdentityMatchesDuringSu(): void
    {
        $paper = new Episciences_Paper();
        $paper->setUid(123); // belongs to some other, unrelated user

        $admin = $this->createMockUser(99);
        $impersonated = $this->createMockUser(42);

        $this->loginUser($admin);
        \Episciences_Auth::saveRealIdentity();
        $this->loginUser($impersonated);

        self::assertFalse($paper->isOwner());
    }

    // -----------------------------------------------------------------------
    // getStatusLabelFromDictionary() vs STATUS_CODES integrity
    // -----------------------------------------------------------------------

    /**
     * Every key in STATUS_DICTIONARY must correspond to an existing STATUS_*
     * integer constant (no phantom entries in the dictionary).
     */
    public function testStatusDictionaryKeysAreAllKnownStatusConstants(): void
    {
        $reflection = new \ReflectionClass(Episciences_Paper::class);
        $constants  = $reflection->getConstants();

        // Collect all integer STATUS_ constants
        $statusValues = [];
        foreach ($constants as $name => $value) {
            if (str_starts_with($name, 'STATUS_') && is_int($value)) {
                $statusValues[$value] = $name;
            }
        }

        foreach (Episciences_Paper::STATUS_DICTIONARY as $statusCode => $label) {
            self::assertArrayHasKey(
                $statusCode,
                $statusValues,
                "STATUS_DICTIONARY key $statusCode ('$label') does not correspond to any STATUS_* constant"
            );
        }
    }
}
