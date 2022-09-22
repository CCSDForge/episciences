<?php
use Episciences\Notify\Headers;

class BrowseController extends Zend_Controller_Action
{
    public const JSON_MIMETYPE = 'application/json';


    /**
     * @return void
     * @throws Zend_Config_Exception
     */
    public function init()
    {
        Headers::addInboxAutodiscoveryHeader();

        if ($this->getFrontController()->getRequest()->getHeader('Accept') === Episciences_Settings::MIME_LD_JSON) {
            $this->_helper->layout()->disableLayout();
            $this->_helper->viewRenderer->setNoRender();
            echo Headers::addInboxAutodiscoveryLDN();
            exit;
        }


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

        if (!in_array($letter, array_merge(['all', 'other'], range('A', 'Z')), true)) {
            $letter = 'all';
        }

        $facets = [];
        $authors = Episciences_Search_Solr_Search::getFacet('author_fullname_fs', $letter, $sortType, 10000);

        foreach ($authors as $name => $count) {
            $facets[mb_strtolower($name)] = ['name' => $name, 'count' => $count];
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
        $search = new Ccsd_Search_Solr_Search();
        $search->setQuery($query);
        $search->queryAddDefaultFilters(Episciences_Settings::getConfigFile('solr.es.defaultFilters.json'));

        $query = $search->getQuery();
        $query->setRows(0);

        if (RVID && RVID != 0) {
            $query->createFilterQuery('df' . RVID)->setQuery('revue_id_i:' . RVID);
        }

        $facetSet = $query->getFacetSet();
        $facetSet->createFacetField('year')->setField('publication_date_year_fs');
        $facetSet->setMinCount(1);
        $facetSet->setSort('index');

        $resultset = $client->select($query);
        $facet = $resultset->getFacetSet()->getFacet('year');


        $viewYear = [];
        foreach ($facet as $value => $count) {
            $viewYear[$value] = $count;
        }


        krsort($viewYear);

        $this->view->yearsArray = $viewYear;


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

        if ($this->getFrontController()->getRequest()->getHeader('Accept') === self::JSON_MIMETYPE) {
            $this->volumesOrSectionsToJson($sections, 'Episciences_Section');
            return;
        }

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

        if ($this->getFrontController()->getRequest()->getHeader('Accept') === self::JSON_MIMETYPE) {
            $this->volumesOrSectionsToJson($volumes, 'Episciences_Volume');
            return;
        }

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

        if ($this->getFrontController()->getRequest()->getHeader('Accept') === self::JSON_MIMETYPE) {
            $this->volumesOrSectionsToJson($volumes, 'Episciences_Volume');
            return;
        }

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


        if ($this->getFrontController()->getRequest()->getHeader('Accept') === self::JSON_MIMETYPE) {
            $this->volumesOrSectionsToJson($volumes, 'Episciences_Volume');
            return;
        }


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
            $this->view->articles = unserialize($res, ['allowed_classes' => false]);
        }
    }


    public function currentissuesAction()
    {
        $review = Episciences_ReviewsManager::find(RVID);
        $volumes = $review->getCurrentIssues();

        if ($this->getFrontController()->getRequest()->getHeader('Accept') === self::JSON_MIMETYPE) {
            $this->volumesOrSectionsToJson($volumes, 'Episciences_Volume');
            return;
        }

        /** @var Episciences_Volume $volume */
        foreach ($volumes as &$volume) {
            $volume->loadIndexedPapers();
        }
        $this->view->volumes = $volumes;
        $this->renderScript('browse/volumes.phtml');
    }

    /**
     * @param array $volumesOrSections
     * @param string $objectType
     * @return void
     * @throws Zend_Exception
     */
    protected function volumesOrSectionsToJson(array $volumesOrSections, string $objectType): void
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $arrayOfVolumesOrSections = Episciences_Volume::volumesOrSectionsToPublicArray($volumesOrSections, $objectType);
        $this->getResponse()->setHeader('Content-type', self::JSON_MIMETYPE);
        $this->getResponse()->setBody(json_encode($arrayOfVolumesOrSections));
    }

    public function volumesdoajAction()
    {
        $review = Episciences_ReviewsManager::find(RVID);
        $allVolumeOnReview = $review->getVolumes();
    }
}
