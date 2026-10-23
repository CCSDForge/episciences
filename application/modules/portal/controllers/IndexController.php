<?php

class IndexController extends Zend_Controller_Action
{
    public function init(): void
    {
        Zend_Layout::getMvcInstance()->setLayout('portal');
    }

    // Homepage
    public function indexAction(): void
    {
        $this->view->lang = Zend_Registry::get('lang');
    }
}
