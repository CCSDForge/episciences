<?php


class ErrorController  extends Zend_Controller_Action
{
    public function init(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
    }
    public function indexAction(): void
    {
        $this->errorAction();
    }
    public function errorAction(): void
    {
       $this->redirect(SERVER_PROTOCOL . '://' . DOMAIN);
    }
}