<?php
declare(strict_types=1);

use Episciences\Upload\MimeTypePolicy;
use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Symfony\Component\Mime\FileBinaryMimeTypeGuesser;


/**
 * Checks the real content of an uploaded file (not the type announced by the browser)
 * against the content types expected for its extension.
 *
 * An empty file is always refused.
 */
class Episciences_Form_Validate_MimeType extends Zend_Validate_Abstract
{

    public const FALSE_TYPE = 'fileMimeTypeFalse';
    public const NOT_DETECTED = 'fileMimeTypeNotDetected';
    public const NOT_READABLE = 'fileMimeTypeNotReadable';
    public const EMPTY_FILE = 'fileMimeTypeEmpty';

    /** Option: list of content types accepted whatever the extension is */
    public const ALLOWED_MIME_TYPE_KEY = 'allowedMimeTypes';

    /** Option: MimeTypePolicy giving the content types accepted for each extension */
    public const POLICY_KEY = 'policy';

    private const DEFAULT_MIME_TYPES = ['application/pdf'];

    protected MimeTypePolicy $_policy;

    /** @var array<string, string> */
    protected $_messageTemplates = [
        self::FALSE_TYPE => "The file '%value%' cannot be accepted: its content does not match its extension. Please check that it is a valid file and that it has not been renamed.",
        self::NOT_DETECTED => "The type of the file '%value%' could not be checked. Please try again with another copy of the file.",
        self::NOT_READABLE => "The file '%value%' could not be read. Please try to upload it again.",
        self::EMPTY_FILE => "The file '%value%' is empty. Please choose a file that contains data.",
    ];

    /**
     * Without option, the policy configured for the journal is used.
     *
     * @param array{allowedMimeTypes?: array<string>, policy?: MimeTypePolicy} $options
     */
    public function __construct(array $options = [])
    {
        if (($options[self::POLICY_KEY] ?? null) instanceof MimeTypePolicy) {
            $this->_policy = $options[self::POLICY_KEY];
        } elseif (isset($options[self::ALLOWED_MIME_TYPE_KEY])) {
            $this->_policy = MimeTypePolicy::forAnyExtension((array)$options[self::ALLOWED_MIME_TYPE_KEY]);
        } else {
            $this->_policy = self::configuredPolicy();
        }
    }

    /**
     * @param mixed $value temporary path of the uploaded file
     * @param array<string, mixed>|null $file file data from Zend_File_Transfer
     * @return bool
     */
    public function isValid($value, $file = null): bool
    {
        if (!is_string($value) || $value === '') {
            return $this->_throw($file, self::NOT_READABLE);
        }

        // Normalize file data if not provided
        $file ??= [
            'type' => null,
            'name' => basename($value),
            'tmp_name' => $value,
        ];

        $this->_setValue($value);

        if (!is_file($value) || !is_readable($value)) {
            return $this->_throw($file, self::NOT_READABLE);
        }

        if (filesize($value) === 0) {
            return $this->_throw($file, self::EMPTY_FILE);
        }

        $guesser = new FileBinaryMimeTypeGuesser();

        if (!$guesser->isGuesserSupported()) {
            error_log("FileBinaryMimeTypeGuesser is not supported on this system (missing 'file' binary)");
            return $this->_throw($file, self::NOT_DETECTED);
        }

        try {
            $type = $guesser->guessMimeType($value);
        } catch (InvalidArgumentException $e) {
            error_log("MIME Guesser InvalidArgumentException: " . $e->getMessage());
            return $this->_throw($file, self::NOT_READABLE);
        }

        if (!$type) {
            return $this->_throw($file, self::NOT_DETECTED);
        }

        if (!$this->_policy->accepts(MimeTypePolicy::extensionOf((string)($file['name'] ?? '')), $type)) {
            return $this->_throw($file, self::FALSE_TYPE);
        }

        return true;
    }

    /**
     * @param array<string, mixed>|string|null $file
     */
    protected function _throw(array|string|null $file, string $errorType): bool
    {
        $this->_value = is_array($file) ? (string)($file['name'] ?? '') : (string)($file ?? '');
        $this->_error($errorType);
        return false;
    }

    /**
     * Accept these content types whatever the extension is.
     *
     * @param array<string> $allowedMimeTypes
     */
    public function setAllowedMimeTypes(array $allowedMimeTypes): void
    {
        $this->_policy = MimeTypePolicy::forAnyExtension($allowedMimeTypes);
    }

    public function setPolicy(MimeTypePolicy $policy): void
    {
        $this->_policy = $policy;
    }

    /**
     * Policy of the journal, with fallbacks for the constants not defined by older configurations.
     */
    private static function configuredPolicy(): MimeTypePolicy
    {
        if (defined('ALLOWED_MIMES_BY_EXTENSION') && ALLOWED_MIMES_BY_EXTENSION !== []) {
            return new MimeTypePolicy(ALLOWED_MIMES_BY_EXTENSION);
        }

        $mimeTypes = defined('ALLOWED_MIMES_TYPES') && ALLOWED_MIMES_TYPES !== []
            ? (array)ALLOWED_MIMES_TYPES
            : self::DEFAULT_MIME_TYPES;

        return MimeTypePolicy::forAnyExtension($mimeTypes);
    }

}
