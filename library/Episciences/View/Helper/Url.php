<?php

class Episciences_View_Helper_Url extends Zend_View_Helper_Url
{

    /**
     * @param array $urlOptions
     * @param null $name //  The name of a Route to use
     * @param bool $reset
     * @param bool $encode
     * @param bool $withSegmentedParameters ([false]: ?param1=val1&param2=val2&... [true]: /param1/val1/param2/val2/...)
     * @return string
     * @throws Zend_Exception
     */

    public const URI_DELIMITER = '/';

    /**
     * @throws Zend_Exception
     */
    public function url(array $urlOptions = [], $name = null, $reset = false, $encode = true, bool $withSegmentedParameters = false): string
    {
        if ($withSegmentedParameters) {

            if (!defined('PREFIX_URL') || PREFIX_URL === PORTAL_PREFIX_URL) {
                return parent::url($urlOptions, $name, $reset, $encode);
            }

            if (!is_array($urlOptions)) {
                throw new Zend_Exception('urlOptions must be an array');
            }

            if (empty($urlOptions)) {

                $urlOptions = [
                    'controller' => Zend_Controller_Front::getInstance()->getRequest()->getControllerName(),
                    'action' => Zend_Controller_Front::getInstance()->getRequest()->getActionName()
                ];
            }

        }

        return rtrim($this->processUri($urlOptions, $encode, $withSegmentedParameters), self::URI_DELIMITER);

    }


    /**
     * @param array $urlOptions
     * @param bool $encode
     * @param bool $withRewrittenParameters
     * @return string
     */
    public function processUri(array &$urlOptions, bool $encode = true, bool  $withRewrittenParameters = true): string
    {
        $uri = PREFIX_URL;

        if (isset ($urlOptions ['controller']) && $urlOptions['controller'] !== 'index') {

            $controller = $urlOptions ['controller'];

            if ($encode) {
                $controller = urlencode((string)$controller);
            }

            $uri .= $controller . self::URI_DELIMITER;
        }

        if ((isset($urlOptions ['action']) && $urlOptions ['action'] !== 'index')) {

            $action = $urlOptions ['action'];

            if ($encode) {
                $action = urlencode((string)$urlOptions['action']);
            }

            $uri .= $action;

        } else {
            $uri .= '';
        }

        unset ($urlOptions ['controller'], $urlOptions ['action']);

        if (!$withRewrittenParameters) { // ?param1=val1&param2=val2&...

            if (!empty($urlOptions)) {
                $uri .= '?';
                $input = static function ($item, $key, $parent_key = '') use (&$output, &$input) {
                    is_array($item)
                        ? array_walk($item, $input, $key)
                        : $output[] = http_build_query([$parent_key ?: $key => $item]);
                };

                array_walk($urlOptions, $input);
                $uri .= implode('&', $output);
            }

            return $uri;
        }

        foreach ($urlOptions as $option => $value) { // /param1/val1/param2/val2/...
            $option = ($encode) ? urlencode((string)$option) : $option;
            if (is_array($value)) {
                foreach ($value as $val) {
                    $val = ($encode) ? urlencode((string)$val) : $val;
                    $uri .= self::URI_DELIMITER . $option;
                    $uri .= self::URI_DELIMITER . $val;
                }
            } else {
                if ($encode && is_string($value)) {
                    $value = urlencode($value);
                }
                $uri .= self::URI_DELIMITER . $option;
                $uri .= self::URI_DELIMITER . $value;
            }

        }
        return $uri;
    }
}