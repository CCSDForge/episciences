<?php

class IndexController extends Zend_Controller_Action
{

    // Homepage
    public function indexAction(): void
    {
        $this->view->controller = 'index';
        $this->forward('index', 'page');
    }


}