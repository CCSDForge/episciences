<?php

/**
 * Données sur les schemas de solr
 * @author rtournoy
 * @see https://lucene.apache.org/solr/guide/6_6/schema-api.html
 *
 */
class Ccsd_Search_Solr_Schema extends Ccsd_Search_Solr
{
    /** @const solr schema handler */
    const HANDLER_SCHEMA = 'schema';

    /** @const int */
    const MAX_FACETS_THREADS = 30;

    /** @var array */
    protected $_fields = [];

    /** @var array champs dynamiques */
    protected $_dynamicFields;
    /** @var array champs recopiés */
    protected $_copyFields;
    /** @var array types de champs */
    protected $_fieldTypes;

    /**
     * Ccsd_Search_Solr_Schema constructor.
     * @param array $options
     */
    public function __construct($options = null)
    {
        parent::setOptions($options);

        return $this;
    }

    /**
     * Liste des champs du schema sélectionnés selon leur type
     * @param array $types types de champs
     * @param string $labelPrefix préfix de nom de champ pour les traductions
     * @return Ccsd_Search_Solr_Schema
     */


    public function getSchemaFieldsByType(array $types, $labelPrefix = '')
    {

        $t = null;

        try {
            $r = Ccsd_Tools::solrCurl('fields?wt=phps', $this->getCore(), self::HANDLER_SCHEMA);
        } catch (Exception $e) {
            $this->setFields(null);
            return $this;
        }

        if (!isset($r)) {
            $this->setFields(null);
            return $this;
        }

        $r = unserialize($r);

        if (!isset($r ['fields'])) {
            $this->setFields(null);
            return $this;
        }

        foreach ($r ['fields'] as $field) {
            if (in_array($field['type'], $types)) {
                $t [$field['name']] = $labelPrefix . $field['name'];
            }
        }

        $this->setFields($t);
        return $this;
    }

    /**
     * Liste des types de champs du schema
     *
     * @return Ccsd_Search_Solr_Schema
     */
    public function getSchemaFieldTypes()
    {
        try {
            $r = Ccsd_Tools::solrCurl('fieldtypes', $this->getCore(), self::HANDLER_SCHEMA);
        } catch (Exception $e) {
            $this->setFields(null);
            return $this;
        }

        $r = json_decode($r);
        $fieldTypesArr = $r->fieldTypes;
        $typeList = [];
        foreach ($fieldTypesArr as $type) {

            if (isset($type->indexed)) {
                $typeList [$type->name] ['indexed'] = $type->indexed;
            } else {
                $typeList [$type->name] ['indexed'] = '';
            }

            if (isset($type->stored)) {
                $typeList [$type->name] ['stored'] = $type->stored;
            } else {
                $typeList [$type->name] ['stored'] = '';
            }
        }
        $this->setFieldTypes($typeList);
        return $this;
    }

    /**
     * Liste des champs du schema
     *
     * @param bool $getSamples
     * @return Ccsd_Search_Solr_Schema
     */
    public function getSchemaFields($getSamples = true)
    {
        $samples = null;
        $this->setFields(null); // default value
        try {
            $r = Ccsd_Tools::solrCurl('fields', $this->getCore(), self::HANDLER_SCHEMA);
        } catch (Exception $e) {
            return $this;
        }

        $r = json_decode($r);
        $fieldTypes = $this->getFieldTypes();
        if ($getSamples === true) {
            $facetQueriesArray = [];
            foreach ($r->fields as $k => $d) {
                if ((($fieldTypes [$d->type] ['indexed'] === true) && ($fieldTypes [$d->type] ['stored'] === true))
                    || ((isset($d->docValues)) && $d->docValues)) {
                    if ($d->name != '_version_') {
                        $facetQueriesArray [] = 'facet.field=' . urlencode($d->name);
                    }
                }
            }
            $samples = $this->getSampleValues($facetQueriesArray);
        }

        foreach ($r->fields as $k => $d) {

            $t [$k] = new stdClass ();

            $t [$k]->name = $d->name;
            $t [$k]->type = $d->type;

            if (isset($d->multiValued)) {
                $t [$k]->multiValued = $d->multiValued;
            }
            if (isset($d->indexed) && ($d->indexed === false) || ($d->indexed === true)) {
                $t [$k]->indexed =$d->indexed ;
            } else {
                $t [$k]->indexed = $fieldTypes [$d->type] ['indexed'];
            }
            $t [$k]->stored = $fieldTypes [$d->type] ['stored'];
            $t [$k]->sample = '';
            if (($getSamples === true) && is_array($samples) && array_key_exists($d->name, $samples)) {
                $t [$k]->sample = array_keys($samples [$d->name]);
            }

            if (isset($d->required)) {
                $t [$k]->required = $d->required;
            }

            if (isset($d->uniqueKey)) {
                $t [$k]->uniqueKey = $d->uniqueKey;
            }

            $t [$k]->docValues = (isset($d->docValues)) ? $d->docValues : false;
        }

        if (isset($t)) {
            $this->setFields($t);
        }
        return $this;
    }

    /**
     * @return array
     */
    public function getFieldTypes()
    {
        return $this->_fieldTypes;
    }

    /**
     * @param array $_fieldTypes
     */
    public function setFieldTypes($_fieldTypes)
    {
        $this->_fieldTypes = $_fieldTypes;
    }

