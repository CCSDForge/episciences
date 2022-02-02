<?php
use Episciences\Notify\Headers;

class IndexController extends Zend_Controller_Action
{

    /**
     * Page d'accueil du site
     */
    public function indexAction()
    {
        Headers::addInboxAutodiscoveryHeader();

        if ($this->getFrontController()->getRequest()->getHeader('Accept') === Episciences_Settings::MIME_LD_JSON) {
            $this->_helper->layout()->disableLayout();
            $this->_helper->viewRenderer->setNoRender();
            echo Headers::addInboxAutodiscoveryLDN();
            exit;
        }
        $this->view->controller = 'index';
        $this->forward('index', 'page');
    }

    public function notfoundAction()
    {
    }
}
