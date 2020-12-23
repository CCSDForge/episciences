<?php


class ErrorController  extends Zend_Controller_Action
{
    public function init()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
    }
    public function indexAction()
    {
        $this->errorAction();
    }
    public function errorAction()
    {
       $this->redirect(HTTP . '://' . DOMAIN);
    }
}