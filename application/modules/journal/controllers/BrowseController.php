<?php

class BrowseController extends Zend_Controller_Action
{

    public function init()
    {
        $solrConfigFile = APPLICATION_PATH . '/../data/' . RVCODE . '/config/solr.json';

        if (is_file($solrConfigFile)) {
            $configSolr = new Zend_Config_Json($solrConfigFile);
        } else {
            $configSolr = new Zend_Config_Json(APPLICATION_PATH . '/../data/default/config/solr.json');
        }

        Zend_Registry::set('solr.es', $configSolr);
    }

    /**
     * Browse by authors
     */
    public function authorAction()
    {
        $letter = $this->_getParam('letter', 'all');
        $sortType = $this->_getParam('sort', 'index');

        if (!in_array($letter, array_merge(['all', 'other'], range('A', 'Z')))) {
            $letter = 'all';
        }

        $facets = [];
        $authors = Episciences_Search_Solr_Search::getFacet('author_fullname_s', $letter, $sortType, 10000);
        foreach ($authors as $name => $count) {
            $facets[strtolower($name)] = ['name' => $name, 'count' => $count];
        }

        $this->view->urlFilterName = 'author_fullname_t';
        $this->view->facets = $facets;
    }

    /**
     * Browse by date
     * @throws Zend_Exception
     */
    public function dateAction()
    {
        $client = Ccsd_Search_Solr::getSolrSearchClient();
        $query = $client->createSelect()->setOmitHeader(true);
        $query->setRows(0);

        $search = new Ccsd_Search_Solr_Search();
        $search->setQuery($query);
        $search->queryAddDefaultFilters(Episciences_Settings::getConfigFile('solr.es.defaultFilters.json'));

        $query = $search->getQuery();

        // filtre les résultats en fonction du RVID
        if (RVID && RVID != 0) {
            $query->createFilterQuery('df' . RVID)->setQuery('revue_id_i:' . RVID);
        }

        $this->view->rangeOffset = 10;
        $this->view->rangeFirstYear = 1800;
        $this->view->rangeLastYear = date('Y');

        $facetSet = $query->getFacetSet();
        $facet = $facetSet->createFacetRange('year');
        $facet->setField('publication_date_year_fs');
        $facet->setStart($this->view->rangeFirstYear);
        $facet->setGap($this->view->rangeOffset);
        $facet->setEnd($this->view->rangeLastYear);
        $facet->setHardend(false);
        $facet->setOther('all');

        $resultset = $client->select($query);

        $results['yearArray'] = $resultset->getFacetSet()->getFacet('year');
        $results['yearsBefore'] = $resultset->getFacetSet()
            ->getFacet('year')
            ->getBefore();
        $results['yearsAfter'] = $resultset->getFacetSet()
            ->getFacet('year')
            ->getAfter();
        $results['yearsBetween'] = $resultset->getFacetSet()
            ->getFacet('year')
            ->getBetween();


        $this->view->yearArray = $results['yearArray'];
        $this->view->yearsBefore = $results['yearsBefore'];
        $this->view->yearsAfter = $results['yearsAfter'];
        $this->view->yearsBetween = $results['yearsBetween'];

    }

    /**
     * Browse by section, count published papers by sections and list their editors
     */
    public function sectionAction()
    {
        $review = Episciences_ReviewsManager::find(RVID);
        $page = new Episciences_Website_Navigation_Page_BrowseByVolume();
        $page->load();

        $pageNb = (is_numeric($this->getRequest()->getParam('page'))) ? $this->getRequest()->getParam('page') : 1;
        $limit = $page->getNbResults();
        if (!is_numeric($limit)) $limit = 10;
        $offset = ($pageNb - 1) * $limit;
        $total = count($review->getSectionsWithPapers());

        $sections = $review->getSectionsWithPapers([$limit, $offset]);

        /** @var $section Episciences_Section */
        foreach ($sections as &$section) {
            $section->countIndexedPapers();
        }


        $this->view->sections = $sections;
        $this->view->page = $pageNb;
        $this->view->limit = $limit;
        $this->view->offset = $offset;
        $this->view->total = $total;

    }

