<?php

use Solarium\QueryType\Select\Query\Query;

class Ccsd_Search_Solr_Search extends Ccsd_Search_Solr
{

    const SOLR_DEFAULT_BOOLEAN_OPERATOR = 'AND';
    const SOLR_DEFAULT_SORT_TYPE = 'score desc';
    /**
     *
     * @var Query object
     */
    private $_query;

    /**
     *
     * @var array Paramètres de recherche récupérés
     */
    private $_rawSearchParams = [];

    /**
     *
     * @var string[] // Paramètres de recherche pour la vue
     */
    private $_parsedSearchParams = [];

    /**
     *
     * @var array // Liste des filtres pour la vue
     */
    private $_parsedFilterParams;
    /**
     *
     * @var array // Liste des filtres pour la vue
     */
    private $_filterExcludeTags;

    /**
     *
     * @var string Opérateur booléen par défaut entre les termes
     */
    private $_booleanOperator;

    /**
     * Ajoute les filtres par défaut à une URL à envoyer à solr
     *
     * @param array $defaultFilters
     * @return NULL string
     */
    public static function getDefaultFiltersAsURL($defaultFilters = null)
    {
        $filterQuery = null;
        if (is_array($defaultFilters)) {
            $filterQuery = '';
            foreach ($defaultFilters as $defaultFilterIndex => $defaultFilterToApply) {
                $filterQuery .= '&fq=' . urlencode($defaultFilterToApply);
            }
        }

        return $filterQuery;
    }

    public static function parseSolrError(Exception $e): string
    {
        switch ($e->getCode()) {
            default :
                // no break, this is fine
            case '0' :
                $message = 'Le serveur est indisponible, merci de réssayer dans quelques instants.';
                break;
            case '400' :
                if (strpos($e->getMessage(), "sort param field can't be found") !== false) {
                    $message = "Le paramètre de tri n'est pas valide";
                } elseif (strpos($e->getMessage(), "undefined field ") !== false) {
                    $message = "Vous essayez d'utiliser un champ qui n'existe pas.";
                } else {
                    $message = 'Le serveur est indisponible, merci de réssayer dans quelques instants.';
                }

                break;

        }
        return $message;
    }

    /**
     * @param null $paginatorNumberOfResultsArray
     * @param null $paginatordefaultNumberOfResults
     * @return $this
     */
    public function queryAddResultPerPage($paginatorNumberOfResultsArray = null, $paginatordefaultNumberOfResults = null)
    {
        if ($paginatorNumberOfResultsArray == null) {
            return $this;
        }

        if ($paginatordefaultNumberOfResults == null) {
            return $this;
        }
        $resultsPerPage = $this->getRawSearchParamsbyKey('rows');

        if (($resultsPerPage == null) || ($resultsPerPage == $paginatordefaultNumberOfResults)) {
            $this->setParsedSearchParamsbyKey('rows', $paginatordefaultNumberOfResults);
        } else if (in_array($resultsPerPage, $paginatorNumberOfResultsArray, true)) {
            $this->setParsedSearchParamsbyKey('rows', $resultsPerPage);
        } else {
            // valeur inacceptable
            $this->setParsedSearchParamsbyKey('rows', $paginatordefaultNumberOfResults);
        }

        return $this;
    }

    /**
     * @param null $key
     * @return mixed|null
     */
    public function getRawSearchParamsbyKey($key = null)
    {
        if (null == $key) {
            return null;
        }

        if (!is_array($this->getRawSearchParams())) {
            return null;
        }

        $rawSearchParams = $this->getRawSearchParams();

        return $rawSearchParams [$key] ?? null;
    }

    /**
     * @return array
     */
    public function getRawSearchParams()
    {
        return $this->_rawSearchParams;
    }

    /**
     * @param $_rawSearchParams
     * @return $this
     */
    public function setRawSearchParams($_rawSearchParams)
    {
        $this->_rawSearchParams = $_rawSearchParams;

        return $this;
    }

    /**
     * Ajoute ou écrase une valeur par clé au tableau des Paramètres de
     * recherche de la vue
     *
     * @param string $key
     * @param string $value
     * @return Ccsd_Search_Solr_Search
     */
    public function setParsedSearchParamsbyKey($key = null, $value = null): \Ccsd_Search_Solr_Search
    {
        if (null == $key) {
            return $this;
        }
        if (null == $value) {
            $parsedSearchParams = $this->getParsedSearchParams();

            unset($parsedSearchParams [$key]);

            $this->setParsedSearchParams($parsedSearchParams);
            return $this;
        }

        $parsedSearchParams = $this->getParsedSearchParams();

        $parsedSearchParams [$key] = $value;

        $this->setParsedSearchParams($parsedSearchParams);
        return $this;
    }

    /**
     * @return string[]
     */
    public function getParsedSearchParams()
    {
        return $this->_parsedSearchParams;
    }

