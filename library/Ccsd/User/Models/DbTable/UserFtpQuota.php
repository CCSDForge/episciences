<?php
/**
 * Modèle pour la table FTP_QUOTA_LIMITS
 * @author rtournoy
 *
 */
class Ccsd_User_Models_DbTable_UserFtpQuota extends Zend_Db_Table_Abstract
{

    protected $_name = 'FTP_QUOTA_LIMITS';

    protected $_primary = 'Id';

    /**
     *
     * @var string Nom de la base de données
     */

    public function __construct ()
    {

        $this->_setAdapter(Ccsd_Db_Adapter_Cas::getAdapter());
    }

}

