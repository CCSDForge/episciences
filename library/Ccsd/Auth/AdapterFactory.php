<?php


/**
 * Factory to return an Adapter for authentication
 *
 */
namespace Ccsd\Auth;
/**
 * Class AdapterFactory
 * @package Ccsd\Auth
 */
class AdapterFactory  {
    /**
     * Liste des authentifications autorisées
     *
     * @var array
     */
    protected $_accepted_auth_list = ['DB', 'CAS', 'IDP', 'ORCID', 'MYSQL'];
    /**
     * @param $authType
     * @return \Ccsd_Auth_Adapter_Cas|Adapter\Idp|\Ccsd_Auth_Adapter_Orcid|Adapter\DbTable
     */

    static public function getTypedAdapter($authType) {
        switch ($authType)
        {
            case 'DB':    $authAdapter = new Adapter\DbTable();
                break;
            case 'CAS':   $authAdapter = new \Ccsd_Auth_Adapter_Cas();
                break;
            case 'IDP':   $authAdapter = new Adapter\Idp();
                break;
            case 'ORCID': $authAdapter = new \Ccsd_Auth_Adapter_Orcid();
                break;
            case 'MYSQL': $authAdapter = new Adapter\Mysql();
                break;

            default : $authAdapter = new \Ccsd_Auth_Adapter_Cas();
        }

        return $authAdapter;
    }

}
