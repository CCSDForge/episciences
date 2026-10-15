<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Acl;
use Episciences_Paper;
use Episciences_Review;
use Episciences_User;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Zend_Auth;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Registry;

/**
 * ExportController::getPaperToExport(): the paper page access rules applied to the metadata exports.
 * The logged user is a real identity with roles; the paper and its published version, read from the
 * database, are stubbed. Redirections and the 404 answer stop the request, they are turned into exceptions.
 */
final class ExportControllerAccessTest extends TestCase
{
    private const UID = 4242;
    private const DOCID = 12;
    private const PAPERID = 10;
    private const PUBLISHED_DOCID = 15;

    private mixed $previousSettings = null;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/ExportController.php';
        $this->previousSettings = Zend_Registry::isRegistered('reviewSettings') ? Zend_Registry::get('reviewSettings') : null;
        Zend_Registry::set('reviewSettings', []);
    }

    protected function tearDown(): void
    {
        Zend_Auth::getInstance()->clearIdentity();
        Zend_Registry::set('reviewSettings', $this->previousSettings);
    }

    /**
     * @param string[] $roles
     */
    private function logInAs(array $roles = [Episciences_Acl::ROLE_MEMBER]): void
    {
        $user = new Episciences_User(['UID' => self::UID]);
        $user->setRoles([RVID => $roles]);
        Zend_Auth::getInstance()->getStorage()->write($user);
    }

    /**
     * @param array<string, bool|int> $state published, owner, coauthor, reviewer, assigned, deleted, removed, rvid, repoid
     */
    private function paper(array $state = []): Episciences_Paper
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getDocid')->willReturn(self::DOCID);
        $paper->method('getPaperid')->willReturn(self::PAPERID);
        $paper->method('getRvid')->willReturn($state['rvid'] ?? RVID);
        $paper->method('getRepoid')->willReturn($state['repoid'] ?? 1);
        $paper->method('isDeleted')->willReturn($state['deleted'] ?? false);
        $paper->method('isRemoved')->willReturn($state['removed'] ?? false);
        $paper->method('isPublished')->willReturn($state['published'] ?? false);
        $paper->method('isOwner')->willReturn($state['owner'] ?? false);
        $paper->method('isCoauthor')->willReturn($state['coauthor'] ?? false);
        $paper->method('getReviewers')->willReturn(($state['reviewer'] ?? false) ? [self::UID => new \stdClass()] : []);
        $paper->method('isAssignedEditorOrCopyEditor')->willReturn($state['assigned'] ?? false);

        return $paper;
    }

    private function controller(?Episciences_Paper $paper, int $publishedDocId = 0, ?string $docId = null): \ExportController
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setControllerName('export')->setActionName('bibtex')->setParam('id', $docId ?? (string)self::DOCID);

        // The stubs reach the controller as invoke arguments, the way the front controller passes its own
        $invokeArgs = ['paper' => $paper, 'publishedDocId' => $publishedDocId];

        $controller = new class ($request, new Zend_Controller_Response_HttpTestCase(), $invokeArgs) extends \ExportController {
            protected function findPaper(int $docId): ?Episciences_Paper
            {
                return $this->getInvokeArg('paper');
            }

            protected function findPublishedDocId(int $paperId): int
            {
                return $this->getInvokeArg('publishedDocId');
            }

            protected function notFound(): never
            {
                throw new RuntimeException('404');
            }

            /**
             * @param array<string, mixed> $options
             */
            public function redirect($url, array $options = []): never
            {
                throw new RuntimeException('redirect:' . $url);
            }
        };
        $controller->view = new \Zend_View();

        return $controller;
    }

    private function export(\ExportController $controller): Episciences_Paper
    {
        $method = new ReflectionMethod($controller, 'getPaperToExport');
        $method->setAccessible(true);

        return $method->invoke($controller);
    }

    private function assertStopsWith(string $expected, \ExportController $controller): void
    {
        try {
            $this->export($controller);
        } catch (RuntimeException $e) {
            self::assertSame($expected, $e->getMessage());
            return;
        }
        self::fail("The export must stop with '$expected'");
    }

    public function testPublishedPaperIsExportedToAnonymousVisitors(): void
    {
        $paper = $this->paper(['published' => true]);

        self::assertSame($paper, $this->export($this->controller($paper)));
    }

    public function testUnpublishedVersionRedirectsToThePublishedOne(): void
    {
        $this->assertStopsWith('redirect:/' . self::PUBLISHED_DOCID . '/bibtex', $this->controller($this->paper(), self::PUBLISHED_DOCID));
    }

    public function testAnonymousVisitorIsSentToTheLoginPage(): void
    {
        // On manager branches the url() helper adds the journal path prefix and passes the parameters as a query string
        $this->assertStopsWith(
            'redirect:' . (defined('PREFIX_URL') ? PREFIX_URL : '/') . 'user/login?forward-controller=export&id=' . self::DOCID . '&forward-action=bibtex',
            $this->controller($this->paper())
        );
    }

    public function testMemberWithoutRoleOnThePaperIsSentToTheHomePage(): void
    {
        $this->logInAs();

        $this->assertStopsWith('redirect:/', $this->controller($this->paper()));
    }

    public function testUnassignedEditorIsRefusedWhenTheJournalRestrictsEditors(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);
        Zend_Registry::set('reviewSettings', [Episciences_Review::SETTING_ENCAPSULATE_EDITORS => '1']);

        $this->assertStopsWith('redirect:/', $this->controller($this->paper()));
    }

    /**
     * @dataProvider allowedUsers
     * @param string[] $roles
     * @param array<string, bool> $state
     */
    public function testUserAllowedToSeeTheUnpublishedPaperExportsIt(array $roles, array $state): void
    {
        $this->logInAs($roles);
        $paper = $this->paper($state);

        self::assertSame($paper, $this->export($this->controller($paper)));
    }

    /**
     * @return iterable<string, array{string[], array<string, bool>}>
     */
    public static function allowedUsers(): iterable
    {
        yield 'owner' => [[Episciences_Acl::ROLE_MEMBER], ['owner' => true]];
        yield 'co-author' => [[Episciences_Acl::ROLE_MEMBER], ['coauthor' => true]];
        yield 'assigned reviewer' => [[Episciences_Acl::ROLE_REVIEWER], ['reviewer' => true]];
        yield 'assigned editor' => [[Episciences_Acl::ROLE_EDITOR], ['assigned' => true]];
        yield 'assigned copy editor' => [[Episciences_Acl::ROLE_COPY_EDITOR], ['assigned' => true]];
        yield 'secretary' => [[Episciences_Acl::ROLE_SECRETARY], []];
    }

    /**
     * @dataProvider notExportedPapers
     * @param array<string, bool|int> $state
     */
    public function testPaperThatCannotBeExportedAnswers404(array $state): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        $this->assertStopsWith('404', $this->controller($this->paper($state + ['published' => true])));
    }

    /**
     * @return iterable<string, array{array<string, bool|int>}>
     */
    public static function notExportedPapers(): iterable
    {
        yield 'deleted' => [['deleted' => true]];
        yield 'removed' => [['removed' => true]];
        yield 'other journal' => [['rvid' => RVID + 1]];
        yield 'no repository' => [['repoid' => 0]];
    }

    public function testUnknownPaperAnswers404(): void
    {
        $this->assertStopsWith('404', $this->controller(null));
    }

    public function testNonNumericIdAnswers404(): void
    {
        $this->assertStopsWith('404', $this->controller($this->paper(['published' => true]), 0, 'abc'));
    }
}
