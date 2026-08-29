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
        trigger_error(
            '[DEAD CODE AUDIT 2026-05-08] ' . __CLASS__ . ' is scheduled for removal.'
            . ' Do NOT use this class in new code. If this message appears in production logs,'
            . ' report it to the development team immediately.',
            E_USER_DEPRECATED
        );


        $this->_setAdapter(Ccsd_Db_Adapter_Cas::getAdapter());
    }

}

