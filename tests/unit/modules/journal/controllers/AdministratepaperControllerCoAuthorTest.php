<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Acl;
use Episciences_Auth;
use Episciences_Paper;
use Episciences_Paper_Conflict;
use Episciences_Review;
use Episciences_User;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Zend_Controller_Action;
use Zend_Controller_Request_HttpTestCase;
use Zend_Session_Namespace;

require_once APPLICATION_PATH . '/modules/journal/controllers/AdministratepaperController.php';

/**
 * Thrown by the fake redirector: a real redirection exits the request.
 */
final class CoAuthorRedirected extends RuntimeException
{
}

/**
 * Captures the flash messages and redirections issued by the controller.
 * Mimics the Zend_Controller_Action_HelperBroker API used by the co-author actions.
 */
final class CoAuthorFakeHelperBroker
{
    /** @var array<int, array{string, string}> namespace and message of each flash message */
    public array $messages = [];
    public ?string $redirectedTo = null;
    private string $namespace = 'default';

    public function __get(string $name): self
    {
        return $this;
    }

    public function layout(): self
    {
        return $this;
    }

    public function disableLayout(): void
    {
    }

    public function setNoRender(): void
    {
    }

    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;
        return $this;
    }

    public function addMessage(string $message): void
    {
        $this->messages[] = [$this->namespace, $message];
    }

    /**
     * @param array<string, mixed> $options
     */
    public function gotoUrl(string $url, array $options = []): void
    {
        $this->redirectedTo = $url;
        throw new CoAuthorRedirected($url);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function url(string $action, string $controller, ?string $module = null, array $params = []): string
    {
        return '/' . $controller . '/' . $action;
    }

    /**
     * @return string[]
     */
    public function namespaces(): array
    {
        return array_column($this->messages, 0);
    }
}

/**
 * The controller with its data access replaced: the paper and the journal are
 * given by the test, and the co-authors added are recorded instead of saved.
 */
final class CoAuthorTestController extends \AdministratepaperController
{
    public ?Episciences_Paper $paper = null;
    public ?Episciences_Review $review = null;
    /** @var array<int, array{int, int}> docId and uid of each co-author added */
    public array $addedCoAuthors = [];

    protected function findPaper(int $docId): ?Episciences_Paper
    {
        return $this->paper;
    }

    protected function findReview(): Episciences_Review
    {
        return $this->review;
    }

    protected function addRoleCoAuthor(int $docId, int $uid): bool
    {
        $this->addedCoAuthors[] = [$docId, $uid];
        return true;
    }
}

/**
 * Behavioural tests of the co-author form of the paper management page
 * (addcoauthorAction) and of the co-author removal (ajaxrequestremovecoauthorAction).
 *
 * The form must go through the same checks as the page it comes from (viewAction).
 *
 * @covers \AdministratepaperController::addcoauthorAction
 * @covers \AdministratepaperController::ajaxrequestremovecoauthorAction
 */
final class AdministratepaperControllerCoAuthorTest extends TestCase
{
    private const DOC_ID = 123;
    private const CO_AUTHOR_UID = 77;
    private const TOKEN = 'session-request-token';

    private CoAuthorTestController $controller;
    private CoAuthorFakeHelperBroker $helper;
    private Zend_Controller_Request_HttpTestCase $request;

    protected function setUp(): void
    {
        $this->resetState();

        $this->controller = (new ReflectionClass(CoAuthorTestController::class))->newInstanceWithoutConstructor();
        $this->helper = new CoAuthorFakeHelperBroker();
        $this->request = new Zend_Controller_Request_HttpTestCase();

        $this->setControllerProperty('_helper', $this->helper);
        $this->setControllerProperty('_request', $this->request);
        $this->setControllerProperty('view', new class {
            public function translate(string $message): string
            {
                return $message;
            }

            public function escape(string $value): string
            {
                return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
        });

        $this->controller->review = $this->createReview();

        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        $session->csrfToken = self::TOKEN;
    }

    protected function tearDown(): void
    {
        $this->resetState();
    }

    private function resetState(): void
    {
        Episciences_Auth::getInstance()->clearIdentity();
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->realIdentities, $session->checkConflictResponseForSu, $session->csrfToken);
        // Zend_Controller_Request_HttpTestCase::setPost() writes to $_POST
        $_POST = [];
    }

    private function setControllerProperty(string $name, mixed $value): void
    {
        $property = new ReflectionProperty(Zend_Controller_Action::class, $name);
        $property->setAccessible(true);
        $property->setValue($this->controller, $value);
    }

