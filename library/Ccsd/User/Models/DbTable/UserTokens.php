<?php

/**
 * Modèle pour la table des tokens utilisateurs CCSD
 * @author rtournoy
 *
 */
class Ccsd_User_Models_DbTable_UserTokens extends Zend_Db_Table_Abstract
{

    protected $_name = 'T_UTILISATEURS_TOKENS';

    protected $_primary = 'TOKEN';

    public function __construct ()
    {
        $this->_setAdapter(Ccsd_Db_Adapter_Cas::getAdapter());
    }

}

