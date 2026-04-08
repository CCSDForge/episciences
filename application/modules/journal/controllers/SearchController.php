<?php

use Solarium\QueryType\Select\Result\Result;

/**
 * Class SearchController
 */
class SearchController extends Episciences_Controller_Action
{

    /**
     * @return bool|void
     * @throws Zend_Config_Exception
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     * @throws Zend_Paginator_Exception
     */
    public function indexAction()
    {

        $q = null;

        $form = new Ccsd_Search_Solr_Form_Search();
        $search = new Ccsd_Search_Solr_Search();

        $this->view->paginatorNumberOfResultsArray = Episciences_Settings_Search::$_numberSearchResultsArray;
        $this->view->paginatordefaultNumberOfResults = Episciences_Settings_Search::DEFAULT_NUMBER_SEARCH_RESULTS;

        /**
         * traitement formulaires
         */

        if (($this->getRequest()->getParam('q') === null)) {
            $this->view->searchType = 'simple';
            return false;
        }


        if ($form->isValid($this->getRequest()->getParams())) {

            $this->view->searchType = 'simple';
            $q = $form->getValue('q');

            // recherche simple
            $search->setRawSearchParams($this->getRequest()->getParams());
            $search->setParsedSearchParamsbyKey('q', $q);
        }

        if ($q === null) {
            return false;
        }

        $currentPage = $this->_getParam('page', 1);
        $startParam = ($currentPage - 1) * $search->getParsedSearchParamsbyKey('rows');

        // create a client instance
        $client = Ccsd_Search_Solr::getSolrSearchClient();

        // get a select query instance
        $query = $client->createSelect()
            ->setOmitHeader(true)
            ->setResponseWriter('phps')
            ->setQuery($q)
            ->setStart($startParam)
            ->setFields(Episciences_Settings::getConfigFile('solr.es.returnedFields.json'));

        if ($search->getParsedSearchParamsbyKey('rows') !== null) {
            $query->setRows($search->getParsedSearchParamsbyKey('rows'));
        }

        if (RVID && RVID !== 0) {
            $query->createFilterQuery('df' . RVID)->setQuery('revue_id_i:' . RVID);
        }


        $search->setQuery($query);

        // get the dismax query parser
        $query->getDisMax()->setQueryParser('edismax');

        $search->setParsedSearchParamsbyKey('controller', 'search')->setParsedSearchParamsbyKey('action', 'index');


        // recherche simple
        $search->setRawSearchParams($this->getRequest()
            ->getParams());

        $search->setParsedSearchParamsbyKey('q', $q);

        $search->queryAddSort()
            ->queryAddDefaultFilters(Episciences_Settings::getConfigFile('solr.es.defaultFilters.json'))
            ->queryAddFilters();

        $query = $search->getQuery();
        $client->createRequest($query);

        $search->setQuery($query);
        $search->queryAddFacets(Episciences_Settings::getConfigFile('solr.es.facets.json'))
            ->queryAddResultPerPage(Episciences_Settings_Search::$_numberSearchResultsArray, Episciences_Settings_Search::DEFAULT_NUMBER_SEARCH_RESULTS);
        $query = $search->getQuery();

        /**
         * Ajout des filtres en cours au formulaire de recherche
         */

        $parsedSearchParams = $search->getParsedSearchParams();
        if (is_array($parsedSearchParams)) {
            unset($parsedSearchParams['controller'], $parsedSearchParams['action'], $parsedSearchParams['q']);
            foreach ($parsedSearchParams as $elementName => $elementValue) {
                $form->addElement('hidden', $elementName);
            }
        }


        $this->view->parsedSearchParams = $search->getParsedSearchParams();
        $this->view->activeFilters = $search->getParsedFilterParams();
        $this->view->paginatorNumberOfResultsPerPage = $search->getParsedSearchParamsbyKey('rows');

        try {
            $adapter = new Ccsd_Paginator_Adapter_Solarium($client, $query);
            $paginator = new Zend_Paginator($adapter);
            $paginator->setItemCountPerPage($this->view->paginatorNumberOfResultsPerPage);
            $paginator->setCurrentPageNumber($currentPage);
        } catch (Solarium\Exception\HttpException $e) {
            echo '<div class="alert alert-block alert-error fade in">';
            echo 'Le serveur est indisponible, merci de réssayer dans quelques instants';
            echo '</div>';
            return;

        }


        $this->view->paginator = $paginator;
        Zend_Paginator::setDefaultScrollingStyle(Episciences_Settings_Search::PAGINATOR_STYLE);
        Zend_View_Helper_PaginationControl::setDefaultViewPartial('partial/search_pagination.phtml');


        try {
            /** @var  Result $resultset */
            $resultset = $paginator->getCurrentItems();
        } catch (Exception $e) {

            error_log($e->getMessage());

            $message = Ccsd_Search_Solr_Search::parseSolrError($e);
            $message = $this->view->translate($message);
            $newSearchUrl = $this->view->url(['controller' => 'search']);

            $newSearch = '<li><a class="btn btn-default btn-xs" href="' . $this->view->escape($newSearchUrl) . '"><span class="glyphicon glyphicon-remove"></span>'
                . $this->view->translate('Nouvelle recherche') . '</a></li>';

            $reTrySearchUrl = $this->view->url($parsedSearchParams);
            $reTrySearch = '<li><a class="btn btn-default btn-xs" href="' . $this->view->escape($reTrySearchUrl) . '"><span class="glyphicon glyphicon-refresh"></span>'
                . $this->view->translate('Recommencer la Recherche') . '</a></li>';

            $message .= '<ul>';

            $message .= $reTrySearch;
            $message .= $newSearch;

            $message .= '</ul>';

            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($message);

            return;
        }

        if ($resultset !== null) {

            /**
             * Facets
             *
             * @see solr.es.json
             */
            $allFacetsArray = [];
            $facetsArr = Episciences_Settings::getConfigFile('solr.es.facets.json');
            if (is_array($facetsArr)) {
                $indexOfArray = 0;
                foreach ($facetsArr as $facet) {
                    $allFacetsArray[$indexOfArray]['fieldName'] = $facet['fieldName'];
                    $allFacetsArray[$indexOfArray]['displayName'] = $facet['displayName'];
                    $allFacetsArray[$indexOfArray]['searchMapping'] = $facet['searchMapping'];
                    $allFacetsArray[$indexOfArray]['hasSepInValue'] = $facet['hasSepInValue'];
                    $allFacetsArray[$indexOfArray]['translated'] = $facet['translated'];
                    $allFacetsArray[$indexOfArray]['translationPrefix'] = $facet['translationPrefix'];
                    $allFacetsArray[$indexOfArray]['values'] = $resultset->getFacetSet()
                        ->getFacet($facet['fieldName'])
                        ->getValues();

                    $indexOfArray++;
                }
                unset($indexOfArray);
            }

            $this->view->facetsArray = $allFacetsArray;
            $this->view->numFound = $resultset->getNumFound();
            $this->view->results = $resultset;

        }
    }


}