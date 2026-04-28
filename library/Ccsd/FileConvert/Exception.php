<?php
/**
 * Created by PhpStorm.
 * User: tournoy
 * Date: 13/07/18
 * Time: 15:05
 */

class Ccsd_FileConvert_Exception extends Exception
{

    const FILE_TOO_BIG = 'Convert: File is too big';
    const FILE_EMPTY = 'Convert: File is empty';
    const FILE_NOT_READABLE = 'Convert: File is not readable';
    const POPPLER_CACHE_MANDATORY = 'A cache file is mandatory with Poppler conversion, output has to be written to a file';
    const UNKNOWN_CONVERT_METHOD = "Unknown conversion method. I'm sorry, Dave. I'm afraid I can't do that.";


    public function __construct($code = null, $message = null, $previous = null)
    {

        switch ($code) {

            case self::FILE_TOO_BIG:
                $this->message = self::FILE_TOO_BIG;
                break;
            case self::FILE_EMPTY:
                $this->message = self::FILE_EMPTY;
                break;
            case self::FILE_NOT_READABLE:
                $this->message = self::FILE_NOT_READABLE;
                break;
            case self::UNKNOWN_CONVERT_METHOD:
                $this->message = self::UNKNOWN_CONVERT_METHOD;
                break;
            case self::POPPLER_CACHE_MANDATORY:
                $this->message .= self::POPPLER_CACHE_MANDATORY;
                break;

            default:
                $this->message = 'Unknown Error';
                break;

        }


        $this->message = $this->message  . '. '  . $message ;

    }


}