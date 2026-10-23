<?php

namespace Episciences;

use Monolog\Logger;
use Zend_Registry;

final class  AppRegistry extends Zend_Registry
{
    private const APP_LOGGER = 'appLogger';

    public static function getMonoLogger(): ?Logger
    {
        if (!self::isRegistered(self::APP_LOGGER)) {
            return null;
        }

        /**
         * LGTM:
         * Exception only happens
         * if (!$instance->offsetExists(self::APP_LOGGER))
         * and it is already checked by
         * self::isRegistered(self::APP_LOGGER)
         */
        /** @var Logger */
        return self::get(self::APP_LOGGER);
    }
}
