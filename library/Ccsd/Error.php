<?php

/**
 * Class Ccsd_Error
 */
class Ccsd_Error extends Exception
{
    protected $_type;

    /**
     * Ccsd_Error constructor.
     * @param string $message
     * @param int $code
     * @param string $type
     */
    public function __construct(string $message, int $code = 0, $type = '')
    {
        $this->_type = $type;
        $this->code = $code;
        $this->message = $message;

        if (!empty($type)) {
            $this->message = $type . '_' . $this->getCode() . ' { ' . $this->getMessage() . '.' . ' }';
        }

        parent::__construct($this->getMessage(), $this->getCode());

    }

    /**
     * Reformate le message
     * @return string
     */
    public function __toString()
    {
        if (!empty($this->_type)) {
            return $this->_type . '_' . $this->getCode() . ' { ' . $this->getMessage() . '.' . ' }';
        }
        return $this->getMessage();
    }

    public function parseError(): string
    {

        $explode = explode(':', $this->getMessage(), 2);
        $code = trim($explode[0]);
        switch ($code) {
            case 'badArgument' :
                $message = 'The argument included in the request is not valid.';
                break;
            case 'badResumptionToken' :
                $message = 'The resumptionToken does not exist or has already expired.';
                break;
            case 'badVerb' :
                $message = 'The verb provided in the request is illegal.';
                break;
            case 'cannotDisseminateFormat':
                $message = 'The metadata format is not supported by this repository.';
                break;
            case 'idDoesNotExist' :
                $message = 'The value of the identifier is illegal for this archive, please check the identifier and the version of the document.';
                break;
            case 'noRecordsMatch' :
                $message = 'The combination of the given values results in an empty list.';
                break;
            case 'noMetadataFormats' :
                $message = 'There are no metadata formats available for the specified item.';
                break;
            case 'noSetHierarchy' :
                $message = 'This repository does not support sets.';
                break;
            case 'docIsNotice' :
                $message = 'You can not submit an empty record: you must first modify the deposit on the open archive to attach the PDF file of your article without formatting.';
                break;
            case 'arXivVersionDoesNotExist' :
                $message = "This document version does not exist in the open archive, please check the document version.";
                break;
            case 'hookIsNotOpenAccessRight':
            case 'hookUnboundVersions':
            case 'docUnderEmbargo' :
                $message = $explode[1];
                break;
            default :
                //todo interpréter les messages CURL exp. CURL_ERROR_6 { Couldn't resolve host name. }
                $message = "The operation ended with an error. Please try again.";
                trigger_error($this->getMessage());
        }

        return $message;

    }

}
