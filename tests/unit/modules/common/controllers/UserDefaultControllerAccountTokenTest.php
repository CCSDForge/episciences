<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Ccsd_User_Models_User;
use Ccsd_User_Models_UserMapper;
use DateTimeImmutable;
use Episciences\User\Token\AccountTokenRepository;
use Episciences\User\Token\TokenPurpose;
use Episciences_User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use unit\library\Episciences\User\Token\UserTokenTestDatabase;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Db_Adapter_Exception;
use Zend_Db_Table_Rowset;
use Zend_View;

require_once __DIR__ . '/../../../library/Episciences/User/Token/UserTokenTestDatabase.php';
require_once APPLICATION_PATH . '/modules/common/controllers/UserDefaultController.php';

/**
 * Account links of UserDefaultController (activation, lost password, password reset, lost login):
 * a token is accepted only for its purpose, until it expires and once; the lost password and lost login
 * pages answer the same whether or not an account matches.
 *
 * Tokens are kept in an in-memory SQLite user_token table; the CAS mapper and the e-mails are stubbed.
 */
final class UserDefaultControllerAccountTokenTest extends TestCase
{
    private const UID = 42;
    private const EMAIL = 'someone@example.org';
    private const PASSWORD = 'A-long-enough-passw0rd!';

    private DateTimeImmutable $now;
    private AccountTokenRepository $tokens;
    /** @var MockObject&Ccsd_User_Models_UserMapper */
    private Ccsd_User_Models_UserMapper $userMapper;
    private AccountTokenTestController $controller;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required');
        }

        $this->now = new DateTimeImmutable('2026-10-24 10:00:00');
        $this->tokens = new AccountTokenRepository(UserTokenTestDatabase::create(), fn(): DateTimeImmutable => $this->now);
        $this->userMapper = $this->createMock(Ccsd_User_Models_UserMapper::class);

        $this->controller = new AccountTokenTestController(
            new Zend_Controller_Request_HttpTestCase(),
            new Zend_Controller_Response_HttpTestCase()
        );
        $this->controller->tokens = $this->tokens;
        $this->controller->userMapper = $this->userMapper;
        $this->controller->view = new Zend_View();
    }

    private function issue(TokenPurpose $purpose, int $uid = self::UID): string
    {
        return $this->tokens->issue($uid, self::EMAIL, $purpose);
    }

    /**
     * @template T
     * @param callable(): T $call
     * @return T
     */
    private function withoutWarnings(callable $call): mixed
    {
        set_error_handler(static fn(): bool => true, E_USER_WARNING);
        try {
            return $call();
        } finally {
            restore_error_handler();
        }
    }

    // ---------------------------------------------------------------- activation

    public function testActivationTokenActivatesTheAccountOnce(): void
    {
        $token = $this->issue(TokenPurpose::ACCOUNT_ACTIVATION);

        $this->userMapper->expects(self::once())->method('activateAccountByUid')->with(self::UID);

        self::assertTrue($this->controller->activate($token));
        self::assertFalse($this->controller->activate($token), 'an activation link works only once');
    }

    /**
     * @dataProvider passwordPurposes
     */
    public function testPasswordTokenDoesNotActivateAnAccount(TokenPurpose $purpose): void
    {
        $token = $this->issue($purpose);

        $this->userMapper->expects(self::never())->method('activateAccountByUid');

        self::assertFalse($this->controller->activate($token));
    }

    public function testExpiredActivationTokenIsRefused(): void
    {
        $token = $this->issue(TokenPurpose::ACCOUNT_ACTIVATION);
        $this->now = $this->now->modify(sprintf('+%d seconds', TokenPurpose::ACCOUNT_ACTIVATION->ttlSeconds()));

        $this->userMapper->expects(self::never())->method('activateAccountByUid');

        self::assertFalse($this->controller->activate($token));
    }

    public function testFailedActivationIsReportedAndKeepsTheLink(): void
    {
        $token = $this->issue(TokenPurpose::ACCOUNT_ACTIVATION);
        $this->userMapper->method('activateAccountByUid')->willThrowException(new Zend_Db_Adapter_Exception('database unavailable'));

        self::assertFalse($this->withoutWarnings(fn(): bool => $this->controller->activate($token)));
        self::assertNotNull($this->tokens->find($token, TokenPurpose::ACCOUNT_ACTIVATION), 'the link must work again once the database is back');
    }

    public function testInvalidActivationLinkShowsTheErrorPageWithoutLeakingTheToken(): void
    {
        $this->controller->getRequest()->setParam('token', str_repeat('a', 64));

        $this->controller->activateAction();

        self::assertSame(['error/error.phtml'], $this->controller->renderedScripts);
        $headers = array_column($this->controller->getResponse()->getHeaders(), 'value', 'name');
        self::assertSame('no-referrer', $headers['Referrer-Policy']);
        self::assertSame('no-store', $headers['Cache-Control']);
    }

    // ---------------------------------------------------------------- password reset

    /**
     * @return iterable<string, array{TokenPurpose}>
     */
    public static function passwordPurposes(): iterable
    {
        yield 'lost password' => [TokenPurpose::PASSWORD_RESET];
        yield 'account created by an editor' => [TokenPurpose::ACCOUNT_SETUP];
    }

    /**
     * @dataProvider passwordPurposes
     */
    public function testPasswordTokenSetsThePasswordOnce(TokenPurpose $purpose): void
    {
        $token = $this->issue($purpose);

        $this->userMapper->expects(self::once())->method('savePassword')->with(self::callback(
            static fn(Ccsd_User_Models_User $user): bool => $user->getUid() === self::UID
                && $user->getPassword() !== self::PASSWORD
        ))->willReturn(1);

        self::assertTrue($this->controller->resetPassword($token, self::PASSWORD));
        self::assertFalse($this->controller->resetPassword($token, self::PASSWORD), 'a password link works only once');
    }

    public function testActivationTokenCannotSetAPassword(): void
    {
        $token = $this->issue(TokenPurpose::ACCOUNT_ACTIVATION);

        $this->userMapper->expects(self::never())->method('savePassword');

        self::assertFalse($this->controller->resetPassword($token, self::PASSWORD));
    }

    public function testExpiredPasswordTokenIsRefused(): void
    {
        $token = $this->issue(TokenPurpose::PASSWORD_RESET);
        $this->now = $this->now->modify(sprintf('+%d seconds', TokenPurpose::PASSWORD_RESET->ttlSeconds()));

        $this->userMapper->expects(self::never())->method('savePassword');

        self::assertFalse($this->controller->resetPassword($token, self::PASSWORD));
    }

    public function testPasswordChangeRevokesTheOtherPasswordLinksOfTheAccount(): void
    {
        $setup = $this->issue(TokenPurpose::ACCOUNT_SETUP);
        $reset = $this->issue(TokenPurpose::PASSWORD_RESET);
        $otherAccount = $this->issue(TokenPurpose::PASSWORD_RESET, self::UID + 1);
        $this->userMapper->method('savePassword')->willReturn(1);

        self::assertTrue($this->controller->resetPassword($reset, self::PASSWORD));

        self::assertNull($this->tokens->find($setup, TokenPurpose::ACCOUNT_SETUP));
        self::assertNotNull($this->tokens->find($otherAccount, TokenPurpose::PASSWORD_RESET));
    }

    public function testPasswordLinkOfAnAccountThatNoLongerExistsReportsAFailure(): void
    {
        $token = $this->issue(TokenPurpose::PASSWORD_RESET);
        $this->userMapper->method('savePassword')->willReturn(0);

        self::assertFalse($this->controller->resetPassword($token, self::PASSWORD));
    }

    public function testPasswordLinkStillWorksWhenThePasswordCouldNotBeWritten(): void
    {
        $token = $this->issue(TokenPurpose::PASSWORD_RESET);
        $this->userMapper->method('savePassword')->willReturnOnConsecutiveCalls(
            self::throwException(new Zend_Db_Adapter_Exception('database unavailable')),
            1
        );

        self::assertFalse($this->withoutWarnings(fn(): bool => $this->controller->resetPassword($token, self::PASSWORD)));
        self::assertTrue($this->controller->resetPassword($token, self::PASSWORD), 'the link must work again once the database is back');
    }

    public function testPasswordChangedFromTheProfileRevokesThePasswordLinksOfTheAccount(): void
    {
        $setup = $this->issue(TokenPurpose::ACCOUNT_SETUP);
        $reset = $this->issue(TokenPurpose::PASSWORD_RESET);
        $otherAccount = $this->issue(TokenPurpose::PASSWORD_RESET, self::UID + 1);
        $this->userMapper->expects(self::once())->method('savePassword')->willReturn(1);

        self::assertTrue($this->controller->changePassword(self::UID, self::PASSWORD));

        self::assertNull($this->tokens->find($setup, TokenPurpose::ACCOUNT_SETUP));
        self::assertNull($this->tokens->find($reset, TokenPurpose::PASSWORD_RESET));
        self::assertNotNull($this->tokens->find($otherAccount, TokenPurpose::PASSWORD_RESET));
    }

    public function testFailedPasswordChangeKeepsThePasswordLinks(): void
    {
        $reset = $this->issue(TokenPurpose::PASSWORD_RESET);
        $this->userMapper->method('savePassword')->willReturn(0);

        self::assertFalse($this->controller->changePassword(self::UID, self::PASSWORD));
        self::assertNotNull($this->tokens->find($reset, TokenPurpose::PASSWORD_RESET));
    }

    public function testEmailChangeRevokesAllTheLinksOfTheAccount(): void
    {
        $links = array_map(fn(TokenPurpose $purpose): array => [$purpose, $this->issue($purpose)], TokenPurpose::cases());
        $otherAccount = $this->issue(TokenPurpose::PASSWORD_RESET, self::UID + 1);

        $this->controller->revokeLinks(self::UID);

        foreach ($links as [$purpose, $token]) {
            self::assertNull($this->tokens->find($token, $purpose), $purpose->value);
        }
        self::assertNotNull($this->tokens->find($otherAccount, TokenPurpose::PASSWORD_RESET));
    }

    // ---------------------------------------------------------------- lost password / lost login

    /**
     * @param array<string, mixed>|null $account
     */
    private function lostPasswordFor(?array $account): mixed
    {
        $this->userMapper->method('findByUsernameIncludingInactive')->with('jdoe')->willReturn($account);
        $this->controller->lostPassword('jdoe');

        return $this->controller->view->resultMessage;
    }

    public function testLostPasswordOfAnActiveAccountSendsAPasswordLink(): void
    {
        $message = $this->lostPasswordFor(['UID' => self::UID, 'USERNAME' => 'jdoe', 'EMAIL' => self::EMAIL, 'VALID' => 1]);

        self::assertSame(Ccsd_User_Models_User::ACCOUNT_VALID_USERNAME, $message);
        self::assertCount(1, $this->controller->sentMails);
        self::assertSame('password reset', $this->controller->sentMails[0][0]);
        self::assertSame(self::UID, (int)$this->controller->sentMails[0][1]->getUid());
    }

    public function testLostPasswordOfAnEpisciencesAccountNotActivatedSendsANewActivationLink(): void
    {
        $this->controller->episciencesUids = [self::UID];

        $message = $this->lostPasswordFor(['UID' => self::UID, 'USERNAME' => 'jdoe', 'EMAIL' => self::EMAIL, 'VALID' => '0']);

        self::assertSame(Ccsd_User_Models_User::ACCOUNT_VALID_USERNAME, $message);
        self::assertSame(['activation'], array_column($this->controller->sentMails, 0));
    }

    public function testLostPasswordOfAnInactiveAccountOfAnotherApplicationSendsNothingButAnswersTheSame(): void
    {
        $this->controller->episciencesUids = [];

        $message = $this->lostPasswordFor(['UID' => self::UID, 'USERNAME' => 'jdoe', 'EMAIL' => self::EMAIL, 'VALID' => '0']);

        self::assertSame(Ccsd_User_Models_User::ACCOUNT_VALID_USERNAME, $message);
        self::assertSame([], $this->controller->sentMails);
    }

    /**
     * @dataProvider accountStates
     */
    public function testLostPasswordAnswersTheSameWhenTheEmailFails(int $valid): void
    {
        $this->controller->episciencesUids = [self::UID];
        $this->controller->mailFailure = new \Zend_Mail_Exception('SMTP unavailable');

        $message = $this->withoutWarnings(
            fn(): mixed => $this->lostPasswordFor(['UID' => self::UID, 'USERNAME' => 'jdoe', 'EMAIL' => self::EMAIL, 'VALID' => $valid])
        );

        self::assertSame(Ccsd_User_Models_User::ACCOUNT_VALID_USERNAME, $message);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function accountStates(): iterable
    {
        yield 'active account' => [1];
        yield 'account not activated' => [0];
    }

    public function testLostPasswordOfAnUnknownUsernameSendsNothingButAnswersTheSame(): void
    {
        $message = $this->lostPasswordFor(null);

        self::assertSame(Ccsd_User_Models_User::ACCOUNT_VALID_USERNAME, $message);
        self::assertSame([], $this->controller->sentMails);
    }

    public function testLostLoginOfAKnownAddressSendsTheUsernames(): void
    {
        $this->userMapper->method('findLoginByEmail')->with(self::EMAIL)
            ->willReturn(new Zend_Db_Table_Rowset(['data' => [['USERNAME' => 'jdoe', 'VALID' => 1]]]));

        $this->controller->lostLogin(self::EMAIL);

        self::assertSame(Ccsd_User_Models_User::ACCOUNT_LOST_LOGIN_FOUND, $this->controller->view->resultMessage);
        self::assertSame(['lost login'], array_column($this->controller->sentMails, 0));
        self::assertSame(self::EMAIL, $this->controller->sentMails[0][1]->getEmail());
    }

    public function testLostLoginOfAnUnknownAddressSendsNothingButAnswersTheSame(): void
    {
        $this->userMapper->method('findLoginByEmail')->willReturn(new Zend_Db_Table_Rowset(['data' => []]));

        $this->controller->lostLogin(self::EMAIL);

        self::assertSame(Ccsd_User_Models_User::ACCOUNT_LOST_LOGIN_FOUND, $this->controller->view->resultMessage);
        self::assertSame([], $this->controller->sentMails);
    }
}

