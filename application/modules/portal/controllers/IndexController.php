<?php

class IndexController extends Episciences_Controller_Action
{

    // Homepage
    public function indexAction(): void
    {
        $this->view->controller = 'index';
        $this->forward('index', 'page');
    }


}