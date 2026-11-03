<?php

declare(strict_types=1);

namespace unit\library\Episciences\Mail;

use Episciences\Mail\RecipientSuggestions;
use Episciences_User;
use PHPUnit\Framework\TestCase;

final class RecipientSuggestionsTest extends TestCase
{
    public function testSuggestionsAreKeyedByUid(): void
    {
        $suggestions = RecipientSuggestions::fromUsers([
            self::user(12, 'Ada', 'Lovelace', 'ALovelace', 'ada@example.org'),
            self::user(34, 'Alan', 'Turing', 'aturing', 'alan@example.org'),
        ]);

        self::assertSame([12, 34], array_keys($suggestions));
        self::assertSame(
            [
                'uid' => 12,
                'fullname' => 'Ada Lovelace',
                'username' => 'ALovelace',
                'mail' => 'ada@example.org',
                'label' => 'Ada Lovelace (alovelace) <ada@example.org>',
                'htmlLabel' => '<span class="ep-ac-name">Ada Lovelace <span class="darkgrey">(alovelace)</span></span>'
                    . '<span class="ep-ac-email">ada@example.org</span>',
            ],
            $suggestions[12]
        );
    }

    /**
     * htmlLabel is appended as HTML to the autocomplete list: every user value is escaped.
     */
    public function testHtmlLabelEscapesTheUserValues(): void
    {
        $suggestion = RecipientSuggestions::fromUsers([
            self::user(1, '<img src=x onerror=alert(1)>', 'Doe', 'j"doe', "o'brien&co@example.org"),
        ])[1];

        self::assertStringNotContainsString('<img', $suggestion['htmlLabel']);
        self::assertStringContainsString('&lt;img ', $suggestion['htmlLabel']);
        self::assertStringContainsString('(j&quot;doe)', $suggestion['htmlLabel']);
        self::assertStringContainsString('o&#039;brien&amp;co@example.org', $suggestion['htmlLabel']);
    }

    /**
     * label is matched as text and JSON-encoded, never inserted as HTML: it stays unescaped.
     */
    public function testLabelIsPlainText(): void
    {
        $suggestion = RecipientSuggestions::fromUsers([self::user(1, 'Jean & Marie', 'Curie', 'jmc', 'jmc@example.org')])[1];

        self::assertSame('Jean & Marie Curie (jmc) <jmc@example.org>', $suggestion['label']);
    }

    /**
     * The label is rendered inside a link: block elements would break the list.
     */
    public function testHtmlLabelUsesInlineElementsOnly(): void
    {
        $suggestion = RecipientSuggestions::fromUsers([self::user(1, 'Ada', 'Lovelace', 'ada', 'ada@example.org')])[1];

        self::assertStringNotContainsString('<div', $suggestion['htmlLabel']);
    }

    public function testIncompleteUserGivesEmptyValues(): void
    {
        $suggestion = RecipientSuggestions::fromUsers([new Episciences_User(['UID' => 5])])[5];

        self::assertSame('', $suggestion['username']);
        self::assertSame('', $suggestion['mail']);
        self::assertStringContainsString('<span class="ep-ac-email"></span>', $suggestion['htmlLabel']);
    }

    public function testNoUserGivesNoSuggestion(): void
    {
        self::assertSame([], RecipientSuggestions::fromUsers([]));
    }

    private static function user(int $uid, string $firstname, string $lastname, string $username, string $email): Episciences_User
    {
        $user = new Episciences_User(['UID' => $uid]);
        $user->setFirstname($firstname)->setLastname($lastname)->setUsername($username)->setEmail($email);

        return $user;
    }
}
