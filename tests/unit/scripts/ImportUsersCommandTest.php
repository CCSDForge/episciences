<?php

declare(strict_types=1);

namespace unit\scripts;

use Episciences\User\Import\UserAccountStore;
use Episciences\User\Import\UserImporter;
use Episciences_Review;
use Episciences_User;
use ImportUsersCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zend_Locale;
use Zend_Registry;

require_once __DIR__ . '/../../../scripts/ImportUsersCommand.php';

/**
 * journal:import-users checks the whole file before importing anything. The journal is loaded without bootstrapping
 * and the importer works on a test double of the database (UserImporter has its own test).
 *
 * @covers ImportUsersCommand
 */
final class ImportUsersCommandTest extends TestCase
{
    private const string SAMPLES = __DIR__ . '/../../../scripts/importSamples/';

    private mixed $previousLanguages = null;

    private mixed $previousLocale = null;

    /** @var list<array{bool, string}> arguments given to the importer factory */
    private array $importerCalls = [];

    protected function setUp(): void
    {
        $this->previousLanguages = Zend_Registry::isRegistered('languages') ? Zend_Registry::get('languages') : null;
        $this->previousLocale = Zend_Registry::isRegistered('Zend_Locale') ? Zend_Registry::get('Zend_Locale') : null;
        Zend_Registry::set('languages', ['en', 'fr']);
        // registered by the bootstrap, which is not run here
        Zend_Registry::set('Zend_Locale', new Zend_Locale('en'));
        $this->importerCalls = [];
    }

    protected function tearDown(): void
    {
        Zend_Registry::set('languages', $this->previousLanguages ?? ['en', 'fr']);
        Zend_Registry::set('Zend_Locale', $this->previousLocale ?? new Zend_Locale('en'));
    }

    private function tester(?Episciences_Review $journal = null): CommandTester
    {
        $journal ??= (new Episciences_Review())->setRvid(42)->setCode('demo');
        $loader = static fn(): ?Episciences_Review => $journal->getCode() === 'unknown' ? null : $journal;

        $store = $this->createMock(UserAccountStore::class);
        $store->method('findByEmail')->willReturn(null);
        $store->method('journalRoles')->willReturn([]);
        $store->method('createAccount')->willReturnCallback(static function (): Episciences_User {
            $user = new Episciences_User();
            $user->setUid(7);
            return $user;
        });

        $factory = function (Episciences_Review $journal, bool $withMails, string $lang) use ($store): UserImporter {
            $this->importerCalls[] = [$withMails, $lang];
            return new UserImporter($store, null, $journal->getRvid(), $lang);
        };

        return new CommandTester(new ImportUsersCommand($loader, $factory));
    }

    public function testBothTheJournalAndTheFileAreRequired(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['--rvcode' => 'demo']));
        self::assertStringContainsString('Missing required option: --rvcode and --csv', $tester->getDisplay());
    }

    public function testAnUnknownJournalIsRefused(): void
    {
        $tester = $this->tester((new Episciences_Review())->setRvid(1)->setCode('unknown'));

        self::assertSame(Command::FAILURE, $tester->execute(['--rvcode' => 'unknown', '--csv' => self::SAMPLES . 'sample-users.csv']));
        self::assertStringContainsString('Unknown journal: unknown', $tester->getDisplay());
    }

    public function testAnUnavailableDefaultLanguageIsRefused(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['--rvcode' => 'demo', '--csv' => self::SAMPLES . 'sample-users.csv', '--lang' => 'de']));
        self::assertStringContainsString('Unavailable language "de"', $tester->getDisplay());
        self::assertSame([], $this->importerCalls);
    }

    public function testAnInvalidFileImportsNothing(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['--rvcode' => 'demo', '--csv' => self::SAMPLES . 'sample-users-invalid.csv']));
        $display = $tester->getDisplay();
        self::assertStringContainsString('Invalid file: nothing was imported.', $display);
        self::assertStringContainsString('unknown role "redactor"', $display);
        self::assertSame([], $this->importerCalls);
    }

    public function testADryRunReportsEveryPerson(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['--rvcode' => 'demo', '--csv' => self::SAMPLES . 'sample-users.csv', '--dry-run' => true]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('Dry-run mode enabled', $display);
        self::assertStringContainsString('camille.durand@example.org', $display);
        self::assertStringContainsString('10 lines checked for journal "demo".', $display);
        self::assertSame([[true, 'en']], $this->importerCalls);
    }

    public function testNoMailAndTheDefaultLanguageAreGivenToTheImporter(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['--rvcode' => 'demo', '--csv' => self::SAMPLES . 'sample-users.csv', '--no-mail' => true, '--lang' => 'FR']));
        self::assertStringContainsString('10 lines imported for journal "demo".', $tester->getDisplay());
        self::assertSame([[false, 'fr']], $this->importerCalls);
    }

    public function testThePathOfALinkFollowsTheRouteScheme(): void
    {
        self::assertSame(
            '/user/resetpassword/token/a%2Fb',
            ImportUsersCommand::buildPath(['controller' => 'user', 'action' => 'resetpassword', 'token' => 'a/b'])
        );
    }
}