    /**
     * @param array $_parsedSearchParams
     * @return $this
     */
    public function setParsedSearchParams(array $_parsedSearchParams)
    {
        $this->_parsedSearchParams = $_parsedSearchParams;
        return $this;
    }

    /**
     * @param null $facetsArr
     * @return $this
     */
    public function queryAddFacets($facetsArr = null)
    {
        if ($facetsArr == null) {
            return $this;
        }

        $query = $this->getQuery();
        $excludeTags = $this->getFilterExcludeTags();

        if (is_array($facetsArr)) {

            // @see
            // https://wiki.apache.org/solr/SimpleFacetParameters#facet.threads
            $query->addParam('facet.threads', count($facetsArr));

            $facetSet = $query->getFacetSet();
            foreach ($facetsArr as $facet) {

                $fc = $facetSet->createFacetField($facet ['fieldName'])->setField($facet ['fieldName'])->setLimit($facet ['maxReturned'])->setMincount($facet ['minCount']);

                if ($facet ['sort'] === 'index') {
                    $fc->setSort('index');
                }

                if (is_array($excludeTags)) {
                    foreach ($excludeTags as $tag) {
                        // le tag contient le nom du paramètre utilisé
                        // dans
                        // l'URL
                        $tagAsArray = explode('__', $tag);
                        if ($tagAsArray [1] == $facet ['fieldName']) {
                            $fc->addExclude($tag);
                            /**
                             * Pour créer la facette, exclue un filtre en le
                             * mentionnant par son tag correspondant
                             * le tag doit être défini avec addTag()
                             */
                        }
                    }
                }
            }
        }

        return $this;
    }

    /**
     * @return Query
     */
    public function getQuery()
    {
        return $this->_query;
    }

    /**
     * @param $_query
     * @return $this
     */
    public function setQuery($_query)
    {
        $this->_query = $_query;
        return $this;
    }

    /**
     * @return array
     */
    public function getFilterExcludeTags()
    {
        return $this->_filterExcludeTags;
    }

    /**
     * @param null $_filterExcludeTags
     * @return $this
     */
    public function setFilterExcludeTags($_filterExcludeTags = null)
    {
        $this->_filterExcludeTags = $_filterExcludeTags;
        return $this;
    }

    /**
     * Filtres de recherche : mise en place des filtres utilisateur
     * correspondance entre le nom du champ
     * passé dans l'URL et son nom dans l'index solr
     *
     * @return $this
     * @see solr.json
     */
    public function queryAddFilters()
    {

        $filterParams = [];
        $parsedSearchParams = '';
        $query = $this->getQuery();

        $helper = $query->getHelper();
        $indexOfArray = 0;

        $searchParams = $this->getRawSearchParams();

        unset($searchParams ['q']);
        unset($searchParams ['qa']);
        unset($searchParams ['controller']);
        unset($searchParams ['action']);
        unset($searchParams ['module']);
        unset($searchParams ['rows']);
        unset($searchParams ['page']);
        unset($searchParams ['lang']);
        unset($searchParams ['sort']);
        unset($searchParams ['submit']);
        unset($searchParams ['tampid']);
        unset($searchParams ['_module']);
        unset($searchParams ['submit_advanced']);

        foreach ($searchParams as $solrFieldName => $filterValue) {

            $filterParams [$solrFieldName] = explode(' OR ', $searchParams [$solrFieldName]);

            $filterValue = $this->getRawSearchParamsbyKey($solrFieldName);

            if ($filterValue != null) {

                $this->setParsedSearchParamsbyKey($solrFieldName, $filterValue);

                // ajout des filtres de recherche successifs

                $filterValue = trim($filterValue, '"');

                $nameOfTagFilter = 'tag' . $indexOfArray . '__' . $solrFieldName;

                // créé le tableau de tags à exclure
                $excludeTags [] = $nameOfTagFilter;

                // filtre pour une recherche exacte avec un champ type string
                //permet un filtre de ce type authFullName_s:("Michèle Soria" OR "Alexis Darrasse" OR "Olivier Roussel")
                if (substr($solrFieldName, -2, 2) === '_s') {
                    $filterValueArray = explode(" OR ", $filterValue);
                    $filterValueArray = array_map(static function ($v) use ($helper) {
                        // Escape the term and put it into quote
                        return '"' . $helper->escapeTerm($v) . '"';
                    }, $filterValueArray);
                    $filterValue = implode(" OR ", $filterValueArray);
                } elseif ($solrFieldName !== 'publication_date_year_fs') {
                    $filterValue = $helper->escapeTerm($filterValue);
                }

                $query->createFilterQuery($nameOfTagFilter)->setQuery($solrFieldName . ':(' . $filterValue . ')')->addTag($nameOfTagFilter);

                /**
                 * addTag ajoute un tag qui doit être unique, il
                 * sert quand on génère les facettes pour
                 * exclure le filtre de la création des facettes
                 * un tag par filtre
                 *
                 * @see https://wiki.apache.org/solr/SimpleFacetParameters#Multi-Select_Faceting_and_LocalParams
                 */
                $indexOfArray++;
                $this->setFilterExcludeTags($excludeTags);
            }
        }

        /**
         * Filtres de recherche : mise en place des filtres utilisateur
         * //
         */
        if (is_array($parsedSearchParams)) {
            $this->setParsedSearchParams(array_merge($this->getParsedSearchParams(), $parsedSearchParams));
        }

        /**
         * Filtres de recherche suite : récupération des filtres en
         * cours pour réaffichage
         */
        $this->setParsedFilterParams($filterParams);

        /**
         * Filtres de recherche suite : récupération des filtres en
         * cours pour réaffichage //
         */
        return $this;
    }

