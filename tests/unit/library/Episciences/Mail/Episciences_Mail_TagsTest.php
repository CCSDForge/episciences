<?php

namespace unit\library\Episciences\Mail;

use Episciences_Mail_Tags;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers Episciences_Mail_Tags
 */
final class Episciences_Mail_TagsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Returns all public constants defined directly on Episciences_Mail_Tags. */
    private function getAllTagConstants(): array
    {
        $rc = new ReflectionClass(Episciences_Mail_Tags::class);
        $all = $rc->getConstants();

        // Exclude non-tag constants (arrays, string-metadata)
        return array_filter($all, static fn ($v) => is_string($v) && str_starts_with($v, '%%'));
    }

    // -------------------------------------------------------------------------
    // Tag format
    // -------------------------------------------------------------------------

    /**
     * Every %%TAG%% constant must open and close with %%.
     */
    public function testAllTagConstantsMatchExpectedFormat(): void
    {
        $tags = $this->getAllTagConstants();
        self::assertNotEmpty($tags);

        foreach ($tags as $name => $value) {
            self::assertMatchesRegularExpression(
                '/^%%[A-Z0-9_%]+%%$/',
                $value,
                "Constant $name has unexpected format: '$value'"
            );
        }
    }

    /**
     * There must be at least 50 tag constants (regression guard).
     */
    public function testMinimumTagCount(): void
    {
        self::assertGreaterThanOrEqual(50, count($this->getAllTagConstants()));
    }

    // -------------------------------------------------------------------------
    // SENDER_TAGS
    // -------------------------------------------------------------------------

    public function testSenderTagsIsNonEmptyArray(): void
    {
        self::assertIsArray(Episciences_Mail_Tags::SENDER_TAGS);
        self::assertNotEmpty(Episciences_Mail_Tags::SENDER_TAGS);
    }

    public function testSenderTagsContainsExpectedValues(): void
    {
        $expected = [
            Episciences_Mail_Tags::TAG_SENDER_SCREEN_NAME,
            Episciences_Mail_Tags::TAG_SENDER_EMAIL,
            Episciences_Mail_Tags::TAG_SENDER_FULL_NAME,
            Episciences_Mail_Tags::TAG_SENDER_FIRST_NAME,
            Episciences_Mail_Tags::TAG_SENDER_LAST_NAME,
        ];

        foreach ($expected as $tag) {
            self::assertContains($tag, Episciences_Mail_Tags::SENDER_TAGS, "Expected $tag in SENDER_TAGS");
        }
    }

    public function testSenderTagsContainsOnlySenderTagConstants(): void
    {
        foreach (Episciences_Mail_Tags::SENDER_TAGS as $tag) {
            self::assertStringContainsString('SENDER', $tag, "Non-sender tag found in SENDER_TAGS: $tag");
        }
    }

    // -------------------------------------------------------------------------
    // TAG_DESCRIPTION coverage
    // -------------------------------------------------------------------------

    public function testTagDescriptionIsNonEmptyArray(): void
    {
        self::assertIsArray(Episciences_Mail_Tags::TAG_DESCRIPTION);
        self::assertNotEmpty(Episciences_Mail_Tags::TAG_DESCRIPTION);
    }

    public function testTagDescriptionKeysAreTagConstants(): void
    {
        $allTags = $this->getAllTagConstants();

        foreach (array_keys(Episciences_Mail_Tags::TAG_DESCRIPTION) as $key) {
            self::assertContains(
                $key,
                $allTags,
                "TAG_DESCRIPTION key '$key' is not a recognised %%TAG%% constant"
            );
        }
    }

    public function testTagDescriptionValuesAreNonEmptyStrings(): void
    {
        foreach (Episciences_Mail_Tags::TAG_DESCRIPTION as $tag => $description) {
            self::assertIsString($description, "Description for $tag must be a string");
            self::assertNotEmpty($description, "Description for $tag must not be empty");
        }
    }

    // -------------------------------------------------------------------------
    // Alias consistency
    // -------------------------------------------------------------------------

    /**
     * TAG_LOST_LOGINS is an alias for TAG_MAIL_ACCOUNT_USERNAME_LIST.
     * Both must share the same underlying value.
     */
    public function testLostLoginsAliasMatchesMailAccountUsernameList(): void
    {
        self::assertSame(
            Episciences_Mail_Tags::TAG_MAIL_ACCOUNT_USERNAME_LIST,
            Episciences_Mail_Tags::TAG_LOST_LOGINS
        );
    }

    // -------------------------------------------------------------------------
    // Individual constant values — regression guard
    // -------------------------------------------------------------------------

    /** @dataProvider provideExpectedTagValues */
    public function testKnownTagValuesAreUnchanged(string $constant, string $expectedValue): void
    {
        $rc = new ReflectionClass(Episciences_Mail_Tags::class);
        self::assertSame($expectedValue, $rc->getConstant($constant));
    }

    public static function provideExpectedTagValues(): array
    {
        return [
            ['TAG_REVIEW_CODE',                '%%REVIEW_CODE%%'],
            ['TAG_REVIEW_NAME',                '%%REVIEW_NAME%%'],
            ['TAG_RECIPIENT_FULL_NAME',        '%%RECIPIENT_FULL_NAME%%'],
            ['TAG_RECIPIENT_EMAIL',            '%%RECIPIENT_EMAIL%%'],
            ['TAG_ARTICLE_ID',                 '%%ARTICLE_ID%%'],
            ['TAG_ARTICLE_TITLE',              '%%ARTICLE_TITLE%%'],
            ['TAG_PAPER_URL',                  '%%PAPER_URL%%'],
            ['TAG_SENDER_EMAIL',               '%%SENDER_EMAIL%%'],
            ['TAG_TOKEN_VALIDATION_LINK',      '%%TOKEN_VALIDATION_LINK%%'],
            ['TAG_DOI',                        '%%DOI%%'],
        ];
    }

    // -------------------------------------------------------------------------
    // TAG_AUTHOR_SCREEN_NAME — correct description after fix
    // -------------------------------------------------------------------------

    public function testAuthorScreenNameDescriptionMentionsDisplayName(): void
    {
        $description = Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_AUTHOR_SCREEN_NAME] ?? null;

        self::assertNotNull($description, 'TAG_AUTHOR_SCREEN_NAME has no description entry');
        // Must refer to display name ("affichage"), not full name ("complet").
        self::assertStringContainsString('affichage', $description);
        self::assertStringNotContainsString('complet', $description);
    }
}
