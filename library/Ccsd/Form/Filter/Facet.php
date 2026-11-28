<?php

use Episciences\AppRegistry;

/**
 * @deprecated Dead code scheduled for removal (dead-code-audit 2026-05-08).
 */
class Ccsd_Form_Filter_Facet implements Zend_Filter_Interface {

    public function __construct()
    {
        $message = '[DEAD CODE AUDIT 2026-05-08] ' . __CLASS__ . ' is scheduled for removal.'
            . ' Do NOT use this class in new code. If this message appears in production logs,'
            . ' report it to the development team immediately.';
        AppRegistry::getMonoLogger()?->warning($message);
        trigger_error($message, E_USER_DEPRECATED);
    }

	public function filter($value)
	{
		return str_replace(Ccsd_Search_Solr::getConstantesFacet(), '', $value);
	}	

}