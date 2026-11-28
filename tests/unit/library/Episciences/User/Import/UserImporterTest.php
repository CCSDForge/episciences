<?php

declare(strict_types=1);

namespace unit\library\Episciences\User\Import;

use Episciences\User\Import\MailOutcome;
use Episciences\User\Import\UserAccountStore;
use Episciences\User\Import\UserImporter;
use Episciences\User\Import\UserImportMailer;
use Episciences\User\Import\UserImportResult;
use Episciences\User\Import\UserImportRow;
use Episciences_User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The database (UserAccountStore) and the mails (UserImportMailer) are replaced by test doubles.
 *
 * @covers \Episciences\User\Import\UserImporter
 */
final class UserImporterTest extends TestCase
{
    private const int RVID = 42;

    /** @var (UserAccountStore&MockObject)|null */
    private ?UserAccountStore $store = null;
    /** @var (UserImportMailer&MockObject)|null */
    private ?UserImportMailer $mailer = null;

    protected function setUp(): void
    {
        $this->store = $this->createMock(UserAccountStore::class);
        $this->mailer = $this->createMock(UserImportMailer::class);
    }

    private function importer(bool $withMails = true): UserImporter
    {
        return new UserImporter($this->store, $withMails ? $this->mailer : null, self::RVID, 'en');
    }

    /**
     * @param list<string> $roles
     */
    private static function row(array $roles = ['editor'], ?string $lang = null, string $email = 'ada@example.org'): UserImportRow
    {
        return new UserImportRow(2, 'Ada', 'Lovelace', $email, $roles, $lang);
    }

    private static function user(int $uid, string $firstname = 'Ada', string $lastname = 'Lovelace'): Episciences_User
    {
        $user = new Episciences_User();
        $user->setUid($uid);
        $user->setFirstname($firstname);
        $user->setLastname($lastname);

        return $user;
    }

    public function testAnUnknownEmailCreatesAnAccountAndSendsTheActivationLink(): void
    {
        $user = self::user(7);
        $this->store->method('findByEmail')->willReturn(null);
        $this->store->expects(self::once())->method('createAccount')
            ->with(self::isInstanceOf(UserImportRow::class), 'fr', self::RVID)->willReturn($user);
        $this->store->expects(self::once())->method('addRoles')->with(7, ['editor'], self::RVID);
        $this->mailer->expects(self::once())->method('sendAccountSetup')->with($user, ['editor'])->willReturn(MailOutcome::Sent);
        $this->mailer->expects(self::never())->method('sendAddedToJournal');

        $result = $this->importer()->import([self::row(['editor'], 'fr')], false)[0];

        self::assertSame(UserImportResult::ACTION_CREATED, $result->action);
        self::assertSame(7, $result->uid);
        self::assertSame(['editor'], $result->addedRoles);
        self::assertSame(MailOutcome::Sent, $result->mail);
        self::assertSame([], $result->warnings);
    }

    public function testANewAccountWithoutLanguageGetsTheDefaultLanguage(): void
    {
        $this->store->method('findByEmail')->willReturn(null);
        $this->store->expects(self::once())->method('createAccount')
            ->with(self::anything(), 'en', self::RVID)->willReturn(self::user(7));

        $this->importer(false)->import([self::row()], false);
    }

    public function testAValidAccountKeepsItsRolesAndIsToldOnlyOfTheAddedOnes(): void
    {
        $user = self::user(8);
        $this->store->method('findByEmail')->willReturn(['UID' => 8, 'VALID' => 1]);
        $this->store->method('loadUser')->with(8)->willReturn($user);
        $this->store->method('journalRoles')->with(8, self::RVID)->willReturn(['member', 'reviewer']);
        $this->store->expects(self::once())->method('completeLocalData')->with($user, 'en', self::RVID);
        $this->store->expects(self::never())->method('createAccount');
        $this->store->expects(self::once())->method('addRoles')->with(8, ['editor'], self::RVID);
        $this->mailer->expects(self::never())->method('sendAccountSetup');
        $this->mailer->expects(self::once())->method('sendAddedToJournal')->with($user, ['editor'])->willReturn(MailOutcome::Sent);

        $result = $this->importer()->import([self::row(['editor', 'reviewer'])], false)[0];

        self::assertSame(UserImportResult::ACTION_ATTACHED, $result->action);
        self::assertSame(['editor'], $result->addedRoles);
        self::assertSame(MailOutcome::Sent, $result->mail);
    }

    public function testAValidAccountHoldingEveryRoleGetsNoMail(): void
    {
        $this->store->method('findByEmail')->willReturn(['UID' => 8, 'VALID' => 1]);
        $this->store->method('loadUser')->willReturn(self::user(8));
        $this->store->method('journalRoles')->willReturn(['editor']);
        $this->store->expects(self::once())->method('addRoles')->with(8, [], self::RVID);
        $this->mailer->expects(self::never())->method('sendAddedToJournal');
        $this->mailer->expects(self::never())->method('sendAccountSetup');

        $result = $this->importer()->import([self::row(['editor'])], false)[0];

        self::assertSame([], $result->addedRoles);
        self::assertSame(MailOutcome::None, $result->mail);
    }

