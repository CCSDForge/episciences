<?php

class RssController extends Zend_Controller_Action
{
    /**
     * @throws Zend_Db_Select_Exception
     * @throws Zend_Exception
     * @throws Zend_Feed_Exception
     */
    public function init(): void
    {
        $params = $this->getRequest()->getParams();

        if (($params['action'] === 'papers' || $params['action'] === 'news')) {
            $this->_helper->layout()->disableLayout();
            $this->_helper->viewRenderer->setNoRender();
            if (!array_key_exists('max', $params) || !is_numeric($params['max'])) {
                $page = new Episciences_Website_Navigation_Page_Rss;
                $page->load();
                $params['max'] = $page->getNbResults();
            }
            new Episciences_Rss($params);
        }

    }

    public function indexAction(): void
    {
        // view here
    }

    public function papersAction(): void
    {
        // render feed here
    }

    public function newsAction(): void
    {
        // render feed here
    }
}