<?php

class IndexController extends Zend_Controller_Action
{

    public function init(): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
    }

    public function indexAction()
    {
        return new Episciences_Oai_Server($this->getRequest());
    }

    public function xslAction(): void
    {
        header('Content-Type: text/xml; charset=UTF-8');
        ob_clean();
        flush();
        echo Episciences_Oai_Server::getXsl();
    }

}

