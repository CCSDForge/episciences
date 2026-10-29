<?php

declare(strict_types=1);

namespace unit\scripts;

use DateTimeImmutable;
use Episciences\User\Token\AccountTokenRepository;
use Episciences\User\Token\TokenPurpose;
use PHPUnit\Framework\TestCase;
use PurgeExpiredUserTokensCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use unit\library\Episciences\User\Token\UserTokenTestDatabase;

require_once __DIR__ . '/../../../scripts/PurgeExpiredUserTokensCommand.php';
require_once __DIR__ . '/../library/Episciences/User/Token/UserTokenTestDatabase.php';

final class PurgeExpiredUserTokensCommandTest extends TestCase
{
    public function testCommandName(): void
    {
        self::assertSame('user:purge-expired-tokens', (new PurgeExpiredUserTokensCommand())->getName());
    }

    public function testExpiredTokensAreDeleted(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required');
        }

        $now = new DateTimeImmutable('2026-10-24 10:00:00');
        $db = UserTokenTestDatabase::create();
        (new AccountTokenRepository($db, static fn(): DateTimeImmutable => $now->modify('-2 hours')))
            ->issue(1, 'expired@example.org', TokenPurpose::PASSWORD_RESET);
        $repository = new AccountTokenRepository($db, static fn(): DateTimeImmutable => $now);
        $valid = $repository->issue(2, 'valid@example.org', TokenPurpose::PASSWORD_RESET);

        $command = new class ($repository) extends PurgeExpiredUserTokensCommand {
            public function __construct(private readonly AccountTokenRepository $repository)
            {
                parent::__construct();
            }

            protected function getRepository(): AccountTokenRepository
            {
                return $this->repository;
            }

            protected function bootstrap(): void
            {
            }
        };

        $tester = new CommandTester($command);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Deleted 1 expired token(s)', $tester->getDisplay());
        self::assertNotNull($repository->find($valid, TokenPurpose::PASSWORD_RESET));
    }
}
