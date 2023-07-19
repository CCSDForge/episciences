<?php

abstract class Ccsd_Auth_Adapter_CasAbstract
{
    /**
     * Retourne le nom d'hôte que l'application CAS va utiliser
     * Pour redirection après login et logout
     *
     * @return string Nom de l'hôte
     */
    public final static function getCurrentHostname() : string {

        if (
            !defined('SERVER_HTTP') ||
            (SERVER_HTTP !== 'http' && SERVER_HTTP !== 'https')
        ) {
            $scheme = 'http://';
        } else {

            $scheme = SERVER_HTTP . '://';
        }


        $hostname = $scheme . $_SERVER['SERVER_NAME'];


        if ((isset($_SERVER['SERVER_PORT'])) && ($_SERVER['SERVER_PORT'] !== '')) {
            switch ($_SERVER['SERVER_PORT']) {
                case '443':
                case '':
                case '80':
                    break;
                default:
                    $hostname .= ":" . $_SERVER['SERVER_PORT'];
                    break;
            }
        }

        return $hostname;
    }


}