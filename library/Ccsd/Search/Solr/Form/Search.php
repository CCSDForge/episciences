<?php
/**
 * Formulaire de recherche simple
 *
 */
class Ccsd_Search_Solr_Form_Search extends Ccsd_Form
{

    public function __construct ($options = null)
    {
        parent::__construct($options);
    }

    public function init ()
    {
        parent::init();
        $ccsdFormConfig = new Zend_Config_Ini(__DIR__ . '/../configs/search.ini', 'ccsd-search-simple');
        $this->setConfig($ccsdFormConfig->search->simple);

        return $this;
    }
}



