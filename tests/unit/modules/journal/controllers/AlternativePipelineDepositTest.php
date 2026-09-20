<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once APPLICATION_PATH . '/modules/journal/controllers/PaperController.php';

final class AlternativePipelineDepositTest extends TestCase
{
    private Zend_Controller_Action_Helper_ViewRenderer $previousViewRenderer;

    protected function setUp(): void
    {
        $this->previousViewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer');
        $renderer = $this->getMockBuilder(Zend_Controller_Action_Helper_ViewRenderer::class)->onlyMethods(['init', 'getName'])->getMock();
        $renderer->method('getName')->willReturn('ViewRenderer');
        Zend_Controller_Action_HelperBroker::addHelper($renderer);
    }

    protected function tearDown(): void
    {
        Zend_Controller_Action_HelperBroker::addHelper($this->previousViewRenderer);
        Episciences_Auth::getInstance()->clearIdentity();
        Zend_Controller_Action_HelperBroker::removeHelper('redirector');
        (new Zend_Session_Namespace('AltFinalVersionDeposit_123'))->unsetAll();
    }

    /** @dataProvider invalidVersions */
    public function testInvalidVersionsDoNotCreateOrSavePapers(string $version): void
    {
        $controller = $this->depositController($version);
        $controller->expects(self::never())->method('createAltFinalVersionPaper');
        $controller->savefinalversiondepositAction();
        $state = new Zend_Session_Namespace('AltFinalVersionDeposit_123');
        self::assertSame($version, $state->values['version']);
        self::assertArrayNotHasKey('paperPassword', $state->values);
    }

    public static function invalidVersions(): iterable
    {
        yield 'older' => ['1'];
        yield 'fraction' => ['2.5'];
        yield 'text' => ['latest'];
        yield 'negative' => ['-1'];
    }

    public function testDuplicateVersionShowsSpecificError(): void
    {
        $controller = $this->depositController('3');
        $message = "Cette version arXiv a déjà été déposée dans la revue. Consultez le dépôt existant ou choisissez une autre version.";
        $controller->expects(self::once())->method('createAltFinalVersionPaper')
            ->willThrowException(new DomainException($message));
        $flash = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
        $flash->setNamespace(PaperDefaultController::ERROR)->clearCurrentMessages();
        $controller->savefinalversiondepositAction();
        self::assertContains($message, $flash->setNamespace(PaperDefaultController::ERROR)->getCurrentMessages());
    }

    private function depositController(string $version): PaperController
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getDocid')->willReturn(123);
        $paper->method('getVersion')->willReturn(2.0);
        $paper->method('isOwner')->willReturn(true);
        $paper->expects(self::never())->method('save');
        $request = $this->getMockBuilder(Zend_Controller_Request_Http::class)->onlyMethods(['isPost'])->getMock();
        $request->method('isPost')->willReturn(true);
        $csrf = 'csrf_finalversiondeposit_123';
        $csrfSession = new Zend_Session_Namespace('Zend_Form_Element_Hash_unique_' . $csrf);
        $csrfSession->hash = 'token';
        $request->setPost(['version' => $version, 'paperPassword' => 'secret', $csrf => 'token']);
        $controller = $this->getMockBuilder(PaperController::class)
            ->setConstructorArgs([$request, new Zend_Controller_Response_Http()])
            ->onlyMethods(['loadAltFinalVersionDepositPaperOrRedirect', 'createAltFinalVersionPaper'])->getMock();
        $controller->method('loadAltFinalVersionDepositPaperOrRedirect')->willReturn($paper);
        $controller->view = new Zend_View();
        $redirector = $this->getMockBuilder(Zend_Controller_Action_Helper_Redirector::class)->onlyMethods(['gotoUrl', 'getName'])->getMock();
        $redirector->method('getName')->willReturn('Redirector');
        $redirector->expects(self::once())->method('gotoUrl')->with('/paper/finalversiondeposit/id/123');
        Zend_Controller_Action_HelperBroker::addHelper($redirector);
        return $controller;
    }

    /** @dataProvider proxyRoles */
    public function testProxyRequiresAuthorizedRoleOrAssignment(string $role, bool $assignedEditor, bool $assignedCopyEditor, int $journal, bool $expected): void
    {
        $user = $this->createMock(Episciences_User::class);
        $user->method('getUid')->willReturn(42);
        $user->method('getRoles')->willReturn([$role]);
        $user->method('getAllRoles')->willReturn([RVID => [$role]]);
        Episciences_Auth::getInstance()->getStorage()->write($user);
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getRvid')->willReturn($journal);
        $paper->method('getEditor')->willReturn($assignedEditor ? $user : null);
        $paper->method('getCopyEditor')->willReturn($assignedCopyEditor ? $user : null);
        $controller = (new ReflectionClass(PaperController::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(PaperController::class, 'isAlternativePipelineAuthorProxy');
        $method->setAccessible(true);
        self::assertSame($expected, $method->invoke($controller, $paper));
    }

    public static function proxyRoles(): iterable
    {
        yield 'secretary' => ['secretary', false, false, 1, true];
        yield 'assigned editor' => ['editor', true, false, 1, true];
        yield 'assigned copy editor' => ['copyeditor', false, true, 1, true];
        yield 'unassigned editor' => ['editor', false, false, 1, false];
        yield 'other journal' => ['secretary', false, false, 999, false];
    }
}
