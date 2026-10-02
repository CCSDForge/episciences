<?php

class RobotsController extends Zend_Controller_Action
{
    public function indexAction(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'User-agent: *' . PHP_EOL;
        echo 'Disallow: /' . PHP_EOL;
    }
}

