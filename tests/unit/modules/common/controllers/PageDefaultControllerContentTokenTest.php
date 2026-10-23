<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Ccsd_View_Helper_DisplayFlashMessages;
use Episciences_Acl;
use Episciences_Auth;
use Episciences_Csrf_Helper;
use Episciences_User;
use PageDefaultController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zend_Auth_Storage_Interface;
use Zend_Auth_Storage_NonPersistent;
use Zend_Controller_Action_Helper_FlashMessenger;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;
use Zend_Session;
use Zend_Session_Namespace;

/**
 * Form token check of the page content form (PageDefaultController::renderAction()).
 *
 * Content submitted without the per-session token is not saved, and the edit form is shown
 * again with the saved content: the submitted one may come from another site.
 */
final class PageDefaultControllerContentTokenTest extends TestCase
{
    /** A page no content file exists for */
    private const PAGE = 'form-token-test-page';

    private Zend_Db_Adapter_Abstract $previousAdapter;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $adapter;

    private bool $previousUnitTestEnabled;

    private Zend_Auth_Storage_Interface $previousAuthStorage;

    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/common/controllers/PageDefaultController.php';
    }

    protected function setUp(): void
    {
        $this->previousUnitTestEnabled = Zend_Session::$_unitTestEnabled;
        Zend_Session::$_unitTestEnabled = true;

        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchRow', 'insert', 'update', 'delete'])
            ->getMock();
        $this->adapter->method('fetchRow')->willReturn(false);
        Zend_Db_Table_Abstract::setDefaultAdapter($this->adapter);

        if (!\Zend_Registry::isRegistered('Zend_Locale')) {
            \Zend_Registry::set('Zend_Locale', new \Zend_Locale('en'));
        }
        if (!\Zend_Registry::isRegistered('languages')) {
            \Zend_Registry::set('languages', ['en', 'fr']);
        }
        if (!\Zend_Registry::isRegistered('lang')) {
            \Zend_Registry::set('lang', 'en');
        }

        $this->previousAuthStorage = Episciences_Auth::getInstance()->getStorage();
        Episciences_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());
        $secretary = $this->createMock(Episciences_User::class);
        $secretary->method('getAllRoles')->willReturn([RVID => [Episciences_Acl::ROLE_SECRETARY]]);
        Episciences_Auth::getInstance()->getStorage()->write($secretary);

        // Messages left by the other tests of the run
        (new \Zend_Controller_Action_Helper_FlashMessenger())->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->clearCurrentMessages();

        // The session holds a token the requests below do not send
        Episciences_Csrf_Helper::getSessionToken();
    }

    protected function tearDown(): void
    {
        (new Zend_Controller_Action_Helper_FlashMessenger())->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->clearCurrentMessages();
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->csrfToken);
        $_POST = [];

        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
        Episciences_Auth::getInstance()->clearIdentity();
        Episciences_Auth::getInstance()->setStorage($this->previousAuthStorage);
        Zend_Session::$_unitTestEnabled = $this->previousUnitTestEnabled;
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function refusedTokenProvider(): array
    {
        return [
            'without token' => [null],
            'with a wrong token' => ['forged'],
        ];
    }

    #[DataProvider('refusedTokenProvider')]
    public function testContentIsNotSavedNorShownAgainWhenTheTokenIsRefused(?string $token): void
    {
        $this->adapter->expects(self::never())->method('insert');
        $this->adapter->expects(self::never())->method('update');
        $this->adapter->expects(self::never())->method('delete');

        $post = ['content' => ['en' => '<p>Typed content</p>', 'fr' => '<p>Contenu saisi</p>']];
        if ($token !== null) {
            $post['csrf_token'] = $token;
        }

        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setActionName(self::PAGE);
        $request->setMethod('POST')->setPost($post);
        $_POST = $post;

        $controller = new PageDefaultController($request, new Zend_Controller_Response_HttpTestCase());
        $controller->renderAction();

        self::assertSame('edit', $controller->view->mode);
        // No content file exists for the page: nothing saved to show
        self::assertSame([], $controller->view->form->getValue('content'));
        self::assertFileDoesNotExist(REVIEW_PAGE_PATH . self::PAGE . '.en.html');

        $flash = new Zend_Controller_Action_Helper_FlashMessenger();
        self::assertSame(
            ['Votre session a expiré, veuillez réessayer.'],
            $flash->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->getCurrentMessages()
        );
    }
}
