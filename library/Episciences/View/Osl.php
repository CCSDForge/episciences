<?php

namespace Episciences\View;


use Zend_Controller_Front;
use Zend_Session_Namespace;

class Osl
{
    public static function getOslScript(): string
    {
        if (!self::isOslAvailable()) {
            return '<!-- OSL Disabled -->';
        }
        $oslScript = file_get_contents(self::getOslConfigPath());
        if (!$oslScript) {
            return '<!-- OSL Disabled -->';
        }
        return $oslScript;

    }


    public static function isOslAvailable(): bool
    {
        $oslFullPath = self::getOslConfigPath();
        if (is_readable($oslFullPath)) {
            return true;
        }
        return false;
    }

    public static function getOslConfigPath(): string
    {
        return REVIEW_PATH . 'config/osl.txt';
    }

    /**
     * @return mixed|string
     */
    public static function getOslWantedStatus()
    {
        $request = Zend_Controller_Front::getInstance()->getRequest();
        $enableOslToChange = $request->getParam('enableOsl');
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);

        // Update
        if ($enableOslToChange === 'on' || $enableOslToChange === 'off') {
            $enableOsl = $enableOslToChange;
            $session->enableOsl = $enableOslToChange;
        } elseif ($session->enableOsl === 'on' || $session->enableOsl === 'off') {
            $enableOsl = $session->enableOsl;
        } else {
            $enableOsl = 'off';
        }
        return $enableOsl;
    }

}