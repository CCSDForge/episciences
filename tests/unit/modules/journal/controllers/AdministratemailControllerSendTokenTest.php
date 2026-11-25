<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Ccsd_View_Helper_Message;
use Episciences_Csrf_Helper;
use PHPUnit\Framework\TestCase;
use Zend_Auth;
use Zend_Auth_Storage_Interface;
use Zend_Auth_Storage_NonPersistent;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Session;
use Zend_Session_Namespace;

/**
 * Form token check of the mailing form (AdministratemailController::sendAction()).
 *
 * A message submitted without the per-session token is not sent. The modal gets an error status
 * (its script keeps the text typed by the user); the full page form is shown again empty, as the
 * values of a refused request may come from another site.
 */
final class AdministratemailControllerSendTokenTest extends TestCase
{
    private bool $previousUnitTestEnabled;

    private Zend_Auth_Storage_Interface $previousAuthStorage;

    private Zend_Controller_Response_HttpTestCase $response;

    protected function setUp(): void
    {
        $this->previousUnitTestEnabled = Zend_Session::$_unitTestEnabled;
        Zend_Session::$_unitTestEnabled = true;

        // The form shows the name and address of the connected sender
        $this->previousAuthStorage = Zend_Auth::getInstance()->getStorage();
        Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());
        $sender = $this->createMock(\Episciences_User::class);
        $sender->method('getUid')->willReturn(1);
        $sender->method('getScreenName')->willReturn('Sender');
        $sender->method('getEmail')->willReturn('sender@example.org');
        $sender->method('getFullName')->willReturn('Sender');
        Zend_Auth::getInstance()->getStorage()->write($sender);

        // Messages left by the other tests of the run
        (new \Zend_Controller_Action_Helper_FlashMessenger())->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->clearCurrentMessages();

        // The session holds a token the requests below do not send
        Episciences_Csrf_Helper::getSessionToken();

        require_once APPLICATION_PATH . '/modules/journal/controllers/AdministratemailController.php';
    }

    protected function tearDown(): void
    {
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->csrfToken);
        $_POST = []; // setPost() writes the global
        Zend_Auth::getInstance()->setStorage($this->previousAuthStorage);

        Zend_Session::$_unitTestEnabled = $this->previousUnitTestEnabled;
    }

    /**
     * @param array<string, mixed> $post
     * @param-out string $output
     */
    private function send(array $post, ?string &$output = null): \AdministratemailController
    {
        $_POST = [];
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST');
        $request->setPost($post);
        $this->response = new Zend_Controller_Response_HttpTestCase();

        $controller = new \AdministratemailController($request, $this->response);

        ob_start();
        try {
            $controller->sendAction();
        } finally {
            $output = (string)ob_get_clean();
        }

        return $controller;
    }

    public function testModalSubmissionWithAWrongTokenIsRefusedWith403(): void
    {
        $this->send(['in_modal' => '1', 'docid' => '12', 'subject' => 'Typed subject', 'csrf_token' => 'forged'], $output);

        self::assertSame(403, $this->response->getHttpResponseCode());
        self::assertStringContainsString('Votre session a expiré, veuillez réessayer.', (string)$output);
    }

    public function testFullPageSubmissionWithAWrongTokenDoesNotShowTheSubmittedValues(): void
    {
        $controller = $this->send(['submit' => '1', 'subject' => 'Typed subject', 'content' => 'Typed content', 'csrf_token' => 'forged']);

        /** @var \Zend_Form $form */
        $form = $controller->view->form;
        self::assertNotSame('Typed subject', $form->getValue('subject'));
        self::assertNotSame('Typed content', $form->getValue('content'));
        self::assertSame(Episciences_Csrf_Helper::getSessionToken(), $form->getElement('csrf_token')->getValue());

        $messenger = $controller->getHelper('FlashMessenger')->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR);
        $messages = $messenger->getCurrentMessages();
        $messenger->clearCurrentMessages();
        self::assertCount(1, $messages);
        self::assertStringContainsString('Votre session a expiré, veuillez réessayer.', $messages[0]);
    }
}