    public function volumesAction()
    {
        $review = Episciences_ReviewsManager::find(RVID);
        $page = new Episciences_Website_Navigation_Page_BrowseByVolume();
        $page->load();

        $pageNb = (is_numeric($this->getRequest()->getParam('page'))) ? $this->getRequest()->getParam('page') : 1;
        $limit = $page->getNbResults();
        if (!is_numeric($limit)) {
            $limit = 10;
        }
        $offset = ($pageNb - 1) * $limit;
        $total = count($review->getVolumesWithPapers());
        $volumes = $review->getVolumesWithPapers([$limit, $offset]);

        /** @var Episciences_Volume $volume */
        foreach ($volumes as &$volume) {
            $volume->loadIndexedPapers();
        }

        $this->view->volumes = $volumes;
        $this->view->page = $pageNb;
        $this->view->limit = $limit;
        $this->view->offset = $offset;
        $this->view->total = $total;
    }

    public function specialissuesAction()
    {
        $review = Episciences_ReviewsManager::find(RVID);
        $page = new Episciences_Website_Navigation_Page_BrowseSpecialIssues();
        $page->load();

        $pageNb = (is_numeric($this->getRequest()->getParam('page'))) ? $this->getRequest()->getParam('page') : 1;
        $limit = $page->getNbResults();
        if (!is_numeric($limit)) {
            $limit = 10;
        }
        $offset = ($pageNb - 1) * $limit;
        $total = count($review->getSpecialIssuesWithPapers());
        $volumes = $review->getSpecialIssuesWithPapers([$limit, $offset]);

        /** @var Episciences_Volume $volume */
        foreach ($volumes as &$volume) {
            $volume->loadIndexedPapers();
        }

        $this->view->volumes = $volumes;
        $this->view->page = $pageNb;
        $this->view->limit = $limit;
        $this->view->offset = $offset;
        $this->view->total = $total;

        $this->renderScript('browse/volumes.phtml');
    }

    public function regularissuesAction()
    {
        $review = Episciences_ReviewsManager::find(RVID);
        $page = new Episciences_Website_Navigation_Page_BrowseRegularIssues();
        $page->load();

        $pageNb = (is_numeric($this->getRequest()->getParam('page'))) ? $this->getRequest()->getParam('page') : 1;
        $limit = $page->getNbResults();
        if (!is_numeric($limit)) {
            $limit = 10;
        }
        $offset = ($pageNb - 1) * $limit;
        $total = count($review->getRegularIssuesWithPapers());
        $volumes = $review->getRegularIssuesWithPapers([$limit, $offset]);

        /** @var Episciences_Volume $volume */
        foreach ($volumes as &$volume) {
            $volume->loadIndexedPapers();
        }

        $this->view->volumes = $volumes;
        $this->view->page = $pageNb;
        $this->view->limit = $limit;
        $this->view->offset = $offset;
        $this->view->total = $total;

        $this->renderScript('browse/volumes.phtml');
    }

    public function latestAction()
    {
        $page = new Episciences_Website_Navigation_Page_BrowseLatest();
        $page->load();

        $limit = $page->getNbResults();
        if (!is_numeric($limit)) {
            $limit = 10;
        }

        $query = 'q=*%3A*';
        $query .= '&sort=publication_date_tdate+desc&rows=' . $limit . '&wt=phps&omitHeader=true';

        // filtre les résultats en fonction du RVID
        if (RVID && RVID != 0) {
            $query .= '&fq=revue_id_i:' . RVID;
        }

        $res = Episciences_Tools::solrCurl($query, 'episciences', 'select', true);
        if ($res) {
            $this->view->articles = unserialize($res);
        }
    }


    public function currentissuesAction()
    {
        $review = Episciences_ReviewsManager::find(RVID);
        $volumes = $review->getCurrentIssues();

        /** @var Episciences_Volume $volume */
        foreach ($volumes as &$volume) {
            $volume->loadIndexedPapers();
        }
        $this->view->volumes = $volumes;
        $this->renderScript('browse/volumes.phtml');
    }

}