    /**
     * @param list<string> $roles
     */
    private function login(int $uid, array $roles): void
    {
        $user = $this->createMock(Episciences_User::class);
        $user->method('getUid')->willReturn($uid);
        $user->method('getRoles')->willReturn($roles);
        $user->method('getAllRoles')->willReturn([RVID => $roles]);
        $user->method('getScreenName')->willReturn('User ' . $uid);

        Episciences_Auth::getInstance()->getStorage()->write($user);
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
        int $rvid = RVID,
        bool $isDeleted = false,
        bool $isOwner = false,
        string $conflictResponse = Episciences_Paper_Conflict::AVAILABLE_ANSWER['no'],
        ?int $assignedEditorUid = null
    ): Episciences_Paper {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getRvid')->willReturn($rvid);
        $paper->method('getDocid')->willReturn(self::DOC_ID);
        $paper->method('isDeleted')->willReturn($isDeleted);
        $paper->method('isRemoved')->willReturn(false);
        $paper->method('isOwner')->willReturn($isOwner);
        $paper->method('checkConflictResponse')->willReturn($conflictResponse);
        $paper->method('isAssignedEditorOrCopyEditor')->willReturnCallback(
            static fn(int $uid) => $uid === $assignedEditorUid
        );
        return $paper;
    }

    /**
     * @param array<string, mixed> $post
     */
    private function post(array $post, ?string $token = self::TOKEN): void
    {
        $this->request->setMethod('POST');
        $this->request->setPost($post);

        if ($token !== null) {
            $this->request->setPost('csrf_token', $token);
        }
    }

    private function postCoAuthorForm(?string $token = self::TOKEN): void
    {
        $this->post(['selectedUserId' => self::CO_AUTHOR_UID, 'docId' => self::DOC_ID], $token);
    }

    /**
     * Runs addcoauthorAction() and returns the redirection URL, null when the action did not redirect.
     */
    private function addCoAuthor(): ?string
    {
        try {
            $this->controller->addcoauthorAction();
        } catch (CoAuthorRedirected $redirection) {
            return $redirection->getMessage();
        }

        return null;
    }

    private function findPaperForCoAuthorForm(): ?Episciences_Paper
    {
        $method = new ReflectionMethod(\AdministratepaperController::class, 'findPaperForCoAuthorForm');
        $method->setAccessible(true);

        return $method->invoke($this->controller, $this->request, self::DOC_ID);
    }

    // =========================================================================
    // addcoauthorAction()
    // =========================================================================

    public function testNonPostRequestIsIgnored(): void
    {
        $this->login(42, [Episciences_Acl::ROLE_SECRETARY]);
        $this->controller->paper = $this->createPaper();
        $this->request->setMethod('GET');
        $this->request->setQuery(['selectedUserId' => self::CO_AUTHOR_UID, 'docId' => self::DOC_ID]);

        self::assertNull($this->addCoAuthor());
        self::assertSame([], $this->controller->addedCoAuthors);
    }

    public function testUserWithoutAnEditorialRoleIsIgnored(): void
    {
        $this->login(42, [Episciences_Acl::ROLE_AUTHOR]);
        $this->controller->paper = $this->createPaper();
        $this->postCoAuthorForm();

        self::assertNull($this->addCoAuthor());
        self::assertSame([], $this->controller->addedCoAuthors);
    }

    public function testUnknownPaperIsRejected(): void
    {
        $this->login(42, [Episciences_Acl::ROLE_SECRETARY]);
        $this->postCoAuthorForm();

        self::assertSame('/administratepaper/list', $this->addCoAuthor());
        self::assertSame(['error'], $this->helper->namespaces());
        self::assertSame([], $this->controller->addedCoAuthors);
    }

    public function testPaperOfAnotherJournalIsRejected(): void
    {
        $this->login(42, [Episciences_Acl::ROLE_SECRETARY]);
        $this->controller->paper = $this->createPaper(rvid: RVID + 1);
        $this->postCoAuthorForm();

        self::assertSame('/administratepaper/list', $this->addCoAuthor());
        self::assertSame([], $this->controller->addedCoAuthors);
    }

    public function testMissingRequestTokenIsRejected(): void
    {
        $this->login(42, [Episciences_Acl::ROLE_SECRETARY]);
        $this->controller->paper = $this->createPaper();
        $this->postCoAuthorForm(null);

        self::assertSame('administratepaper/view?id=' . self::DOC_ID, $this->addCoAuthor());
        self::assertSame(['error'], $this->helper->namespaces());
        self::assertSame([], $this->controller->addedCoAuthors);
    }

    public function testWrongRequestTokenIsRejected(): void
    {
        $this->login(42, [Episciences_Acl::ROLE_SECRETARY]);
        $this->controller->paper = $this->createPaper();
        $this->postCoAuthorForm('forged-token');

        self::assertSame('administratepaper/view?id=' . self::DOC_ID, $this->addCoAuthor());
        self::assertSame([], $this->controller->addedCoAuthors);
    }

    public function testDeletedPaperIsRejected(): void
    {
        $this->login(42, [Episciences_Acl::ROLE_SECRETARY]);
        $this->controller->paper = $this->createPaper(isDeleted: true);
        $this->postCoAuthorForm();

        self::assertSame('/', $this->addCoAuthor());
        self::assertSame([], $this->controller->addedCoAuthors);
    }

