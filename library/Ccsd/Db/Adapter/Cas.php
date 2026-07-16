<?php

/**
 * Adapter base de données CAS
 * @author Yannick Barborini
 *
 */
class Ccsd_Db_Adapter_Cas extends Ccsd_Db_Adapter
{
    const USER_TABLE = "T_UTILISATEURS";

    /** @var Zend_Db_Adapter_Pdo_Mysql null : On garde l'adapter pour ne le construire qu'un seule fois! */
    static private $cas_adapter;

    /**
     * Retourne l'adapter base de données de la base CAS (utilisateurs)
     * @return Zend_Db_Adapter_Pdo_Mysql
     */
    public static function getAdapter()
    {
        if (!self::$cas_adapter) {
            self::$_params = ['dbname' => CAS_NAME, 'port' => CAS_PORT, 'username' => CAS_USER, 'host' => CAS_HOST, 'password' => CAS_PWD];

            // Enable profiler if main DB profiler is enabled (for debugging)
            $mainAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
            if ($mainAdapter && $mainAdapter->getProfiler()->getEnabled()) {
                self::$_params['profiler'] = true;
            }

            self::$cas_adapter = parent::getAdapter();

            // Enable profiler on the adapter if it was set in params
            if (isset(self::$_params['profiler']) && self::$_params['profiler']) {
                $profiler = new Zend_Db_Profiler();
                $profiler->setEnabled(true);
                self::$cas_adapter->setProfiler($profiler);
            }
        }
        return self::$cas_adapter;
    }
}
