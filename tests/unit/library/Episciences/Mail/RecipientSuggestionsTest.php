<?php

declare(strict_types=1);

namespace unit\library\Episciences\Mail;

use Episciences\Mail\RecipientSuggestions;
use Episciences_User;
use PHPUnit\Framework\TestCase;

final class RecipientSuggestionsTest extends TestCase
{
    public function testRowIsConvertedToTheCommonFormat(): void
    {
        $suggestion = RecipientSuggestions::fromRow([
            'UID' => '12',
            'SCREEN_NAME' => 'ALovelace',
            'FIRSTNAME' => 'ada',
            'LASTNAME' => 'lovelace',
            'USERNAME' => 'ALovelace',
            'EMAIL' => 'ada@example.org',
            'ROLES' => 'chief_editor,member',
        ]);

        self::assertSame([
            'uid' => 12,
            'fullname' => 'Ada Lovelace',
            'username' => 'ALovelace',
            'mail' => 'ada@example.org',
            'roles' => ['chief_editor'],
            'group' => RecipientSuggestions::GROUP_STAFF,
            'paperRole' => null,
        ], $suggestion);
    }

    /**
     * Values are rendered as text by the scripts: they are never HTML-encoded here
     */
    public function testValuesAreRaw(): void
    {
        $suggestion = RecipientSuggestions::fromRow(['UID' => 1, 'LASTNAME' => 'Curie & <b>co</b>', 'EMAIL' => 'a"b@example.org']);

        self::assertStringContainsString('&', $suggestion['fullname']);
        self::assertStringNotContainsString('&amp;', $suggestion['fullname']);
        self::assertSame('a"b@example.org', $suggestion['mail']);
    }

    public function testScreenNameIsUsedWithoutFirstAndLastName(): void
    {
        $suggestion = RecipientSuggestions::fromRow(['UID' => 1, 'SCREEN_NAME' => 'Ada', 'FIRSTNAME' => '', 'LASTNAME' => '']);

        self::assertSame('Ada', $suggestion['fullname']);
    }

    /**
     * @return array<string, array{string, ?string, string}>
     */
    public static function suggestionGroups(): array
    {
        return [
            'participant of the paper' => ['reviewer', 'reviewer', RecipientSuggestions::GROUP_PAPER],
            'editorial staff' => ['editor,reviewer', null, RecipientSuggestions::GROUP_STAFF],
            'other user' => ['author,reviewer', null, RecipientSuggestions::GROUP_OTHER],
            'no role in the journal' => ['', null, RecipientSuggestions::GROUP_OTHER],
        ];
    }

    /**
     * @dataProvider suggestionGroups
     */
    public function testGroupOfTheSuggestion(string $roles, ?string $paperRole, string $group): void
    {
        self::assertSame($group, RecipientSuggestions::fromRow(['UID' => 1, 'ROLES' => $roles], $paperRole)['group']);
    }

    public function testKnownUser(): void
    {
        $user = new Episciences_User(['UID' => 5]);
        $user->setFirstname('Alan')->setLastname('Turing')->setUsername('aturing')->setEmail('alan@example.org')->setScreenName('Alan T.');

        self::assertSame([
            'uid' => 5,
            'fullname' => 'Alan Turing',
            'screen_name' => 'Alan T.',
            'username' => 'aturing',
            'mail' => 'alan@example.org',
        ], RecipientSuggestions::fromUser($user));
    }

    public function testScriptJsonCannotCloseTheScriptBlock(): void
    {
        $json = RecipientSuggestions::toScriptJson(['fullname' => "</script><script>alert(1)</script>", 'mail' => "a'b@example.org"]);

        self::assertStringNotContainsString('</script>', $json);
        self::assertStringNotContainsString("'", $json);
        self::assertSame("</script><script>alert(1)</script>", json_decode($json, true, 512, JSON_THROW_ON_ERROR)['fullname']);
    }

    public function testSenderIsNullWhenNotLoggedIn(): void
    {
        \Zend_Auth::getInstance()->clearIdentity();

        self::assertSame('null', RecipientSuggestions::senderJson());
    }
}
