<?php
/**
 * Modèle pour la table FTP_QUOTA_TOTAL
 * @author rtournoy
 *
 */
class Ccsd_User_Models_DbTable_UserFtpQuotaTotal extends Zend_Db_Table_Abstract
{

    protected $_name = 'FTP_QUOTA_TOTAL';


    /**
     *
     * @var string Nom de la base de données
     */

    public function __construct ()
    {

        $this->_setAdapter(Ccsd_Db_Adapter_Cas::getAdapter());
    }

}

