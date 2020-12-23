<?php

namespace Ccsd\Xml;

/**
 * Class Exception
 * @package Ccsd\Xml
 */
Class Exception extends \Exception
{
    /**
     * @throws
     */
	public static function HandleXmlError($errno, $errstr)
    {
        if ($errno==E_WARNING && (substr_count($errstr,"DOMDocument::loadXML()")>0)) {
            throw new self('source XML incorrecte: ' .  $errstr);
        } else {
            return false;
        }
    }
    /**
     * Need to get errors form XML validation
     * @param $errno
     * @param $errstr
     * @throws Exception
     */
    function  validateErrorHandler($errno, $errstr) {
        $errstr = str_replace('DOMDocument::schemaValidate():', '', $errstr);
        throw new self($errstr);
    }

}