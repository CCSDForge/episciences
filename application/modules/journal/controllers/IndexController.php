<?php


class IndexController extends Zend_Controller_Action
{
    use Episciences\Notify\Headers;

    /**
     * Page d'accueil du site
     */
    public function indexAction()
    {
        $this->addInboxAutodiscoveryHeader();

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
        $this->getResponse()?->setHttpResponseCode(404);
    }
}
