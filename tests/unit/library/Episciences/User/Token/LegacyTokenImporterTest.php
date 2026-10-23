<?php

declare(strict_types=1);

namespace unit\library\Episciences\User\Token;

use DateTimeImmutable;
use Episciences\User\Token\AccountTokenRepository;
use Episciences\User\Token\LegacyTokenImporter;
use Episciences\User\Token\TokenPurpose;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Pdo_Sqlite;

require_once __DIR__ . '/UserTokenTestDatabase.php';

/**
 * One-off import of the CAS tokens, run against in-memory SQLite copies of the CAS token table,
 * the Episciences USER table and the user_token table.
 *
 * @covers \Episciences\User\Token\LegacyTokenImporter
 */
final class LegacyTokenImporterTest extends TestCase
{
    private const EPISCIENCES_UID = 10;
    private const OTHER_APPLICATION_UID = 20;

    private Zend_Db_Adapter_Pdo_Sqlite $casDb;
    private Zend_Db_Adapter_Pdo_Sqlite $appDb;
    private DateTimeImmutable $now;
    private AccountTokenRepository $repository;
    private LegacyTokenImporter $importer;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required');
        }

        $this->now = new DateTimeImmutable('2026-10-24 10:00:00');
        $clock = fn(): DateTimeImmutable => $this->now;

        $this->casDb = new Zend_Db_Adapter_Pdo_Sqlite(['dbname' => ':memory:']);
        // Case-insensitive USAGE, like the default collation of the MySQL table
        $this->casDb->query('CREATE TABLE T_UTILISATEURS_TOKENS (UID INTEGER, EMAIL TEXT, TOKEN TEXT, TIME_MODIFIED TEXT, "USAGE" TEXT COLLATE NOCASE)');

        $this->appDb = UserTokenTestDatabase::create();
        $this->appDb->query('CREATE TABLE USER (UID INTEGER PRIMARY KEY)');
        $this->appDb->insert('USER', ['UID' => self::EPISCIENCES_UID]);

        $this->repository = new AccountTokenRepository($this->appDb, $clock);
        $this->importer = new LegacyTokenImporter($this->casDb, $this->appDb, $this->repository, 'USER', $clock);
    }

    private function addLegacyToken(string $token, string $usage, string $age, int $uid = self::EPISCIENCES_UID): void
    {
        $this->casDb->insert('T_UTILISATEURS_TOKENS', [
            'UID' => $uid,
            'EMAIL' => 'someone@example.org',
            'TOKEN' => $token,
            'TIME_MODIFIED' => $this->now->modify($age)->format('Y-m-d H:i:s'),
            'USAGE' => $usage,
        ]);
    }

    public function testRecentTokensOfEpisciencesAccountsAreImported(): void
    {
        $activation = sha1('activation');
        $password = sha1('password');
        $this->addLegacyToken($activation, 'VALID', '-2 days');
        $this->addLegacyToken($password, 'PASSWORD', '-2 days');

        $result = $this->importer->import();

        self::assertSame(['candidates' => 2, 'imported' => 2, 'skipped' => 0], $result);
        self::assertNotNull($this->repository->find($activation, TokenPurpose::ACCOUNT_ACTIVATION));
        self::assertNotNull($this->repository->find($password, TokenPurpose::PASSWORD_RESET));
    }

    public function testLegacyPasswordTokenKeepsTheAccountSetupLifetime(): void
    {
        $password = sha1('password');
        $this->addLegacyToken($password, 'PASSWORD', '-10 days');

        $this->importer->import();

        $found = $this->repository->find($password, TokenPurpose::PASSWORD_RESET);
        self::assertNotNull($found);
        self::assertEquals($this->now->modify('-10 days')->modify(sprintf('+%d seconds', TokenPurpose::ACCOUNT_SETUP->ttlSeconds())), $found->expiresAt);
    }

    public function testTokensTooOldForTheNewLifetimesAreNotImported(): void
    {
        $this->addLegacyToken(sha1('old activation'), 'VALID', '-8 days');
        $this->addLegacyToken(sha1('old password'), 'PASSWORD', '-15 days');

        $result = $this->importer->import();

        self::assertSame(0, $result['imported']);
        self::assertSame(0, (int)$this->appDb->fetchOne('SELECT COUNT(*) FROM user_token'));
    }

    public function testTokensOfOtherApplicationsAccountsAreNotImported(): void
    {
        $this->addLegacyToken(sha1('other'), 'PASSWORD', '-1 hour', self::OTHER_APPLICATION_UID);

        $result = $this->importer->import();

        self::assertSame(['candidates' => 1, 'imported' => 0, 'skipped' => 1], $result);
    }

    public function testUnknownUsageIsNotImported(): void
    {
        $this->addLegacyToken(sha1('new email'), 'NEW_EMAIL', '-1 hour');

        self::assertSame(0, $this->importer->import()['candidates']);
    }

    public function testUsageIsMatchedWhateverItsCase(): void
    {
        $this->addLegacyToken(sha1('activation'), 'valid', '-1 hour');

        self::assertSame(['candidates' => 1, 'imported' => 1, 'skipped' => 0], $this->importer->import());
        self::assertNotNull($this->repository->find(sha1('activation'), TokenPurpose::ACCOUNT_ACTIVATION));
    }

    public function testDryRunAfterAnImportCountsNothingToImport(): void
    {
        $this->addLegacyToken(sha1('activation'), 'VALID', '-1 hour');
        $this->importer->import();

        self::assertSame(0, $this->importer->import(true)['imported']);
    }

    public function testDryRunWritesNothing(): void
    {
        $this->addLegacyToken(sha1('activation'), 'VALID', '-1 hour');

        $result = $this->importer->import(true);

        self::assertSame(1, $result['imported']);
        self::assertSame(0, (int)$this->appDb->fetchOne('SELECT COUNT(*) FROM user_token'));
    }

    public function testRunningTwiceImportsOnce(): void
    {
        $this->addLegacyToken(sha1('activation'), 'VALID', '-1 hour');

        $this->importer->import();
        $second = $this->importer->import();

        self::assertSame(0, $second['imported']);
        self::assertSame(1, (int)$this->appDb->fetchOne('SELECT COUNT(*) FROM user_token'));
    }

    public function testTheCasTableIsLeftUntouched(): void
    {
        $this->addLegacyToken(sha1('activation'), 'VALID', '-1 hour');

        $this->importer->import();

        self::assertSame(1, (int)$this->casDb->fetchOne('SELECT COUNT(*) FROM T_UTILISATEURS_TOKENS'));
    }
}
