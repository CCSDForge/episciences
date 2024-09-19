<?php

class Episciences_View_Helper_Url extends Zend_View_Helper_Url
{

    /**
     * @param array $urlOptions
     * @param null $name //  The name of a Route to use
     * @param bool $reset
     * @param bool $encode
     * @return string
     */
    public function url(array $urlOptions = [], $name = null, $reset = false, $encode = true): string
    {

        if (!defined('PREFIX_URL') || PREFIX_URL === PORTAL_PREFIX_URL) {
            return parent::url($urlOptions, $name, $reset, $encode);
        }


        if (empty($urlOptions)) {

            $urlOptions = [
                'controller' => Zend_Controller_Front::getInstance()->getRequest()->getControllerName(),
                'action' => Zend_Controller_Front::getInstance()->getRequest()->getActionName()
            ];
        }

        $uri = PREFIX_URL;

        if (isset ($urlOptions ['controller']) && $urlOptions['controller'] !== 'index') {
            $uri .= $urlOptions ['controller'] . '/';
        }

        $uri .= (isset($urlOptions ['action']) && $urlOptions ['action'] !== 'index')  ? $urlOptions ['action'] :  '';

        unset ($urlOptions ['controller'], $urlOptions ['action']);

        foreach ($urlOptions as $option => $value) {

            $uri .= '/' . urlencode($option) . '/';

            if (is_array($value)) {
                $uri .= urlencode($value[0]);
            } else {
                $uri .= urlencode($value);
            }
        }

        return $uri;

    }

}