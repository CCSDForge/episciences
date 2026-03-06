<?php

namespace unit\library\Episciences\Paper;

use Episciences_CommentsManager;
use Episciences_Paper;
use Episciences_Paper_AuthorEditorCommunicationService;
use Episciences_Review;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Paper_AuthorEditorCommunicationService
 *
 * This service class encapsulates all business logic for author-editor communication.
 * It was extracted from PaperController to follow the separation of concerns principle.
 *
 * The service handles:
 * - Checking journal settings (can authors contact editors AND disclose editor names)
 * - Retrieving assigned editors for a paper
 * - Loading author-editor comments
 * - Creating message forms (main form and reply forms)
 *
 * Key dependencies:
 * - Episciences_Paper: The paper entity being discussed
 * - Episciences_Review: The journal containing settings
 *
 * Related settings in REVIEW_SETTINGS table:
 * - SETTING_AUTHORS_CAN_CONTACT_EDITORS: Enable/disable author-editor messaging
 * - SETTING_DISCLOSE_EDITOR_NAMES_TO_AUTHORS: Show/hide editor names to authors
 *
 * @covers Episciences_Paper_AuthorEditorCommunicationService
 * @see Episciences_Review For journal settings constants
 * @see Episciences_CommentsManager For comment handling
 * @see Episciences_CommentHierarchyProcessor For comment hierarchy processing
 */
class Episciences_Paper_AuthorEditorCommunicationServiceTest extends TestCase
{
    // =========================================================================
    // Controller Path Constants Tests
    // =========================================================================

    /**
     * Test that CONTROLLER_PAPER constant is correctly defined.
     *
     * This constant is used to generate form action URLs for the author view.
     * Example: /paper/view?id=123
     */
    public function testControllerPaperConstant(): void
    {
        $this->assertSame('paper', Episciences_Paper_AuthorEditorCommunicationService::CONTROLLER_PAPER);
    }

    /**
     * Test that CONTROLLER_ADMINISTRATEPAPER constant is correctly defined.
     *
     * This constant is used to generate form action URLs for the editor view.
     * Example: /administratepaper/view?id=123
     */
    public function testControllerAdministratepaperConstant(): void
    {
        $this->assertSame('administratepaper', Episciences_Paper_AuthorEditorCommunicationService::CONTROLLER_ADMINISTRATEPAPER);
    }

    // =========================================================================
    // canAuthorContactEditors() Tests
    // =========================================================================

    /**
     * Test that canAuthorContactEditors() returns true when the setting is enabled.
     *
     * When SETTING_AUTHORS_CAN_CONTACT_EDITORS = '1' (or truthy value),
     * authors should be able to send messages to assigned editors.
     *
     * This setting is configured per journal in the review settings panel.
     */
    public function testCanAuthorContactEditorsReturnsTrueWhenSettingEnabled(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);

