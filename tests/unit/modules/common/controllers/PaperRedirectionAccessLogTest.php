<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Episciences\Security\AccessLog;
use Episciences_Acl;
use Episciences_Paper;
use Episciences_User;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionMethod;
use Zend_Auth;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_View;

require_once APPLICATION_PATH . '/modules/journal/controllers/CommentsController.php';

/**
 * Access refusals answered by a redirection to another page (DefaultController, CommentsController):
 * logged as access_redirected, with the paper, whatever the user is.
 */
final class PaperRedirectionAccessLogTest extends TestCase
{
    private const DOCID = 12;
    private const PAPERID = 10;

    private TestHandler $logHandler;

    protected function setUp(): void
    {
        $this->logHandler = new TestHandler();
        AccessLog::setLogger(new Logger(AccessLog::CHANNEL, [$this->logHandler]));
        AccessLog::reset();
    }

    protected function tearDown(): void
    {
        Zend_Auth::getInstance()->clearIdentity();
        AccessLog::setLogger(new NullLogger());
        AccessLog::reset();
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

    private function paper(bool $isOwner = false): Episciences_Paper
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getDocid')->willReturn(self::DOCID);
        $paper->method('getPaperid')->willReturn(self::PAPERID);
        $paper->method('isOwner')->willReturn($isOwner);

        return $paper;
    }

    /**
     * @return list<array{string, string, mixed, mixed}> event, reason, docid and paperid of each security log record
     */
    private function loggedEvents(): array
    {
        return array_map(
            static fn(LogRecord $record): array => [
                $record->message,
                $record->context['reason'] ?? '',
                $record->context['docid'] ?? null,
                $record->context['paperid'] ?? null,
            ],
            $this->logHandler->getRecords()
        );
    }

    private function defaultController(bool $isRestricted): RedirectionTestController
    {
        $controller = new RedirectionTestController(
            new Zend_Controller_Request_HttpTestCase(),
            new Zend_Controller_Response_HttpTestCase(),
            ['restricted' => $isRestricted]
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
            $this->loggedEvents()
        );
    }

    public function testAllowedPaperIsNotLogged(): void
    {
        $this->defaultController(false)->check($this->paper());

        self::assertSame([], $this->loggedEvents());
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
            $this->loggedEvents()
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
        self::assertSame([], $this->loggedEvents());
    }
}

final class RedirectionTestController extends \DefaultController
{
    protected function isRestrictedAccess(Episciences_Paper $paper): bool
    {
        return (bool)$this->getInvokeArg('restricted');
    }

    public function check(Episciences_Paper $paper): void
    {
        $this->redirectsIfHaveNotEnoughPermissions($paper);
    }
}
