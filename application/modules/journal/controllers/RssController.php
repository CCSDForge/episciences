<?php

class RssController extends Episciences_Controller_Action
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
            // Same rule as the feed: an invalid 'max' falls back to the journal setting
            if (Episciences_Rss::parseMaxEntries($params['max'] ?? null) === null) {
                $page = new Episciences_Website_Navigation_Page_Rss;
                $page->load();
                $params['max'] = $page->getNbResults();
            }
            header('Content-Type: text/xml');
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