<?php

declare(strict_types=1);

namespace unit\library\Episciences\User\Token;

use DateTimeImmutable;
use Episciences\User\Token\AccountToken;
use Episciences\User\Token\AccountTokenRepository;
use Episciences\User\Token\TokenPurpose;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Pdo_Sqlite;

require_once __DIR__ . '/UserTokenTestDatabase.php';

/**
 * Account link tokens, run against an in-memory SQLite copy of the user_token table.
 *
 * @covers \Episciences\User\Token\AccountTokenRepository
 */
final class AccountTokenRepositoryTest extends TestCase
{
    private const UID = 42;
    private const OTHER_UID = 43;
    private const EMAIL = 'someone@example.org';

    private Zend_Db_Adapter_Pdo_Sqlite $db;
    private DateTimeImmutable $now;
    private AccountTokenRepository $repository;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required');
        }

        $this->db = UserTokenTestDatabase::create();
        $this->now = new DateTimeImmutable('2026-10-24 10:00:00');
        $this->repository = new AccountTokenRepository($this->db, fn(): DateTimeImmutable => $this->now);
    }

    private function elapse(int $seconds): void
    {
        $this->now = $this->now->modify(sprintf('+%d seconds', $seconds));
    }

    private function countTokens(): int
    {
        return (int)$this->db->fetchOne('SELECT COUNT(*) FROM user_token');
    }

    public function testIssuedTokenIsLongRandomHexadecimal(): void
    {
        $token = $this->repository->issue(self::UID, self::EMAIL, TokenPurpose::PASSWORD_RESET);
        $other = $this->repository->issue(self::OTHER_UID, self::EMAIL, TokenPurpose::PASSWORD_RESET);

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        self::assertNotSame($token, $other);
    }

    public function testOnlyTheHashOfTheTokenIsStored(): void
    {
        $token = $this->repository->issue(self::UID, self::EMAIL, TokenPurpose::PASSWORD_RESET);

        $row = $this->db->fetchRow('SELECT * FROM user_token');

        self::assertSame(hash('sha256', $token), $row['token_hash']);
        self::assertNotContains($token, $row);
    }

    public function testIssuedTokenExpiresAfterTheLifetimeOfItsPurpose(): void
    {
        $this->repository->issue(self::UID, self::EMAIL, TokenPurpose::ACCOUNT_ACTIVATION);

        $row = $this->db->fetchRow('SELECT created_at, expires_at FROM user_token');

        self::assertSame('2026-10-24 10:00:00', $row['created_at']);
        self::assertSame(
            $this->now->modify(sprintf('+%d seconds', TokenPurpose::ACCOUNT_ACTIVATION->ttlSeconds()))->format('Y-m-d H:i:s'),
            $row['expires_at']
        );
    }

    public function testFindReturnsTheAccountOfAValidTokenWithoutConsumingIt(): void
    {
        $token = $this->repository->issue(self::UID, self::EMAIL, TokenPurpose::PASSWORD_RESET);

        $found = $this->repository->find($token, TokenPurpose::PASSWORD_RESET);

        self::assertInstanceOf(AccountToken::class, $found);
        self::assertSame(self::UID, $found->uid);
        self::assertSame(self::EMAIL, $found->email);
        self::assertSame(TokenPurpose::PASSWORD_RESET, $found->purpose);
        self::assertNotNull($this->repository->find($token, TokenPurpose::PASSWORD_RESET), 'find() must not consume the token');
    }

    public function testTokenIsRefusedForAnotherPurpose(): void
    {
        $activation = $this->repository->issue(self::UID, self::EMAIL, TokenPurpose::ACCOUNT_ACTIVATION);

        self::assertNull($this->repository->find($activation, TokenPurpose::PASSWORD_RESET, TokenPurpose::ACCOUNT_SETUP));
        self::assertNull($this->repository->consume($activation, TokenPurpose::PASSWORD_RESET, TokenPurpose::ACCOUNT_SETUP));
        self::assertNotNull($this->repository->find($activation, TokenPurpose::ACCOUNT_ACTIVATION), 'a refused attempt must not delete the token');
    }

    public function testTokenIsAcceptedForAnyOfTheGivenPurposes(): void
    {
        $setup = $this->repository->issue(self::UID, self::EMAIL, TokenPurpose::ACCOUNT_SETUP);

        self::assertNotNull($this->repository->find($setup, TokenPurpose::PASSWORD_RESET, TokenPurpose::ACCOUNT_SETUP));
    }

    public function testNoPurposeAcceptsNothing(): void
    {
        $token = $this->repository->issue(self::UID, self::EMAIL, TokenPurpose::PASSWORD_RESET);

        self::assertNull($this->repository->find($token));
    }

    public function testTokenIsRefusedOnceExpired(): void
    {
        $token = $this->repository->issue(self::UID, self::EMAIL, TokenPurpose::PASSWORD_RESET);

        $this->elapse(TokenPurpose::PASSWORD_RESET->ttlSeconds() - 1);
        self::assertNotNull($this->repository->find($token, TokenPurpose::PASSWORD_RESET));

        $this->elapse(1);
        self::assertNull($this->repository->find($token, TokenPurpose::PASSWORD_RESET));
        self::assertNull($this->repository->consume($token, TokenPurpose::PASSWORD_RESET));
    }

    public function testTokenCanBeConsumedOnlyOnce(): void
    {
        $token = $this->repository->issue(self::UID, self::EMAIL, TokenPurpose::PASSWORD_RESET);

        $consumed = $this->repository->consume($token, TokenPurpose::PASSWORD_RESET);

        self::assertNotNull($consumed);
        self::assertSame(self::UID, $consumed->uid);
        self::assertNull($this->repository->consume($token, TokenPurpose::PASSWORD_RESET));
        self::assertSame(0, $this->countTokens());
    }

    public function testTokenConsumedByAConcurrentRequestIsNotGivenTwice(): void
    {
        $token = $this->repository->issue(self::UID, self::EMAIL, TokenPurpose::PASSWORD_RESET);
        $concurrentRequest = new AccountTokenRepository($this->db, fn(): DateTimeImmutable => $this->now);

        // The other request consumes the token between the lookup and the deletion of this one
        $racing = new class ($this->db, fn(): DateTimeImmutable => $this->now, $concurrentRequest) extends AccountTokenRepository {
            public function __construct(Zend_Db_Adapter_Pdo_Sqlite $db, \Closure $clock, private readonly AccountTokenRepository $concurrentRequest)
            {
                parent::__construct($db, $clock);
            }

            public function find(mixed $presented, TokenPurpose ...$purposes): ?AccountToken
            {
                $found = parent::find($presented, ...$purposes);
                $this->concurrentRequest->consume($presented, ...$purposes);

                return $found;
            }
        };

        self::assertNull($racing->consume($token, TokenPurpose::PASSWORD_RESET));
        self::assertSame(0, $this->countTokens());
    }

    public function testIssuingRevokesThePreviousTokenOfTheSameAccountAndPurpose(): void
    {
        $first = $this->repository->issue(self::UID, self::EMAIL, TokenPurpose::PASSWORD_RESET);
        $activation = $this->repository->issue(self::UID, self::EMAIL, TokenPurpose::ACCOUNT_ACTIVATION);
        $otherAccount = $this->repository->issue(self::OTHER_UID, self::EMAIL, TokenPurpose::PASSWORD_RESET);

        $second = $this->repository->issue(self::UID, self::EMAIL, TokenPurpose::PASSWORD_RESET);

        self::assertNull($this->repository->find($first, TokenPurpose::PASSWORD_RESET));
        self::assertNotNull($this->repository->find($second, TokenPurpose::PASSWORD_RESET));
        self::assertNotNull($this->repository->find($activation, TokenPurpose::ACCOUNT_ACTIVATION));
        self::assertNotNull($this->repository->find($otherAccount, TokenPurpose::PASSWORD_RESET));
    }

    public function testIssuingKeepsOnlyTheLatestTokenWhenOlderOnesAreLeft(): void
    {
        // State left by a concurrent request that inserted its token before this one
        $concurrent = bin2hex(random_bytes(32));
        $this->db->insert(AccountTokenRepository::TABLE_NAME, [
            'uid' => self::UID,
            'email' => self::EMAIL,
            'purpose' => TokenPurpose::PASSWORD_RESET->value,
            'token_hash' => hash('sha256', $concurrent),
            'created_at' => $this->now->format('Y-m-d H:i:s'),
            'expires_at' => $this->now->modify('+1 hour')->format('Y-m-d H:i:s'),
        ]);

        $latest = $this->repository->issue(self::UID, self::EMAIL, TokenPurpose::PASSWORD_RESET);

        self::assertNull($this->repository->find($concurrent, TokenPurpose::PASSWORD_RESET));
        self::assertNotNull($this->repository->find($latest, TokenPurpose::PASSWORD_RESET));
        self::assertSame(1, $this->countTokens());
    }

    public function testRestoredTokenCanBeUsedAgainUntilItsExpiry(): void
    {
        $token = $this->repository->issue(self::UID, self::EMAIL, TokenPurpose::PASSWORD_RESET);
        $consumed = $this->repository->consume($token, TokenPurpose::PASSWORD_RESET);
        self::assertNotNull($consumed);

        $this->repository->restore($token, $consumed);

        $restored = $this->repository->find($token, TokenPurpose::PASSWORD_RESET);
        self::assertNotNull($restored);
        self::assertSame(self::UID, $restored->uid);
        self::assertEquals($consumed->expiresAt, $restored->expiresAt);

        $this->elapse(TokenPurpose::PASSWORD_RESET->ttlSeconds());
        self::assertNull($this->repository->find($token, TokenPurpose::PASSWORD_RESET));
    }

    public function testRestoringATokenStillStoredDoesNotDuplicateIt(): void
    {
        $token = $this->repository->issue(self::UID, self::EMAIL, TokenPurpose::PASSWORD_RESET);
        $found = $this->repository->find($token, TokenPurpose::PASSWORD_RESET);
        self::assertNotNull($found);

        $this->repository->restore($token, $found);

        self::assertSame(1, $this->countTokens());
    }

    public function testRevokeAllOnlyRevokesTheGivenAccountAndPurposes(): void
    {
        $reset = $this->repository->issue(self::UID, self::EMAIL, TokenPurpose::PASSWORD_RESET);
        $setup = $this->repository->issue(self::UID, self::EMAIL, TokenPurpose::ACCOUNT_SETUP);
        $activation = $this->repository->issue(self::UID, self::EMAIL, TokenPurpose::ACCOUNT_ACTIVATION);
        $otherAccount = $this->repository->issue(self::OTHER_UID, self::EMAIL, TokenPurpose::PASSWORD_RESET);

        $this->repository->revokeAll(self::UID, TokenPurpose::PASSWORD_RESET, TokenPurpose::ACCOUNT_SETUP);

        self::assertNull($this->repository->find($reset, TokenPurpose::PASSWORD_RESET));
        self::assertNull($this->repository->find($setup, TokenPurpose::ACCOUNT_SETUP));
        self::assertNotNull($this->repository->find($activation, TokenPurpose::ACCOUNT_ACTIVATION));
        self::assertNotNull($this->repository->find($otherAccount, TokenPurpose::PASSWORD_RESET));
    }

    /**
     * @dataProvider malformedTokens
     */
    public function testMalformedTokenIsRefused(mixed $presented): void
    {
        $this->repository->issue(self::UID, self::EMAIL, TokenPurpose::PASSWORD_RESET);

        self::assertNull($this->repository->find($presented, TokenPurpose::PASSWORD_RESET));
        self::assertNull($this->repository->consume($presented, TokenPurpose::PASSWORD_RESET));
        self::assertSame(1, $this->countTokens());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function malformedTokens(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'array' => [['0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef']];
        yield 'integer' => [123];
        yield 'too short' => [str_repeat('a', 63)];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'uppercase' => [str_repeat('A', 64)];
        yield 'not hexadecimal' => [str_repeat('g', 64)];
        yield 'SQL' => ["' OR '1'='1"];
    }

    public function testUnknownWellFormedTokenIsRefused(): void
    {
        $this->repository->issue(self::UID, self::EMAIL, TokenPurpose::PASSWORD_RESET);

        self::assertNull($this->repository->find(str_repeat('a', 64), TokenPurpose::PASSWORD_RESET));
    }

    public function testPurgeDeletesOnlyExpiredTokens(): void
    {
        $this->repository->issue(self::UID, self::EMAIL, TokenPurpose::PASSWORD_RESET);
        $this->repository->issue(self::OTHER_UID, self::EMAIL, TokenPurpose::PASSWORD_RESET);
        $activation = $this->repository->issue(self::UID, self::EMAIL, TokenPurpose::ACCOUNT_ACTIVATION);

        $this->elapse(TokenPurpose::PASSWORD_RESET->ttlSeconds());

        self::assertSame(2, $this->repository->purgeExpired(1));
        self::assertSame(1, $this->countTokens());
        self::assertNotNull($this->repository->find($activation, TokenPurpose::ACCOUNT_ACTIVATION));
        self::assertSame(0, $this->repository->purgeExpired());
    }

    public function testImportedLegacyTokenIsAcceptedUntilItsExpiry(): void
    {
        $legacy = sha1('legacy');

        self::assertTrue($this->repository->importLegacy(
            self::UID,
            self::EMAIL,
            TokenPurpose::PASSWORD_RESET,
            $legacy,
            $this->now->modify('-1 day'),
            $this->now->modify('+1 hour')
        ));

        $row = $this->db->fetchRow('SELECT token_hash FROM user_token');
        self::assertSame(hash('sha256', $legacy), $row['token_hash']);
        self::assertNotNull($this->repository->find($legacy, TokenPurpose::PASSWORD_RESET));

        $this->elapse(3600);
        self::assertNull($this->repository->find($legacy, TokenPurpose::PASSWORD_RESET));
    }

    public function testLegacyTokenIsImportedOnlyOnce(): void
    {
        $legacy = sha1('legacy');
        $expiresAt = $this->now->modify('+1 hour');

        self::assertTrue($this->repository->importLegacy(self::UID, self::EMAIL, TokenPurpose::PASSWORD_RESET, $legacy, $this->now, $expiresAt));
        self::assertFalse($this->repository->importLegacy(self::UID, self::EMAIL, TokenPurpose::PASSWORD_RESET, $legacy, $this->now, $expiresAt));
        self::assertSame(1, $this->countTokens());
    }

    public function testCanImportLegacyTellsWhetherTheTokenWouldBeStored(): void
    {
        $legacy = sha1('legacy');

        self::assertTrue($this->repository->canImportLegacy($legacy));
        self::assertFalse($this->repository->canImportLegacy('not-a-token'));

        $this->repository->importLegacy(self::UID, self::EMAIL, TokenPurpose::PASSWORD_RESET, $legacy, $this->now, $this->now->modify('+1 hour'));

        self::assertFalse($this->repository->canImportLegacy($legacy));
    }

    public function testMalformedLegacyTokenIsNotImported(): void
    {
        self::assertFalse($this->repository->importLegacy(self::UID, self::EMAIL, TokenPurpose::PASSWORD_RESET, 'not-a-token', $this->now, $this->now->modify('+1 hour')));
        self::assertSame(0, $this->countTokens());
    }
}
