<?php

declare(strict_types=1);

namespace unit\library\Episciences\Reviewer;

use Episciences\Reviewer\InvitationResend;
use Episciences\Reviewer\InvitationTemplates;
use PHPUnit\Framework\TestCase;

/**
 * Sending an invitation again (#319, lot F): when it is possible, which template, which language
 *
 * @covers \Episciences\Reviewer\InvitationResend
 */
final class InvitationResendTest extends TestCase
{
    public function testAnInvitationWaitingForAnAnswerIsSentAgainByWhoManagesTheReviewing(): void
    {
        self::assertNull(InvitationResend::refusal(true, true, true));
    }

    public function testWhyAnInvitationIsNotSentAgain(): void
    {
        self::assertSame(InvitationResend::NOT_ALLOWED, InvitationResend::refusal(false, true, true));
        // answered, cancelled or expired
        self::assertSame(InvitationResend::NOT_PENDING, InvitationResend::refusal(true, false, true));
        self::assertSame(InvitationResend::NOT_REVIEWABLE, InvitationResend::refusal(true, true, false));
        // the rights first: nothing is said about the invitation to who may not manage it
        self::assertSame(InvitationResend::NOT_ALLOWED, InvitationResend::refusal(false, false, false));
    }

    public function testTheTemplateFollowsTheAccountOfTheInvitedPerson(): void
    {
        self::assertSame(InvitationTemplates::ACCOUNT_NEW_USER, InvitationResend::accountState(true, false));
        self::assertSame(InvitationTemplates::ACCOUNT_NEW_USER, InvitationResend::accountState(true, true));
        self::assertSame(InvitationTemplates::ACCOUNT_KNOWN_REVIEWER, InvitationResend::accountState(false, true));
        self::assertSame(InvitationTemplates::ACCOUNT_KNOWN_USER, InvitationResend::accountState(false, false));
    }

    public function testTheMessageIsInTheLanguageOfTheInvitedPersonElseOfTheJournal(): void
    {
        $texts = ['fr' => 'Bonjour', 'en' => 'Hello'];

        self::assertSame('Hello', InvitationResend::inLanguage($texts, 'en', 'fr'));
        self::assertSame('Bonjour', InvitationResend::inLanguage($texts, 'de', 'fr'));
        self::assertSame('Hello', InvitationResend::inLanguage(['en' => 'Hello'], 'de', 'fr'));
        self::assertSame('', InvitationResend::inLanguage([], 'en', 'fr'));
    }
}