    /**
     * @param null $defaultFilters
     * @return $this
     */
    public function queryAddDefaultFilters($defaultFilters = null)
    {
        $query = $this->getQuery();

        if (($defaultFilters != null) && is_array($defaultFilters)) {

            foreach ($defaultFilters as $defaultFilterIndex => $defaultFilterToApply) {
                $query->createFilterQuery('df' . $defaultFilterIndex)->setQuery($defaultFilterToApply);
            }
        }

        // cas d'une collection
        if ((defined('MODULE')) && defined('SPACE_COLLECTION') && defined('SPACE_NAME')) {

            if (MODULE == SPACE_COLLECTION) {
                $query->createFilterQuery('df' . SPACE_NAME)->setQuery('collCode_s:' . strtoupper(SPACE_NAME));
            } else {
                // portail
                $query->createFilterQuery('df_status')->setQuery('NOT status_i:111');
            }
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function queryAddSort()
    {
        $query = $this->getQuery();

        $sortValue = $this->getRawSearchParamsbyKey('sort');

        if (null == $sortValue) {
            // Tri par défaut = score desc = pas de tri
            return $this;
        }

        // le sens de tri est obligatoire
        $sortValuesArray = explode(' ', $this->getRawSearchParamsbyKey('sort'));
        $solrSortFieldName = htmlspecialchars($sortValuesArray [0]);

        $this->setParsedSearchParamsbyKey('sort', $this->getRawSearchParamsbyKey('sort'));


        if (!isset($sortValuesArray [1])) {
            $query->addSort($solrSortFieldName, $query::SORT_ASC);
            return $this;
        }


        switch ($sortValuesArray [1]) {
            case 'asc' :
            default :
                $query->addSort($solrSortFieldName, $query::SORT_ASC);

                break;
            case 'desc' :
                $query->addSort($solrSortFieldName, $query::SORT_DESC);
                break;
        }

        return $this;
    }

    /**
     * Retourne une clé du tableau des Paramètres de recherche de la vue
     *
     * @param string $key
     * @return string
     */
    public function getParsedSearchParamsbyKey($key = null)
    {
        if (null == $key) {
            return null;
        }

        if (!is_array($this->getParsedSearchParams())) {
            return null;
        }

        $parsedSearchParams = $this->getParsedSearchParams();
        return $parsedSearchParams [$key] ?? null;
    }

    /**
     * Ajoute ou écrase une valeur par clé au tableau des Paramètres de
     * recherche récupérés
     *
     * @param string $key
     * @param string $value
     * @return Ccsd_Search_Solr_Search
     */
    public function setRawSearchParamsbyKey($key = null, $value = null)
    {
        if (null == $key) {
            return $this;
        }
        if (null == $value) {
            return $this;
        }

        $rawSearchParams = $this->getRawSearchParams();

        $rawSearchParams [$key] = $value;

        $this->setRawSearchParams($rawSearchParams);
        return $this;
    }

    /**
     * @return array
     */
    public function getParsedFilterParams()
    {
        return $this->_parsedFilterParams;
    }

    /**
     * @param null $_parsedFilterParams
     * @return $this
     */
    public function setParsedFilterParams($_parsedFilterParams = null)
    {
        $this->_parsedFilterParams = $_parsedFilterParams;
        return $this;
    }

    /**
     * @return string
     */
    public function getBooleanOperator()
    {
        return $this->_booleanOperator;
    }

    /**
     * @param null $_booleanOperator
     * @return $this
     */
    public function setBooleanOperator($_booleanOperator = null)
    {
        if (($_booleanOperator !== 'AND') && ($_booleanOperator !== 'OR')) {
            $this->_booleanOperator = self::SOLR_DEFAULT_BOOLEAN_OPERATOR;
        } else {
            $this->_booleanOperator = $_booleanOperator;
        }

        $this->setParsedSearchParamsbyKey('op', $_booleanOperator);
        return $this;
    }

}

//End class

