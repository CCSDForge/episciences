<?php

class IndexController extends Zend_Controller_Action
{

    /**
     * Page d'accueil du site
     */
    public function indexAction()
    {
        $headerLinks[] = sprintf('Link: <%s>; rel="http://www.w3.org/ns/ldp#inbox"', INBOX_URL);
        header(implode(', ', $headerLinks));

        if ($this->getFrontController()->getRequest()->getHeader('Accept') === 'application/ld+json') {
            $this->_helper->layout()->disableLayout();
            $this->_helper->viewRenderer->setNoRender();
            $ldJson['@context'] = "http://www.w3.org/ns/ldp";
            $ldJson['inbox'] = INBOX_URL;
            echo json_encode($ldJson);
            exit;
        }
        $this->view->controller = 'index';
        $this->forward('index', 'page');
    }

    public function notfoundAction()
    {
    }
}
