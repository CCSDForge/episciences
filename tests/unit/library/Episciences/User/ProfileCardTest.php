<?php

declare(strict_types=1);

namespace unit\library\Episciences\User;

use Episciences\User\ProfileCard;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Episciences\User\ProfileCard
 */
final class ProfileCardTest extends TestCase
{
    public function testRegisteredAccountIsNormalizedFromEpisciencesUserToArray(): void
    {
        $card = ProfileCard::fromArray([
            'uid' => '42',
            'SCREEN_NAME' => ' Jane Doe ',
            'email' => 'jane@example.org',
            'orcid' => '0000-0002-1825-0097',
            'affiliations' => [['label' => 'CNRS', 'rorId' => 'https://ror.org/02feahw73'], 'not-an-array'],
            'web_sites' => ['https://example.org', '', null],
            'social_medias' => [],
            'biography' => '',
            'langueid' => 'fr',
            'time_registered' => '2024-03-15 10:20:30',
            'ROLES' => [7 => ['reviewer', 'editor'], 8 => ['member']],
        ], 7);

        self::assertSame(42, $card->uid);
        self::assertTrue($card->isRegistered());
        self::assertSame('Jane Doe', $card->name);
        self::assertSame('jane@example.org', $card->email);
        self::assertSame('0000-0002-1825-0097', $card->orcid);
        self::assertSame([['label' => 'CNRS', 'rorId' => 'https://ror.org/02feahw73']], $card->affiliations);
        self::assertSame(['https://example.org'], $card->webSites);
        self::assertNull($card->biography);
        self::assertSame('fr', $card->languageCode);
        self::assertSame('2024-03-15', $card->registrationDate);
        self::assertSame(['reviewer', 'editor'], $card->roles, 'only the roles of the current journal');
    }

    public function testAccountLessReviewerIsNormalizedFromTmpUserToArray(): void
    {
        $card = ProfileCard::fromArray([
            'id' => 12,
            'email' => 'guest@example.org',
            'screen_name' => 'Guest Reviewer',
            'lang' => 'en',
        ], 7);

        self::assertNull($card->uid);
        self::assertFalse($card->isRegistered());
        self::assertSame('Guest Reviewer', $card->name);
        self::assertSame('en', $card->languageCode);
        self::assertSame([], $card->roles);
        self::assertNull($card->registrationDate);
    }

    public function testNameFallsBackToFirstAndLastName(): void
    {
        $card = ProfileCard::fromArray(['firstname' => 'Ada', 'lastname' => 'Lovelace'], 7);

        self::assertSame('Ada Lovelace', $card->name);
    }

    public function testTimestampRegistrationDateIsFormatted(): void
    {
        $card = ProfileCard::fromArray(['time_registered' => (string)mktime(12, 0, 0, 1, 2, 2025)], 7);

        self::assertSame('2025-01-02', $card->registrationDate);
    }

    public function testSocialMediaStoredAsASingleStringIsKept(): void
    {
        // Episciences_User::getSocialMedias() returns a string, not a list
        $card = ProfileCard::fromArray(['social_medias' => ' @jane@mastodon.social '], 7);

        self::assertSame(['@jane@mastodon.social'], $card->socialMedias);
    }

    public function testPrivateDetailsAreForManagersAndTheOwnerOnly(): void
    {
        $card = ProfileCard::fromArray(['uid' => 42], 7);

        self::assertTrue($card->canShowPrivateDetailsTo(99, true), 'manager of a journal the person belongs to');
        self::assertTrue($card->canShowPrivateDetailsTo(42, false), 'the person themself');
        self::assertFalse($card->canShowPrivateDetailsTo(99, false), 'another member');
        self::assertFalse($card->canShowPrivateDetailsTo(0, false), 'anonymous visitor');
    }

    public function testAnonymousVisitorIsNeverTheOwnerOfAnAccountLessProfile(): void
    {
        // uid null vs viewer uid 0: must not be treated as "the same person"
        $card = ProfileCard::fromArray(['email' => 'guest@example.org'], 7);

        self::assertFalse($card->canShowPrivateDetailsTo(0, false));
    }

    public function testAccountLessReviewerAvatarIsInlinedNotServedByUserPhoto(): void
    {
        // /user/photo without a uid serves the *viewer's* photo: never use it for them
        $card = ProfileCard::fromArray(['screen_name' => 'Guest Reviewer'], 7);

        $src = $card->avatarSrc(42, 'v1');

        self::assertStringStartsWith('data:image/svg+xml;base64,', $src);
        self::assertStringContainsString('GR', (string)base64_decode(substr($src, strlen('data:image/svg+xml;base64,'))));
    }
}