        $review->method('getSetting')
            ->with(Episciences_Review::SETTING_AUTHORS_CAN_CONTACT_EDITORS)
            ->willReturn('1');

        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $this->assertTrue($service->canAuthorContactEditors());
    }

    /**
     * Test that canAuthorContactEditors() returns false when the setting is disabled.
     *
     * When SETTING_AUTHORS_CAN_CONTACT_EDITORS = '0', the author-editor
     * communication panel should not be displayed and message submission
     * should be blocked.
     */
    public function testCanAuthorContactEditorsReturnsFalseWhenSettingDisabled(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);

        $review->method('getSetting')
            ->with(Episciences_Review::SETTING_AUTHORS_CAN_CONTACT_EDITORS)
            ->willReturn('0');

        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $this->assertFalse($service->canAuthorContactEditors());
    }

    /**
     * Test that canAuthorContactEditors() returns false when the setting is null.
     *
     * A null value indicates the setting was never configured for this journal.
     * For security, we default to false (deny access) when the setting is missing.
     */
    public function testCanAuthorContactEditorsReturnsFalseWhenSettingNull(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);

        $review->method('getSetting')
            ->with(Episciences_Review::SETTING_AUTHORS_CAN_CONTACT_EDITORS)
            ->willReturn(null);

        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $this->assertFalse($service->canAuthorContactEditors());
    }

    // =========================================================================
    // shouldDiscloseEditorNames() Tests
    // =========================================================================

    /**
     * Test that shouldDiscloseEditorNames() returns true when the setting is enabled.
     *
     * When SETTING_DISCLOSE_EDITOR_NAMES_TO_AUTHORS = '1', authors can see:
     * - Names of assigned editors in the "Assigned Editors" panel
     * - Editor names in email notifications
     * - Editor names in the communication timeline
     */
    public function testShouldDiscloseEditorNamesReturnsTrueWhenSettingEnabled(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);

        $review->method('getSetting')
            ->with(Episciences_Review::SETTING_DISCLOSE_EDITOR_NAMES_TO_AUTHORS)
            ->willReturn('1');

        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $this->assertTrue($service->shouldDiscloseEditorNames());
    }

    /**
     * Test that shouldDiscloseEditorNames() returns false when the setting is disabled.
     *
     * When SETTING_DISCLOSE_EDITOR_NAMES_TO_AUTHORS = '0' (anonymous review),
     * editor identities are hidden:
     * - No "Assigned Editors" panel shown
     * - Emails use generic "The Editors" instead of names
     * - Communication shows "Editor" instead of actual names
     */
    public function testShouldDiscloseEditorNamesReturnsFalseWhenSettingDisabled(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);

        $review->method('getSetting')
            ->with(Episciences_Review::SETTING_DISCLOSE_EDITOR_NAMES_TO_AUTHORS)
            ->willReturn('0');

        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $this->assertFalse($service->shouldDiscloseEditorNames());
    }

    // =========================================================================
    // getAssignedEditors() Tests
    // =========================================================================

    /**
     * Test that getAssignedEditors() returns empty array when both settings are disabled.
     *
     * When neither "can contact editors" nor "disclose names" is enabled,
     * there's no reason to fetch or display editor information.
     * This also saves a database query.
     *
     * Important: getEditors() should NOT be called in this case.
     */
    public function testGetAssignedEditorsReturnsEmptyArrayWhenBothSettingsDisabled(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);

        $review->method('getSetting')
            ->willReturnCallback(function ($setting) {
                return match ($setting) {
                    Episciences_Review::SETTING_AUTHORS_CAN_CONTACT_EDITORS => '0',
                    Episciences_Review::SETTING_DISCLOSE_EDITOR_NAMES_TO_AUTHORS => '0',
                    default => null
                };
            });

        // Verify getEditors() is NOT called when both settings are disabled
        $paper->expects($this->never())->method('getEditors');

        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $result = $service->getAssignedEditors();
        $this->assertSame([], $result);
    }

    /**
     * Test that getAssignedEditors() returns editors when "can contact editors" is enabled.
     *
     * Even if editor names are hidden, we still need the editor list for:
     * - Sending email notifications to editors
     * - Recording who receives the message (for audit trail)
     *
     * The UI will not display names, but the backend needs the data.
     */
    public function testGetAssignedEditorsReturnsEditorsWhenContactEditorsEnabled(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);

        $editors = ['editor1', 'editor2'];

        $review->method('getSetting')
            ->willReturnCallback(function ($setting) {
                return match ($setting) {
                    Episciences_Review::SETTING_AUTHORS_CAN_CONTACT_EDITORS => '1',
                    Episciences_Review::SETTING_DISCLOSE_EDITOR_NAMES_TO_AUTHORS => '0',
                    default => null
                };
            });

        $paper->method('getEditors')
            ->with(true, true) // (onlyActive, onlyAssigned)
            ->willReturn($editors);

        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $result = $service->getAssignedEditors();
        $this->assertSame($editors, $result);
    }

    /**
     * Test that getAssignedEditors() returns editors when "disclose names" is enabled.
     *
     * When editor names should be shown (even if messaging is disabled),
     * we need to fetch the editor list to display in the "Assigned Editors" panel.
     */
    public function testGetAssignedEditorsReturnsEditorsWhenDiscloseNamesEnabled(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);

        $editors = ['editor1'];

        $review->method('getSetting')
            ->willReturnCallback(function ($setting) {
                return match ($setting) {
                    Episciences_Review::SETTING_AUTHORS_CAN_CONTACT_EDITORS => '0',
                    Episciences_Review::SETTING_DISCLOSE_EDITOR_NAMES_TO_AUTHORS => '1',
                    default => null
                };
            });

        $paper->method('getEditors')
            ->with(true, true)
            ->willReturn($editors);

        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $result = $service->getAssignedEditors();
        $this->assertSame($editors, $result);
    }

    /**
     * Test that getAssignedEditors() returns editors when both settings are enabled.
     *
     * This is the full-featured mode where:
     * - Authors can send messages
     * - Authors can see who the editors are
     * - Full transparency between authors and editors
     */
    public function testGetAssignedEditorsReturnsEditorsWhenBothSettingsEnabled(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);

        $editors = ['editor1', 'editor2', 'editor3'];

        $review->method('getSetting')
            ->willReturnCallback(function ($setting) {
                return match ($setting) {
                    Episciences_Review::SETTING_AUTHORS_CAN_CONTACT_EDITORS => '1',
                    Episciences_Review::SETTING_DISCLOSE_EDITOR_NAMES_TO_AUTHORS => '1',
                    default => null
                };
            });

        $paper->method('getEditors')
            ->with(true, true)
            ->willReturn($editors);

        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $result = $service->getAssignedEditors();
        $this->assertSame($editors, $result);
    }

    // =========================================================================
    // Constructor Tests
    // =========================================================================

    /**
     * Test that the constructor defaults to CONTROLLER_PAPER path.
     *
     * When no controller path is specified, the service assumes it's being
     * used from the author's paper view (PaperController).
     * Form actions will point to /paper/view?id=XXX
     */
    public function testConstructorDefaultsToControllerPaper(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);

        // Test passes if no exception is thrown during instantiation
        $this->expectNotToPerformAssertions();
        /** @noinspection PhpExpressionResultUnusedInspection */
        new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);
    }

    /**
     * Test that the constructor accepts a custom controller path.
     *
     * When used from AdministratepaperController (editor view),
     * form actions should point to /administratepaper/view?id=XXX
     */
    public function testConstructorAcceptsCustomControllerPath(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);

        // Test passes if no exception is thrown during instantiation with custom path
        $this->expectNotToPerformAssertions();
        /** @noinspection PhpExpressionResultUnusedInspection */
        new Episciences_Paper_AuthorEditorCommunicationService(
            $paper,
            $review,
            Episciences_Paper_AuthorEditorCommunicationService::CONTROLLER_ADMINISTRATEPAPER
        );
    }

    // =========================================================================
    // Reply Forms Tests (Empty Input)
    // =========================================================================

    /**
     * Test that createAuthorReplyForms() returns empty array for empty comments.
     *
     * When there are no existing comments, there's nothing to reply to,
     * so no reply forms should be created.
     *
     * @throws \Zend_Form_Exception
     */
    public function testCreateAuthorReplyFormsWithEmptyCommentsReturnsEmptyArray(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);

        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $result = $service->createAuthorReplyForms([]);
        $this->assertSame([], $result);
    }

    /**
     * Test that createEditorReplyForms() returns empty array for empty comments.
     *
     * When there are no existing comments, there's nothing to reply to,
     * so no reply forms should be created.
     *
     * @throws \Zend_Form_Exception
     */
    public function testCreateEditorReplyFormsWithEmptyCommentsReturnsEmptyArray(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);

        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $result = $service->createEditorReplyForms([]);
        $this->assertSame([], $result);
    }

    // =========================================================================
    // canAuthorContactEditors() — edge cases
    // =========================================================================

    /**
     * Test that canAuthorContactEditors() returns false when the setting is an empty string.
     *
     * An empty string is falsy, so (bool)'' = false.
     * This matches the "never configured" or "explicitly cleared" state.
     */
    public function testCanAuthorContactEditorsReturnsFalseWhenSettingIsEmptyString(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);

        $review->method('getSetting')
            ->with(Episciences_Review::SETTING_AUTHORS_CAN_CONTACT_EDITORS)
            ->willReturn('');

        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $this->assertFalse($service->canAuthorContactEditors());
    }

    /**
     * Test that canAuthorContactEditors() returns true for any truthy value, not just '1'.
     *
     * The (bool) cast means '2', 'yes', 'true' would all be truthy.
     * This documents the implicit behaviour — only '0', '', null are falsy.
     */
    public function testCanAuthorContactEditorsReturnsTrueForAnyTruthyValue(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);

        $review->method('getSetting')
            ->with(Episciences_Review::SETTING_AUTHORS_CAN_CONTACT_EDITORS)
            ->willReturn('2');

        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $this->assertTrue($service->canAuthorContactEditors());
    }

    // =========================================================================
    // shouldDiscloseEditorNames() — missing edge cases
    // =========================================================================

    /**
     * Test that shouldDiscloseEditorNames() returns false when the setting is null.
     *
     * A null value (unconfigured setting) must default to false (privacy-safe default).
     */
    public function testShouldDiscloseEditorNamesReturnsFalseWhenSettingNull(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);

        $review->method('getSetting')
            ->with(Episciences_Review::SETTING_DISCLOSE_EDITOR_NAMES_TO_AUTHORS)
            ->willReturn(null);

        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $this->assertFalse($service->shouldDiscloseEditorNames());
    }

    /**
     * Test that shouldDiscloseEditorNames() returns false when the setting is an empty string.
     */
    public function testShouldDiscloseEditorNamesReturnsFalseWhenSettingIsEmptyString(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);

        $review->method('getSetting')
            ->with(Episciences_Review::SETTING_DISCLOSE_EDITOR_NAMES_TO_AUTHORS)
            ->willReturn('');

        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $this->assertFalse($service->shouldDiscloseEditorNames());
    }

    // =========================================================================
    // getAssignedEditors() — additional edge cases
    // =========================================================================

    /**
     * Test that getAssignedEditors() returns [] when both settings are null (unconfigured).
     *
     * null is falsy for both (bool) casts, so the early-exit branch is taken.
     * getEditors() must NOT be called.
     */
    public function testGetAssignedEditorsReturnsEmptyArrayWhenBothSettingsNull(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);

        $review->method('getSetting')->willReturn(null);

        $paper->expects($this->never())->method('getEditors');

        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $this->assertSame([], $service->getAssignedEditors());
    }

    /**
     * Test that getAssignedEditors() returns [] when paper has no editors assigned.
     *
     * Both settings are enabled, but getEditors() returns an empty array
     * (e.g. paper not yet assigned to anyone).
     */
    public function testGetAssignedEditorsReturnsEmptyArrayWhenPaperHasNoEditors(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);

        $review->method('getSetting')->willReturn('1');
        $paper->method('getEditors')->with(true, true)->willReturn([]);

        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $this->assertSame([], $service->getAssignedEditors());
    }

    /**
     * Test that getAssignedEditors() calls getEditors when only canContact is null
     * but discloseNames is enabled.
     *
     * Even with messaging disabled (null=falsy), showing names requires the editor list.
     */
    public function testGetAssignedEditorsCallsGetEditorsWhenOnlyDiscloseEnabled(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);

        $editors = ['editor_a'];

        $review->method('getSetting')
            ->willReturnCallback(function ($setting) {
                return match ($setting) {
                    Episciences_Review::SETTING_AUTHORS_CAN_CONTACT_EDITORS => null,
                    Episciences_Review::SETTING_DISCLOSE_EDITOR_NAMES_TO_AUTHORS => '1',
                    default => null
                };
            });

        $paper->expects($this->once())->method('getEditors')->with(true, true)->willReturn($editors);

        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $this->assertSame($editors, $service->getAssignedEditors());
    }

    /**
     * Test that getEditors() is called exactly once per getAssignedEditors() call.
     *
     * Performance note: there is no internal caching. Multiple calls to
     * getAssignedEditors() will call paper->getEditors() multiple times.
     * This test documents the current (uncached) behaviour.
     */
    public function testGetAssignedEditorsCallsGetEditorsEveryTime(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);

        $review->method('getSetting')->willReturn('1');
        $paper->expects($this->exactly(2))->method('getEditors')->with(true, true)->willReturn([]);

        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $service->getAssignedEditors();
        $service->getAssignedEditors();
    }

    // =========================================================================
    // createAuthorReplyForms() — type filtering (safe: no static form calls triggered)
    // =========================================================================

    /**
     * Test that createAuthorReplyForms() ignores comments of type TYPE_AUTHOR_TO_EDITOR.
     *
     * Author reply forms respond to editor messages (TYPE_EDITOR_TO_AUTHOR_RESPONSE).
     * Root comments sent BY the author (TYPE_AUTHOR_TO_EDITOR) do not get a reply form.
     */
    public function testCreateAuthorReplyFormsIgnoresAuthorTypeRootComments(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);
        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $comments = [
            [
                'TYPE' => Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR,
                'PCID' => 10,
                'WHEN' => '2025-01-01 10:00:00',
            ],
        ];

        $this->assertSame([], $service->createAuthorReplyForms($comments));
    }

    /**
     * Test that createAuthorReplyForms() ignores replies of type TYPE_AUTHOR_TO_EDITOR.
     *
     * Nested replies of type AUTHOR_TO_EDITOR under any root do not get author reply forms.
     */
    public function testCreateAuthorReplyFormsIgnoresAuthorTypeReplies(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);
        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $comments = [
            [
                'TYPE' => Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR,
                'PCID' => 10,
                'replies' => [
                    [
                        'TYPE' => Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR,
                        'PCID' => 11,
                    ],
                ],
            ],
        ];

        $this->assertSame([], $service->createAuthorReplyForms($comments));
    }

    /**
     * Test that createAuthorReplyForms() ignores comments with no TYPE field.
     *
     * Missing TYPE is treated as no match and no form is created.
     */
    public function testCreateAuthorReplyFormsIgnoresCommentsWithNoType(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);
        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $comments = [
            ['PCID' => 10, 'WHEN' => '2025-01-01'],          // no TYPE key
            ['TYPE' => null, 'PCID' => 11],                   // TYPE null (cast to 0 ≠ 23)
        ];

        $this->assertSame([], $service->createAuthorReplyForms($comments));
    }

    /**
     * Test that createAuthorReplyForms() ignores comments with empty replies array.
     *
     * The empty check (!empty($comment['replies'])) prevents iterating null/[].
     */
    public function testCreateAuthorReplyFormsHandlesEmptyRepliesGracefully(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);
        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $comments = [
            [
                'TYPE' => Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR,
                'PCID' => 10,
                'replies' => [],    // empty → !empty() = false → not iterated
            ],
        ];

        $this->assertSame([], $service->createAuthorReplyForms($comments));
    }

    /**
     * BUG B documented: collectCommentForms() accesses $comment['PCID'] without isset check.
     *
     * When a comment has the matching TYPE (TYPE_EDITOR_TO_AUTHOR_RESPONSE for author forms)
     * but no PCID key, the code does $pcid = $comment['PCID'] blindly.
     * PHP 8 emits an "Undefined array key" warning; null is coerced to int 0 for the
     * createReplyForm(int $pcid) parameter.
     *
     * This test uses a non-matching type (AUTHOR_TO_EDITOR) to stay safe in unit tests
     * (no static Episciences_CommentsManager::getForm() call). It documents the missing guard.
     *
     * Fix: add isset($comment['PCID']) to the type check condition on lines 142-146 and
     * similar for replies on lines 153-157.
     *
     * @see AuthorEditorCommunicationService::collectCommentForms() line 143
     */
    public function testCreateAuthorReplyFormsSkipsCommentsMissingPcidWhenTypeDoesNotMatch(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);
        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        // Safe path: TYPE doesn't match, so PCID is never accessed
        $comments = [
            ['TYPE' => Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR], // no PCID — safe because TYPE != target
        ];

        $this->assertSame([], $service->createAuthorReplyForms($comments));
    }

    // =========================================================================
    // createEditorReplyForms() — type filtering (safe: no static form calls triggered)
    // =========================================================================

    /**
     * Test that createEditorReplyForms() ignores comments of type TYPE_EDITOR_TO_AUTHOR_RESPONSE.
     *
     * Editor reply forms respond to author messages (TYPE_AUTHOR_TO_EDITOR).
     * Root comments from the editor (TYPE_EDITOR_TO_AUTHOR_RESPONSE) do not need a reply form.
     */
    public function testCreateEditorReplyFormsIgnoresEditorTypeRootComments(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);
        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $comments = [
            [
                'TYPE' => Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR_RESPONSE,
                'PCID' => 20,
                'WHEN' => '2025-02-01 10:00:00',
            ],
        ];

        $this->assertSame([], $service->createEditorReplyForms($comments));
    }

    /**
     * Test that createEditorReplyForms() ignores replies of type TYPE_EDITOR_TO_AUTHOR_RESPONSE.
     */
    public function testCreateEditorReplyFormsIgnoresEditorTypeReplies(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);
        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $comments = [
            [
                'TYPE' => Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR_RESPONSE,
                'PCID' => 20,
                'replies' => [
                    [
                        'TYPE' => Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR_RESPONSE,
                        'PCID' => 21,
                    ],
                ],
            ],
        ];

        $this->assertSame([], $service->createEditorReplyForms($comments));
    }

    /**
     * Test that createEditorReplyForms() ignores comments with no TYPE field.
     */
    public function testCreateEditorReplyFormsIgnoresCommentsWithNoType(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);
        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $comments = [
            ['PCID' => 20, 'WHEN' => '2025-02-01'],
            ['TYPE' => null, 'PCID' => 21],
        ];

        $this->assertSame([], $service->createEditorReplyForms($comments));
    }

    /**
     * Test that createEditorReplyForms() handles mixed-type root comments.
     *
     * A batch with both wrong-type roots and wrong-type replies yields no forms.
     * Neither root nor reply type matches TYPE_AUTHOR_TO_EDITOR (22).
     */
    public function testCreateEditorReplyFormsIgnoresMixedWrongTypeComments(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);
        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        $comments = [
            [
                'TYPE' => Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR_RESPONSE,
                'PCID' => 30,
                'replies' => [
                    [
                        'TYPE' => Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR_RESPONSE,
                        'PCID' => 31,
                    ],
                ],
            ],
            [
                'TYPE' => Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR_RESPONSE,
                'PCID' => 32,
                'replies' => [],
            ],
        ];

        $this->assertSame([], $service->createEditorReplyForms($comments));
    }

    /**
     * Test that collectCommentForms() only inspects direct replies, not deeper nesting.
     *
     * The method intentionally processes only 2 levels: root + direct replies.
     * Deeper nested replies (reply['replies']) are ignored.
     * This tests that the non-recursion design holds.
     */
    public function testCreateEditorReplyFormsDoesNotRecurseIntoNestedReplies(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);
        $service = new Episciences_Paper_AuthorEditorCommunicationService($paper, $review);

        // Root: wrong type. Reply: wrong type. Deep reply: would be right type for editor, but never read.
        $comments = [
            [
                'TYPE' => Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR_RESPONSE,
                'PCID' => 40,
                'replies' => [
                    [
                        'TYPE' => Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR_RESPONSE,
                        'PCID' => 41,
                        'replies' => [
                            // This level is intentionally NOT processed
                            [
                                'TYPE' => Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR,
                                'PCID' => 42,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // No form for PCID 42 because replies['replies'] is not iterated
        $this->assertSame([], $service->createEditorReplyForms($comments));
    }

    // =========================================================================
    // Security: controllerPath validation
    // =========================================================================

    /**
     * Security: the constructor validates $controllerPath against the two allowed constants.
     *
     * An arbitrary path would otherwise be embedded unsanitised in form action URLs via setAction().
     * Fix: in_array guard throws InvalidArgumentException for unknown controller paths.
     */
    public function testConstructorThrowsForInvalidControllerPath(): void
    {
        $paper  = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);

        $this->expectException(\InvalidArgumentException::class);
        new Episciences_Paper_AuthorEditorCommunicationService($paper, $review, 'unexpected-path');
    }

    /**
     * Constructor accepts CONTROLLER_PAPER without exception.
     */
    public function testConstructorAcceptsControllerPaperPath(): void
    {
        $paper  = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);

        $this->expectNotToPerformAssertions();
        new Episciences_Paper_AuthorEditorCommunicationService(
            $paper, $review, Episciences_Paper_AuthorEditorCommunicationService::CONTROLLER_PAPER
        );
    }

    /**
     * Constructor accepts CONTROLLER_ADMINISTRATEPAPER without exception.
     */
    public function testConstructorAcceptsAdministratepaperPath(): void
    {
        $paper  = $this->createMock(Episciences_Paper::class);
        $review = $this->createMock(Episciences_Review::class);

        $this->expectNotToPerformAssertions();
        new Episciences_Paper_AuthorEditorCommunicationService(
            $paper, $review, Episciences_Paper_AuthorEditorCommunicationService::CONTROLLER_ADMINISTRATEPAPER
        );
    }
}