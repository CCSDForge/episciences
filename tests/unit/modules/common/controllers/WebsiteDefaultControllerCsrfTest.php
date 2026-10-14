<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Ccsd_View_Helper_DisplayFlashMessages;
use DOMDocument;
use DOMXPath;
use Episciences_Csrf_Helper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WebsiteDefaultController;
use Zend_Controller_Action_Helper_Abstract;
use Zend_Controller_Action_Helper_FlashMessenger;
use Zend_Controller_Action_Helper_Redirector;
use Zend_Controller_Action_Helper_ViewRenderer;
use Zend_Controller_Action_HelperBroker;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Session;
use Zend_Session_Namespace;
use Zend_View;

/**
 * Form token check of the public resources page (publicAction of WebsiteDefaultController).
 *
 * A file removal without the per-session token must leave the file in place and tell the user
 * the session has expired. The action is called for real on a file of the journal public directory.
 */
final class WebsiteDefaultControllerCsrfTest extends TestCase
{
    private const EXPIRED = 'Votre session a expiré, veuillez réessayer.';

    private bool $previousUnitTestEnabled;

    /** @var Zend_Controller_Action_Helper_Abstract[] */
    private array $previousHelpers = [];

    private string $fileName;

    public static function setUpBeforeClass(): void
    {
        require_once APPLICATION_PATH . '/modules/common/controllers/WebsiteDefaultController.php';
    }

    protected function setUp(): void
    {
        $this->previousUnitTestEnabled = Zend_Session::$_unitTestEnabled;
        Zend_Session::$_unitTestEnabled = true;

        $this->previousHelpers = iterator_to_array(Zend_Controller_Action_HelperBroker::getStack(), false);
        Zend_Controller_Action_HelperBroker::resetHelpers();
        $viewRenderer = new Zend_Controller_Action_Helper_ViewRenderer(new Zend_View());
        $viewRenderer->setNeverRender(true);
        Zend_Controller_Action_HelperBroker::addHelper($viewRenderer);
        $redirector = new Zend_Controller_Action_Helper_Redirector();
        $redirector->setExit(false);
        Zend_Controller_Action_HelperBroker::addHelper($redirector);

        // Messages left by the other tests of the run
        (new Zend_Controller_Action_Helper_FlashMessenger())->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->clearCurrentMessages();

        Episciences_Csrf_Helper::getSessionToken();

        $dir = REVIEW_PATH . 'public/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $this->fileName = uniqid('csrf-test-', true) . '.txt';
        file_put_contents($dir . $this->fileName, 'test');
    }

    protected function tearDown(): void
    {
        $file = REVIEW_PATH . 'public/' . $this->fileName;
        if (is_file($file)) {
            unlink($file);
        }

        (new Zend_Controller_Action_Helper_FlashMessenger())->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->clearCurrentMessages();
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->csrfToken);
        $_POST = [];

        Zend_Controller_Action_HelperBroker::resetHelpers();
        foreach ($this->previousHelpers as $helper) {
            Zend_Controller_Action_HelperBroker::addHelper($helper);
        }
        Zend_Session::$_unitTestEnabled = $this->previousUnitTestEnabled;
    }

    /**
     * @param array<string, mixed> $post
     */
    private function postRemoval(array $post): Zend_Controller_Response_HttpTestCase
    {
        $post += ['method' => 'remove', 'name' => $this->fileName];
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setPost($post);
        $request->setRequestUri('/website/public');
        $_POST = $post;

        $response = new Zend_Controller_Response_HttpTestCase();
        $controller = new WebsiteDefaultController($request, $response);
        $controller->publicAction();

        return $response;
    }

    /**
     * @return list<string>
     */
    private function errorMessages(): array
    {
        $flash = new Zend_Controller_Action_Helper_FlashMessenger();

        return $flash->setNamespace(Ccsd_View_Helper_DisplayFlashMessages::MSG_ERROR)->getCurrentMessages();
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function refusedTokenProvider(): array
    {
        return [
            'without token' => [[]],
            'with a wrong token' => [['csrf_token' => 'forged']],
        ];
    }

    /**
     * @param array<string, string> $token
     */
    #[DataProvider('refusedTokenProvider')]
    public function testFileIsNotRemovedWhenTheTokenIsRefused(array $token): void
    {
        $response = $this->postRemoval($token);

        self::assertFileExists(REVIEW_PATH . 'public/' . $this->fileName);
        self::assertSame([self::EXPIRED], $this->errorMessages());
        self::assertTrue($response->isRedirect());
    }

    public function testFileIsRemovedWithAValidToken(): void
    {
        $this->postRemoval(['csrf_token' => Episciences_Csrf_Helper::getSessionToken()]);

        self::assertFileDoesNotExist(REVIEW_PATH . 'public/' . $this->fileName);
        self::assertSame([], $this->errorMessages());
    }

    public function testPageFormSendsTheSessionToken(): void
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/common/views/scripts');
        $view->addHelperPath(dirname(APPLICATION_PATH) . '/library/Ccsd/View/Helper', 'Ccsd_View_Helper_');
        $view->csrfToken = Episciences_Csrf_Helper::getSessionToken();
        $view->files = [];

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $view->render('website/public.phtml'));
        libxml_clear_errors();

        $inputs = (new DOMXPath($dom))->query('//form[@id="form"]//input[@name="csrf_token"]');

        self::assertNotFalse($inputs);
        self::assertSame(1, $inputs->length);
        $input = $inputs->item(0);
        self::assertInstanceOf(\DOMElement::class, $input);
        self::assertSame(Episciences_Csrf_Helper::getSessionToken(), $input->getAttribute('value'));
    }
}
