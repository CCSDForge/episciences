<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Behavioural test for DefaultController::renderFormErrors().
 *
 * Validation messages can contain the submitted value and are placed in an HTML
 * flash message: they must be escaped. The controller is instantiated without its
 * constructor, with a plain Zend_View and a recording flash messenger.
 */
final class DefaultControllerFormErrorsTest extends TestCase
{
    private object $controller;

    /** @var \ArrayObject<int, string> */
    private \ArrayObject $flashMessages;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/common/controllers/DefaultController.php';

        $this->flashMessages = new \ArrayObject();

        $flashMessenger = new class($this->flashMessages) {
            /** @param \ArrayObject<int, string> $messages */
            public function __construct(private readonly \ArrayObject $messages)
            {
            }

            public function setNamespace(string $namespace): self
            {
                return $this;
            }

            public function addMessage(string $message): self
            {
                $this->messages->append($message);
                return $this;
            }
        };

        $helper = new \stdClass();
        $helper->FlashMessenger = $flashMessenger;

        $this->controller = (new ReflectionClass(\DefaultController::class))->newInstanceWithoutConstructor();
        $this->controller->view = new \Zend_View();

        $property = new ReflectionProperty(\Zend_Controller_Action::class, '_helper');
        $property->setAccessible(true);
        $property->setValue($this->controller, $helper);
    }

    private function render(?\Zend_Form $form): void
    {
        $method = new \ReflectionMethod(\DefaultController::class, 'renderFormErrors');
        $method->setAccessible(true);
        $method->invoke($this->controller, $form);
    }

    private function formWithError(string $error): \Zend_Form
    {
        $form = new \Zend_Form();
        $element = new \Zend_Form_Element_Text('title');
        $element->addError($error);
        $form->addElement($element);

        return $form;
    }

    public function testValidationMessagesAreEscaped(): void
    {
        $this->render($this->formWithError('<script>alert(1)</script> & "x"'));

        self::assertCount(1, $this->flashMessages);
        self::assertStringNotContainsString('<script', $this->flashMessages[0]);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;x&quot;', $this->flashMessages[0]);
    }

    public function testMarkupAroundTheMessagesIsKept(): void
    {
        $this->render($this->formWithError('plain error'));

        self::assertStringContainsString('<ol  type="i"><li><code>plain error</code></li></ol>', $this->flashMessages[0]);
        self::assertTrue($this->controller->view->error);
    }

    public function testNothingIsRenderedWithoutForm(): void
    {
        $this->render(null);

        self::assertCount(0, $this->flashMessages);
    }
}
