<?php

namespace Episciences;

use Monolog\Logger;
use Zend_Exception;

final class  AppRegistry extends \Zend_Registry
{
    public static function getMonoLogger(): ?Logger
    {
        try {
            /** @var Logger $logger */
            $logger = self::get('appLogger');
        } catch (Zend_Exception $e) {
            $logger = null;
            trigger_error($e->getMessage());
        }
        return $logger;
    }


}