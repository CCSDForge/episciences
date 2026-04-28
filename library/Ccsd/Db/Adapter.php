<?php

/**
 * Adapter Base de données
 * Permet de créer un autre adapter que celui de l'application par défaut
 * @author Yannick Barborini
 *
 */
class Ccsd_Db_Adapter
{
    /**
     * Parametres de l'adapter
     * @var array
     */
    static protected $_params = array(); 

    /**
     * Retourne un adapter base de données
     * @return Zend_Db_Adapter_Abstract
     */
    public static function getAdapter()
    {
        return Ccsd_Db::factory('Pdo_Mysql', self::$_params);
    }
}