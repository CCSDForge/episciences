<?php

/**
 * Class Ccsd_Db_Adapter_SolrIndexQueue
 * Adapter base de données pour la file d'indexation de solr
 */
class Ccsd_Db_Adapter_SolrIndexQueue extends Ccsd_Db_Adapter
{
    /** @var Zend_Db_Adapter_Pdo_Mysql */
    static private $cas_adapter;

    /**
     * Retourne l'adapter base de données pour la file d'indexation de solr
     * @return Zend_Db_Adapter_Abstract
     */
    public static function getAdapter()
    {
        if (!self::$cas_adapter) {
            self::$_params = ['dbname' => SOLR_NAME, 'port' => SOLR_PORT, 'username' => SOLR_USER, 'host' => SOLR_HOST, 'password' => SOLR_PWD];
            self::$cas_adapter = parent::getAdapter();
        }
        return self::$cas_adapter;
    }
}