    public function testAnAccountNotActivatedYetGetsTheLinkWithAllItsRoles(): void
    {
        $user = self::user(9);
        $this->store->method('findByEmail')->willReturn(['UID' => 9, 'VALID' => 0]);
        $this->store->method('loadUser')->willReturn($user);
        $this->store->method('journalRoles')->willReturn(['reviewer']);
        $this->mailer->expects(self::once())->method('sendAccountSetup')->with($user, ['reviewer'])->willReturn(MailOutcome::AlreadySent);

        $result = $this->importer()->import([self::row(['reviewer'])], false)[0];

        self::assertSame(UserImportResult::ACTION_INACTIVE, $result->action);
        self::assertSame(MailOutcome::AlreadySent, $result->mail);
    }

    public function testTheNameOfAnExistingAccountIsKeptAndTheDifferenceReported(): void
    {
        $this->store->method('findByEmail')->willReturn(['UID' => 8, 'VALID' => 1]);
        $this->store->method('loadUser')->willReturn(self::user(8, 'Augusta Ada', 'King'));
        $this->store->method('journalRoles')->willReturn([]);

        $result = $this->importer(false)->import([self::row()], false)[0];

        self::assertSame(['name kept: "Augusta Ada King" (file: "Ada Lovelace")'], $result->warnings);
    }

    public function testANameDifferingOnlyByCaseIsNotReported(): void
    {
        $this->store->method('findByEmail')->willReturn(['UID' => 8, 'VALID' => 1]);
        $this->store->method('loadUser')->willReturn(self::user(8, 'ADA', ' lovelace'));
        $this->store->method('journalRoles')->willReturn([]);

        self::assertSame([], $this->importer(false)->import([self::row()], false)[0]->warnings);
    }

    public function testThePlatformAdministratorRoleIsReported(): void
    {
        $this->store->method('findByEmail')->willReturn(null);
        $this->store->method('createAccount')->willReturn(self::user(7));

        $warnings = $this->importer(false)->import([self::row(['epiadmin'])], false)[0]->warnings;

        self::assertSame(['role "epiadmin" gives full access to the whole platform'], $warnings);
    }

    public function testWithoutMailerNoMailIsSent(): void
    {
        $this->store->method('findByEmail')->willReturn(null);
        $this->store->method('createAccount')->willReturn(self::user(7));
        $this->mailer->expects(self::never())->method(self::anything());

        self::assertSame(MailOutcome::None, $this->importer(false)->import([self::row()], false)[0]->mail);
    }

    public function testDryRunWritesNothingAndSendsNothing(): void
    {
        $this->store->method('findByEmail')->willReturnOnConsecutiveCalls(null, ['UID' => 8, 'VALID' => 1], ['UID' => 9, 'VALID' => 1]);
        $this->store->method('loadUser')->willReturn(self::user(8));
        $this->store->method('journalRoles')->willReturnOnConsecutiveCalls(['member'], ['editor']);
        foreach (['createAccount', 'completeLocalData', 'addRoles'] as $method) {
            $this->store->expects(self::never())->method($method);
        }
        $this->mailer->expects(self::never())->method(self::anything());

        $results = $this->importer()->import([
            self::row(['editor'], null, 'new@example.org'),
            self::row(['editor'], null, 'member@example.org'),
            self::row(['editor'], null, 'editor@example.org'),
        ], true);

        self::assertSame([UserImportResult::ACTION_CREATED, UserImportResult::ACTION_ATTACHED, UserImportResult::ACTION_ATTACHED], array_map(static fn(UserImportResult $r): string => $r->action, $results));
        self::assertSame([['editor'], ['editor'], []], array_map(static fn(UserImportResult $r): array => $r->addedRoles, $results));
        self::assertSame([MailOutcome::WouldSend, MailOutcome::WouldSend, MailOutcome::None], array_map(static fn(UserImportResult $r): MailOutcome => $r->mail, $results));
        self::assertNull($results[0]->uid);
    }

    public function testAFailingLineIsReportedAndTheNextOnesAreImported(): void
    {
        $this->store->method('findByEmail')->willReturn(null);
        $this->store->method('createAccount')->willReturnCallback(static function (UserImportRow $row): Episciences_User {
            if ($row->email === 'broken@example.org') {
                throw new RuntimeException('the account of "broken@example.org" cannot be created');
            }
            return self::user(7);
        });

        $results = $this->importer(false)->import([self::row(['editor'], null, 'broken@example.org'), self::row()], false);

        self::assertSame(UserImportResult::ACTION_FAILED, $results[0]->action);
        self::assertSame(['the account of "broken@example.org" cannot be created'], $results[0]->warnings);
        self::assertSame(UserImportResult::ACTION_CREATED, $results[1]->action);
    }
}
