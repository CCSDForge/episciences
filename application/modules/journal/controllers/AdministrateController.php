<?php

class AdministrateController extends Episciences_Controller_Action
{
    public function indexAction()
    {
        $this->renderScript('index/submenu.phtml');
    }

    public function reviewAction()
    {
        $this->renderScript('index/submenu.phtml');
    }

    public function usersAction()
    {
        $this->renderScript('index/submenu.phtml');
    }
}