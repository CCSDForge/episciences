<?php

class RightsController extends Episciences_Controller_Action
{
    public function indexAction()
    {
        $this->renderScript('index/submenu.phtml');
    }

}