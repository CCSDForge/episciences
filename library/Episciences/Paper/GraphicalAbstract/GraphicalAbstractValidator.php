<?php

declare(strict_types=1);

namespace Episciences\Paper\GraphicalAbstract;

use Episciences_Form_Validate_MimeType;
use Symfony\Component\Mime\FileBinaryMimeTypeGuesser;
use Symfony\Component\Mime\MimeTypes;
use Throwable;
use Zend_Validate_File_Extension;
use Zend_Validate_File_Size;

/**
 * Validation rules of a paper illustration (graphical abstract): image file, text alternative and license.
 *
 * Error codes are returned rather than messages, so that the caller translates them
 * (see self::MESSAGES).
 */
final class GraphicalAbstractValidator
{
    /** Maximum file size, in bytes (500 KB) */
    public const MAX_SIZE = 512000;

    /** SVG is deliberately excluded: it can embed scripts and is served from the journal's domain */
    public const ACCEPTED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    public const ACCEPTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public const ALT_MAX_LENGTH = 1000;
    public const LICENSE_MAX_LENGTH = 255;

    public const FIELD_FILE = 'illustration_file';
    public const FIELD_ALT = 'illustration_alt';
    public const FIELD_LICENSE = 'illustration_license';

    public const ERROR_FILE_REQUIRED = 'fileRequired';
    public const ERROR_FILE_TOO_LARGE = 'fileTooLarge';
    public const ERROR_FILE_TYPE = 'fileType';
    public const ERROR_ALT_REQUIRED = 'altRequired';
    public const ERROR_ALT_TOO_LONG = 'altTooLong';
    public const ERROR_LICENSE_TOO_LONG = 'licenseTooLong';

    /** Translation keys of the error codes; "%s" is replaced by the limit */
    public const MESSAGES = [
        self::ERROR_FILE_REQUIRED => 'Veuillez choisir une image.',
        self::ERROR_FILE_TOO_LARGE => 'Fichier trop volumineux : %s ko maximum.',
        self::ERROR_FILE_TYPE => 'Type de fichier non accepté : JPEG, PNG, WebP ou GIF uniquement.',
        self::ERROR_ALT_REQUIRED => 'Le texte alternatif est obligatoire.',
        self::ERROR_ALT_TOO_LONG => 'Le texte alternatif ne doit pas dépasser %s caractères.',
        self::ERROR_LICENSE_TOO_LONG => 'La licence ne doit pas dépasser %s caractères.',
    ];

    /**
     * Validates an uploaded image on its size, its extension and its actual (binary) MIME type.
     *
     * @param string $path temporary path of the uploaded file
     * @param string $originalName file name sent by the browser
     * @return string|null error code, null if the file is valid
     */
    public static function validateFile(string $path, string $originalName): ?string
    {
        $fileInfo = ['name' => $originalName, 'tmp_name' => $path, 'type' => null];

        if (!(new Zend_Validate_File_Size(['max' => self::MAX_SIZE]))->isValid($path, $fileInfo)) {
            return is_file($path) ? self::ERROR_FILE_TOO_LARGE : self::ERROR_FILE_REQUIRED;
        }

        if (!(new Zend_Validate_File_Extension(self::ACCEPTED_EXTENSIONS))->isValid($path, $fileInfo)) {
            return self::ERROR_FILE_TYPE;
        }

        $mimeValidator = new Episciences_Form_Validate_MimeType([
            Episciences_Form_Validate_MimeType::ALLOWED_MIME_TYPE_KEY => self::ACCEPTED_MIME_TYPES,
        ]);

        if (!$mimeValidator->isValid($path, $fileInfo)) {
            return self::ERROR_FILE_TYPE;
        }

        return null;
    }

    /**
     * Extension to store the file with, derived from its actual MIME type rather than from its original name.
     */
    public static function extensionFor(string $path): ?string
    {
        try {
            $mimeType = (new FileBinaryMimeTypeGuesser())->guessMimeType($path);
        } catch (Throwable) {
            return null;
        }

        if ($mimeType === null || !in_array($mimeType, self::ACCEPTED_MIME_TYPES, true)) {
            return null;
        }

        return MimeTypes::getDefault()->getExtensions($mimeType)[0] ?? null;
    }

    /**
     * Validates and normalizes the text alternative and the license.
     *
     * @return array{alt: string, license: string|null, errors: array<string, string>} errors: field name => error code
     */
    public static function validateText(mixed $alt, mixed $license): array
    {
        $alt = is_string($alt) ? trim($alt) : '';
        $license = is_string($license) ? trim(strip_tags($license)) : '';
        $errors = [];

        if ($alt === '') {
            $errors[self::FIELD_ALT] = self::ERROR_ALT_REQUIRED;
        } elseif (mb_strlen($alt) > self::ALT_MAX_LENGTH) {
            $errors[self::FIELD_ALT] = self::ERROR_ALT_TOO_LONG;
        }

        if (mb_strlen($license) > self::LICENSE_MAX_LENGTH) {
            $errors[self::FIELD_LICENSE] = self::ERROR_LICENSE_TOO_LONG;
        }

        return [
            'alt' => $alt,
            'license' => $license !== '' ? $license : null,
            'errors' => $errors,
        ];
    }

    /**
     * Value substituted for "%s" in the message of an error code.
     */
    public static function messageArgument(string $errorCode): ?string
    {
        return match ($errorCode) {
            self::ERROR_FILE_TOO_LARGE => (string)(self::MAX_SIZE / 1024),
            self::ERROR_ALT_TOO_LONG => (string)self::ALT_MAX_LENGTH,
            self::ERROR_LICENSE_TOO_LONG => (string)self::LICENSE_MAX_LENGTH,
            default => null,
        };
    }
}
