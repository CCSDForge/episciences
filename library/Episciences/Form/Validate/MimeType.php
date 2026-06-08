<?php
declare(strict_types=1);

use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Symfony\Component\Mime\Exception\LogicException;
use Symfony\Component\Mime\FileBinaryMimeTypeGuesser;


/**
 * Enhanced MIME type validator
 * Using FileBinaryMimeTypeGuesser
 *
 * */
class Episciences_Form_Validate_MimeType extends Zend_Validate_Abstract
{

    public const FALSE_TYPE = 'fileMimeTypeFalse';
    public const NOT_DETECTED = 'fileMimeTypeNotDetected';
    public const NOT_READABLE = 'fileMimeTypeNotReadable';
    public const ALLOWED_MIME_TYPE_KEY = 'allowedMimeTypes';

    protected string $_type = '';

    protected $_messageVariables = [
            'type' => '_type',
    ];


    protected array $_allowedMimeTypes = [];

    protected $_messageTemplates = [
            self::FALSE_TYPE => "File '%value%' has a false mimetype of '%type%'",
            self::NOT_DETECTED => "The mimetype of file '%value%' could not be detected",
            self::NOT_READABLE => "File '%value%' is not readable or does not exist",
    ];

    public function __construct(array $options = [])
    {

        // Set allowed MIME types
        if (isset($options[self::ALLOWED_MIME_TYPE_KEY])) {
            $this->_allowedMimeTypes = (array)$options[self::ALLOWED_MIME_TYPE_KEY];
        } elseif (defined('ALLOWED_MIMES_TYPES')) {
            $this->_allowedMimeTypes = (array)ALLOWED_MIMES_TYPES;
        } else {
            $this->_allowedMimeTypes = ['application/pdf'];
        }
    }

    /**
     * @param $value // $value is the temporary path of the uploaded file
     * @param $file // File data from Zend_File_Transfer
     * @return bool
     */


    public function isValid($value, $file = null): bool
    {


        // Validate input parameters
        if (!is_string($value) || empty($value)) {
            return $this->_throw(null, self::NOT_READABLE);
        }

        // Normalize file data if not provided
        if ($file === null) {
            $file = [
                    'type' => null,
                    'name' => basename($value),
                    'tmp_name' => $value,
            ];
        }


        $this->_setValue($value);

        $guesser = new FileBinaryMimeTypeGuesser();

        try {
            $type = $guesser->guessMimeType($value);

        } catch (LogicException $e) {
            error_log("MIME Guesser LogicException: " . $e->getMessage());
            return $this->_throw($file, self::NOT_DETECTED);

        } catch (InvalidArgumentException $e) {
            error_log("MIME Guesser InvalidArgumentException: " . $e->getMessage());
            return $this->_throw($file, self::NOT_READABLE);
        }



        if (!$type) {
            return $this->_throw($file, self::NOT_DETECTED);
        }

        $this->_type = $type ;

        if (!in_array($type, $this->_allowedMimeTypes, true)) {
            return $this->_throw($file, self::FALSE_TYPE);
        }

        return true;

    }

    protected function _throw($file, $errorType): bool
    {
        $this->_value = is_array($file) ? $file['name'] : $file ?? '';
        $this->_error($errorType);
        return false;
    }

    public function setAllowedMimeTypes(array $allowedMimeTypes): void
    {
        $this->_allowedMimeTypes = $allowedMimeTypes;
    }

}