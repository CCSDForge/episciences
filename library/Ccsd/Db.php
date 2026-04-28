<?php

use Zend\Db\Adapter\Adapter;

/**
 * Created by PhpStorm.
 * User: sdenoux
 * Date: 07/03/18
 * Time: 10:00
 *
 * API entre Zend 1 et Zend 3
 */
class Ccsd_Db
{
    /**
     * @param $adapter
     * @param array $config
     * @return Adapter|Zend_Db_Adapter_Abstract
     */
    public static function factory($adapter, $config = array()) {

        if (class_exists('Zend_Db')) {
            // ZF version 1
            return Zend_Db::factory($adapter, $config);
        } else {
            // ZF version 3
            $config = array_merge(['driver'   => $adapter], $config);
            return new Adapter($config);
        }
    }
}