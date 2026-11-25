<?php

declare(strict_types=1);

namespace unit\library\Episciences\Csrf;

use Episciences_Csrf_Helper;
use PHPUnit\Framework\TestCase;
use Zend_Controller_Request_HttpTestCase;
use Zend_Form;
use Zend_Session;
use Zend_Session_Namespace;

/**
 * Behavioural tests of the hidden session token field added to forms.
 *
 * @covers Episciences_Csrf_Helper::addSessionTokenElement
 */
class Episciences_Csrf_SessionTokenElementTest extends TestCase
{
    protected function setUp(): void
    {
        Zend_Session::$_unitTestEnabled = true;
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->csrfToken);
    }

    protected function tearDown(): void
    {
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
        unset($session->csrfToken);
    }

    private function buildForm(): Zend_Form
    {
        $form = new Zend_Form();
        $form->addElement('text', 'title');
        Episciences_Csrf_Helper::addSessionTokenElement($form);

        return $form;
    }

    public function testFieldCarriesTheSessionToken(): void
    {
        $form = $this->buildForm();

        $this->assertSame(Episciences_Csrf_Helper::getSessionToken(), $form->getElement('csrf_token')->getValue());
    }

    public function testFieldIsRenderedAsHiddenInput(): void
    {
        $html = $this->buildForm()->getElement('csrf_token')->render(new \Zend_View());

        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('name="csrf_token"', $html);
        $this->assertStringContainsString(Episciences_Csrf_Helper::getSessionToken(), $html);
    }

    public function testFieldIsNotPartOfTheSubmittedValues(): void
    {
        $form = $this->buildForm();
        $form->isValid(['title' => 'abc', 'csrf_token' => Episciences_Csrf_Helper::getSessionToken()]);

        $this->assertSame(['title' => 'abc'], $form->getValues());
    }

    public function testRenderedTokenIsAcceptedByRequestValidation(): void
    {
        $token = $this->buildForm()->getElement('csrf_token')->getValue();

        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST');
        $request->setPost(['csrf_token' => $token]);

        $this->assertTrue(Episciences_Csrf_Helper::validateRequestToken($request));
    }

    public function testRequestWithoutTokenIsRejected(): void
    {
        $this->buildForm();

        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST');
        $request->setPost(['title' => 'abc']);

        $this->assertFalse(Episciences_Csrf_Helper::validateRequestToken($request));
    }
}
