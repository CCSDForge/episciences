<?php

declare(strict_types=1);

namespace unit\scripts;

use DateTimeImmutable;
use Episciences\User\Token\AccountTokenRepository;
use Episciences\User\Token\LegacyTokenImporter;
use ImportLegacyUserTokensCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use unit\library\Episciences\User\Token\UserTokenTestDatabase;
use Zend_Db_Adapter_Pdo_Sqlite;

require_once __DIR__ . '/../../../scripts/ImportLegacyUserTokensCommand.php';
require_once __DIR__ . '/../library/Episciences/User/Token/UserTokenTestDatabase.php';

final class ImportLegacyUserTokensCommandTest extends TestCase
{
    private Zend_Db_Adapter_Pdo_Sqlite $appDb;
    private ImportLegacyUserTokensCommand $command;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required');
        }

        $now = new DateTimeImmutable('2026-10-24 10:00:00');
        $clock = static fn(): DateTimeImmutable => $now;

        $casDb = new Zend_Db_Adapter_Pdo_Sqlite(['dbname' => ':memory:']);
        $casDb->query('CREATE TABLE T_UTILISATEURS_TOKENS (UID INTEGER, EMAIL TEXT, TOKEN TEXT, TIME_MODIFIED TEXT, "USAGE" TEXT)');
        $casDb->insert('T_UTILISATEURS_TOKENS', [
            'UID' => 10, 'EMAIL' => 'someone@example.org', 'TOKEN' => sha1('activation'), 'TIME_MODIFIED' => '2026-10-24 09:00:00', 'USAGE' => 'VALID',
        ]);

        $this->appDb = UserTokenTestDatabase::create();
        $this->appDb->query('CREATE TABLE USER (UID INTEGER PRIMARY KEY)');
        $this->appDb->insert('USER', ['UID' => 10]);

        $importer = new LegacyTokenImporter($casDb, $this->appDb, new AccountTokenRepository($this->appDb, $clock), 'USER', $clock);

        $this->command = new class ($importer) extends ImportLegacyUserTokensCommand {
            public function __construct(private readonly LegacyTokenImporter $importer)
            {
                parent::__construct();
            }

            protected function getImporter(): LegacyTokenImporter
            {
                return $this->importer;
            }

            protected function bootstrap(): void
            {
            }
        };
    }

    private function importedCount(): int
    {
        return (int)$this->appDb->fetchOne('SELECT COUNT(*) FROM user_token');
    }

    public function testCommandNameAndDryRunOption(): void
    {
        $command = new ImportLegacyUserTokensCommand();

        self::assertSame('user:import-legacy-tokens', $command->getName());
        self::assertTrue($command->getDefinition()->hasOption('dry-run'));
    }

    public function testDryRunOnlyCounts(): void
    {
        $tester = new CommandTester($this->command);

        self::assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true]));
        self::assertStringContainsString('1 to import', $tester->getDisplay());
        self::assertSame(0, $this->importedCount());
    }

    public function testImport(): void
    {
        $tester = new CommandTester($this->command);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('1 imported', $tester->getDisplay());
        self::assertSame(1, $this->importedCount());
    }
}
