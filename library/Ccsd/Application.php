<?php


namespace Ccsd;


class Application
{

    /** @var Application */
    static private $_current = null;

    /** @var string */
    protected $_name = null;

    /**
     * @param Application $app
     */
    public static function  setCurrent($app) {
        self::$_current = $app;
    }

    /**
     * @return Application
     */
    public static function getCurrent() {
        return self::$_current;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->_name;
    }
}