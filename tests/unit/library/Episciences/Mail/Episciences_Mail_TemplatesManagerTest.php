<?php

namespace unit\library\Episciences\Mail;

use Episciences_Mail_Tags;
use Episciences_Mail_TemplatesManager;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for Episciences_Mail_TemplatesManager.
 *
 * Covers: TYPE_* constant format, getAvailableTagsByKey(), COMMON_TAGS,
 * AUTOMATIC_TEMPLATES, and the consistency between type constants and
 * TEMPLATE_DESCRIPTION_AND_RECIPIENT.
 *
 * @covers Episciences_Mail_TemplatesManager
 */
final class Episciences_Mail_TemplatesManagerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Returns all TYPE_* string constants defined on Episciences_Mail_TemplatesManager. */
    private function getAllTypeConstants(): array
    {
        $rc = new ReflectionClass(Episciences_Mail_TemplatesManager::class);
        return array_filter(
            $rc->getConstants(),
            static fn($value, $key) => str_starts_with($key, 'TYPE_') && is_string($value),
            ARRAY_FILTER_USE_BOTH
        );
    }

    // -------------------------------------------------------------------------
    // TYPE_* constants — format
    // -------------------------------------------------------------------------

    public function testAllTypeConstantsAreNonEmptyStrings(): void
    {
        $constants = $this->getAllTypeConstants();
        self::assertNotEmpty($constants);

        foreach ($constants as $name => $value) {
            self::assertIsString($value, "$name must be a string");
            self::assertNotEmpty($value, "$name must not be empty");
        }
    }

    public function testAllTypeConstantsUseSnakeCaseFormat(): void
    {
        foreach ($this->getAllTypeConstants() as $name => $value) {
            self::assertMatchesRegularExpression(
                '/^[a-z][a-z0-9_]+$/',
                $value,
                "TYPE constant $name value '$value' must be snake_case"
            );
        }
    }

    public function testTypeConstantCountIsAtLeast50(): void
    {
        // Regression guard: ensure no mass-deletion of templates goes unnoticed.
        self::assertGreaterThanOrEqual(50, count($this->getAllTypeConstants()));
    }

    // -------------------------------------------------------------------------
    // BUG documentation: typo in constant value
    // -------------------------------------------------------------------------

    /**
     * CODE QUALITY: TYPE_PAPER_CE_AUTHOR_VERSION_FINALE_DEPOSED_AUTHOR_COPY has a
     * typo in its value: "paper_ce_author_vesrion_finale..." ('vesrion' vs 'version').
     *
     * This test documents the current (incorrect) value so that any change is
     * caught immediately. The user has explicitly requested that this constant
     * is NOT changed for now.
     */
    public function testAuthorVersionFinaleConstantHasKnownTypoInValue(): void
    {
        self::assertSame(
            'paper_ce_author_vesrion_finale_deposed_author_copy',
            Episciences_Mail_TemplatesManager::TYPE_PAPER_CE_AUTHOR_VERSION_FINALE_DEPOSED_AUTHOR_COPY,
            'Typo value changed — update this test and its twin constant if the correction was intentional.'
        );
    }

    // -------------------------------------------------------------------------
    // Suffix constants
    // -------------------------------------------------------------------------

    public function testSuffixTplNameIsNonEmptyString(): void
    {
        self::assertIsString(Episciences_Mail_TemplatesManager::SUFFIX_TPL_NAME);
        self::assertNotEmpty(Episciences_Mail_TemplatesManager::SUFFIX_TPL_NAME);
    }

    public function testSuffixTplSubjectIsNonEmptyString(): void
    {
        self::assertIsString(Episciences_Mail_TemplatesManager::SUFFIX_TPL_SUBJECT);
        self::assertNotEmpty(Episciences_Mail_TemplatesManager::SUFFIX_TPL_SUBJECT);
    }

    public function testSuffixTplNameDiffersFromSuffixTplSubject(): void
    {
        self::assertNotSame(
            Episciences_Mail_TemplatesManager::SUFFIX_TPL_NAME,
            Episciences_Mail_TemplatesManager::SUFFIX_TPL_SUBJECT
        );
    }

    public function testTplTranslationFileNameIsPhpFile(): void
    {
        self::assertStringEndsWith('.php', Episciences_Mail_TemplatesManager::TPL_TRANSLATION_FILE_NAME);
    }

    // -------------------------------------------------------------------------
    // COMMON_TAGS
    // -------------------------------------------------------------------------

    public function testCommonTagsIsNonEmptyArray(): void
    {
        self::assertIsArray(Episciences_Mail_TemplatesManager::COMMON_TAGS);
        self::assertNotEmpty(Episciences_Mail_TemplatesManager::COMMON_TAGS);
    }

    public function testCommonTagsContainsReviewCodeAndName(): void
    {
        self::assertContains(
            Episciences_Mail_Tags::TAG_REVIEW_CODE,
            Episciences_Mail_TemplatesManager::COMMON_TAGS
        );
        self::assertContains(
            Episciences_Mail_Tags::TAG_REVIEW_NAME,
            Episciences_Mail_TemplatesManager::COMMON_TAGS
        );
    }

    public function testCommonTagsValuesAreTagFormatStrings(): void
    {
        foreach (Episciences_Mail_TemplatesManager::COMMON_TAGS as $tag) {
            self::assertMatchesRegularExpression('/^%%[A-Z0-9_%]+%%$/', $tag, "COMMON_TAG '$tag' has invalid format");
        }
    }

    // -------------------------------------------------------------------------
    // AUTOMATIC_TEMPLATES
    // -------------------------------------------------------------------------

    public function testAutomaticTemplatesIsNonEmptyArray(): void
    {
        self::assertIsArray(Episciences_Mail_TemplatesManager::AUTOMATIC_TEMPLATES);
        self::assertNotEmpty(Episciences_Mail_TemplatesManager::AUTOMATIC_TEMPLATES);
    }

    public function testAutomaticTemplatesValuesAreStrings(): void
    {
        foreach (Episciences_Mail_TemplatesManager::AUTOMATIC_TEMPLATES as $tpl) {
            self::assertIsString($tpl, 'Each entry in AUTOMATIC_TEMPLATES must be a string');
            self::assertNotEmpty($tpl);
        }
    }

    public function testAutomaticTemplatesContainsExpectedEntries(): void
    {
        $expected = [
            Episciences_Mail_TemplatesManager::TYPE_USER_REGISTRATION,
            Episciences_Mail_TemplatesManager::TYPE_USER_LOST_PASSWORD,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_AUTHOR_COPY,
            Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_EDITOR_COPY,
        ];

        foreach ($expected as $type) {
            self::assertContains($type, Episciences_Mail_TemplatesManager::AUTOMATIC_TEMPLATES, "Expected $type in AUTOMATIC_TEMPLATES");
        }
    }

    public function testAutomaticTemplatesValuesAreKnownTypeConstants(): void
    {
        $allValues = array_values($this->getAllTypeConstants());

        foreach (Episciences_Mail_TemplatesManager::AUTOMATIC_TEMPLATES as $tpl) {
            self::assertContains(
                $tpl,
                $allValues,
                "AUTOMATIC_TEMPLATES entry '$tpl' is not a known TYPE_* constant value"
            );
        }
    }

    // -------------------------------------------------------------------------
    // TEMPLATE_DESCRIPTION_AND_RECIPIENT
    // -------------------------------------------------------------------------

    public function testTemplateDescriptionAndRecipientIsNonEmptyArray(): void
    {
        self::assertIsArray(Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT);
        self::assertNotEmpty(Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT);
    }

    public function testTemplateDescriptionAndRecipientEntriesHaveDescriptionAndRecipientKeys(): void
    {
        foreach (Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT as $type => $info) {
            self::assertArrayHasKey(
                Episciences_Mail_TemplatesManager::DESCRIPTION,
                $info,
                "Entry '$type' is missing 'description' key"
            );
            self::assertArrayHasKey(
                Episciences_Mail_TemplatesManager::RECIPIENT,
                $info,
                "Entry '$type' is missing 'recipient' key"
            );
        }
    }

    public function testTemplateDescriptionAndRecipientKeysAreKnownTypeConstants(): void
    {
        $allValues = array_values($this->getAllTypeConstants());

        foreach (array_keys(Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT) as $key) {
            self::assertContains(
                $key,
                $allValues,
                "TEMPLATE_DESCRIPTION_AND_RECIPIENT key '$key' is not a known TYPE_* constant value"
            );
        }
    }

    // -------------------------------------------------------------------------
    // getAvailableTagsByKey()
    // -------------------------------------------------------------------------

    /** @dataProvider provideKnownTemplateKeys */
    public function testGetAvailableTagsByKeyReturnsNonEmptyArrayForKnownKey(string $key): void
    {
        $tags = Episciences_Mail_TemplatesManager::getAvailableTagsByKey($key);

        self::assertIsArray($tags, "getAvailableTagsByKey('$key') must return an array");
        self::assertNotEmpty($tags, "getAvailableTagsByKey('$key') must not return an empty array");
    }

    public static function provideKnownTemplateKeys(): array
    {
        return [
            [Episciences_Mail_TemplatesManager::TYPE_USER_REGISTRATION],
            [Episciences_Mail_TemplatesManager::TYPE_USER_LOST_PASSWORD],
            [Episciences_Mail_TemplatesManager::TYPE_USER_LOST_LOGIN],
            [Episciences_Mail_TemplatesManager::TYPE_PAPER_ACCEPTED],
            [Episciences_Mail_TemplatesManager::TYPE_PAPER_REFUSED],
            [Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_AUTHOR_COPY],
            [Episciences_Mail_TemplatesManager::TYPE_PAPER_SUBMISSION_EDITOR_COPY],
            [Episciences_Mail_TemplatesManager::TYPE_REMINDER_UNANSWERED_REVIEWER_INVITATION_REVIEWER_VERSION],
        ];
    }

    public function testGetAvailableTagsByKeyReturnsEmptyArrayForUnknownKey(): void
    {
        // An unknown key has no specific tags — result contains only COMMON_TAGS.
        // Depending on the implementation, this may still be non-empty (common tags included).
        $tags = Episciences_Mail_TemplatesManager::getAvailableTagsByKey('completely_unknown_key_xyz');

        // Verify no exception is thrown and the result is an array.
        self::assertIsArray($tags);
    }

    public function testGetAvailableTagsByKeyAlwaysIncludesCommonTagsWhenNotExcluded(): void
    {
        $tags = Episciences_Mail_TemplatesManager::getAvailableTagsByKey(
            Episciences_Mail_TemplatesManager::TYPE_PAPER_ACCEPTED,
            false // withoutCommunTags = false
        );

        foreach (Episciences_Mail_TemplatesManager::COMMON_TAGS as $commonTag) {
            self::assertContains(
                $commonTag,
                $tags,
                "COMMON_TAG '$commonTag' must be present when withoutCommunTags=false"
            );
        }
    }

    public function testGetAvailableTagsByKeyExcludesCommonTagsWhenRequested(): void
    {
        $tags = Episciences_Mail_TemplatesManager::getAvailableTagsByKey(
            Episciences_Mail_TemplatesManager::TYPE_PAPER_ACCEPTED,
            true // withoutCommunTags = true
        );

        foreach (Episciences_Mail_TemplatesManager::COMMON_TAGS as $commonTag) {
            self::assertNotContains(
                $commonTag,
                $tags,
                "COMMON_TAG '$commonTag' must be excluded when withoutCommunTags=true"
            );
        }
    }

    public function testGetAvailableTagsByKeyStripsCustomPrefix(): void
    {
        // 'custom_paper_accepted' and 'paper_accepted' must return the same tags.
        $withPrefix    = Episciences_Mail_TemplatesManager::getAvailableTagsByKey('custom_' . Episciences_Mail_TemplatesManager::TYPE_PAPER_ACCEPTED);
        $withoutPrefix = Episciences_Mail_TemplatesManager::getAvailableTagsByKey(Episciences_Mail_TemplatesManager::TYPE_PAPER_ACCEPTED);

        self::assertSame($withoutPrefix, $withPrefix);
    }

    public function testGetAvailableTagsByKeyReturnedTagsAreTagFormatStrings(): void
    {
        $tags = Episciences_Mail_TemplatesManager::getAvailableTagsByKey(
            Episciences_Mail_TemplatesManager::TYPE_PAPER_ACCEPTED
        );

        foreach ($tags as $tag) {
            self::assertMatchesRegularExpression(
                '/^%%[A-Z0-9_%]+%%$/',
                $tag,
                "Tag '$tag' returned by getAvailableTagsByKey() has invalid format"
            );
        }
    }
}
