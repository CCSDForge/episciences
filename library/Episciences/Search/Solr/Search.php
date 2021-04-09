<?php

// Méthodes spécifiques à Episciences pour la recherche avec solr
class Episciences_Search_Solr_Search extends Ccsd_Search_Solr_Search
{
    /**
     * Liste des filtres de recherche utilisés dans une requête
     * Pour chaque filtre = param créer une url avec tous les filtres moins un à
     * supprimer
     * filtre
     *
     * @param array $activeFilters
     * @param array $parsedSearchParams
     * @return array URL du filtre
     */
    static function buildActiveFiltersUrl($activeFilters, $parsedSearchParams)
    {
        $url = [];
        $parsedSearchParamsOriginal = $parsedSearchParams;

        foreach (array_keys($activeFilters) as $filterName) {
            $parsedSearchParams = $parsedSearchParamsOriginal;
            unset($parsedSearchParams[$filterName]);
            $url[$filterName] = $parsedSearchParams;
        }

        return $url;
    }

    /**
     * @param string $facetFieldName
     * @param string $facetPrefix
     * @param string $sortType
     * @param int $facetLimit
     * @return array
     */
    static function getFacet($facetFieldName = '', $facetPrefix = 'A', $sortType = 'index', $facetLimit = Ccsd_Search_Solr::SOLR_MAX_RETURNED_FACETS_RESULTS)
    {
        if ($facetFieldName === '') {
            return [];
        }

        $list = null;
        $result = [];

        if ($sortType !== 'count') {
            $sortType = 'index';
        }


        $baseQueryString = 'q=*:*&rows=0&wt=phps&indent=false&facet=true&omitHeader=true&facet.limit=' . $facetLimit . '&facet.mincount=1&facet.field={!key=list}' . urlencode($facetFieldName);
        $baseQueryString .= '&fq=revue_id_i:' . RVID;

        if ($facetPrefix !== 'all') {
            $baseQueryString .= '&facet.prefix=' . $facetPrefix;
        }

        $baseQueryString .= '&facet.sort=' . $sortType;

        $defaultFilterQuery = self::getDefaultFiltersAsURL(Episciences_Settings::getConfigFile('solr.es.defaultFilters.json'));

        if ($defaultFilterQuery !== null) {
            $baseQueryString .= $defaultFilterQuery;
        }

        try {
            $solrResponse = Ccsd_Tools::solrCurl($baseQueryString, 'episciences');
            $solrResponse = unserialize($solrResponse, ['allowed_classes' => false]);
        } catch (Exception $e) {
            return [];
        }


        $list = $solrResponse['facet_counts']['facet_fields']['list'];

        if ((!is_array($list)) || (count($list) === 0)) {
            return [];
        }

        foreach ($list as $name => $count) {
            $exploded = explode(parent::SOLR_FACET_SEPARATOR, $name);
            if (count($exploded) > 1) {
                $result[$exploded[0]]['name'] = $exploded[1];
                $result[$exploded[0]]['count'] = $count;
            } else {
                $result[$name] = $count;
            }
        }

        return $result;
    }

    /**
     * Ajoute les filtres par défaut à une URL à envoyer à solr
     *
     * @param array $defaultFilters
     * @return NULL string
     */
    static function getDefaultFiltersAsURL($defaultFilters = null)
    {
        $filterQuery = parent::getDefaultFiltersAsURL($defaultFilters);

        if ($filterQuery === null) {
            $filterQuery = '';
        }

        return $filterQuery;
    }




    // --------------------------------------------------------------------------------------------------------------------
    // affichage des valeurs des facettes

    /**
     * Construit l'URL des facettes
     *
     * @param string $facetQueryFieldName
     * @param array $parsedSearchParams
     * @param string $facetValueCode
     * @param int $valueCount
     * @return array
     */
    static function buildFacetUrl($facetQueryFieldName, $parsedSearchParams, $facetValueCode, $valueCount)
    {
        $urlParams['checked'] = false;
        // le champs de facette est déjà utilisé dans la recherche, on
        // ajoute une valeur

        $arrayOfFacetParams = [];

        // la facette est utilisée dans les filtres
        if (array_key_exists($facetQueryFieldName, $parsedSearchParams)) {

            $arrayOfFacetParams = explode(' OR ', $parsedSearchParams[$facetQueryFieldName]);

            $facetUrlParams[$facetQueryFieldName] = $parsedSearchParams[$facetQueryFieldName];

            // la valeur de cette facette est utilisée comme filtre
            if (in_array($facetValueCode, $arrayOfFacetParams)) {

                // on coche la case
                $urlParams['checked'] = true;

                // on enlève la valeur de la facette au tableau des
                // filtres en
                // cours
                $arrayWithoutCheckedFacet = array_diff($arrayOfFacetParams, [
                    $facetValueCode
                ]);

                if (!empty($arrayWithoutCheckedFacet)) {

                    $facetUrlParams[$facetQueryFieldName] = implode(' OR ', $arrayWithoutCheckedFacet);

                    $urlParams['url'] = array_merge($parsedSearchParams, $arrayWithoutCheckedFacet);
                } else {
                    unset($parsedSearchParams[$facetQueryFieldName]);
                    $urlParams['url'] = $parsedSearchParams;
                    return $urlParams;
                }
            } else {
                // valeur non utilisée on concatène à une valeur
                // existante
                $facetUrlParams[$facetQueryFieldName] .= ' OR ' . $facetValueCode;
            }
        } else {
            // facette non utilisée dans les filtres
            $facetUrlParams[$facetQueryFieldName] = $facetValueCode;
        }

        if (is_array($facetUrlParams)) {
            $urlParams['url'] = array_merge($parsedSearchParams, $facetUrlParams);
            return $urlParams;
        }

        $urlParams['url'] = $parsedSearchParams;
        return $urlParams;
    }



}