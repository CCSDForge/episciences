<?php

/**
 * Class Ccsd_Error
 */
class Ccsd_Error extends Exception
{
    public const ID_DOES_NOT_EXIST_CODE = 'idDoesNotExist';
    public const DEFAULT_PREFIX_CODE = 'The operation ended with this error: <strong><small>[%s]</small></strong>.';
    public const ARXIV_VERSION_DOES_NOT_EXIST_CODE = 'arXivVersionDoesNotExist';
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
            case self::ID_DOES_NOT_EXIST_CODE :
                $message = "To submit your document, please make sure it is online in the open archive. In the event of difficulties, please contact the support at %s by indicating the document identifier (example of an identifier for a document submitted in %s: %s).";
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
            case self::ARXIV_VERSION_DOES_NOT_EXIST_CODE :
                $message = "This version of the document does not exist or is not online in the open archive. In the event of difficulties, please contact the support at %s by indicating the document identifier (example of an identifier for a document submitted in %s: %s).";
                break;
            case 'hookIsNotOpenAccessRight':
            case 'hookUnboundVersions':
            case 'docUnderEmbargo' :
                $message = $explode[1];
                break;
            default :

                $message = self::DEFAULT_PREFIX_CODE;
                $message .= ' ';
                $message .= "Please try again. In the event of difficulties, please contact the support at %s by indicating the document identifier (example of an identifier for a document submitted in %s: %s).";
        }

        return $message;

    }

}
