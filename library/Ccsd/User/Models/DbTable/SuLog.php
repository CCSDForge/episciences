<?php

/**
 * Modèle table SU_LOG
 * @author rtournoy
 *
 */
class Ccsd_User_Models_DbTable_SuLog extends Zend_Db_Table_Abstract {
	protected $_name = 'SU_LOG';
	protected $_primary = 'ID';
	public function __construct() {
		$this->_setAdapter ( Ccsd_Db_Adapter_Cas::getAdapter (  ) );
	}
}