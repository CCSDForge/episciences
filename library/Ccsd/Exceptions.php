<?php
/**
 * Created by PhpStorm.
 * User: marmol
 * Date: 24/08/18
 * Time: 11:34
 */

class Ccsd_FileNotFoundException extends Exception
{

    public function __construct(mixed ...$args)
    {
        trigger_error(
            '[DEAD CODE AUDIT 2026-05-08] ' . __CLASS__ . ' is scheduled for removal.'
            . ' Do NOT use this class in new code. If this message appears in production logs,'
            . ' report it to the development team immediately.',
            E_USER_DEPRECATED
        );

        if (get_parent_class($this) !== false && method_exists(get_parent_class($this), '__construct')) {
            parent::__construct(...$args);
        }
    }

}

