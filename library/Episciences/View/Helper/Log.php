<?php

use Psr\Log\LogLevel;

class Episciences_View_Helper_Log extends Zend_View_Helper_Abstract
{
    public static function log(string $message, $level = LogLevel::NOTICE, array $context = []): bool
    {

        try {
            /** @var Monolog\Logger $logger */
            $logger = Zend_Registry::get('appLogger');
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return false;
        }

        $logger->log($level, $message, $context);
        return true;
    }
}