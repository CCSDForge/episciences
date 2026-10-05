<?php

declare(strict_types=1);

namespace unit\library\Episciences\Paper;

use Episciences_Acl;
use Episciences_Auth;
use Episciences_Paper;
use Episciences_Paper_AccessControlControllerTrait;
use Episciences_Paper_Conflict;
use Episciences_Review;
use Episciences_User;
use PHPUnit\Framework\TestCase;
use Zend_Session_Namespace;

/**
 * Captures the flash messages and redirections issued by the trait instead of
 * performing them, so the access-control outcome can be asserted.
 * Mimics the Zend_Controller_Action_HelperBroker API used by the trait:
 * FlashMessenger / redirector property access and the url() helper call.
 */
final class AccessControlFakeHelperBroker
{
    /** @var string[] */
    public array $messages = [];
    /** @var string[] */
    public array $namespaces = [];
    public ?string $redirectedTo = null;
    /** @var array<string, mixed>|null */
    public ?array $urlParams = null;

    public function __get(string $name): self
    {
        return $this;
    }

    public function setNamespace(string $namespace): self
    {
        $this->namespaces[] = $namespace;
        return $this;
    }

    public function addMessage(string $message): void
    {
        $this->messages[] = $message;
    }

    public function gotoUrl(string $url): void
    {
        $this->redirectedTo = $url;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function url(string $action, string $controller, ?string $module = null, array $params = []): string
    {
        $this->urlParams = $params;
        return '/' . $controller . '/' . $action;
    }
}

/**
 * Minimal host class for the trait, standing in for the controllers
 * (AdministratepaperController / ActivityController) that use it.
 */
final class AccessControlHarness
{
    use Episciences_Paper_AccessControlControllerTrait;

    public const ADMINISTRATE_PAPER_CONTROLLER = 'administratepaper';

    public static bool $conflictDetected = false;

    public object $view;
    public AccessControlFakeHelperBroker $_helper;
    private string $actionName = 'view';

    public function __construct()
    {
        $this->_helper = new AccessControlFakeHelperBroker();
        $this->view = new class {
            public function translate(string $message): string
            {
                return $message;
            }
        };
    }

    public function setActionName(string $actionName): void
    {
        $this->actionName = $actionName;
    }

    public function getRequest(): object
    {
        return new class($this->actionName) {
            public function __construct(private readonly string $actionName)
            {
            }

            public function getActionName(): string
            {
                return $this->actionName;
            }
        };
    }

    /** Same signature as DefaultController::isConflictDetected(), resolved via self:: in the trait */
    public static function isConflictDetected(Episciences_Paper $paper, Episciences_Review $journal = null): bool
    {
        return self::$conflictDetected;
    }

    public function callCheckPermissions(Episciences_Review $review, Episciences_Paper $paper): bool
    {
        return $this->checkPermissions($review, $paper);
    }

    public function callRedirectIfConflict(Episciences_Paper $paper, Episciences_Review $review): void
    {
        $this->redirectWithFlashMessageIfConflictDetected($paper, $review);
    }
}

/**
 * Behavioural tests for the shared "manage this paper" access-control trait.
 *
 * The trait was refactored (cognitive complexity) into smaller private
 * methods; these tests pin down the permission rules so the refactoring - and
 * any future one - cannot silently change who may access or decide on a paper:
 *   - secretary/admin/chief-editor bypass
 *   - editors / copy editors encapsulation (unassigned users are redirected)
 *   - guest editors are always blocked, and per-action settings never
 *     override that denial
 *   - per-action journal settings (accept / publish / refuse / revision),
 *     including the copy-editor exception on author-approved papers
 *   - conflict-of-interest redirections (own submission, declared conflict,
 *     switched-user "su" flows)
 *
 * @covers Episciences_Paper_AccessControlControllerTrait
 */
final class Episciences_Paper_AccessControlControllerTraitTest extends TestCase
{
    private const ACCESS_DENIED_MESSAGE = "Vous n'avez pas les droits suffisants pour accéder à cet article";
    private const CONFIRMATION_REQUIRED_MESSAGE = "Vous avez été redirigé, car vous devez confirmer l'absence de conflit d'intérêt pour accéder à cette soumission";

    private AccessControlHarness $harness;

    protected function setUp(): void
    {
        if (!defined('SESSION_NAMESPACE')) {
            define('SESSION_NAMESPACE', 'Episciences_Auth');
        }
        if (!defined('RVID')) {
            define('RVID', 1);
        }

        $this->harness = new AccessControlHarness();
        AccessControlHarness::$conflictDetected = false;
        $this->resetAuthState();
    }

