<?php

class Ccsd_Auth extends Zend_Auth
{

    public static function isLogged () : bool
    {
        return self::getInstance()->hasIdentity();
    }

    /**
     *
     * @param object $user
     *            Ccsd_User ou Hal_User ou ...
     */
    public static function setIdentity ($user)
    {
        self::getInstance()->getStorage()->write($user);
    }

    /**
     * retourne l'objet User de l'utilisateur courant
     * @return mixed|null
     */
    public static function getUser ()
    {
        return self::getInstance()->getIdentity();
    }

    /**
     * Retourne le username de l'utilisateur courant
     * @return string|null
     */
    public static function getUsername ()
    {
        $instance = self::getInstance();
        if ($instance === null) {
            return null;
        }
        $identity = $instance -> getIdentity();
        if ($identity === null) {
            return null;
        }
        return $identity->getUsername();
    }

    public static function getUid ()
    {
        return self::isLogged() ? self::getInstance()->getIdentity()->getUid() : 0;
    }


    public static function getFullName ()
    {
        return self::getInstance()->getIdentity()->getFullName();
    }


    public static function getRoles ()
    {}



}