    public function testOwnSubmissionIsRejected(): void
    {
        $this->login(42, [Episciences_Acl::ROLE_SECRETARY]);
        $this->controller->paper = $this->createPaper(isOwner: true);
        $this->postCoAuthorForm();

        self::assertSame('/paper/view?id=' . self::DOC_ID, $this->addCoAuthor());
        self::assertSame([], $this->controller->addedCoAuthors);
    }

    /**
     * A secretary is not restricted by the paper permissions: the declared
     * conflict of interest must still stop them, as on the paper management page.
     */
    public function testSecretaryWithADeclaredConflictIsRejected(): void
    {
        $this->login(42, [Episciences_Acl::ROLE_SECRETARY]);
        $this->controller->review = $this->createReview([Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED => 1]);
        $this->controller->paper = $this->createPaper(conflictResponse: Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes']);
        $this->postCoAuthorForm();

        self::assertSame('/coi/report?id=' . self::DOC_ID, $this->addCoAuthor());
        self::assertSame([], $this->controller->addedCoAuthors);
    }

    public function testEditorWithADeclaredConflictIsRejected(): void
    {
        $this->login(42, [Episciences_Acl::ROLE_EDITOR]);
        $this->controller->review = $this->createReview([Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED => 1]);
        $this->controller->paper = $this->createPaper(
            conflictResponse: Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes'],
            assignedEditorUid: 42
        );
        $this->postCoAuthorForm();

        self::assertSame('/coi/report?id=' . self::DOC_ID, $this->addCoAuthor());
        self::assertSame([], $this->controller->addedCoAuthors);
    }

    public function testUnassignedEditorIsRejectedWhenEditorsAreEncapsulated(): void
    {
        $this->login(42, [Episciences_Acl::ROLE_EDITOR]);
        $this->controller->review = $this->createReview(['encapsulateEditors' => 1]);
        $this->controller->paper = $this->createPaper();
        $this->postCoAuthorForm();

        self::assertSame('/administratepaper/assigned', $this->addCoAuthor());
        self::assertSame([], $this->controller->addedCoAuthors);
    }

    public function testUnassignedGuestEditorIsRejected(): void
    {
        $this->login(42, [Episciences_Acl::ROLE_GUEST_EDITOR]);
        $this->controller->paper = $this->createPaper();
        $this->postCoAuthorForm();

        self::assertSame('/administratepaper/assigned', $this->addCoAuthor());
        self::assertSame([], $this->controller->addedCoAuthors);
    }

    public function testAssignedEditorPassesTheChecks(): void
    {
        $this->login(42, [Episciences_Acl::ROLE_EDITOR]);
        $this->controller->review = $this->createReview(['encapsulateEditors' => 1]);
        $this->controller->paper = $this->createPaper(assignedEditorUid: 42);
        $this->postCoAuthorForm();

        self::assertSame($this->controller->paper, $this->findPaperForCoAuthorForm());
        self::assertNull($this->helper->redirectedTo);
        self::assertSame([], $this->helper->messages);
    }

    public function testSecretaryWithoutConflictPassesTheChecks(): void
    {
        $this->login(42, [Episciences_Acl::ROLE_SECRETARY]);
        $this->controller->review = $this->createReview([Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED => 1]);
        $this->controller->paper = $this->createPaper();
        $this->postCoAuthorForm();

        self::assertSame($this->controller->paper, $this->findPaperForCoAuthorForm());
        self::assertNull($this->helper->redirectedTo);
    }

    // =========================================================================
    // ajaxrequestremovecoauthorAction()
    // =========================================================================

    private function postCoAuthorRemoval(?string $token): void
    {
        $this->request->setHeader('X-Requested-With', 'XMLHttpRequest');
        $this->post(['docId' => self::DOC_ID, 'uid' => self::CO_AUTHOR_UID, 'rvid' => RVID], null);

        if ($token !== null) {
            $this->request->setHeader('X-CSRF-Token', $token);
        }
    }

    public function testCoAuthorRemovalWithoutRequestTokenIsRejected(): void
    {
        $this->login(42, [Episciences_Acl::ROLE_ADMIN]);
        $this->postCoAuthorRemoval(null);

        $this->expectOutputString('0');
        $this->controller->ajaxrequestremovecoauthorAction();

        self::assertSame(['error'], $this->helper->namespaces());
    }

    public function testCoAuthorRemovalWithWrongRequestTokenIsRejected(): void
    {
        $this->login(42, [Episciences_Acl::ROLE_ADMIN]);
        $this->postCoAuthorRemoval('forged-token');

        $this->expectOutputString('0');
        $this->controller->ajaxrequestremovecoauthorAction();

        self::assertSame(['error'], $this->helper->namespaces());
    }
}