    protected function tearDown(): void
    {
        $this->resetAuthState();
    }

    private function resetAuthState(): void
    {
        Episciences_Auth::getInstance()->clearIdentity();
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->realIdentities, $session->photoVersion, $session->checkConflictResponseForSu);
    }

    /**
     * @param list<string> $roles
     */
    private function createMockUser(int $uid, array $roles = []): Episciences_User
    {
        $user = $this->createMock(Episciences_User::class);
        $user->method('getUid')->willReturn($uid);
        $user->method('getRoles')->willReturn($roles);
        $user->method('getAllRoles')->willReturn([RVID => $roles]);
        $user->method('getScreenName')->willReturn('User ' . $uid);
        return $user;
    }

    /**
     * @param list<string> $roles
     */
    private function loginUser(int $uid, array $roles): void
    {
        Episciences_Auth::getInstance()->getStorage()->write($this->createMockUser($uid, $roles));
    }

    /**
     * @param array<string, mixed> $settings settings returned by getSetting(), keyed by setting name
     */
    private function createReview(array $settings = []): Episciences_Review
    {
        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturnCallback(
            static fn(string $setting) => $settings[$setting] ?? null
        );
        return $review;
    }

    private function createPaper(
        ?int $assignedEditorUid = null,
        ?int $assignedCopyEditorUid = null,
        bool $isApprovedByAuthor = false,
        bool $isOwner = false,
        int $docId = 123,
        string $conflictResponse = Episciences_Paper_Conflict::AVAILABLE_ANSWER['no']
    ): Episciences_Paper {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getEditor')->willReturnCallback(
            fn(int $uid) => $uid === $assignedEditorUid ? $this->createMockUser($uid) : null
        );
        $paper->method('getCopyEditor')->willReturnCallback(
            fn(int $uid) => $uid === $assignedCopyEditorUid ? $this->createMockUser($uid) : null
        );
        $paper->method('isApprovedByAuthor')->willReturn($isApprovedByAuthor);
        $paper->method('isOwner')->willReturn($isOwner);
        $paper->method('getDocid')->willReturn($docId);
        $paper->method('checkConflictResponse')->willReturn($conflictResponse);
        return $paper;
    }

    // =========================================================================
    // checkPermissions(): role-based rules
    // =========================================================================

    public function testSecretaryBypassesAllChecks(): void
    {
        $this->loginUser(42, [Episciences_Acl::ROLE_SECRETARY]);
        // encapsulation on and every decision setting off: a secretary must still pass
        $this->harness->setActionName('accept');

        $allowed = $this->harness->callCheckPermissions(
            $this->createReview(['encapsulateEditors' => 1]),
            $this->createPaper()
        );

        self::assertTrue($allowed);
        self::assertNull($this->harness->_helper->redirectedTo);
    }

    public function testChiefEditorBypassesAllChecks(): void
    {
        $this->loginUser(42, [Episciences_Acl::ROLE_CHIEF_EDITOR]);

        $allowed = $this->harness->callCheckPermissions(
            $this->createReview(['encapsulateEditors' => 1]),
            $this->createPaper()
        );

        self::assertTrue($allowed);
        self::assertNull($this->harness->_helper->redirectedTo);
    }

    public function testUnassignedEditorIsRedirectedWhenEncapsulationIsOn(): void
    {
        $this->loginUser(42, [Episciences_Acl::ROLE_EDITOR]);

        $allowed = $this->harness->callCheckPermissions(
            $this->createReview(['encapsulateEditors' => 1]),
            $this->createPaper()
        );

        self::assertFalse($allowed);
        self::assertSame('/administratepaper/assigned', $this->harness->_helper->redirectedTo);
        self::assertSame([self::ACCESS_DENIED_MESSAGE], $this->harness->_helper->messages);
        self::assertContains('warning', $this->harness->_helper->namespaces);
        self::assertSame([], $this->harness->_helper->urlParams);
    }

    public function testAssignedEditorIsNotRedirectedWhenEncapsulationIsOn(): void
    {
        $this->loginUser(42, [Episciences_Acl::ROLE_EDITOR]);

        $allowed = $this->harness->callCheckPermissions(
            $this->createReview(['encapsulateEditors' => 1]),
            $this->createPaper(assignedEditorUid: 42)
        );

        self::assertTrue($allowed);
        self::assertNull($this->harness->_helper->redirectedTo);
    }

    public function testUnassignedEditorIsAllowedWhenEncapsulationIsOff(): void
    {
        $this->loginUser(42, [Episciences_Acl::ROLE_EDITOR]);

        $allowed = $this->harness->callCheckPermissions($this->createReview(), $this->createPaper());

        self::assertTrue($allowed);
        self::assertNull($this->harness->_helper->redirectedTo);
    }

    public function testUnassignedCopyEditorRedirectionCarriesTheCeParam(): void
    {
        $this->loginUser(42, [Episciences_Acl::ROLE_COPY_EDITOR]);

        $allowed = $this->harness->callCheckPermissions(
            $this->createReview(['encapsulateCopyEditors' => 1]),
            $this->createPaper()
        );

        self::assertFalse($allowed);
        self::assertSame('/administratepaper/assigned', $this->harness->_helper->redirectedTo);
        self::assertSame(['ce' => 1], $this->harness->_helper->urlParams);
    }

    public function testGuestEditorIsRedirectedEvenWhenTheActionSettingWouldAllowIt(): void
    {
        $this->loginUser(42, [Episciences_Acl::ROLE_GUEST_EDITOR]);
        // the per-action check would allow 'accept': the guest-editor denial must win anyway
        $this->harness->setActionName('accept');

        $allowed = $this->harness->callCheckPermissions(
            $this->createReview([Episciences_Review::SETTING_EDITORS_CAN_ACCEPT_PAPERS => 1]),
            $this->createPaper()
        );

        self::assertFalse($allowed);
        self::assertSame([self::ACCESS_DENIED_MESSAGE], $this->harness->_helper->messages);
        // internal marker keys ('isFinal') must never leak into the redirect params
        self::assertSame([], $this->harness->_helper->urlParams);
    }

    public function testGuestEditorKeepsTheGenericMessageOnDeniedActions(): void
    {
        $this->loginUser(42, [Episciences_Acl::ROLE_GUEST_EDITOR]);
        // guest editors are blocked before the per-action checks: the message
        // must stay the generic one, not the accept-specific one
        $this->harness->setActionName('accept');

        $this->harness->callCheckPermissions($this->createReview(), $this->createPaper());

        self::assertSame([self::ACCESS_DENIED_MESSAGE], $this->harness->_helper->messages);
    }

    public function testGuestEditorWhoIsAlsoEditorIsNotBlockedByTheGuestRule(): void
    {
        $this->loginUser(42, [Episciences_Acl::ROLE_GUEST_EDITOR, Episciences_Acl::ROLE_EDITOR]);

        $allowed = $this->harness->callCheckPermissions($this->createReview(), $this->createPaper());

        self::assertTrue($allowed);
    }

    // =========================================================================
    // checkPermissions(): per-action journal settings
    // =========================================================================

    /**
     * @dataProvider decisionActions
     */
    public function testDecisionActionIsDeniedWhenTheJournalSettingIsOff(string $action, string $setting, string $expectedMessage): void
    {
        $this->loginUser(42, [Episciences_Acl::ROLE_EDITOR]);
        $this->harness->setActionName($action);

        $allowed = $this->harness->callCheckPermissions(
            $this->createReview([$setting => 0]),
            $this->createPaper(assignedEditorUid: 42)
        );

        self::assertFalse($allowed);
        self::assertSame([$expectedMessage], $this->harness->_helper->messages);
        self::assertSame('/administratepaper/assigned', $this->harness->_helper->redirectedTo);
    }

    /**
     * @dataProvider decisionActions
     */
    public function testDecisionActionIsAllowedWhenTheJournalSettingIsOn(string $action, string $setting): void
    {
        $this->loginUser(42, [Episciences_Acl::ROLE_EDITOR]);
        $this->harness->setActionName($action);

        $allowed = $this->harness->callCheckPermissions(
            $this->createReview([$setting => 1]),
            $this->createPaper(assignedEditorUid: 42)
        );

        self::assertTrue($allowed);
        self::assertNull($this->harness->_helper->redirectedTo);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function decisionActions(): iterable
    {
        yield 'accept' => [
            'accept',
            Episciences_Review::SETTING_EDITORS_CAN_ACCEPT_PAPERS,
            "Vous n'avez pas les droits suffisants pour accepter cet article",
        ];
        yield 'publish' => [
            'publish',
            Episciences_Review::SETTING_EDITORS_CAN_PUBLISH_PAPERS,
            "Vous n'avez pas les droits suffisants pour publier cet article",
        ];
        yield 'refuse' => [
            'refuse',
            Episciences_Review::SETTING_EDITORS_CAN_REJECT_PAPERS,
            "Vous n'avez pas les droits suffisants pour refuser cet article",
        ];
        yield 'revision' => [
            'revision',
            Episciences_Review::SETTING_EDITORS_CAN_ASK_PAPER_REVISIONS,
            "Vous n'avez pas les droits suffisants pour demander des modifications sur cet article",
        ];
    }

    public function testPublishIsAllowedForTheCopyEditorOfAnAuthorApprovedPaper(): void
    {
        $this->loginUser(42, [Episciences_Acl::ROLE_COPY_EDITOR]);
        $this->harness->setActionName('publish');

        $allowed = $this->harness->callCheckPermissions(
            $this->createReview([Episciences_Review::SETTING_EDITORS_CAN_PUBLISH_PAPERS => 0]),
            $this->createPaper(assignedCopyEditorUid: 42, isApprovedByAuthor: true)
        );

        self::assertTrue($allowed);
        self::assertNull($this->harness->_helper->redirectedTo);
    }

    public function testPublishIsDeniedForTheCopyEditorWhenThePaperIsNotAuthorApproved(): void
    {
        $this->loginUser(42, [Episciences_Acl::ROLE_COPY_EDITOR]);
        $this->harness->setActionName('publish');

        $allowed = $this->harness->callCheckPermissions(
            $this->createReview([Episciences_Review::SETTING_EDITORS_CAN_PUBLISH_PAPERS => 0]),
            $this->createPaper(assignedCopyEditorUid: 42, isApprovedByAuthor: false)
        );

        self::assertFalse($allowed);
        self::assertSame(
            ["Vous n'avez pas les droits suffisants pour publier cet article"],
            $this->harness->_helper->messages
        );
    }

    public function testNonDecisionActionIsNotRestrictedByDecisionSettings(): void
    {
        $this->loginUser(42, [Episciences_Acl::ROLE_EDITOR]);
        $this->harness->setActionName('view');

        $allowed = $this->harness->callCheckPermissions(
            $this->createReview([Episciences_Review::SETTING_EDITORS_CAN_ACCEPT_PAPERS => 0]),
            $this->createPaper(assignedEditorUid: 42)
        );

        self::assertTrue($allowed);
    }

    // =========================================================================
    // redirectWithFlashMessageIfConflictDetected()
    // =========================================================================

    public function testNoRedirectionWhenNotOwnerAndNoConflict(): void
    {
        $this->loginUser(42, [Episciences_Acl::ROLE_EDITOR]);

        $this->harness->callRedirectIfConflict($this->createPaper(), $this->createReview());

        self::assertNull($this->harness->_helper->redirectedTo);
        self::assertSame([], $this->harness->_helper->messages);
    }

    public function testOwnerIsRedirectedToThePaperPublicView(): void
    {
        $this->loginUser(42, [Episciences_Acl::ROLE_EDITOR]);

        $this->harness->callRedirectIfConflict(
            $this->createPaper(isOwner: true, docId: 123),
            $this->createReview()
        );

        self::assertSame('/paper/view?id=123', $this->harness->_helper->redirectedTo);
        self::assertCount(1, $this->harness->_helper->messages);
        self::assertStringContainsString('vous-même déposé', $this->harness->_helper->messages[0]);
        self::assertContains('warning', $this->harness->_helper->namespaces);
    }

    public function testOwnerRedirectionMentionsTheOriginalIdentityWhenSwitched(): void
    {
        $this->loginUser(42, [Episciences_Acl::ROLE_EDITOR]);
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        $session->realIdentities = [$this->createMockUser(99)];

        $this->harness->callRedirectIfConflict(
            $this->createPaper(isOwner: true),
            $this->createReview()
        );

        $message = $this->harness->_helper->messages[0];
        self::assertStringContainsString('User 99', $message);
        self::assertStringContainsString('Vous êtes connecté en tant que', $message);
        self::assertStringContainsString('vous-même déposé', $message);
    }

    public function testDeclaredConflictRedirectsToTheCoiReport(): void
    {
        $this->loginUser(42, [Episciences_Acl::ROLE_EDITOR]);
        AccessControlHarness::$conflictDetected = true;

        $this->harness->callRedirectIfConflict(
            $this->createPaper(docId: 123, conflictResponse: Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes']),
            $this->createReview()
        );

        self::assertSame('/coi/report?id=123', $this->harness->_helper->redirectedTo);
        self::assertSame(
            ["Vous avez été redirigé, car vous avez déclaré un conflit d'intérêts avec cette soumission."],
            $this->harness->_helper->messages
        );
    }

    public function testUnansweredConflictAsksForConfirmation(): void
    {
        $this->loginUser(42, [Episciences_Acl::ROLE_EDITOR]);
        AccessControlHarness::$conflictDetected = true;

        $this->harness->callRedirectIfConflict(
            $this->createPaper(conflictResponse: Episciences_Paper_Conflict::AVAILABLE_ANSWER['later']),
            $this->createReview()
        );

        self::assertSame([self::CONFIRMATION_REQUIRED_MESSAGE], $this->harness->_helper->messages);
    }

    public function testSwitchedUserConflictAnsweredYesMentionsTheSelfReportedConflict(): void
    {
        $this->loginUser(42, [Episciences_Acl::ROLE_EDITOR]);
        AccessControlHarness::$conflictDetected = true;
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        $session->realIdentities = [$this->createMockUser(99)];
        $session->checkConflictResponseForSu = Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes'];

        $this->harness->callRedirectIfConflict($this->createPaper(), $this->createReview());

        $message = $this->harness->_helper->messages[0];
        self::assertStringContainsString('User 99', $message);
        self::assertStringContainsString("Vous avez vous-même signalé un conflit d'intérêts", $message);
        self::assertStringContainsString('Vous êtes connecté en tant que', $message);
        // identity must not be switched back in this flow
        self::assertSame(42, Episciences_Auth::getUid());
    }

    public function testSwitchedUserConflictAnsweredLaterRestoresTheOriginalIdentity(): void
    {
        $this->loginUser(42, [Episciences_Acl::ROLE_EDITOR]);
        AccessControlHarness::$conflictDetected = true;
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        $session->realIdentities = [$this->createMockUser(99)];
        $session->checkConflictResponseForSu = Episciences_Paper_Conflict::AVAILABLE_ANSWER['later'];

        $this->harness->callRedirectIfConflict($this->createPaper(), $this->createReview());

        $message = $this->harness->_helper->messages[0];
        self::assertStringContainsString('User 99', $message);
        self::assertStringContainsString('Vous êtes maintenant connecté à votre compte', $message);
        self::assertStringContainsString(self::CONFIRMATION_REQUIRED_MESSAGE, $message);
        // the original account has been restored so it can confirm the conflict absence
        self::assertSame(99, Episciences_Auth::getUid());
    }

    // =========================================================================
    // Usage guards: the controllers must keep delegating to the trait
    // =========================================================================

    /**
     * @return iterable<string, array{string}>
     */
    public static function usingControllers(): iterable
    {
        yield 'AdministratepaperController' => [APPLICATION_PATH . '/modules/journal/controllers/AdministratepaperController.php'];
        yield 'ActivityController' => [APPLICATION_PATH . '/modules/journal/controllers/ActivityController.php'];
    }

    /**
     * @dataProvider usingControllers
     */
    public function testControllerUsesTheTraitAndItsGuards(string $path): void
    {
        $source = (string)file_get_contents($path);

        self::assertStringContainsString('use Episciences_Paper_AccessControlControllerTrait;', $source);
        self::assertStringContainsString('$this->checkPermissions(', $source);
        self::assertStringContainsString('$this->redirectWithFlashMessageIfConflictDetected(', $source);
    }

    /**
     * A method defined in the using class silently takes precedence over the
     * trait method with the same name: no controller (or parent) may redefine
     * one of the trait's methods.
     *
     * @dataProvider classesInTheTraitHierarchy
     */
    public function testTraitMethodsAreNotOverriddenByTheControllers(string $path): void
    {
        $source = (string)file_get_contents($path);

        $traitMethods = (new \ReflectionClass(Episciences_Paper_AccessControlControllerTrait::class))->getMethods();
        self::assertNotEmpty($traitMethods);

        foreach ($traitMethods as $method) {
            self::assertStringNotContainsString(
                'function ' . $method->getName() . '(',
                $source,
                basename($path) . " must not redefine the trait method {$method->getName()}()"
            );
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function classesInTheTraitHierarchy(): iterable
    {
        yield from self::usingControllers();
        yield 'PaperDefaultController' => [APPLICATION_PATH . '/modules/common/controllers/PaperDefaultController.php'];
        yield 'DefaultController' => [APPLICATION_PATH . '/modules/common/controllers/DefaultController.php'];
    }
}
