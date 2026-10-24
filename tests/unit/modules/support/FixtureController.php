<?php

declare(strict_types=1);

namespace unit\modules\support;

use Zend_Controller_Action;

/**
 * Controller whose actions exercise ControllerTestCase: each one echoes what it did after its
 * redirection or JSON answer, which a stopped action never reaches.
 */
final class FixtureController extends Zend_Controller_Action
{
    public function redirectAction(): void
    {
        $this->_helper->redirector->gotoUrl('/volume/list');
        echo 'after redirect';
    }

    public function jsonAction(): void
    {
        $this->_helper->json(['status' => 1]);
        echo 'after json';
    }

    public function echoAction(): void
    {
        $this->getResponse()->setHttpResponseCode(202)->setBody('body');
        echo 'echoed ' . $this->getRequest()->getActionName();
    }
}
