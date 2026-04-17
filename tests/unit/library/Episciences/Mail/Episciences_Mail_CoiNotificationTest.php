<?php

namespace unit\library\Episciences\Mail;

use Episciences_Mail_Tags;
use Episciences_Mail_TemplatesManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the COI (Conflict of Interest) notification feature — issue #630.
 *
 * Covers:
 * - New tag constants (TAG_COI_EDITOR_FULL_NAME, TAG_COI_LAST_EDITOR_MESSAGE)
 * - New template type constants and their tag lists
 * - Correct difference between chief-editor and other-editors tag sets
 * - Both new types registered in TEMPLATE_DESCRIPTION_AND_RECIPIENT
 * - Email template files (EN + FR) exist and contain required placeholders
 *
 * @covers Episciences_Mail_Tags
 * @covers Episciences_Mail_TemplatesManager
 */
final class Episciences_Mail_CoiNotificationTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Tag constants
    // -------------------------------------------------------------------------

    public function testCoiEditorFullNameTagConstantHasCorrectValue(): void
    {
        self::assertSame('%%COI_EDITOR_FULL_NAME%%', Episciences_Mail_Tags::TAG_COI_EDITOR_FULL_NAME);
    }

    public function testCoiLastEditorMessageTagConstantHasCorrectValue(): void
    {
        self::assertSame('%%COI_LAST_EDITOR_MESSAGE%%', Episciences_Mail_Tags::TAG_COI_LAST_EDITOR_MESSAGE);
    }

    public function testCoiTagsFollowExpectedFormat(): void
    {
        foreach ([
            Episciences_Mail_Tags::TAG_COI_EDITOR_FULL_NAME,
            Episciences_Mail_Tags::TAG_COI_LAST_EDITOR_MESSAGE,
        ] as $tag) {
            self::assertMatchesRegularExpression('/^%%[A-Z0-9_%]+%%$/', $tag, "COI tag '$tag' has invalid format");
        }
    }

    // -------------------------------------------------------------------------
    // TAG_DESCRIPTION entries for COI tags
    // -------------------------------------------------------------------------

    public function testCoiEditorFullNameTagHasDescriptionEntry(): void
    {
        self::assertArrayHasKey(
            Episciences_Mail_Tags::TAG_COI_EDITOR_FULL_NAME,
            Episciences_Mail_Tags::TAG_DESCRIPTION,
            'TAG_COI_EDITOR_FULL_NAME must have an entry in TAG_DESCRIPTION'
        );
    }

    public function testCoiLastEditorMessageTagHasDescriptionEntry(): void
    {
        self::assertArrayHasKey(
            Episciences_Mail_Tags::TAG_COI_LAST_EDITOR_MESSAGE,
            Episciences_Mail_Tags::TAG_DESCRIPTION,
            'TAG_COI_LAST_EDITOR_MESSAGE must have an entry in TAG_DESCRIPTION'
        );
    }

    public function testCoiEditorFullNameDescriptionMentionsConflict(): void
    {
        $description = Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_COI_EDITOR_FULL_NAME];
        self::assertStringContainsString('conflit', $description);
    }

    public function testCoiLastEditorMessageDescriptionMentionsCoi(): void
    {
        $description = Episciences_Mail_Tags::TAG_DESCRIPTION[Episciences_Mail_Tags::TAG_COI_LAST_EDITOR_MESSAGE];
        self::assertStringContainsString('COI', $description);
    }

    // -------------------------------------------------------------------------
    // Template type constants
    // -------------------------------------------------------------------------

    public function testChiefEditorCopyTypeConstantHasCorrectValue(): void
    {
        self::assertSame(
            'paper_coi_unassign_chief_editor_copy',
            Episciences_Mail_TemplatesManager::TYPE_PAPER_COI_UNASSIGN_CHIEF_EDITOR_COPY
        );
    }

    public function testOtherEditorsCopyTypeConstantHasCorrectValue(): void
    {
        self::assertSame(
            'paper_coi_unassign_other_editors_copy',
            Episciences_Mail_TemplatesManager::TYPE_PAPER_COI_UNASSIGN_OTHER_EDITORS_COPY
        );
    }

    // -------------------------------------------------------------------------
    // Tag lists — chief-editor template
    // -------------------------------------------------------------------------

    public function testChiefEditorCopyTagsAreNonEmpty(): void
    {
        self::assertNotEmpty(
            Episciences_Mail_TemplatesManager::getAvailableTagsByKey(
                Episciences_Mail_TemplatesManager::TYPE_PAPER_COI_UNASSIGN_CHIEF_EDITOR_COPY,
                true
            )
        );
    }

    public function testChiefEditorCopyTagsIncludeCoiEditorFullName(): void
    {
        $tags = Episciences_Mail_TemplatesManager::getAvailableTagsByKey(
            Episciences_Mail_TemplatesManager::TYPE_PAPER_COI_UNASSIGN_CHIEF_EDITOR_COPY,
            true
        );

        self::assertContains(
            Episciences_Mail_Tags::TAG_COI_EDITOR_FULL_NAME,
            $tags,
            'Chief-editor template must declare TAG_COI_EDITOR_FULL_NAME'
        );
    }

    public function testChiefEditorCopyTagsIncludeLastEditorMessage(): void
    {
        $tags = Episciences_Mail_TemplatesManager::getAvailableTagsByKey(
            Episciences_Mail_TemplatesManager::TYPE_PAPER_COI_UNASSIGN_CHIEF_EDITOR_COPY,
            true
        );

        self::assertContains(
            Episciences_Mail_Tags::TAG_COI_LAST_EDITOR_MESSAGE,
            $tags,
            'Chief-editor template must declare TAG_COI_LAST_EDITOR_MESSAGE (used to warn about no remaining editors)'
        );
    }

    public function testChiefEditorCopyTagsIncludeArticleContext(): void
    {
        $tags = Episciences_Mail_TemplatesManager::getAvailableTagsByKey(
            Episciences_Mail_TemplatesManager::TYPE_PAPER_COI_UNASSIGN_CHIEF_EDITOR_COPY,
            true
        );

        self::assertContains(Episciences_Mail_Tags::TAG_ARTICLE_ID, $tags);
        self::assertContains(Episciences_Mail_Tags::TAG_ARTICLE_TITLE, $tags);
        self::assertContains(Episciences_Mail_Tags::TAG_PAPER_URL, $tags);
    }

    // -------------------------------------------------------------------------
    // Tag lists — other-editors template
    // -------------------------------------------------------------------------

    public function testOtherEditorsCopyTagsAreNonEmpty(): void
    {
        self::assertNotEmpty(
            Episciences_Mail_TemplatesManager::getAvailableTagsByKey(
                Episciences_Mail_TemplatesManager::TYPE_PAPER_COI_UNASSIGN_OTHER_EDITORS_COPY,
                true
            )
        );
    }

    public function testOtherEditorsCopyTagsIncludeCoiEditorFullName(): void
    {
        $tags = Episciences_Mail_TemplatesManager::getAvailableTagsByKey(
            Episciences_Mail_TemplatesManager::TYPE_PAPER_COI_UNASSIGN_OTHER_EDITORS_COPY,
            true
        );

        self::assertContains(
            Episciences_Mail_Tags::TAG_COI_EDITOR_FULL_NAME,
            $tags,
            'Other-editors template must declare TAG_COI_EDITOR_FULL_NAME'
        );
    }

    /**
     * The other-editors template does not include %%COI_LAST_EDITOR_MESSAGE%%:
     * only the chief editor is responsible for reassigning editors.
     */
    public function testOtherEditorsCopyTagsDoNotIncludeLastEditorMessage(): void
    {
        $tags = Episciences_Mail_TemplatesManager::getAvailableTagsByKey(
            Episciences_Mail_TemplatesManager::TYPE_PAPER_COI_UNASSIGN_OTHER_EDITORS_COPY,
            true
        );

        self::assertNotContains(
            Episciences_Mail_Tags::TAG_COI_LAST_EDITOR_MESSAGE,
            $tags,
            'Other-editors template must NOT declare TAG_COI_LAST_EDITOR_MESSAGE (only the chief-editor template uses it)'
        );
    }

    public function testOtherEditorsCopyTagsIncludeArticleContext(): void
    {
        $tags = Episciences_Mail_TemplatesManager::getAvailableTagsByKey(
            Episciences_Mail_TemplatesManager::TYPE_PAPER_COI_UNASSIGN_OTHER_EDITORS_COPY,
            true
        );

        self::assertContains(Episciences_Mail_Tags::TAG_ARTICLE_ID, $tags);
        self::assertContains(Episciences_Mail_Tags::TAG_ARTICLE_TITLE, $tags);
        self::assertContains(Episciences_Mail_Tags::TAG_PAPER_URL, $tags);
    }

    // -------------------------------------------------------------------------
    // Key asymmetry: chief-editor has TAG_COI_LAST_EDITOR_MESSAGE; others do not
    // -------------------------------------------------------------------------

    public function testLastEditorMessageTagIsExclusiveToChiefEditorTemplate(): void
    {
        $chiefTags = Episciences_Mail_TemplatesManager::getAvailableTagsByKey(
            Episciences_Mail_TemplatesManager::TYPE_PAPER_COI_UNASSIGN_CHIEF_EDITOR_COPY,
            true
        );
        $otherTags = Episciences_Mail_TemplatesManager::getAvailableTagsByKey(
            Episciences_Mail_TemplatesManager::TYPE_PAPER_COI_UNASSIGN_OTHER_EDITORS_COPY,
            true
        );

        self::assertContains(Episciences_Mail_Tags::TAG_COI_LAST_EDITOR_MESSAGE, $chiefTags);
        self::assertNotContains(Episciences_Mail_Tags::TAG_COI_LAST_EDITOR_MESSAGE, $otherTags);
    }

    // -------------------------------------------------------------------------
    // TEMPLATE_DESCRIPTION_AND_RECIPIENT registration
    // -------------------------------------------------------------------------

    public function testChiefEditorCopyTypeRegisteredInDescriptionAndRecipient(): void
    {
        self::assertArrayHasKey(
            Episciences_Mail_TemplatesManager::TYPE_PAPER_COI_UNASSIGN_CHIEF_EDITOR_COPY,
            Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT
        );
    }

    public function testOtherEditorsCopyTypeRegisteredInDescriptionAndRecipient(): void
    {
        self::assertArrayHasKey(
            Episciences_Mail_TemplatesManager::TYPE_PAPER_COI_UNASSIGN_OTHER_EDITORS_COPY,
            Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT
        );
    }

    public function testChiefEditorCopyDescriptionMentionsChiefEditor(): void
    {
        $entry = Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[
            Episciences_Mail_TemplatesManager::TYPE_PAPER_COI_UNASSIGN_CHIEF_EDITOR_COPY
        ];

        self::assertStringContainsString('rédacteur en chef', $entry[Episciences_Mail_TemplatesManager::DESCRIPTION]);
        self::assertStringContainsString('rédacteur en chef', $entry[Episciences_Mail_TemplatesManager::RECIPIENT]);
    }

    public function testOtherEditorsCopyDescriptionMentionsOtherEditors(): void
    {
        $entry = Episciences_Mail_TemplatesManager::TEMPLATE_DESCRIPTION_AND_RECIPIENT[
            Episciences_Mail_TemplatesManager::TYPE_PAPER_COI_UNASSIGN_OTHER_EDITORS_COPY
        ];

        self::assertStringContainsString('éditeur', $entry[Episciences_Mail_TemplatesManager::DESCRIPTION]);
        self::assertStringContainsString('éditeur', $entry[Episciences_Mail_TemplatesManager::RECIPIENT]);
    }

    // -------------------------------------------------------------------------
    // Email template files — existence
    // -------------------------------------------------------------------------

    /** @dataProvider provideTemplateFilePaths */
    public function testTemplateFileExists(string $path): void
    {
        self::assertFileExists($path, "Expected email template file not found: $path");
    }

    public static function provideTemplateFilePaths(): array
    {
        $base = __DIR__ . '/../../../../../../application/languages';

        return [
            'EN chief-editor'  => [$base . '/en/emails/paper_coi_unassign_chief_editor_copy.phtml'],
            'EN other-editors' => [$base . '/en/emails/paper_coi_unassign_other_editors_copy.phtml'],
            'FR chief-editor'  => [$base . '/fr/emails/paper_coi_unassign_chief_editor_copy.phtml'],
            'FR other-editors' => [$base . '/fr/emails/paper_coi_unassign_other_editors_copy.phtml'],
        ];
    }

    // -------------------------------------------------------------------------
    // Email template files — required placeholders
    // -------------------------------------------------------------------------

    /** @dataProvider provideChiefEditorTemplatePaths */
    public function testChiefEditorTemplateContainsRequiredPlaceholders(string $path): void
    {
        self::assertFileExists($path);
        $content = (string) file_get_contents($path);

        foreach ([
            '%%RECIPIENT_SCREEN_NAME%%',
            '%%COI_EDITOR_FULL_NAME%%',
            '%%ARTICLE_ID%%',
            '%%ARTICLE_TITLE%%',
            '%%PAPER_URL%%',
            '%%COI_LAST_EDITOR_MESSAGE%%',
            '%%RECIPIENT_FORGOTTEN_USERNAME_LINK%%',
        ] as $placeholder) {
            self::assertStringContainsString(
                $placeholder,
                $content,
                "Chief-editor template '$path' is missing placeholder $placeholder"
            );
        }
    }

    public static function provideChiefEditorTemplatePaths(): array
    {
        $base = __DIR__ . '/../../../../../../application/languages';

        return [
            'EN' => [$base . '/en/emails/paper_coi_unassign_chief_editor_copy.phtml'],
            'FR' => [$base . '/fr/emails/paper_coi_unassign_chief_editor_copy.phtml'],
        ];
    }

    /** @dataProvider provideOtherEditorsTemplatePaths */
    public function testOtherEditorsTemplateContainsRequiredPlaceholders(string $path): void
    {
        self::assertFileExists($path);
        $content = (string) file_get_contents($path);

        foreach ([
            '%%RECIPIENT_SCREEN_NAME%%',
            '%%COI_EDITOR_FULL_NAME%%',
            '%%ARTICLE_ID%%',
            '%%ARTICLE_TITLE%%',
            '%%PAPER_URL%%',
            '%%RECIPIENT_FORGOTTEN_USERNAME_LINK%%',
        ] as $placeholder) {
            self::assertStringContainsString(
                $placeholder,
                $content,
                "Other-editors template '$path' is missing placeholder $placeholder"
            );
        }
    }

    public static function provideOtherEditorsTemplatePaths(): array
    {
        $base = __DIR__ . '/../../../../../../application/languages';

        return [
            'EN' => [$base . '/en/emails/paper_coi_unassign_other_editors_copy.phtml'],
            'FR' => [$base . '/fr/emails/paper_coi_unassign_other_editors_copy.phtml'],
        ];
    }

    /**
     * The other-editors template must NOT contain %%COI_LAST_EDITOR_MESSAGE%%:
     * that slot is only for the chief-editor template.
     *
     * @dataProvider provideOtherEditorsTemplatePaths
     */
    public function testOtherEditorsTemplateDoesNotContainLastEditorMessagePlaceholder(string $path): void
    {
        self::assertFileExists($path);
        $content = (string) file_get_contents($path);

        self::assertStringNotContainsString(
            '%%COI_LAST_EDITOR_MESSAGE%%',
            $content,
            "Other-editors template '$path' must not include %%COI_LAST_EDITOR_MESSAGE%%"
        );
    }
}