/**
 * UserDefaultController with stubbed collaborators: tokens in SQLite, CAS mapper mocked, e-mails and rendering recorded
 */
final class AccountTokenTestController extends \UserDefaultController
{
    public AccountTokenRepository $tokens;
    public Ccsd_User_Models_UserMapper $userMapper;
    /** @var list<array{string, Episciences_User}> */
    public array $sentMails = [];
    /** @var list<string> */
    public array $renderedScripts = [];
    /** @var list<int> UIDs of the accounts having their data in the application database */
    public array $episciencesUids = [];
    /** Thrown by the e-mail stubs when set */
    public ?\Exception $mailFailure = null;

    protected function getAccountTokenRepository(): AccountTokenRepository
    {
        return $this->tokens;
    }

    protected function getUserMapper(): Ccsd_User_Models_UserMapper
    {
        return $this->userMapper;
    }

    protected function loadUser(array $account): Episciences_User
    {
        return new Episciences_User($account);
    }

    protected function isEpisciencesAccount(int $uid): bool
    {
        return in_array($uid, $this->episciencesUids, true);
    }

    protected function sendActivationEmail(Episciences_User $user): void
    {
        $this->recordMail('activation', $user);
    }

    protected function sendPasswordResetEmail(Episciences_User $user): void
    {
        $this->recordMail('password reset', $user);
    }

    private function recordMail(string $type, Episciences_User $user): void
    {
        if ($this->mailFailure !== null) {
            throw $this->mailFailure;
        }

        $this->sentMails[] = [$type, $user];
    }

    protected function sendLostLoginEmail(Episciences_User $user, array $userLogins): void
    {
        $this->sentMails[] = ['lost login', $user];
    }

    public function renderScript($script, $name = null): void
    {
        $this->renderedScripts[] = $script;
    }

    public function activate(mixed $token): bool
    {
        return $this->activateAccountWithToken($token);
    }

    public function resetPassword(mixed $token, string $password): bool
    {
        return $this->resetPasswordWithToken($token, $password);
    }

    public function changePassword(int $uid, string $password): bool
    {
        return $this->saveNewPassword($uid, $password);
    }

    public function revokeLinks(int $uid): void
    {
        $this->revokeAccountLinks($uid);
    }

    public function lostPassword(string $username): void
    {
        $this->processLostPasswordRequest($username);
    }

    public function lostLogin(string $email): void
    {
        $this->processLostLoginRequest($email);
    }
}
