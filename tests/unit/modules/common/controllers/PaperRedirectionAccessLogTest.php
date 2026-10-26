<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Episciences\Security\AccessLog;
use Episciences_Acl;
use Episciences_Paper;
use Episciences_User;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Auth;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_View;
use unit\library\Episciences\Security\SecurityLogCaptureTrait;

require_once dirname(__DIR__, 3) . '/library/Episciences/Security/SecurityLogCaptureTrait.php';

// Parent of the test controller declared below
require_once APPLICATION_PATH . '/modules/common/controllers/DefaultController.php';

/**
 * Access refusals answered by a redirection to another page (DefaultController, CommentsController):
 * logged with the paper, as auth_required when a guest is sent to the login page, as access_redirected otherwise.
 */
final class PaperRedirectionAccessLogTest extends TestCase
{
    use SecurityLogCaptureTrait;

    private const DOCID = 12;
    private const PAPERID = 10;

    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/CommentsController.php';
    }

    protected function tearDown(): void
    {
        Zend_Auth::getInstance()->clearIdentity();
    }

    /**
     * @param string[] $roles
     */
    private function logInAs(array $roles = [Episciences_Acl::ROLE_MEMBER]): void
    {
        $user = new Episciences_User(['UID' => 4242]);
        $user->setRoles([RVID => $roles]);
        Zend_Auth::getInstance()->getStorage()->write($user);
    }

    private function paper(bool $isOwner = false, int $paperId = self::PAPERID): Episciences_Paper
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getDocid')->willReturn(self::DOCID);
        $paper->method('getPaperid')->willReturn($paperId);
        $paper->method('isOwner')->willReturn($isOwner);

        return $paper;
    }

    private function defaultController(bool $isRestricted, int $publishedDocId = 0, ?string $journalPrefix = null): RedirectionTestController
    {
        $controller = new RedirectionTestController(
            new Zend_Controller_Request_HttpTestCase(),
            new Zend_Controller_Response_HttpTestCase(),
            ['restricted' => $isRestricted, 'publishedId' => $publishedDocId, 'journalPrefix' => $journalPrefix]
        );
        $controller->view = new Zend_View();
        $controller->getHelper('redirector')->setExit(false);

        return $controller;
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function visitors(): iterable
    {
        yield 'guest' => [false];
        yield 'member' => [true];
    }

    /**
     * Guests too are sent to the home page, not to the login page
     * @dataProvider visitors
     */
    public function testRestrictedPaperRedirectionIsLogged(bool $isLogged): void
    {
        if ($isLogged) {
            $this->logInAs();
        }
        $controller = $this->defaultController(true);

        $controller->check($this->paper());

        self::assertTrue($controller->getResponse()->isRedirect());
        self::assertSame(
            [[AccessLog::ACCESS_REDIRECTED, 'unpublished_paper', self::DOCID, self::PAPERID]],
            $this->loggedEvents('reason', 'docid', 'paperid')
        );
    }

    public function testAllowedPaperIsNotLogged(): void
    {
        $this->defaultController(false)->check($this->paper());

        self::assertSame([], $this->loggedEvents('reason', 'docid', 'paperid'));
    }

    private static function redirectionOf(\DefaultController $controller): ?string
    {
        foreach ($controller->getResponse()->getHeaders() as $header) {
            if (strcasecmp($header['name'], 'Location') === 0) {
                return (string)$header['value'];
            }
        }

        return null;
    }

    // requestingAnUnpublishedFile(): the file of a version without published version

    public function testGuestRequestingAnUnpublishedFileIsSentToTheLoginPage(): void
    {
        $controller = $this->defaultController(true);

        $controller->checkFile($this->paper());

        // On manager branches the url() helper adds the journal path prefix and passes the parameters as a query string
        self::assertSame(
            (defined('PREFIX_URL') ? PREFIX_URL : '/') . 'user/login?forward-controller=paper&forward-action=pdf&id=' . self::DOCID,
            self::redirectionOf($controller)
        );
        self::assertSame(
            [[AccessLog::AUTH_REQUIRED, 'unpublished_paper', self::DOCID, self::PAPERID]],
            $this->loggedEvents('reason', 'docid', 'paperid')
        );
    }

    public function testMemberRequestingAnUnpublishedFileIsSentToTheHomePage(): void
    {
        $this->logInAs();
        $controller = $this->defaultController(true);

        $controller->checkFile($this->paper());

        self::assertSame('/', self::redirectionOf($controller));
        self::assertSame(
            [[AccessLog::ACCESS_REDIRECTED, 'unpublished_paper', self::DOCID, self::PAPERID]],
            $this->loggedEvents('reason', 'docid', 'paperid')
        );
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function paperIds(): iterable
    {
        yield 'paper id' => [self::PAPERID, self::PAPERID];
        yield 'docid of a paper without paper id' => [0, self::DOCID];
    }

    /**
     * @dataProvider paperIds
     */
    public function testPublishedVersionIsLookedUpForThePaper(int $paperId, int $expectedLookup): void
    {
        $this->logInAs();
        $controller = $this->defaultController(true);

        $controller->checkFile($this->paper(paperId: $paperId));

        self::assertSame([$expectedLookup], $controller->publishedLookups);
    }

    /**
     * The published version is the address of the file from now on, whoever asks: nothing is refused
     */
    public function testFileOfAnUnpublishedVersionMovesPermanentlyToThePublishedOne(): void
    {
        $controller = $this->defaultController(true, 15);

        $controller->checkFile($this->paper());

        self::assertSame('/15/pdf', self::redirectionOf($controller));
        self::assertSame(301, $controller->getResponse()->getHttpResponseCode());
        self::assertSame([], $this->loggedEvents());
    }

    /**
     * The status code is kept by the shared redirector helper: the next redirections are temporary again
     */
    public function testPermanentMoveDoesNotLeakToTheNextRedirections(): void
    {
        $this->defaultController(true, 15)->checkFile($this->paper());

        $controller = $this->defaultController(true);
        $controller->checkFile($this->paper());

        self::assertSame(302, $controller->getResponse()->getHttpResponseCode());
    }

    public function testPageOfAnUnpublishedVersionRedirectsTemporarilyToThePublishedOne(): void
    {
        $controller = $this->defaultController(true, 15);

        $controller->checkPage($this->paper());

        self::assertSame('/15', self::redirectionOf($controller));
        self::assertSame(302, $controller->getResponse()->getHttpResponseCode());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function journalPrefixes(): iterable
    {
        yield 'journal' => ['/rvcode/'];
        yield 'portal' => ['/'];
    }

    /**
     * On path-based deployments the published version is served under the journal prefix
     * @dataProvider journalPrefixes
     */
    public function testPublishedVersionIsServedUnderTheJournalPrefix(string $journalPrefix): void
    {
        $file = $this->defaultController(true, 15, $journalPrefix);
        $file->checkFile($this->paper());

        $page = $this->defaultController(true, 15, $journalPrefix);
        $page->checkPage($this->paper());

        self::assertSame($journalPrefix . '15/pdf', self::redirectionOf($file));
        self::assertSame($journalPrefix . '15', self::redirectionOf($page));
    }

    public function testAllowedFileIsNotLookedUpNorLogged(): void
    {
        $controller = $this->defaultController(false);

        $controller->checkFile($this->paper());

        self::assertNull(self::redirectionOf($controller));
        self::assertSame([], $controller->publishedLookups);
        self::assertSame([], $this->loggedEvents('reason', 'docid', 'paperid'));
    }

    private function checkCommentAccess(Episciences_Paper $paper): \CommentsController
    {
        $controller = new \CommentsController(new Zend_Controller_Request_HttpTestCase(), new Zend_Controller_Response_HttpTestCase());
        $controller->getHelper('redirector')->setExit(false);
        (new ReflectionMethod(\CommentsController::class, 'checkAccess'))->invoke($controller, $paper);

        return $controller;
    }

    public function testCommentOfAnotherAuthorIsLogged(): void
    {
        $this->logInAs();

        $controller = $this->checkCommentAccess($this->paper());

        self::assertTrue($controller->getResponse()->isRedirect());
        self::assertSame(
            [[AccessLog::ACCESS_REDIRECTED, 'not_comment_author', self::DOCID, self::PAPERID]],
            $this->loggedEvents('reason', 'docid', 'paperid')
        );
    }

    /**
     * @return iterable<string, array{bool, string[]}>
     */
    public static function commentEditors(): iterable
    {
        yield 'author' => [true, [Episciences_Acl::ROLE_MEMBER]];
        yield 'secretary' => [false, [Episciences_Acl::ROLE_SECRETARY]];
    }

    /**
     * @dataProvider commentEditors
     * @param string[] $roles
     */
    public function testCommentEditorIsNotLogged(bool $isOwner, array $roles): void
    {
        $this->logInAs($roles);

        $controller = $this->checkCommentAccess($this->paper($isOwner));

        self::assertFalse($controller->getResponse()->isRedirect());
        self::assertSame([], $this->loggedEvents('reason', 'docid', 'paperid'));
    }
}

final class RedirectionTestController extends \DefaultController
{
    protected function isRestrictedAccess(Episciences_Paper $paper): bool
    {
        return (bool)$this->getInvokeArg('restricted');
    }

    protected function journalPrefix(): string
    {
        return $this->getInvokeArg('journalPrefix') ?? parent::journalPrefix();
    }

    /** @var list<int> paper ids whose published version was looked up */
    public array $publishedLookups = [];

    protected function findPublishedDocId(int $paperId): int
    {
        $this->publishedLookups[] = $paperId;

        return (int)$this->getInvokeArg('publishedId');
    }

    public function check(Episciences_Paper $paper): void
    {
        $this->redirectsIfHaveNotEnoughPermissions($paper);
    }

    public function checkFile(Episciences_Paper $paper): void
    {
        $this->requestingAnUnpublishedFile($paper);
    }

    public function checkPage(Episciences_Paper $paper): void
    {
        $this->redirectFromUnpublishedVersion($paper, '/%d', '/user/login');
    }
}
