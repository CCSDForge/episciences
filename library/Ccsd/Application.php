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
        self::deprecated();
        self::$_current = $app;
    }

    /**
     * @return Application
     */
    public static function getCurrent() {
        self::deprecated();
        return self::$_current;
    }

    /**
     * @return string
     */
    public function getName() {
        self::deprecated();
        return $this->_name;
    }

    private static function deprecated()
    {
        trigger_error(
            '[DEAD CODE AUDIT 2026-08-28] ' . __CLASS__ . ' is scheduled for removal.'
            . ' Do NOT use this class in new code. If this message appears in production logs,'
            . ' report it to the development team immediately.',
            E_USER_DEPRECATED
        );
    }
}
