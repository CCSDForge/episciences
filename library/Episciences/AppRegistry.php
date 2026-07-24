<?php

namespace Episciences;

use Monolog\Logger;
use Zend_Registry;

final class  AppRegistry extends Zend_Registry
{
    public static function getMonoLogger(): ?Logger
    {
        if (!self::isRegistered('appLogger')) {
            return null;
        }

        /** @var Logger */
        return self::get('appLogger');
    }


}