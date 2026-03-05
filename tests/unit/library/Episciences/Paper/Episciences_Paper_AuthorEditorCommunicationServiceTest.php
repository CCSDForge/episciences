<?php

namespace unit\library\Episciences\Paper;

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
}