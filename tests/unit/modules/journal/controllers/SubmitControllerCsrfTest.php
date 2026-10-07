<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Ccsd_View_Helper_Message;
use Episciences_Csrf_Helper;
use Episciences_Submit;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;
use Zend_Form;

/**
 * Submitting a paper changes data on behalf of the user: a request without the session
 * request token must be refused before anything is read from or written to the database,
 * and the form must be given back with what the user typed, but neither the rejected token
 * nor the posted record.
 */
final class SubmitControllerCsrfTest extends TestCase
{
    private Zend_Db_Adapter_Abstract $previousAdapter;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $adapter;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/SubmitController.php';

        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchRow', 'fetchAll', 'fetchOne', 'fetchCol', 'query', 'insert', 'update', 'delete'])
            ->getMock();
        Zend_Db_Table_Abstract::setDefaultAdapter($this->adapter);
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    private ?\SubmitController $controller = null;

    /**
     * @param array<string, mixed> $post
     */
    private function submit(array $post, ?string $headerToken = null): Zend_Form
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')->setPost($post);
        if ($headerToken !== null) {
            $request->setHeader('X-CSRF-Token', $headerToken);
        }

        $form = new Zend_Form();
        foreach (['title', 'xml', Episciences_Submit::CSRF_TOKEN_ELEMENT_NAME] as $name) {
            $form->addElement('text', $name);
        }

        $controller = $this->controller = new \SubmitController($request, new Zend_Controller_Response_HttpTestCase());
        $method = new ReflectionMethod($controller, 'handleSubmitPaper');
        $method->setAccessible(true);
        $method->invoke($controller, $request, $form, new Episciences_Submit(), $post);

        return $form;
    }

    /**
     * @return array<string, array{array<string, string>, ?string}>
     */
    public static function rejectedRequestProvider(): array
    {
        return [
            'no token' => [['title' => 'T', 'xml' => '<x/>'], null],
            'empty token' => [['title' => 'T', 'xml' => '<x/>', 'csrf_token' => ''], null],
            'wrong token' => [['title' => 'T', 'xml' => '<x/>', 'csrf_token' => 'forged'], null],
            'wrong header token' => [['title' => 'T', 'xml' => '<x/>'], 'forged'],
        ];
    }

    /**
     * @param array<string, string> $post
     * @dataProvider rejectedRequestProvider
     */
    public function testRequestWithoutTheSessionTokenIsRefusedWithoutSideEffect(array $post, ?string $headerToken): void
    {
        Episciences_Csrf_Helper::getSessionToken(); // the session holds a token that the request does not send
        $this->adapter->expects(self::never())->method('insert');
        $this->adapter->expects(self::never())->method('update');
        $this->adapter->expects(self::never())->method('delete');
        $this->adapter->expects(self::never())->method('query');

        $form = $this->submit($post, $headerToken);

        self::assertSame('T', $form->getElement('title')->getValue(), 'what the user typed is kept');
        self::assertEmpty($form->getElement('xml')->getValue(), 'the posted record is not given back');
        self::assertNotSame('forged', $form->getElement('csrf_token')->getValue(), 'the rejected token is not given back');
    }

    public function testRefusalIsReportedToTheUser(): void
    {
        // Current messages are static: start from a clean state
        (new \Zend_Controller_Action_Helper_FlashMessenger())->clearCurrentMessages(Ccsd_View_Helper_Message::MSG_ERROR);

        $this->submit(['title' => 'T']);

        $flash = $this->controller->getHelper('FlashMessenger');
        $messages = $flash->getCurrentMessages(Ccsd_View_Helper_Message::MSG_ERROR);
        self::assertCount(1, $messages, 'an error message must tell the user the form was refused');
    }
}
