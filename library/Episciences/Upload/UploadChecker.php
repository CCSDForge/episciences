<?php

declare(strict_types=1);

namespace Episciences\Upload;

use Episciences_Form_Validate_MimeType;

/**
 * Checks a file that was uploaded outside a Zend form (AJAX upload, hand-built form handling).
 *
 * Applies the same rules as the upload forms: the file is not empty and its real content type is one
 * expected for its extension.
 */
final class UploadChecker
{
    /**
     * @param string $temporaryPath where the server stored the uploaded file
     * @param string $originalName file name sent by the browser
     * @param int $uploadError PHP upload error code ($_FILES[...]['error'])
     * @return string|null message for the user (translated), null if the file is acceptable
     */
    public static function firstError(string $temporaryPath, string $originalName, int $uploadError = UPLOAD_ERR_OK, ?MimeTypePolicy $policy = null): ?string
    {
        $options = $policy instanceof MimeTypePolicy ? [Episciences_Form_Validate_MimeType::POLICY_KEY => $policy] : [];
        $validator = new Episciences_Form_Validate_MimeType($options);

        // A failed transfer leaves no usable file: report it as an unreadable file
        $path = $uploadError === UPLOAD_ERR_OK ? $temporaryPath : '';

        if ($validator->isValid($path, ['name' => $originalName, 'tmp_name' => $temporaryPath, 'type' => null])) {
            return null;
        }

        $messages = array_values($validator->getMessages());

        return $messages[0] ?? null;
    }
}