    /**
     * Récupère des exemples de valeurs pour les champs du schéma
     *
     * @param array $facetQueriesArray
     * @return NULL
     */
    public function getSampleValues(array $facetQueriesArray)
    {

        $slicedArray = (array_chunk($facetQueriesArray, self::MAX_FACETS_THREADS));

        $results = [];
        foreach ($slicedArray as $slice) {

            $facetQueries = implode("&", $slice);
            $q = 'q=*:*&facet.threads=' . self::MAX_FACETS_THREADS . '&rows=0&wt=phps&facet=true&omitHeader=true&facet.limit=5&' . $facetQueries;

            try {
                $r = Ccsd_Tools::solrCurl($q, $this->getCore());
            } catch (Exception $e) {
                continue;
            }

            $r = unserialize($r);

            if (array_key_exists('facet_counts', $r)) {
                $results += $r ['facet_counts'] ['facet_fields'];
            }
        }
        return $results;
    }

    /**
     * Liste des champs dynamiques du schema, merge avec les noms de champs
     * générés par les champs dynamiques
     *
     * @return Ccsd_Search_Solr_Schema
     */
    public function getSchemaDynamicFields()
    {
        try {
            $r = Ccsd_Tools::solrCurl('dynamicfields', $this->getCore(), self::HANDLER_SCHEMA);
        } catch (Exception $e) {
            $this->setDynamicFields(null);
            return $this;
        }

        $r = json_decode($r);
        if (isset($r->dynamicFields)) {
            $fieldTypes = $this->getFieldTypes();
            $usedFieldList = $this->getSchemaDynamicFieldsList();
            if (null == $usedFieldList) {
                return $this;
            }
            $t = [];
            foreach ($r->dynamicFields as $k => $schemaDynField) {
                if (array_key_exists($schemaDynField->name, $usedFieldList)) {
                    $usedDynField = new stdClass ();

                    $usedDynField->name = $schemaDynField->name;
                    $usedDynField->type = $schemaDynField->type;
                    if (isset($schemaDynField->multiValued)) {
                        $usedDynField->multiValued = $schemaDynField->multiValued;
                    }
                    if (isset($schemaDynField->docValues)) {
                        $usedDynField->docValues = $schemaDynField->docValues;
                    } else {
                        $usedDynField->docValues = '';
                    }
                    $usedDynField->indexed   = $fieldTypes [$schemaDynField->type] ['indexed'];
                    $usedDynField->stored    = $fieldTypes [$schemaDynField->type] ['stored'];
                    $usedDynField->fieldList = $usedFieldList [$schemaDynField->name];
                    $usedDynField->sample    = '';

                    $t [$k] = $usedDynField;
                }
            }

            $this->setDynamicFields($t);
        } else {
            $this->setDynamicFields(null);
        }
        return $this;
    }

    /**
     * Retourne les noms d'index existants, ne prend que ceux générés par les
     * champs dynamiques
     *
     * @uses the force
     * @return array
     */
    public function getSchemaDynamicFieldsList()
    {
        $t = null;
        $q = '?numTerms=0&reportDocCount=false&wt=phps';

        try {
            $r = Ccsd_Tools::solrCurl($q, $this->getCore(), 'admin/luke');
        } catch (Exception $e) {
            return null;
        }

        $r = unserialize($r);

        foreach ($r ["fields"] as $fieldName => $field) {
            if (array_key_exists("dynamicBase", $field)) {
                $t [$field ["dynamicBase"]] [] = $fieldName;
            }
        }

        if (is_array($t)) {
            return $t;
        } else {
            return null;
        }
    }

    /**
     * Liste des champs alimentés via copyField du schema
     *
     * @return Ccsd_Search_Solr_Schema
     */
    public function getSchemaCopyFields()
    {
        $this->setCopyFields(null);
        try {
            $r = Ccsd_Tools::solrCurl('copyfields', $this->getCore(), self::HANDLER_SCHEMA);
        } catch (Exception $e) {
            return $this;
        }
        /** @var ArrayObject $r */
        $r = json_decode($r);

        if (isset($r->copyFields) && is_array($r->copyFields)) {
            $copyFieldsArray = [];
            foreach ($r->copyFields as $field) {
                $copyFieldsArray [$field->dest] [] = $field->source;
            }
            $this->setCopyFields($copyFieldsArray);
        }
        return $this;
    }

    /**
     *
     * @return array $_fields
     */
    public function getFields()
    {
        return $this->_fields;
    }

    /**
     * @param array $_fields
     * @return Ccsd_Search_Solr_Schema
     */
    public function setFields($_fields)
    {
        if (!is_array($_fields)) {
            $_fields = [];
        }
        $this->_fields = $_fields;
        return $this;
    }

    /**
     *
     * @return array $_dynamicFields
     */
    public function getDynamicFields()
    {
        return $this->_dynamicFields;
    }

    /**
     * @param array $_dynamicFields
     * @return Ccsd_Search_Solr_Schema
     */
    public function setDynamicFields($_dynamicFields)
    {
        $this->_dynamicFields = $_dynamicFields;
        return $this;
    }

    /**
     *
     * @return array $_copyFields
     */
    public function getCopyFields()
    {
        return $this->_copyFields;
    }

    /**
     * @param array $_copyFields
     * @return Ccsd_Search_Solr_Schema
     */
    public function setCopyFields($_copyFields)
    {
        $this->_copyFields = $_copyFields;
        return $this;
    }
}
