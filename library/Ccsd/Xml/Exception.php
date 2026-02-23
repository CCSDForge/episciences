<?php

namespace Ccsd\Xml;

use Exception as ExceptionAlias;

/**
 * Class Exception
 * @package Ccsd\Xml
 */
class Exception extends ExceptionAlias
{
    /**
     * @throws
     */
    public static function HandleXmlError($errno, $errstr): bool
    {
        if ($errno === E_WARNING && (substr_count($errstr, "DOMDocument::loadXML()") > 0)) {
            throw new self('source XML incorrecte: ' . $errstr);
        }

        return false;
    }

    /**
     * Need to get errors form XML validation
     * @param $errno
     * @param $errstr
     * @throws Exception
     */
    function validateErrorHandler($errno, $errstr): void
    {
        $errstr = str_replace('DOMDocument::schemaValidate():', '', $errstr);
        throw new self($errstr);
    }

}