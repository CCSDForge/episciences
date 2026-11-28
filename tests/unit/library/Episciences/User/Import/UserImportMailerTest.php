<?php

declare(strict_types=1);

namespace unit\library\Episciences\User\Import;

use Episciences\User\Import\MailOutcome;
use Episciences\User\Import\UserImportMailer;
use Episciences\User\Token\AccountTokenRepository;
use Episciences\User\Token\TokenPurpose;
use Episciences_User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zend_Locale;
use Zend_Registry;

/**
 * @covers \Episciences\User\Import\UserImportMailer
 */
final class UserImportMailerTest extends TestCase
{
    private ?Zend_Locale $previousLocale = null;

    protected function setUp(): void
    {
        $this->previousLocale = Zend_Registry::isRegistered('Zend_Locale') ? Zend_Registry::get('Zend_Locale') : null;
        Zend_Registry::set('Zend_Locale', new Zend_Locale('en'));
    }

    protected function tearDown(): void
    {
        if ($this->previousLocale instanceof Zend_Locale) {
            Zend_Registry::set('Zend_Locale', $this->previousLocale);
        } else {
            Zend_Registry::getInstance()->offsetUnset('Zend_Locale');
        }
    }

    public function testMemberIsNotListedWithOtherRoles(): void
    {
        self::assertSame(['editor', 'reviewer'], UserImportMailer::displayedRoles(['member', 'editor', 'reviewer']));
    }

    public function testMemberAloneIsListed(): void
    {
        self::assertSame(['member'], UserImportMailer::displayedRoles(['member']));
    }

    public function testTheLinkValidityIsGivenInDays(): void
    {
        self::assertGreaterThanOrEqual(1, UserImportMailer::linkValidityDays());
    }

    public function testAFailedSetupEmailRevokesItsLinkSoTheNextImportSendsItAgain(): void
    {
        $tokens = $this->tokens(false);
        $tokens->expects(self::once())->method('revokeAll')->with(42, TokenPurpose::ACCOUNT_SETUP);

        self::assertSame(MailOutcome::Failed, $this->mailer($tokens, false)->sendAccountSetup($this->user(), ['member']));
    }

    public function testASentSetupEmailKeepsItsLink(): void
    {
        $tokens = $this->tokens(false);
        $tokens->expects(self::never())->method('revokeAll');

        self::assertSame(MailOutcome::Sent, $this->mailer($tokens, true)->sendAccountSetup($this->user(), ['member']));
    }

    public function testASetupEmailIsNotSentAgainWithinTheResendDelay(): void
    {
        $tokens = $this->tokens(true);
        $tokens->expects(self::never())->method('revokeAll');

        self::assertSame(MailOutcome::AlreadySent, $this->mailer($tokens, false)->sendAccountSetup($this->user(), ['member']));
    }

    /**
     * @return AccountTokenRepository&MockObject
     */
    private function tokens(bool $issuedRecently): AccountTokenRepository
    {
        $tokens = $this->createMock(AccountTokenRepository::class);
        $tokens->method('issuedWithin')->willReturn($issuedRecently);

        return $tokens;
    }

    private function mailer(AccountTokenRepository $tokens, bool $isSent): UserImportMailer
    {
        return new class ($tokens, static fn(array $params): string => '/', $isSent) extends UserImportMailer {
            /**
             * @param callable(array<string, string>): string $urlBuilder
             */
            public function __construct(AccountTokenRepository $tokens, callable $urlBuilder, private readonly bool $isSent)
            {
                parent::__construct($tokens, $urlBuilder);
            }

            protected function sendSetupLink(Episciences_User $user, array $tags): bool
            {
                return $this->isSent;
            }
        };
    }

    private function user(): Episciences_User
    {
        $user = new Episciences_User();
        $user->setUid(42);
        $user->setLangueid('en');

        return $user;
    }
}
