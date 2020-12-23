<?php

class IndexController extends Zend_Controller_Action
{

    /**
     * Page d'accueil du site
     */
    public function indexAction()
    {
        $this->view->controller = 'index';
        $this->forward('index', 'page');
    }

    public function notfoundAction()
    {
    }
}
