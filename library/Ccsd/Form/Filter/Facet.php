<?php

class Ccsd_Form_Filter_Facet implements Zend_Filter_Interface {

	public function filter($value)
	{
		return str_replace(Ccsd_Search_Solr::getConstantesFacet(), '', $value);
	}	

}